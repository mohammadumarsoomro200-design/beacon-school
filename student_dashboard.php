<?php
// student_dashboard.php
require_once __DIR__ . '/config/config.php';

if (!user()) {
    header("Location: admin/login.php");
    exit();
}

$u = $_SESSION['user'] ?? [];

if (($u['role'] ?? '') !== 'student') {
    header("Location: admin/index.php");
    exit();
}

$db_conn = function_exists('db') ? db() : ($db_conn ?? $conn ?? $db ?? null);
if (!$db_conn) {
    die("Database connection initialize nahi ho saka.");
}

$user_id = (int)($u['id'] ?? 0);
$username = trim((string)($u['username'] ?? ''));
$full_name = trim((string)($u['full_name'] ?? $u['name'] ?? $username));
$session_student_id = (int)($u['student_id'] ?? $user_id);

$student = [
    'id' => $session_student_id,
    'student_name' => $full_name,
    'admission_no' => $username,
    'class_id' => 0,
    'class_name' => '',
    'section' => '',
    'status' => 'paid'
];

if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars((string)($val ?? ''), ENT_QUOTES, 'UTF-8');
    }
}

// 1. FETCH EXACT STUDENT RECORD
try {
    $stmt = $db_conn->prepare("SELECT * FROM students WHERE id = ? OR admission_no = ? OR student_name = ? OR email = ? LIMIT 1");
    $stmt->execute([$session_student_id, $username, $full_name, $username]);
    $db_student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($db_student) {
        $student = array_merge($student, $db_student);
    }
} catch (Throwable $e) {}

$real_student_id = (int)($student['id'] ?? $session_student_id);
$admission_no    = trim((string)($student['admission_no'] ?? $username));
$std_name        = trim((string)($student['student_name'] ?? $full_name));
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
            foreach (['class_name', 'name', 'title', 'class', 'grade'] as $col) {
                if (!empty($c_row[$col])) { $fetched_class_name = $c_row[$col]; break; }
            }
            foreach (['section', 'sec', 'section_name'] as $col) {
                if (!empty($c_row[$col])) { $fetched_section = $c_row[$col]; break; }
            }
        }
    } catch (Throwable $e) {}
}

if (empty($fetched_class_name)) {
    $fetched_class_name = $student['class_name'] ?? $student['class'] ?? $student['grade'] ?? '';
}
if (empty($fetched_section)) {
    $fetched_section = $student['section'] ?? '';
}

$display_class = trim($fetched_class_name . ' ' . $fetched_section);
if (empty($display_class)) {
    $display_class = 'N/A';
}

// HELPER FUNCTION: Find Table Columns
function getTableColumns($db_conn, $table) {
    try {
        $stmt = $db_conn->query("SHOW COLUMNS FROM `$table`");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (Throwable $e) {
        return [];
    }
}

// 2. FETCH ATTENDANCE (STRICT FILTER)
$attendance_records = [];
try {
    $att_cols = getTableColumns($db_conn, 'attendance');
    $where_clauses = [];
    $params = [];

    if (in_array('student_id', $att_cols)) { $where_clauses[] = "`student_id` = ?"; $params[] = $real_student_id; }
    if (in_array('std_id', $att_cols)) { $where_clauses[] = "`std_id` = ?"; $params[] = $real_student_id; }
    if (in_array('admission_no', $att_cols)) { $where_clauses[] = "`admission_no` = ?"; $params[] = $admission_no; }

    if (!empty($where_clauses)) {
        $att_sql = "SELECT * FROM attendance WHERE " . implode(' OR ', $where_clauses) . " ORDER BY id DESC LIMIT 30";
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

// 3. FETCH ANNOUNCEMENTS / NOTICES
$announcements = [];
try {
    $n_stmt = $db_conn->query("SELECT * FROM notices ORDER BY id DESC LIMIT 20");
    $raw_notices = $n_stmt ? $n_stmt->fetchAll(PDO::FETCH_ASSOC) : [];

    foreach ($raw_notices as $row) {
        $msg = $row['message'] ?? $row['description'] ?? $row['notice'] ?? $row['content'] ?? $row['details'] ?? $row['body'] ?? '';
        if (empty(trim((string)$msg))) {
            $msg = $row['title'] ?? 'No additional details provided.';
        }
        $announcements[] = [
            'id'         => $row['id'] ?? rand(100, 999),
            'title'      => $row['title'] ?? $row['subject'] ?? $row['heading'] ?? 'Notice',
            'message'    => $msg,
            'created_at' => $row['created_at'] ?? $row['date'] ?? date('Y-m-d')
        ];
    }
} catch (Throwable $e) {}

// 4. FETCH EXAM RESULTS (STRICT & EXACT MATCH)
$exam_results = [];
try {
    $subjects_map = [];
    try {
        $sub_query = $db_conn->query("SELECT * FROM subjects");
        if ($sub_query) {
            while ($s_row = $sub_query->fetch(PDO::FETCH_ASSOC)) {
                $s_id = $s_row['id'] ?? 0;
                $s_name = $s_row['subject_name'] ?? $s_row['name'] ?? $s_row['title'] ?? '';
                if ($s_id && $s_name) {
                    $subjects_map[$s_id] = $s_name;
                }
            }
        }
    } catch (Throwable $ex) {}

    $res_cols = getTableColumns($db_conn, 'results');
    $where_clauses = [];
    $params = [];

    if (in_array('student_id', $res_cols)) { $where_clauses[] = "`student_id` = ?"; $params[] = $real_student_id; }
    if (in_array('std_id', $res_cols)) { $where_clauses[] = "`std_id` = ?"; $params[] = $real_student_id; }
    if (in_array('user_id', $res_cols)) { $where_clauses[] = "`user_id` = ?"; $params[] = $user_id; }
    if (in_array('admission_no', $res_cols)) { $where_clauses[] = "`admission_no` = ?"; $params[] = $admission_no; }
    if (in_array('student_name', $res_cols)) { $where_clauses[] = "`student_name` = ?"; $params[] = $std_name; }

    if (!empty($where_clauses)) {
        $res_sql = "SELECT * FROM results WHERE (" . implode(' OR ', $where_clauses) . ") ORDER BY id DESC";
        $res_stmt = $db_conn->prepare($res_sql);
        $res_stmt->execute($params);
        $raw_results = $res_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_results as $row) {
            $sub_id = $row['subject_id'] ?? 0;
            $sub = '';

            if (!empty($subjects_map[$sub_id])) {
                $sub = $subjects_map[$sub_id];
            } elseif (!empty($row['subject_name'])) {
                $sub = $row['subject_name'];
            } elseif (!empty($row['subject'])) {
                $sub = $row['subject'];
            } else {
                $sub = 'Subject';
            }

            $marks = (float)($row['marks_obtained'] ?? $row['marks'] ?? $row['obtained_marks'] ?? $row['score'] ?? 0);
            $total = (float)($row['total_marks'] ?? $row['max_marks'] ?? 100);
            if ($total <= 0) { $total = 100; }

            $pct = round(($marks / $total) * 100, 1);

            $grd = trim((string)($row['grade'] ?? ''));
            if (empty($grd) || $grd === '-') {
                if ($pct >= 80) { $grd = 'A+'; }
                elseif ($pct >= 70) { $grd = 'A'; }
                elseif ($pct >= 60) { $grd = 'B'; }
                elseif ($pct >= 50) { $grd = 'C'; }
                elseif ($pct >= 40) { $grd = 'D'; }
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

// 5. FETCH FEE VOUCHERS (STRICT MATCH ONLY)
$fee_vouchers = [];
try {
    $fee_cols = getTableColumns($db_conn, 'fees');
    $where_clauses = [];
    $params = [];

    if (in_array('student_id', $fee_cols)) { $where_clauses[] = "`student_id` = ?"; $params[] = $real_student_id; }
    if (in_array('std_id', $fee_cols)) { $where_clauses[] = "`std_id` = ?"; $params[] = $real_student_id; }
    if (in_array('admission_no', $fee_cols)) { $where_clauses[] = "`admission_no` = ?"; $params[] = $admission_no; }

    if (!empty($where_clauses)) {
        $fee_sql = "SELECT * FROM fees WHERE (" . implode(' OR ', $where_clauses) . ") ORDER BY id DESC";
        $fee_stmt = $db_conn->prepare($fee_sql);
        $fee_stmt->execute($params);
        $raw_fees = $fee_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($raw_fees as $row) {
            $fee_vouchers[] = [
                'month_details' => $row['month_details'] ?? $row['month'] ?? $row['fee_month'] ?? 'Monthly Fee',
                'amount'        => number_format((float)($row['amount'] ?? $row['total_amount'] ?? 0), 2),
                'status'        => $row['status'] ?? 'Unpaid'
            ];
        }
    }
} catch (Throwable $e) {}

// 6. BLOCK LOGIC
$st_val = strtolower(trim((string)($student['status'] ?? 'paid')));
$is_blocked = in_array($st_val, ['unpaid', 'blocked', 'inactive', '0']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard | New Beacon School System</title>
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
        th { background: #f8fafc; color: #475569; font-weight: 700; text-align: left; padding: 10px 8px; border-bottom: 2px solid #e2e8f0; text-transform: uppercase; font-size: 11px; letter-spacing: 0.5px; }
        td { padding: 10px 8px; border-bottom: 1px solid #f1f5f9; color: #1e293b; }
        tr:hover td { background-color: #f8fafc; }

        .grade-badge { display: inline-block; padding: 3px 8px; border-radius: 6px; font-weight: bold; font-size: 12px; background: #e0f2fe; color: #0369a1; text-align: center; min-width: 24px; }
        .status-badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: bold; text-transform: capitalize; }
        .status-present { background: #d1fae5; color: #065f46; }
        .status-absent { background: #fee2e2; color: #991b1b; }
        .status-leave { background: #fef3c7; color: #92400e; }
        .status-paid { background: #d1fae5; color: #065f46; }
        .status-unpaid { background: #fee2e2; color: #991b1b; }

        .notice-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px; margin-bottom: 10px; cursor: pointer; transition: all 0.2s ease-in-out; }
        .notice-card:hover { border-color: #2563eb; background: #eff6ff; transform: translateY(-1px); }
        .notice-title { font-weight: 700; color: #1e293b; font-size: 14px; margin-bottom: 4px; display: flex; align-items: center; gap: 6px; }
        .notice-date-text { font-size: 11px; color: #64748b; }

        .modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.6); backdrop-filter: blur(2px); display: none; justify-content: center; align-items: center; z-index: 99999; }
        .modal-box { background: #ffffff; padding: 25px; border-radius: 14px; max-width: 500px; width: 90%; box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1); position: relative; border-top: 6px solid #001f3f; }
        .modal-box h3 { margin-top: 0; color: #0f172a; font-size: 20px; font-weight: 700; border-bottom: 1px solid #f1f5f9; padding-bottom: 12px; padding-right: 20px; }
        .modal-box p { color: #334155; line-height: 1.6; font-size: 14px; margin: 15px 0; white-space: pre-line; background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid #e2e8f0; }
        .modal-box .notice-date { font-size: 12px; color: #64748b; font-weight: 600; }
        .close-btn { position: absolute; top: 15px; right: 20px; font-size: 24px; cursor: pointer; color: #94a3b8; font-weight: bold; }
        .close-btn:hover { color: #0f172a; }

        .blocked-modal-overlay { position: fixed; top: 0; left: 0; width: 100vw; height: 100vh; background: rgba(15, 23, 42, 0.95); display: flex; justify-content: center; align-items: center; z-index: 999999; }
        .blocked-modal-box { background: #ffffff; padding: 45px 35px; border-radius: 20px; text-align: center; max-width: 500px; width: 90%; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.9); border-top: 10px solid #dc2626; }
        .blocked-modal-box h2 { color: #dc2626; margin: 15px 0 10px; font-size: 26px; font-weight: 700; }
        .blocked-modal-box p { color: #1e293b; font-size: 16px; line-height: 1.6; margin-bottom: 25px; }
        .blocked-modal-box .btn-logout { display: inline-block; background: #dc2626; color: #ffffff; text-decoration: none; padding: 12px 30px; border-radius: 8px; font-weight: 600; font-size: 16px; }
    </style>
</head>
<body>

    <?php if ($is_blocked): ?>
    <div class="blocked-modal-overlay">
        <div class="blocked-modal-box">
            <div style="font-size: 60px; line-height: 1; color: #dc2626;">⛔</div>
            <h2>Portal Access Blocked</h2>
            <p>Your portal access has been blocked due to pending fee dues.</p>
            <p>Please contact the school administration to clear your dues and reactivate your portal access.</p>
            <a href="admin/logout.php" class="btn-logout">Logout</a>
        </div>
    </div>
    <?php else: ?>

    <div class="navbar">
        <div>
            <h2 style="margin:0;">New Beacon School System</h2>
            <div class="student-info">
                Student: <strong><?= e($student['student_name'] ?? $username) ?></strong> | 
                Admission No: <strong><?= e($student['admission_no'] ?? $username) ?></strong> | 
                Class: <strong><?= e($display_class) ?></strong>
            </div>
        </div>
        <a href="admin/logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="grid">
        <!-- Attendance -->
        <div class="card">
            <h3>📅 Attendance History</h3>
            <?php if (!empty($attendance_records)): ?>
                <table>
                    <thead>
                        <tr><th>DATE</th><th>STATUS</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($attendance_records as $att): ?>
                        <?php 
                            $stClass = 'status-present';
                            $stLow = strtolower(trim((string)($att['status'] ?? '')));
                            if($stLow === 'absent') { $stClass = 'status-absent'; }
                            elseif($stLow === 'leave') { $stClass = 'status-leave'; }
                            $dateVal = !empty($att['attendance_date']) ? $att['attendance_date'] : 'now';
                        ?>
                        <tr>
                            <td><?= e(date('d M Y', strtotime($dateVal))) ?></td>
                            <td><span class="status-badge <?= $stClass ?>"><?= e(ucfirst($att['status'] ?? 'N/A')) ?></span></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p class="empty">No attendance records found.</p>
            <?php endif; ?>
        </div>

        <!-- Announcements / Notices -->
        <div class="card">
            <h3>📢 Announcements & Notices</h3>
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

        <!-- Exam Results -->
        <div class="card">
            <h3>🎓 Exam Results</h3>
            <?php if (!empty($exam_results)): ?>
                <table>
                    <thead>
                        <tr>
                            <th>Subject</th>
                            <th>Marks</th>
                            <th>Grade</th>
                        </tr>
                    </thead>
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
                    <thead>
                        <tr><th>Month</th><th>Amount</th><th>Status</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($fee_vouchers as $fee): ?>
                        <?php $f_st = strtolower(trim((string)$fee['status'])) === 'paid' ? 'status-paid' : 'status-unpaid'; ?>
                        <tr>
                            <td><?= e($fee['month_details'] ?? 'N/A') ?></td>
                            <td><strong>Rs. <?= e($fee['amount']) ?></strong></td>
                            <td><span class="status-badge <?= $f_st ?>"><?= e(ucfirst($fee['status'])) ?></span></td>
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
            <div class="notice-date" id="modalDate">Date</div>
            <p id="modalBody">Notice Details...</p>
        </div>
    </div>

    <script>
        const noticesData = <?= json_encode($announcements, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP); ?>;

        function openNoticeModal(index) {
            const notice = noticesData[index];
            if (notice) {
                document.getElementById('modalTitle').innerText = notice.title || 'Notice';
                document.getElementById('modalBody').innerText = notice.message || 'No additional details available.';
                document.getElementById('modalDate').innerText = 'Posted on: ' + notice.created_at;
                document.getElementById('noticeModal').style.display = 'flex';
            }
        }

        function closeNoticeModal() {
            document.getElementById('noticeModal').style.display = 'none';
        }

        window.onclick = function(event) {
            var modal = document.getElementById('noticeModal');
            if (event.target == modal) {
                modal.style.display = "none";
            }
        }
    </script>

    <?php endif; ?>

</body>
</html>