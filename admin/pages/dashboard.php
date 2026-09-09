<?php
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect('localhost', 'root', '', 'beacon_school');

if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$students = $teachers = $classesCount = $newAdmissions = $due = 0;
$presentCount = $absentCount = $leaveCount = 0;
$recent = [];

// Date setup (Default: Date picker OR latest date in DB)
$selectedDate = $_GET['att_date'] ?? date('Y-m-d');

$classListData = [];

if ($conn) {
    // 1. Basic Stats
    $q1 = @mysqli_query($conn, "SELECT COUNT(*) as total FROM students");
    if ($q1 && $r1 = mysqli_fetch_assoc($q1)) { $students = (int)$r1['total']; }

    $q2 = @mysqli_query($conn, "SELECT COUNT(*) as total FROM teachers");
    if ($q2 && $r2 = mysqli_fetch_assoc($q2)) { $teachers = (int)$r2['total']; }

    $q3 = @mysqli_query($conn, "SELECT COUNT(*) as total FROM classes");
    if ($q3 && $r3 = mysqli_fetch_assoc($q3)) { $classesCount = (int)$r3['total']; }

    $q4 = @mysqli_query($conn, "SELECT COUNT(*) as total FROM admission_enquiries");
    if ($q4 && $r4 = mysqli_fetch_assoc($q4)) { $newAdmissions = (int)$r4['total']; }

    // 2. Attendance Summary Counts
    $qp = @mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance WHERE attendance_date = '$selectedDate' AND status = 'present'");
    if ($qp && $rp = mysqli_fetch_assoc($qp)) { $presentCount = (int)$rp['total']; }

    $qa = @mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance WHERE attendance_date = '$selectedDate' AND status = 'absent'");
    if ($qa && $ra = mysqli_fetch_assoc($qa)) { $absentCount = (int)$ra['total']; }

    $ql = @mysqli_query($conn, "SELECT COUNT(*) as total FROM attendance WHERE attendance_date = '$selectedDate' AND status = 'leave'");
    if ($ql && $rl = mysqli_fetch_assoc($ql)) { $leaveCount = (int)$rl['total']; }

    // 3. Fetch All Classes
    $classesArr = [];
    $qClasses = @mysqli_query($conn, "SELECT * FROM classes ORDER BY id ASC");
    if ($qClasses) {
        while ($c = mysqli_fetch_assoc($qClasses)) {
            $classesArr[$c['id']] = $c;
        }
    }

    // 4. Fetch All Students (Mapping ID -> Data)
    $studentsArr = [];
    $qStd = @mysqli_query($conn, "SELECT * FROM students");
    if ($qStd) {
        while ($s = mysqli_fetch_assoc($qStd)) {
            $studentsArr[$s['id']] = $s;
        }
    }

    // 5. Fetch All Teachers (Mapping ID -> Name)
    $teachersArr = [];
    $qT = @mysqli_query($conn, "SELECT id, name FROM teachers");
    if ($qT) {
        while ($t = mysqli_fetch_assoc($qT)) {
            $teachersArr[$t['id']] = $t['name'];
        }
    }

    // 6. Fetch ALL Attendance Records for Selected Date
    $attRecords = [];
    $qAtt = @mysqli_query($conn, "SELECT * FROM attendance WHERE attendance_date = '$selectedDate'");
    if ($qAtt) {
        while ($a = mysqli_fetch_assoc($qAtt)) {
            $attRecords[] = $a;
        }
    }

    // 7. Group Data Class-wise
    foreach ($classesArr as $cid => $cls) {
        $cName = trim($cls['class_name'] ?? $cls['name'] ?? '');
        $cSec = trim($cls['section'] ?? '');
        $fullClassName = trim($cName . ' ' . $cSec);
        if(!$fullClassName) { $fullClassName = "Class #" . $cid; }

        $t_id = $cls['teacher_id'] ?? $cls['class_teacher_id'] ?? null;
        $teacherName = ($t_id && isset($teachersArr[$t_id])) ? $teachersArr[$t_id] : 'Not Assigned';

        $hasAttendance = false;
        $studentsList = ['present' => [], 'absent' => [], 'leave' => []];

        foreach ($attRecords as $att) {
            $stId = $att['student_id'];
            $stData = $studentsArr[$stId] ?? null;

            // Check if student belongs to this class
            $stClassId = $stData['class_id'] ?? $stData['class'] ?? null;
            
            if ($stClassId == $cid || (strcasecmp((string)$stClassId, (string)$cName) == 0)) {
                $hasAttendance = true;
                
                $stName = $stData['student_name'] ?? $stData['name'] ?? 'Student #' . $stId;
                $admNo = $stData['admission_no'] ?? $stId;
                $stStatus = strtolower(trim($att['status']));

                $item = [
                    'admission_no' => $admNo,
                    'student_name' => $stName,
                    'status' => ucfirst($stStatus)
                ];

                if ($stStatus === 'present') {
                    $studentsList['present'][] = $item;
                } elseif ($stStatus === 'absent') {
                    $studentsList['absent'][] = $item;
                } elseif ($stStatus === 'leave') {
                    $studentsList['leave'][] = $item;
                }
            }
        }

        $classListData[] = [
            'class_id' => $cid,
            'class_name' => $fullClassName,
            'teacher_name' => $teacherName,
            'has_attendance' => $hasAttendance,
            'students' => $studentsList
        ];
    }

    // 8. Fees & Enquiries
    $q6 = @mysqli_query($conn, "SELECT SUM(amount - paid) as total_due FROM fees");
    if (!$q6) { $q6 = @mysqli_query($conn, "SELECT SUM(amount) as total_due FROM fees"); }
    if ($q6 && $r6 = mysqli_fetch_assoc($q6)) { $due = (float)($r6['total_due'] ?? 0); }

    $q7 = @mysqli_query($conn, "SELECT * FROM admission_enquiries ORDER BY id DESC LIMIT 5");
    if ($q7 && mysqli_num_rows($q7) > 0) {
        while ($row = mysqli_fetch_assoc($q7)) { $recent[] = $row; }
    }
}
?>

<style>
.date-picker-box { background: #ffffff; padding: 12px 18px; border-radius: 8px; border: 1px solid #e2e8f0; margin-top: 15px; display: flex; align-items: center; justify-content: space-between; gap: 15px; flex-wrap: wrap; }
.date-picker-box input[type="date"] { padding: 6px 12px; border-radius: 6px; border: 1px solid #cbd5e1; font-size: 14px; }

.stats-att { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 15px; margin-top: 15px; margin-bottom: 20px; }
.stat-card-att { background: #fff; padding: 15px; border-radius: 8px; border-left: 5px solid #ccc; cursor: pointer; box-shadow: 0 2px 5px rgba(0,0,0,0.05); transition: transform 0.2s; }
.stat-card-att:hover { transform: translateY(-3px); }
.stat-card-att.present { border-left-color: #28a745; }
.stat-card-att.absent { border-left-color: #dc3545; }
.stat-card-att.leave { border-left-color: #ffc107; }
.stat-card-att.all { border-left-color: #0d6efd; }
.stat-card-att span { font-size: 13px; color: #666; font-weight: 600; display: block; }
.stat-card-att b { font-size: 22px; font-weight: bold; color: #111; display: block; margin: 4px 0; }
.stat-card-att small { color: #888; font-size: 11px; }

.att-modal-overlay { display: none; position: fixed; top:0; left:0; width:100%; height:100%; background:rgba(0,0,0,0.5); z-index:9999; justify-content:center; align-items:center; }
.att-modal-content { background:#fff; width:92%; max-width:700px; padding:20px; border-radius:8px; max-height:85vh; overflow-y:auto; }

.class-card { border: 1px solid #e0e0e0; border-radius: 8px; margin-bottom: 15px; overflow: hidden; background: #fff; box-shadow: 0 1px 3px rgba(0,0,0,0.05); }
.class-header { padding: 12px 15px; background: #f8f9fa; display: flex; justify-content: space-between; align-items: center; cursor: pointer; border-bottom: 1px solid #eee; }
.class-header:hover { background: #f1f3f5; }
.badge-status { padding: 4px 10px; border-radius: 20px; font-size: 11px; font-weight: bold; text-transform: uppercase; }
.badge-taken { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.badge-pending { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.class-body { padding: 15px; display: block; }
.teacher-info { font-size: 13px; color: #555; margin-bottom: 10px; padding-bottom: 8px; border-bottom: 1px dashed #e0e0e0; }
.count-pills { display: flex; gap: 10px; margin-bottom: 12px; }
.pill { font-size: 12px; padding: 3px 8px; border-radius: 4px; font-weight: 600; }
.pill-p { background: #e8f5e9; color: #2e7d32; }
.pill-a { background: #ffebee; color: #c62828; }
.pill-l { background: #fff8e1; color: #f57f17; }
</style>

<!-- Counter Cards -->
<div class="stats">
    <div class="stat"><span>Active Students</span><b><?=number_format($students)?></b><i>👨‍🎓</i></div>
    <div class="stat"><span>Active Teachers</span><b><?=number_format($teachers)?></b><i>👨‍🏫</i></div>
    <div class="stat"><span>Classes</span><b><?=number_format($classesCount)?></b><i>🏫</i></div>
    <div class="stat"><span>New Admissions</span><b><?=number_format($newAdmissions)?></b><i>📝</i></div>
</div>

<!-- Date Selector Box -->
<div class="date-picker-box">
    <div>
        <h3 style="margin:0; font-size:16px; color:#1e293b;">📅 Attendance Records Overview</h3>
        <small style="color:#64748b;">Showing data for date: <b><?=date('d M Y', strtotime($selectedDate))?></b></small>
    </div>
    <form method="GET" action="index.php" style="display:flex; gap:8px; align-items:center; margin:0;">
        <?php if(isset($_GET['page'])): ?>
            <input type="hidden" name="page" value="<?=e($_GET['page'])?>">
        <?php endif; ?>
        <label for="att_date" style="font-size:13px; font-weight:bold;">Select Date:</label>
        <input type="date" id="att_date" name="att_date" value="<?=$selectedDate?>" onchange="this.form.submit()">
    </form>
</div>

<!-- Attendance Cards -->
<div class="stats-att">
    <div class="stat-card-att all" onclick="showAttList('all')">
        <span style="color:#0d6efd;">All Classes Status</span>
        <b><?=$classesCount?> Classes</b>
        <small>Click for Class-wise Details</small>
    </div>
    <div class="stat-card-att present" onclick="showAttList('present')">
        <span style="color:#28a745;">Present Students</span>
        <b><?=number_format($presentCount)?></b>
        <small>Click for Class-wise List</small>
    </div>
    <div class="stat-card-att absent" onclick="showAttList('absent')">
        <span style="color:#dc3545;">Absent Students</span>
        <b><?=number_format($absentCount)?></b>
        <small>Click for Class-wise List</small>
    </div>
    <div class="stat-card-att leave" onclick="showAttList('leave')">
        <span style="color:#d39e00;">On Leave</span>
        <b><?=number_format($leaveCount)?></b>
        <small>Click for Class-wise List</small>
    </div>
</div>

<div class="stats second">
    <div class="stat"><span>Total Outstanding</span><b>Rs <?=number_format($due, 2)?></b><small>Unpaid/partial fees</small></div>
</div>

<div class="grid2">
    <section class="panel">
        <div class="panel-head">
            <h2>Recent Admission Enquiries</h2>
            <a href="index.php?page=admissions">View all</a>
        </div>
        <div class="table-wrap">
            <table>
                <thead>
                    <tr>
                        <th>Student</th>
                        <th>Parent</th>
                        <th>Class</th>
                        <th>Phone</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($recent as $r): ?>
                    <tr>
                        <td><?=e($r['student_name'] ?? $r['name'] ?? $r['full_name'] ?? '-')?></td>
                        <td><?=e($r['parent_name'] ?? $r['father_name'] ?? $r['guardian_name'] ?? '-')?></td>
                        <td><?=e($r['class_level'] ?? $r['class'] ?? $r['class_id'] ?? '-')?></td>
                        <td><?=e($r['phone'] ?? $r['mobile'] ?? '-')?></td>
                        <td><span class="badge <?=e($r['status'] ?? 'pending')?>"><?=e(ucfirst($r['status'] ?? 'pending'))?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if(!$recent): ?>
                    <tr><td colspan="5" class="empty">No enquiries yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <section class="panel quick">
        <h2>Quick Actions</h2>
        <a href="index.php?page=students&action=new">＋ Add Student</a>
        <a href="index.php?page=teachers&action=new">＋ Add Teacher</a>
        <a href="index.php?page=attendance">✓ Mark Attendance</a>
        <a href="index.php?page=fees&action=new">＋ Create Fee</a>
        <a href="index.php?page=notices&action=new">＋ Publish Notice</a>
    </section>
</div>

<!-- Modal Popup -->
<div id="attListModal" class="att-modal-overlay">
    <div class="att-modal-content">
        <div style="display:flex; justify-content:space-between; align-items:center; border-bottom:1px solid #eee; padding-bottom:10px;">
            <h3 id="attModalTitle" style="margin:0; font-size:18px;">Class-wise Attendance Status</h3>
            <button type="button" onclick="closeAttModal()" style="border:none; background:none; font-size:22px; cursor:pointer; font-weight:bold;">&times;</button>
        </div>
        <div id="attModalBody" style="margin-top:15px;"></div>
    </div>
</div>

<script>
var classListData = <?=json_encode($classListData)?>;
var currentSelectedDate = "<?=date('d M Y', strtotime($selectedDate))?>";

function showAttList(filterType) {
    var title = document.getElementById('attModalTitle');
    var body = document.getElementById('attModalBody');
    
    var filterText = "All Classes Status";
    if(filterType === 'present') filterText = "Present Students Class-wise";
    if(filterType === 'absent') filterText = "Absent Students Class-wise";
    if(filterType === 'leave') filterText = "On Leave Students Class-wise";

    title.innerText = filterText + " (" + currentSelectedDate + ")";
    
    if (!classListData || classListData.length === 0) {
        body.innerHTML = "<p style='text-align:center; color:#888; padding:20px;'>No classes found in the database.</p>";
    } else {
        var html = '';
        var matchesFound = 0;
        
        classListData.forEach(function(cls, idx) {
            var hasAtt = cls.has_attendance;
            var pList = cls.students.present || [];
            var aList = cls.students.absent || [];
            var lList = cls.students.leave || [];

            var targetList = [];
            if(filterType === 'present') targetList = pList;
            else if(filterType === 'absent') targetList = aList;
            else if(filterType === 'leave') targetList = lList;
            else targetList = pList.concat(aList).concat(lList);

            if(filterType !== 'all' && targetList.length === 0) {
                return;
            }

            matchesFound++;
            html += '<div class="class-card">';
            
            html += '<div class="class-header" onclick="toggleBody(' + idx + ')">';
            html += '<div><b style="font-size:15px; color:#222;">🏫 ' + cls.class_name + '</b></div>';
            
            if(hasAtt) {
                html += '<span class="badge-status badge-taken">✓ Attendance Taken</span>';
            } else {
                html += '<span class="badge-status badge-pending">✕ Attendance Pending</span>';
            }
            html += '</div>';

            html += '<div id="classBody_' + idx + '" class="class-body">';
            
            if(hasAtt) {
                html += '<div class="teacher-info"><b>Teacher:</b> ' + cls.teacher_name + '</div>';
                html += '<div class="count-pills">';
                html += '<span class="pill pill-p">Present: ' + pList.length + '</span>';
                html += '<span class="pill pill-a">Absent: ' + aList.length + '</span>';
                html += '<span class="pill pill-l">Leave: ' + lList.length + '</span>';
                html += '</div>';

                if(targetList.length > 0) {
                    html += '<table width="100%" cellpadding="6" style="border-collapse:collapse; font-size:13px; border:1px solid #eee;">';
                    html += '<thead><tr style="background:#f4f4f4; text-align:left;"><th>ADM NO / ID</th><th>STUDENT NAME</th>' + (filterType === 'all' ? '<th>STATUS</th>' : '') + '</tr></thead><tbody>';
                    
                    targetList.forEach(function(s){
                        var statusColor = '#28a745';
                        if(s.status.toLowerCase() === 'absent') statusColor = '#dc3545';
                        if(s.status.toLowerCase() === 'leave') statusColor = '#ffc107';

                        html += '<tr><td>'+s.admission_no+'</td><td><b>'+s.student_name+'</b></td>';
                        if(filterType === 'all') {
                            html += '<td><span style="color:'+statusColor+'; font-weight:bold;">'+s.status+'</span></td>';
                        }
                        html += '</tr>';
                    });
                    
                    html += '</tbody></table>';
                }
            } else {
                html += '<div class="teacher-info" style="color:#dc3545;"><b>Teacher:</b> ' + cls.teacher_name + '</div>';
                html += '<p style="margin:0; font-size:13px; color:#666;">Attendance for this class has not been marked yet for this date.</p>';
            }

            html += '</div></div>';
        });

        if(matchesFound === 0) {
            html = "<p style='text-align:center; color:#888; padding:20px;'>No records found for this date/category.</p>";
        }

        body.innerHTML = html;
    }

    document.getElementById('attListModal').style.display = 'flex';
}

function toggleBody(idx) {
    var el = document.getElementById('classBody_' + idx);
    if(el.style.display === 'none') {
        el.style.display = 'block';
    } else {
        el.style.display = 'none';
    }
}

function closeAttModal() {
    document.getElementById('attListModal').style.display = 'none';
}
</script>