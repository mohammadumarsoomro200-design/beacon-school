<?php
// C:\xampp\htdocs\beacon-school\print_result.php
require_once __DIR__ . '/config/config.php';

if (!user()) {
    die("Access denied. Please login first.");
}

$u = $_SESSION['user'] ?? [];
$pdo = db();

// 1. Get Logged In Student Session Details
$session_user_id  = (int)($u['id'] ?? 0);
$session_st_id    = (int)($u['student_id'] ?? 0);
$admission_no     = trim((string)($u['username'] ?? ''));
$full_name        = trim((string)($u['full_name'] ?? $u['name'] ?? ''));

// 2. Find Student Record in `students`
$student = null;
try {
    $stmt = $pdo->prepare("
        SELECT s.*, c.class_name, c.section 
        FROM students s 
        LEFT JOIN classes c ON c.id = s.class_id 
        WHERE s.id = ? OR s.id = ? OR s.admission_no = ? OR s.student_name LIKE ?
        LIMIT 1
    ");
    $stmt->execute([$session_st_id, $session_user_id, $admission_no, '%' . $full_name . '%']);
    $student = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {}

$student_id   = (int)($student['id'] ?? $session_st_id ?: $session_user_id);
$student_name = $student['student_name'] ?? ($full_name ?: $admission_no);
$admission_no = $student['admission_no'] ?? $admission_no;
$class_title  = trim(($student['class_name'] ?? '') . ' ' . ($student['section'] ?? ''));
if (empty($class_title)) $class_title = 'N/A';

// 3. Pre-load ALL Subjects into a Map (Auto-Detect Column Names)
$subjects_map = [];
try {
    $sub_stmt = $pdo->query("SELECT * FROM subjects");
    while ($row = $sub_stmt->fetch(PDO::FETCH_ASSOC)) {
        $s_id = $row['id'] ?? 0;
        // Search across common subject name columns
        $s_name = $row['subject_name'] ?? $row['name'] ?? $row['title'] ?? $row['subject'] ?? '';
        if ($s_id && $s_name) {
            $subjects_map[$s_id] = $s_name;
        }
    }
} catch (Throwable $e) {}

// 4. Smart Result Retrieval Query (Exact Your Working Query)
$results = [];
$exam_name = 'Official Result Card';

try {
    $sql = "
        SELECT r.*, e.exam_name, sub.subject_name 
        FROM results r 
        LEFT JOIN exams e ON e.id = r.exam_id 
        LEFT JOIN subjects sub ON sub.id = r.subject_id 
        WHERE r.student_id = :st_id 
           OR r.student_id = :sess_st_id 
           OR r.student_id = :user_id
           OR (r.admission_no IS NOT NULL AND r.admission_no != '' AND r.admission_no = :adm_no)
           OR r.student_id IN (
               SELECT id FROM students WHERE admission_no = :adm_no2 OR student_name LIKE :st_name
           )
        ORDER BY r.id DESC
    ";
    
    $res_stmt = $pdo->prepare($sql);
    $res_stmt->execute([
        ':st_id'      => $student_id,
        ':sess_st_id' => $session_st_id,
        ':user_id'    => $session_user_id,
        ':adm_no'     => $admission_no,
        ':adm_no2'    => $admission_no,
        ':st_name'    => '%' . $student_name . '%'
    ]);
    $results = $res_stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($results[0]['exam_name'])) {
        $exam_name = $results[0]['exam_name'];
    }
} catch (Throwable $e) {
    try {
        $fallback = $pdo->prepare("SELECT * FROM results WHERE student_id = ? OR student_id = ?");
        $fallback->execute([$student_id, $session_st_id]);
        $results = $fallback->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $ex) {}
}

$grand_obtained = 0;
$grand_total = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Result Card - <?= htmlspecialchars($student_name) ?></title>
    <style>
        body { font-family: Arial, sans-serif; padding: 25px; text-align: center; background: #fff; }
        .card { border: 3px double #b8860b; padding: 25px; border-radius: 10px; max-width: 650px; margin: auto; }
        h1 { margin: 0; color: #1a2a3a; font-size: 22px; text-transform: uppercase; }
        p { margin: 4px 0; font-size: 13px; }
        .info { text-align: left; margin: 15px 0; border-bottom: 1px solid #ccc; padding-bottom: 10px; font-size: 14px; }
        .info p { margin: 6px 0; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; }
        th, td { border: 1px solid #333; padding: 8px; text-align: center; font-size: 13px; }
        th { background: #f4f4f4; }
        .tfoot { font-weight: bold; background: #fafafa; }
        .footer { margin-top: 45px; display: flex; justify-content: space-between; font-weight: bold; font-size: 13px; }
        .no-print { margin-bottom: 20px; }
        .btn-print { background: #2563eb; color: #fff; border: none; padding: 8px 16px; border-radius: 5px; cursor: pointer; font-weight: bold; }
        @media print { .no-print { display: none; } }
    </style>
</head>
<body>

    <div class="no-print">
        <button onclick="window.print()" class="btn-print">🖨️ Print Result Card</button>
    </div>

    <div class="card">
        <h1>THE NEW BEACON SCHOOL SYSTEM</h1>
        <p>Larkana Campus - Official Result Card</p>
        <hr style="margin: 10px 0; border: 0; border-top: 1px solid #ccc;">

        <div class="info">
            <p><b>Student Name:</b> <?= htmlspecialchars($student_name) ?> (Adm No: <?= htmlspecialchars($admission_no) ?>)</p>
            <p><b>Class:</b> <?= htmlspecialchars($class_title) ?></p>
            <p><b>Examination:</b> <?= htmlspecialchars($exam_name) ?></p>
        </div>

        <table>
            <thead>
                <tr>
                    <th style="text-align:left;">Subject</th>
                    <th>Obtained Marks</th>
                    <th>Total Marks</th>
                    <th>Percentage</th>
                </tr>
            </thead>
            <tbody>
                <?php if (!empty($results)): ?>
                    <?php foreach ($results as $res): ?>
                    <?php 
                        // Auto-detect marks
                        $marks = (float)($res['marks'] ?? $res['marks_obtained'] ?? $res['obtained_marks'] ?? 0);
                        $total = (float)($res['total_marks'] ?? $res['max_marks'] ?? 100);
                        
                        // Auto-detect Subject Name (DB value OR preloaded map OR direct column)
                        $sub_id = (int)($res['subject_id'] ?? 0);
                        $sub_name = $res['subject_name'] ?? $res['subject'] ?? '';
                        
                        if (empty($sub_name) && isset($subjects_map[$sub_id])) {
                            $sub_name = $subjects_map[$sub_id];
                        }
                        if (empty($sub_name)) {
                            $sub_name = ($sub_id > 0) ? "Subject ({$sub_id})" : "Subject";
                        }
                        
                        $pct   = ($total > 0) ? round(($marks / $total) * 100, 1) : 0;
                        $grand_obtained += $marks;
                        $grand_total += $total;
                    ?>
                    <tr>
                        <td style="text-align:left; padding:8px;"><?= htmlspecialchars($sub_name) ?></td>
                        <td><?= number_format($marks, 2) ?></td>
                        <td><?= number_format($total, 2) ?></td>
                        <td><b><?= $pct ?>%</b></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="4" style="text-align:center;">No results uploaded yet.</td></tr>
                <?php endif; ?>
            </tbody>
            <?php if ($grand_total > 0): ?>
            <tfoot>
                <tr class="tfoot">
                    <td style="text-align:left; padding:8px;">TOTAL</td>
                    <td><?= $grand_obtained ?></td>
                    <td><?= $grand_total ?></td>
                    <td><b><?= round(($grand_obtained / $grand_total) * 100, 1) ?>%</b></td>
                </tr>
            </tfoot>
            <?php endif; ?>
        </table>

        <div class="footer">
            <span>Class Teacher Signature</span>
            <span>Principal Signature</span>
        </div>
    </div>

</body>
</html>