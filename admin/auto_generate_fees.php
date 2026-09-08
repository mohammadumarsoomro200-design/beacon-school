<?php
require_once __DIR__ . '/../config/config.php';

// Database Connection Check
if (!isset($db)) {
    if (isset($conn)) { $db = $conn; }
    elseif (isset($pdo)) { $db = $pdo; }
    elseif (function_exists('db')) { $db = db(); }
}

if (!$db) {
    die("Database connection error.");
}

$month_text = date('F Y');        // e.g. "September 2026"
$month_date = date('Y-m-01');     // e.g. "2026-09-01"
$due_date   = date('Y-m-15');     // 15th of current month

try {
    // Active Students jinki monthly_fee Set Ho
    $students = $db->query("SELECT id, monthly_fee FROM students WHERE status = 'active' AND monthly_fee > 0")->fetchAll(PDO::FETCH_ASSOC);

    $inserted_count = 0;

    foreach ($students as $student) {
        $student_id = (int)$student['id'];
        $fee_amount = (float)($student['monthly_fee'] ?? 0);

        if ($fee_amount <= 0) {
            continue;
        }

        // 1. Strict Duplicate Check (Check if voucher already exists for this month)
        $check = $db->prepare("
            SELECT id FROM fees 
            WHERE student_id = :sid 
            AND (month = :mtext OR month = :mdate OR month_details = :mtext OR month_details = :mdate)
            LIMIT 1
        ");
        
        $check->execute([
            ':sid'   => $student_id,
            ':mtext' => $month_text,
            ':mdate' => $month_date
        ]);

        // 2. Insert ONLY if no record exists
        if ($check->rowCount() == 0) {
            
            $voucher_no = "VOUCH-" . date('Ymd') . "-" . rand(100, 999);

            $stmt = $db->prepare("
                INSERT INTO fees (
                    voucher_no, 
                    student_id, 
                    amount, 
                    total_amount, 
                    paid_amount, 
                    month, 
                    month_details, 
                    status, 
                    due_date, 
                    created_at
                ) VALUES (
                    :vno, 
                    :sid, 
                    :amt, 
                    :t_amt, 
                    0.00, 
                    :mtext, 
                    :mtext, 
                    'unpaid', 
                    :ddate, 
                    NOW()
                )
            ");
            
            $stmt->execute([
                ':vno'   => $voucher_no,
                ':sid'   => $student_id,
                ':amt'   => $fee_amount,
                ':t_amt' => $fee_amount, // Double/Multiply hone se bachane ke liye strict amount
                ':mtext' => $month_text,
                ':ddate' => $due_date
            ]);

            $inserted_count++;
        }
    }

    echo "Fee Vouchers Auto-Generated Successfully! Generated: " . $inserted_count;

} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>