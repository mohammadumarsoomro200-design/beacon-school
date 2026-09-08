<?php
require_once __DIR__ . '/../config/config.php';

// Database Connection Check
if (!isset($db)) {
    if (isset($conn)) { $db = $conn; }
    elseif (isset($pdo)) { $db = $pdo; }
    elseif (function_exists('db')) { $db = db(); }
}

$current_month = date('Y-m-01'); // Iss mahine ki 1st date

try {
    // 1. Sabhi Active Students Ko Access Karein
    $students = $db->query("SELECT id, monthly_fee FROM students WHERE status = 'active'")->fetchAll();

    foreach ($students as $student) {
        $student_id = $student['id'];
        $fee_amount = $student['monthly_fee'] ?? 0;

        // Check karein ke kya iss mahine ki fee pehle se generated to nahi?
        $check = $db->prepare("SELECT id FROM fees WHERE student_id = ? AND month = ?");
        $check->execute([$student_id, $current_month]);

        if ($check->rowCount() == 0 && $fee_amount > 0) {
            // Naya Fee Voucher Insert Karein (Pending Status Ke Saath)
            $stmt = $db->prepare("INSERT INTO fees (student_id, amount, paid_amount, due_amount, month, status, created_at) VALUES (?, ?, 0, ?, ?, 'pending', NOW())");
            $stmt->execute([$student_id, $fee_amount, $fee_amount, $current_month]);
        }
    }
    echo "Fee Vouchers Auto-Generated Successfully!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>