<?php
// student_dashboard.php
require_once __DIR__ . '/config/config.php';

// 1. Check if student is logged in
if (!user()) {
    header("Location: admin/login.php");
    exit();
}

$u = $_SESSION['user'];

if (($u['role'] ?? '') !== 'student') {
    header("Location: admin/index.php");
    exit();
}

$db_conn = db();
$user_id = (int)($u['id'] ?? 0);
$username = trim((string)($u['username'] ?? ''));
$full_name = trim((string)($u['full_name'] ?? $u['name'] ?? $username));
$student_id = (int)($u['student_id'] ?? $user_id);

// Safe Default Structure
$student = [
    'id' => $student_id,
    'student_name' => $full_name,
    'admission_no' => $username,
    'class_name' => 'Grade 1',
    'section' => 'Red',
    'status' => 'paid'
];

// 2. FETCH STUDENT RECORD FROM DATABASE (Multiple Matching Fields)
try {
    $stmt = $db_conn->prepare("SELECT * FROM students WHERE id = ? OR admission_no = ? OR student_name = ? OR email = ? LIMIT 1");
    $stmt->execute([$student_id, $username, $full_name, $username]);
    $db_student = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($db_student) {
        $student = array_merge($student, $db_student);
    }
} catch (Throwable $e) {}

$real_student_id = $student['id'];
$admission_no = $student['admission_no'] ?? $username;
$std_name = $student['student_name'] ?? $full_name;

// 3. FETCH DASHBOARD DATA
$attendance_records = [];
try {
    $att_stmt = $db_conn->prepare("SELECT attendance_date, status FROM attendance WHERE student_id = ? OR student_id = ? OR student_name = ? ORDER BY attendance_date DESC");
    $att_stmt->execute([$real_student_id, $admission_no, $std_name]);
    $attendance_records = $att_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$announcements = [];
try {
    $ann_stmt = $db_conn->query("SELECT title, message, created_at FROM announcements WHERE status = 'active' ORDER BY id DESC LIMIT 5");
    if ($ann_stmt) { $announcements = $ann_stmt->fetchAll(PDO::FETCH_ASSOC) ?: []; }
} catch (Throwable $e) {}

$exam_results = [];
try {
    $res_stmt = $db_conn->prepare("SELECT subject_name, marks_obtained, total_marks, grade FROM exam_results WHERE student_id = ? OR student_id = ? OR student_name = ?");
    $res_stmt->execute([$real_student_id, $admission_no, $std_name]);
    $exam_results = $res_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

$fee_vouchers = [];
try {
    $fee_stmt = $db_conn->prepare("SELECT * FROM fees WHERE student_id = ? OR student_id = ? OR student_name = ?");
    $fee_stmt->execute([$real_student_id, $admission_no, $std_name]);
    $fee_vouchers = $fee_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
} catch (Throwable $e) {}

// 4. BLOCK / POPUP LOGIC
$st_val = strtolower(trim((string)($student['status'] ?? 'paid')));

// Student blocked tabhi hoga jab status explicitly 'unpaid', 'blocked', 'inactive', ya '0' hoga
$is_blocked = in_array($st_val, ['unpaid', 'blocked', 'inactive', '0']);
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Student Dashboard | New Beacon School System</title>
    <link rel="stylesheet" href="assets/css/app.css">
    <style>
        body { background: #f4f7fe; font-family: system-ui, -apple-system, sans-serif; margin: 0; padding: 20px; position: relative; }
        
        .navbar { 
            display: flex; 
            justify-content: space-between; 
            align-items: center; 
            background: #001f3f; 
            color: #fff; 
            padding: 15px 25px; 
            border-radius: 10px; 
        }
        .student-info { font-size: 14px; color: #d0e1fd; margin-top: 5px; }
        .logout-btn { background: #e63946; color: #fff; text-decoration: none; padding: 8px 16px; border-radius: 6px; font-weight: bold; }
        
        .grid { 
            display: grid; 
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); 
            gap: 20px; 
            margin-top: 25px; 
        }

        .card { background: #fff; padding: 20px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h3 { margin-top: 0; color: #1d3557; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; }
        .empty { color: #888; font-style: italic; }
        table { width: 100%; border-collapse: collapse; margin-top: 10px; }
        th, td { text-align: left; padding: 8px; border-bottom: 1px solid #eee; }

        /* FULLSCREEN LOCK OVERLAY */
        .blocked-modal-overlay { 
            position: fixed !important; 
            top: 0 !important; 
            left: 0 !important; 
            width: 100vw !important; 
            height: 100vh !important; 
            background: rgba(15, 23, 42, 0.95) !important; 
            display: flex !important; 
            justify-content: center !important; 
            align-items: center !important; 
            z-index: 99999999 !important; 
        }
        .blocked-modal-box { 
            background: #ffffff !important; 
            padding: 45px 35px !important; 
            border-radius: 20px !important; 
            text-align: center !important; 
            max-width: 500px !important; 
            width: 90% !important; 
            box-shadow: 0 25px 50px -12px rgba(0,0,0,0.9) !important; 
            border-top: 10px solid #dc2626 !important; 
        }
        .blocked-modal-box h2 { color: #dc2626; margin: 15px 0 10px; font-size: 26px; font-weight: 700; }
        .blocked-modal-box p { color: #1e293b; font-size: 16px; line-height: 1.6; margin-bottom: 25px; }
        .blocked-modal-box .btn-logout { 
            display: inline-block; 
            background: #dc2626; 
            color: #ffffff; 
            text-decoration: none; 
            padding: 12px 30px; 
            border-radius: 8px; 
            font-weight: 600; 
            font-size: 16px; 
        }
    </style>
</head>
<body>

    <?php if ($is_blocked): ?>
   <!-- POPUP MODAL FOR BLOCKED/UNPAID STUDENTS -->
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
                Class: <strong><?= e(($student['class_name'] ?? 'Grade 1') . ' ' . ($student['section'] ?? 'Red')) ?></strong>
            </div>
        </div>
        <a href="admin/logout.php" class="logout-btn">Logout</a>
    </div>

    <div class="grid">
        <!-- Attendance History -->
        <div class="card">
            <h3>📅 Attendance History</h3>
            <?php if (!empty($attendance_records)): ?>
                <table>
                    <tr><th>Date</th><th>Status</th></tr>
                    <?php foreach ($attendance_records as $att): ?>
                        <tr>
                            <td><?= e($att['attendance_date']) ?></td>
                            <td><?= e($att['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p class="empty">No attendance records found.</p>
            <?php endif; ?>
        </div>

        <!-- Announcements & Notices -->
        <div class="card">
            <h3>📢 Announcements & Notices</h3>
            <?php if (!empty($announcements)): ?>
                <?php foreach ($announcements as $ann): ?>
                    <div style="margin-bottom: 10px;">
                        <strong><?= e($ann['title']) ?></strong><br>
                        <small><?= e($ann['message']) ?></small>
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
                    <tr><th>Subject</th><th>Marks</th><th>Grade</th></tr>
                    <?php foreach ($exam_results as $res): ?>
                        <tr>
                            <td><?= e($res['subject_name']) ?></td>
                            <td><?= e($res['marks_obtained']) ?>/<?= e($res['total_marks']) ?></td>
                            <td><?= e($res['grade']) ?></td>
                        </tr>
                    <?php endforeach; ?>
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
                    <tr><th>Month</th><th>Amount</th><th>Status</th></tr>
                    <?php foreach ($fee_vouchers as $fee): ?>
                        <tr>
                            <td><?= e($fee['month_details'] ?? $fee['month'] ?? 'N/A') ?></td>
                            <td><?= e($fee['amount']) ?></td>
                            <td><?= e($fee['status']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </table>
            <?php else: ?>
                <p class="empty">No fee vouchers generated.</p>
            <?php endif; ?>
        </div>
    </div>

    <?php endif; ?>

</body>
</html>