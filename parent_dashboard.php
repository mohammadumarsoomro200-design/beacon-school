<?php
// parent_dashboard.php
require_once __DIR__ . '/config/config.php';

if (!user()) {
    header("Location: admin/login.php");
    exit();
}

$u = $_SESSION['user'] ?? [];

// Check Parent Role
if (($u['role'] ?? '') !== 'parent') {
    header("Location: admin/index.php");
    exit();
}

$db_conn = function_exists('db') ? db() : ($db_conn ?? $conn ?? $db ?? null);
if (!$db_conn) {
    die("Database connection failed.");
}

$parent_id = (int)($u['id'] ?? 0);
$parent_name = trim((string)($u['full_name'] ?? $u['name'] ?? $u['username'] ?? 'Parent'));

if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

function getTableColumns($db_conn, $table) {
    try {
        $stmt = $db_conn->query("SHOW COLUMNS FROM `$table`");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (Throwable $e) {
        return [];
    }
}

// 1. FETCH LINKED CHILD / STUDENT
$student = null;
try {
    // Check if students table has parent_id or father_phone / email matching logged in user
    $std_stmt = $db_conn->prepare("
        SELECT * FROM students 
        WHERE parent_id = ? 
           OR father_name = ? 
           OR guardian_name = ?
        LIMIT 1
    ");
    $std_stmt->execute([$parent_id, $parent_name, $parent_name]);
    $student = $std_stmt->fetch(PDO::FETCH_ASSOC);

    // Fallback: If not linked by parent_id, fetch first active student
    if (!$student) {
        $fallback_stmt = $db_conn->query("SELECT * FROM students ORDER BY id ASC LIMIT 1");
        $student = $fallback_stmt ? $fallback_stmt->fetch(PDO::FETCH_ASSOC) : null;
    }
} catch (Throwable $e) {}

$real_student_id = (int)($student['id'] ?? 0);
$admission_no    = trim((string)($student['admission_no'] ?? ''));
$std_name        = trim((string)($student['student_name'] ?? $student['name'] ?? 'Child'));
$class_id        = (int)($student['class_id'] ?? 0);

// Fetch Class Name
$fetched_class_name = '';
$fetched_section = '';

if ($class_id > 0) {
    try {
        $c_stmt = $db_conn->prepare("SELECT * FROM classes WHERE id = ? LIMIT 1");
        $c_stmt->execute([$class_id]);
        $c_row = $c_stmt->fetch(PDO::FETCH_ASSOC);

        if ($c_row) {
            foreach (['class_name', 'name', 'title', 'class'] as $col) {
                if (!empty($c_row[$col])) { $fetched_class_name = $c_row[$col]; break; }
            }
            foreach (['section', 'sec'] as $col) {
                if (!empty($c_row[$col])) { $fetched_section = $c_row[$col]; break; }
            }
        }
    } catch (Throwable $e) {}
}

$display_class = trim($fetched_class_name . ' ' . $fetched_section);
if (empty($display_class)) { $display_class = 'N/A'; }

// 2. FETCH CHILD ATTENDANCE
$attendance_records = [];
if ($real_student_id > 0) {
    try {
        $att_cols = getTableColumns($db_conn, 'attendance');
        $where = [];
        $params = [];

        if (in_array('student_id', $att_cols)) { $where[] = "`student_id` = ?"; $params[] = $real_student_id; }
        if (in_array('std_id', $att_cols)) { $where[] = "`std_id` = ?"; $params[] = $real_student_id; }

        if (!empty($where)) {
            $att_sql = "SELECT * FROM attendance WHERE " . implode(' OR ', $where) . " ORDER BY id DESC LIMIT 30";
            $att_stmt = $db_conn->prepare($att_sql);
            $att_stmt->execute($params);
            $all_att = $att_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($all_att as $row) {
                $attendance_records[] = [
                    'attendance_date' => $row['attendance_date'] ?? $row['date'] ?? $row['created_at'] ?? 'now',
                    'status' => $row['status'] ?? 'Present'
                ];
            }
        }
    } catch (Throwable $e) {}
}

// 3. FETCH NOTICES FOR PARENT & CHILD CLASS
$announcements = [];
try {
    $n_stmt = $db_conn->prepare("
        SELECT * FROM notices 
        WHERE TRIM(LOWER(audience)) = 'all' 
           OR TRIM(LOWER(audience)) = 'parents' 
           OR audience = ? 
           OR audience = '0'
        ORDER BY id DESC 
        LIMIT 20
    ");
    $n_stmt->execute([(string)$class_id]);
    $raw_notices = $n_stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($raw_notices as $row) {
        $msg = $row['message'] ?? $row['description'] ?? $row['body'] ?? $row['notice'] ?? '';
        $announcements[] = [
            'id'         => $row['id'] ?? rand(100, 999),
            'title'      => $row['title'] ?? 'Notice',
            'message'    => $msg,
            'created_at' => $row['created_at'] ?? $row['published_at'] ?? $row['date'] ?? date('Y-m-d')
        ];
    }
} catch (Throwable $e) {}

// 4. FETCH EXAM RESULTS
$exam_results = [];
if ($real_student_id > 0) {
    try {
        $res_cols = getTableColumns($db_conn, 'results');
        $subjects_map = [];
        try {
            $sub_query = $db_conn->query("SELECT * FROM subjects");
            if ($sub_query) {
                while ($s_row = $sub_query->fetch(PDO::FETCH_ASSOC)) {
                    $s_id = $s_row['id'] ?? 0;
                    $s_name = $s_row['subject_name'] ?? $s_row['name'] ?? '';
                    if ($s_id && $s_name) { $subjects_map[$s_id] = $s_name; }
                }
            }
        } catch (Throwable $ex) {}

        $where = [];
        $params = [];
        if (in_array('student_id', $res_cols)) { $where[] = "`student_id` = ?"; $params[] = $real_student_id; }
        if (in_array('admission_no', $res_cols)) { $where[] = "`admission_no` = ?"; $params[] = $admission_no; }

        if (!empty($where)) {
            $res_sql = "SELECT * FROM results WHERE " . implode(' OR ', $where) . " ORDER BY id DESC";
            $res_stmt = $db_conn->prepare($res_sql);
            $res_stmt->execute($params);
            $raw_results = $res_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($raw_results as $row) {
                $sub_id = $row['subject_id'] ?? 0;
                $sub = $subjects_map[$sub_id] ?? $row['subject_name'] ?? $row['subject'] ?? 'Subject';
                $marks = (float)($row['marks_obtained'] ?? $row['marks'] ?? 0);
                $total = (float)($row['total_marks'] ?? 100);
                if ($total <= 0) { $total = 100; }
                $pct = round(($marks / $total) * 100, 1);
                $grd = trim((string)($row['grade'] ?? ''));

                if (empty($grd)) {
                    if ($pct >= 80) { $grd = 'A+'; }
                    elseif ($pct >= 70) { $grd = 'A'; }
                    elseif ($pct >= 60) { $grd = 'B'; }
                    elseif ($pct >= 50) { $grd = 'C'; }
                    else { $grd = 'F'; }
                }

                $exam_results[] = [
                    'subject_name'   => $sub,
                    'marks_obtained' => number_format($marks, 2),
                    'total_marks'    => number_format($total, 2),
                    'grade'          => $grd
                ];
            }
        }
    } catch (Throwable $e) {}
}

// 5. FETCH FEE VOUCHERS
$fee_vouchers = [];
if ($real_student_id > 0) {
    try {
        $fee_cols = getTableColumns($db_conn, 'fees');
        $where = [];
        $params = [];
        if (in_array('student_id', $fee_cols)) { $where[] = "`student_id` = ?"; $params[] = $real_student_id; }
        if (in_array('admission_no', $fee_cols)) { $where[] = "`admission_no` = ?"; $params[] = $admission_no; }

        if (!empty($where)) {
            $fee_sql = "SELECT * FROM fees WHERE " . implode(' OR ', $where) . " ORDER BY id DESC";
            $fee_stmt = $db_conn->prepare($fee_sql);
            $fee_stmt->execute($params);
            $raw_fees = $fee_stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($raw_fees as $row) {
                $fee_vouchers[] = [
                    'id'            => $row['id'] ?? 0,
                    'month_details' => $row['month_details'] ?? $row['month'] ?? 'Monthly Fee',
                    'amount'        => number_format((float)($row['amount'] ?? 0), 2),
                    'status'        => $row['status'] ?? 'Unpaid'
                ];
            }
        }
    } catch (Throwable $e) {}
}

// 6. FETCH CLASS HOMEWORK
$homework_records = [];
if ($class_id > 0) {
    try {
        $hw_stmt = $db_conn->prepare("SELECT * FROM homework WHERE class_id = ? ORDER BY id DESC LIMIT 10");
        $hw_stmt->execute([$class_id]);
        $raw_hw = $hw_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_hw as $row) {
            $homework_records[] = [
                'title'       => $row['title'] ?? 'Homework',
                'subject'     => $row['subject'] ?? 'General',
                'description' => $row['description'] ?? 'No description',
                'due_date'    => !empty($row['due_date']) ? date('d M Y', strtotime($row['due_date'])) : 'N/A'
            ];
        }
    } catch (Throwable $e) {}
}
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Parent Portal | New Beacon School System</title>
    <style>
        body { background: #f4f7fe; font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 20px; }
        .navbar { display: flex; justify-content: space-between; align-items: center; background: #001f3f; color: #fff; padding: 15px 25px; border-radius: 10px; }
        .student-info { font-size: 14px; color: #d0e1fd; margin-top: 5px; }
        .logout-btn { background: #e63946; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; }
        .grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 20px; margin-top: 25px; }
        .card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h3 { margin-top: 0; color: #1d3557; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; font-size: 18px; }
        .empty { color: #888; font-style: italic; text-align: center; margin: 20px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; font-size: 13px; }
        th { background: #f8fafc; color: #475569; font-weight: 700; text-align: left; padding: 10px 8px; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; font-size: 11px; }
        td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
        .grade-badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-weight: bold; font-size: 12px; background: #e0f2fe; color: #0369a1; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; }
        .status-present { background: #d1fae5; color: #065f46; }
        .status-absent { background: #fee2e2; color: #991b1b; }
        .status-leave { background: #fef3c7; color: #92400e; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-unpaid { background: #fee2e2; color: #991b1b; }
        .btn-print { display: inline-block; padding: 5px 10px; border-radius: 4px; font-size: 11px; font-weight: bold; text-decoration: none; color: #fff; }
        .btn-print.paid { background: #10b981; }
        .btn-print.unpaid { background: #ef4444; }
        .notice-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 10px; cursor: pointer; }
        .notice-title { font-weight: 700; color: #1e293b; font-size: 14px; }
        .notice-date-text { font-size: 11px; color: #64748b; }
        .modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); display: none; justify-content: center; align-items: center; z-index: 99999; }
        .modal-box { background: #fff; padding: 25px; border-radius: 14px; max-width: 500px; width: 90%; position: relative; border-top: 6px solid #001f3f; }
        .close-btn { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #94a3b8; }
    </style>
</head>
<body>

    <div class="navbar">
        <div>
            <h2 style="margin:0;">Parent Dashboard</h2>
            <div class="student-info">
                Welcome, <strong><?= e($parent_name) ?></strong> | 
                Child: <strong><?= e($std_name) ?></strong> (<?= e($admission_no) ?>) | 
                Class: <strong><?= e($display_class) ?></strong>
            </div>
        </div>
        <a href="admin/logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="grid">
        <!-- Attendance -->
        <div class="card">
            <h3>📅 Child's Attendance</h3>
            <?php if (!empty($attendance_records)): ?>
                <table>
                    <thead><tr><th>DATE</th><th>STATUS</th></tr></thead>
                    <tbody>
                    <?php foreach ($attendance_records as $att): ?>
                        <?php 
                            $stClass = 'status-present';
                            $stLow = strtolower(trim((string)($att['status'] ?? '')));
                            if($stLow === 'absent') { $stClass = 'status-absent'; }
                            elseif($stLow === 'leave') { $stClass = 'status-leave'; }
                        ?>
                        <tr>
                            <td><?= e(date('d M Y', strtotime($att['attendance_date']))) ?></td>
                            <td><span class="status-badge <?= $stClass ?>"><?= e(ucfirst($att['status'])) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty">No attendance records found.</p>
            <?php endif; ?>
        </div>

        <!-- Notices -->
        <div class="card">
            <h3>📢 Notices & Announcements</h3>
            <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $index => $ann): ?>
                    <div class="notice-card" onclick="openNoticeModal(<?= $index ?>)">
                        <div class="notice-title">📌 <?= e($ann['title']) ?></div>
                        <div class="notice-date-text">Posted on: <?= e(date('d M Y', strtotime($ann['created_at']))) ?></div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="empty">No active notices.</p>
            <?php endif; ?>
        </div>

        <!-- Class Homework -->
        <div class="card">
            <h3>📚 Assigned Homework</h3>
            <?php if (!empty($homework_records)): ?>
                <table>
                    <thead><tr><th>Subject</th><th>Title</th><th>Due Date</th></tr></thead>
                    <tbody>
                    <?php foreach ($homework_records as $hw): ?>
                        <tr>
                            <td><span class="grade-badge"><?= e($hw['subject']) ?></span></td>
                            <td><strong><?= e($hw['title']) ?></strong></td>
                            <td><span class="status-badge status-leave"><?= e($hw['due_date']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty">No homework assigned yet.</p>
            <?php endif; ?>
        </div>

        <!-- Exam Results -->
        <div class="card">
            <h3>🎓 Child's Exam Results</h3>
            <?php if (!empty($exam_results)): ?>
                <table>
                    <thead><tr><th>Subject</th><th>Marks</th><th>Grade</th></tr></thead>
                    <tbody>
                    <?php foreach ($exam_results as $res): ?>
                        <tr>
                            <td><strong><?= e($res['subject_name']) ?></strong></td>
                            <td><?= e($res['marks_obtained']) ?> / <?= e($res['total_marks']) ?></td>
                            <td><span class="grade-badge"><?= e($res['grade']) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty">No result records available.</p>
            <?php endif; ?>
        </div>

        <!-- Fee Vouchers -->
        <div class="card">
            <h3>💳 Fee Vouchers</h3>
            <?php if (!empty($fee_vouchers)): ?>
                <table>
                    <thead><tr><th>Month</th><th>Amount</th><th>Status</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($fee_vouchers as $fee): ?>
                        <?php 
                            $isPaid = strtolower(trim((string)$fee['status'])) === 'paid';
                            $f_st = $isPaid ? 'status-paid' : 'status-unpaid'; 
                        ?>
                        <tr>
                            <td><?= e($fee['month_details']) ?></td>
                            <td><strong>Rs. <?= e($fee['amount']) ?></strong></td>
                            <td><span class="status-badge <?= $f_st ?>"><?= e(ucfirst($fee['status'])) ?></span></td>
                            <td>
                                <a href="print_voucher.php?id=<?= $fee['id'] ?>" target="_blank" class="btn-print <?= $isPaid ? 'paid' : 'unpaid' ?>">
                                    🖨️ <?= $isPaid ? 'Receipt' : 'Voucher' ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty">No fee vouchers generated.</p>
            <?php endif; ?>
        </div>
    </div>

    <!-- Notice Modal Popup -->
    <div id="noticeModal" class="modal-overlay">
        <div class="modal-box">
            <span class="close-btn" onclick="closeNoticeModal()">&times;</span>
            <h3 id="modalTitle">Notice Title</h3>
            <div id="modalDate" style="font-size:12px; color:#64748b;">Date</div>
            <p id="modalBody" style="background:#f8fafc; padding:12px; border-radius:8px; margin-top:15px; font-size:14px; color:#334155; line-height:1.5;"></p>
        </div>
    </div>

    <script>
        const noticesData = <?= json_encode($announcements, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function openNoticeModal(index) {
            const notice = noticesData[index];
            if (notice) {
                document.getElementById('modalTitle').innerText = notice.title || 'Notice';
                document.getElementById('modalBody').innerText = notice.message || 'No details.';
                document.getElementById('modalDate').innerText = 'Posted on: ' + notice.created_at;
                document.getElementById('noticeModal').style.display = 'flex';
            }
        }

        function closeNoticeModal() {
            document.getElementById('noticeModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var modal = document.getElementById('noticeModal');
            if (event.target == modal) { modal.style.display = "none"; }
        }
    </script>
</body>
</html>