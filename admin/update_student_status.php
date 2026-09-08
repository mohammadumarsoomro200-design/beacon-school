<?php
require_once __DIR__ . '/../config/config.php';

// Check if user is logged in
if (!user()) {
    header("Location: login.php");
    exit();
}

$db_conn = db();
$student_id = (int)($_GET['id'] ?? 0);
$new_status = trim($_GET['status'] ?? '');

if ($student_id > 0 && in_array($new_status, ['paid', 'unpaid'])) {
    try {
        // 1. Update status in students table
        $stmt = $db_conn->prepare("UPDATE students SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $student_id]);

        // 2. Update fee status in fees table as well
        $fee_status = ($new_status === 'paid') ? 'paid' : 'unpaid';
        $fee_stmt = $db_conn->prepare("UPDATE fees SET status = ? WHERE student_id = ?");
        $fee_stmt->execute([$fee_status, $student_id]);

    } catch (Throwable $e) {
        // Handle error if needed
    }
}

// Redirect back to students page
header("Location: index.php?page=students");
exit();