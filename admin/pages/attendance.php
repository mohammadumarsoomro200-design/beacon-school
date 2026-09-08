<?php
require_login(['admin', 'teacher']);
$pdo = db();

$date = $_GET['date'] ?? date('Y-m-d');
$class_id = $_GET['class_id'] ?? '';

// Detect actual date column name in attendance table
$date_col = 'date';
try {
    $cols = $pdo->query("SHOW COLUMNS FROM attendance")->fetchAll(PDO::FETCH_COLUMN);
    if (in_array('attendance_date', $cols)) {
        $date_col = 'attendance_date';
    } elseif (in_array('att_date', $cols)) {
        $date_col = 'att_date';
    }
} catch (Exception $e) {
    $date_col = 'date';
}

// Fetch Classes for Dropdown
$classes = [];
try {
    $classes = $pdo->query("SELECT id, class_name, section FROM classes ORDER BY id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Handle Save Attendance POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_attendance'])) {
    check_csrf();
    
    $att_date = $_POST['att_date'] ?? date('Y-m-d');
    $students_status = $_POST['status'] ?? [];
    $remarks = $_POST['remarks'] ?? [];

    $stmt = $pdo->prepare("
        INSERT INTO attendance (student_id, `{$date_col}`, status, remarks) 
        VALUES (?, ?, ?, ?) 
        ON DUPLICATE KEY UPDATE status = VALUES(status), remarks = VALUES(remarks)
    ");

    foreach ($students_status as $student_id => $status) {
        $rem = $remarks[$student_id] ?? '';
        $stmt->execute([(int)$student_id, $att_date, $status, $rem]);
    }

    flash('success', 'Attendance saved successfully!');
    redirect("index.php?page=attendance&class_id=$class_id&date=$att_date");
}

// Fetch Students strictly by selected class_id
$students = [];
if ($class_id !== '') {
    try {
        $st_stmt = $pdo->prepare("
            SELECT id, admission_no, student_name 
            FROM students 
            WHERE class_id = ? 
            ORDER BY id ASC
        ");
        $st_stmt->execute([(int)$class_id]);
        $students = $st_stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
}

// Fetch Existing Attendance Records for selected date
$existing_attendance = [];
if (!empty($students)) {
    try {
        $att_stmt = $pdo->prepare("SELECT student_id, status, remarks FROM attendance WHERE `{$date_col}` = ?");
        $att_stmt->execute([$date]);
        $att_rows = $att_stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($att_rows as $row) {
            $existing_attendance[$row['student_id']] = $row;
        }
    } catch (Exception $e) {}
}
?>

<section class="panel">
    <!-- Header Controls -->
    <div class="panel-head" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:15px; padding:15px;">
        <h2 style="margin:0;">Daily Attendance</h2>
        <form method="get" style="display:flex; gap:10px; align-items:center; margin:0;">
            <input type="hidden" name="page" value="attendance">
            <input type="date" name="date" value="<?=e($date)?>" style="padding:6px; border-radius:6px; border:1px solid #ccc;">
            <select name="class_id" style="padding:6px; border-radius:6px; border:1px solid #ccc;" required>
                <option value="">-- Select Class --</option>
                <?php foreach ($classes as $c): ?>
                    <option value="<?=$c['id']?>" <?=$class_id == $c['id'] ? 'selected' : ''?>>
                        <?=e($c['class_name'] . ($c['section'] ? ' - ' . $c['section'] : ''))?>
                    </option>
                <?php endforeach; ?>
            </select>
            <button type="submit" class="btn primary" style="background:#007bff; color:#fff; border:none; padding:6px 15px; border-radius:6px; cursor:pointer;">Load</button>
        </form>
    </div>

    <!-- Attendance Form -->
    <?php if ($class_id !== ''): ?>
        <form method="post" style="margin-top:15px; padding:15px;">
            <input type="hidden" name="csrf" value="<?=e(csrf())?>">
            <input type="hidden" name="save_attendance" value="1">
            <input type="hidden" name="att_date" value="<?=e($date)?>">

            <div class="table-wrap">
                <table width="100%" cellpadding="10" style="border-collapse:collapse; text-align:left;">
                    <thead>
                        <tr style="border-bottom:2px solid #eee; background:#f9f9f9;">
                            <th>ADMISSION</th>
                            <th>STUDENT</th>
                            <th>STATUS</th>
                            <th>REMARKS</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($students)): ?>
                            <?php foreach ($students as $st): 
                                $curr_status = $existing_attendance[$st['id']]['status'] ?? 'Present';
                                $curr_remark = $existing_attendance[$st['id']]['remarks'] ?? '';
                            ?>
                            <tr style="border-bottom:1px solid #eee;">
                                <td><?=e($st['admission_no'])?></td>
                                <td><b><?=e($st['student_name'])?></b></td>
                                <td>
                                    <select name="status[<?=$st['id']?>]" style="padding:6px 12px; border-radius:6px; font-weight:600; cursor:pointer; width:130px;">
                                        <option value="Present" <?=$curr_status === 'Present' ? 'selected' : ''?> style="color:green;">Present</option>
                                        <option value="Absent" <?=$curr_status === 'Absent' ? 'selected' : ''?> style="color:red;">Absent</option>
                                        <option value="Leave" <?=$curr_status === 'Leave' ? 'selected' : ''?> style="color:orange;">Leave</option>
                                    </select>
                                </td>
                                <td>
                                    <input type="text" name="remarks[<?=$st['id']?>]" value="<?=e($curr_remark)?>" placeholder="Optional" style="padding:6px; width:100%; max-width:250px; border-radius:4px; border:1px solid #ccc;">
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="4" style="text-align:center; padding:20px; color:#888;">No students found in this selected class.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if (!empty($students)): ?>
                <div style="margin-top:20px;">
                    <button type="submit" class="btn primary" style="background:#b8860b; color:#fff; padding:10px 20px; font-weight:bold; border:none; border-radius:6px; cursor:pointer;">
                        Save Attendance
                    </button>
                </div>
            <?php endif; ?>
        </form>
    <?php else: ?>
        <p style="text-align:center; padding:30px; color:#666;">Please select a class and click <b>Load</b> to view attendance.</p>
    <?php endif; ?>
</section>