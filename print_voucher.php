<?php
// print_voucher.php
require_once __DIR__ . '/config/config.php';

// User login check
if (!user()) {
    header("Location: admin/login.php");
    exit();
}

$db_conn = function_exists('db') ? db() : ($db_conn ?? $conn ?? $db ?? null);
if (!$db_conn) {
    die("Database connection error.");
}

$fee_id = (int)($_GET['id'] ?? 0);

if ($fee_id <= 0) {
    die("Invalid Voucher ID.");
}

// Helper function to safely find table columns
function getTableColumns($db_conn, $table) {
    try {
        $stmt = $db_conn->query("SHOW COLUMNS FROM `$table`");
        return $stmt ? $stmt->fetchAll(PDO::FETCH_COLUMN) : [];
    } catch (Throwable $e) {
        return [];
    }
}

// 1. FETCH FEE RECORD
$fee = null;
try {
    $stmt = $db_conn->prepare("SELECT * FROM fees WHERE id = ? LIMIT 1");
    $stmt->execute([$fee_id]);
    $fee = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    die("Error executing fee query: " . $e->getMessage());
}

if (!$fee) {
    die("Fee voucher record not found in database.");
}

// Determine Student ID from Fee Record
$std_id_val = (int)($fee['student_id'] ?? $fee['std_id'] ?? $fee['user_id'] ?? 0);

// 2. FETCH STUDENT RECORD
$student = [];
if ($std_id_val > 0) {
    try {
        $s_stmt = $db_conn->prepare("SELECT * FROM students WHERE id = ? LIMIT 1");
        $s_stmt->execute([$std_id_val]);
        $student = $s_stmt->fetch(PDO::FETCH_ASSOC) ?: [];
    } catch (Throwable $e) {}
}

// Fallback to session user data if student table search fails
$u = $_SESSION['user'] ?? [];
$student_name = $student['student_name'] ?? $student['name'] ?? $fee['student_name'] ?? $u['full_name'] ?? $u['username'] ?? 'N/A';
$admission_no = $student['admission_no'] ?? $student['roll_no'] ?? $fee['admission_no'] ?? $u['username'] ?? 'N/A';
$class_id     = (int)($student['class_id'] ?? $fee['class_id'] ?? 0);

// 3. FETCH CLASS NAME
$class_name = 'N/A';
if ($class_id > 0) {
    try {
        $c_stmt = $db_conn->prepare("SELECT * FROM classes WHERE id = ? LIMIT 1");
        $c_stmt->execute([$class_id]);
        $c_row = $c_stmt->fetch(PDO::FETCH_ASSOC);

        if ($c_row) {
            foreach (['class_name', 'name', 'title', 'class', 'grade'] as $col) {
                if (!empty($c_row[$col])) {
                    $class_name = $c_row[$col];
                    break;
                }
            }
        }
    } catch (Throwable $e) {}
}

if ($class_name === 'N/A') {
    $class_name = $student['class_name'] ?? $fee['class_name'] ?? 'N/A';
}

$is_paid     = strtolower(trim((string)($fee['status'] ?? 'unpaid'))) === 'paid';
$school_name = "The New Beacon School System";
$month       = $fee['month_details'] ?? $fee['month'] ?? $fee['fee_month'] ?? 'Monthly Fee';
$amount      = number_format((float)($fee['amount'] ?? $fee['total_amount'] ?? 0), 2);
$issue_date  = !empty($fee['created_at']) ? date('d-M-Y', strtotime($fee['created_at'])) : date('d-M-Y');
$due_date    = !empty($fee['due_date']) ? date('d-M-Y', strtotime($fee['due_date'])) : date('10-M-Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $is_paid ? 'Fee Paid Receipt' : 'Fee Voucher' ?> - <?= htmlspecialchars($student_name) ?></title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7fe; padding: 20px; color: #333; }
        .voucher-card { max-width: 650px; margin: 0 auto; background: #fff; padding: 30px; border-radius: 12px; box-shadow: 0 4px 15px rgba(0,0,0,0.1); border-top: 8px solid <?= $is_paid ? '#10b981' : '#ef4444' ?>; }
        .header { text-align: center; border-bottom: 2px dashed #e2e8f0; padding-bottom: 15px; margin-bottom: 20px; }
        .header h1 { margin: 0; font-size: 22px; color: #001f3f; text-transform: uppercase; }
        .header p { margin: 5px 0 0; color: #64748b; font-size: 14px; }
        .badge { display: inline-block; padding: 6px 16px; border-radius: 50px; font-weight: bold; font-size: 12px; text-transform: uppercase; letter-spacing: 1px; margin-top: 10px; }
        .badge-paid { background: #d1fae5; color: #065f46; border: 1px solid #10b981; }
        .badge-unpaid { background: #fee2e2; color: #991b1b; border: 1px solid #ef4444; }
        .info-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 15px; margin-bottom: 20px; background: #f8fafc; padding: 15px; border-radius: 8px; font-size: 14px; }
        .info-grid div span { display: block; color: #64748b; font-size: 11px; text-transform: uppercase; font-weight: bold; }
        .info-grid div strong { color: #1e293b; font-size: 15px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #e2e8f0; font-size: 14px; }
        th { background: #f1f5f9; color: #475569; }
        .total-row { font-size: 16px; font-weight: bold; background: #fafafa; }
        .footer { text-align: center; margin-top: 30px; font-size: 12px; color: #94a3b8; }
        .print-btn { display: block; width: 100%; padding: 12px; background: #001f3f; color: #fff; text-align: center; border: none; border-radius: 6px; font-weight: bold; cursor: pointer; margin-bottom: 20px; font-size: 15px; }
        .print-btn:hover { background: #001429; }
        @media print {
            body { background: #fff; padding: 0; }
            .voucher-card { box-shadow: none; border: 1px solid #ccc; max-width: 100%; }
            .print-btn { display: none; }
        }
    </style>
</head>
<body>

    <div style="max-width: 650px; margin: 0 auto;">
        <button class="print-btn" onclick="window.print()">🖨️ Print Voucher / Receipt</button>
    </div>

    <div class="voucher-card">
        <div class="header">
            <h1><?= htmlspecialchars($school_name) ?></h1>
            <p><?= $is_paid ? 'OFFICIAL FEE RECEIPT' : 'STUDENT FEE VOUCHER' ?></p>
            <span class="badge <?= $is_paid ? 'badge-paid' : 'badge-unpaid' ?>">
                STATUS: <?= $is_paid ? 'PAID' : 'UNPAID' ?>
            </span>
        </div>

        <div class="info-grid">
            <div>
                <span>Student Name</span>
                <strong><?= htmlspecialchars($student_name) ?></strong>
            </div>
            <div>
                <span>Admission No</span>
                <strong><?= htmlspecialchars($admission_no) ?></strong>
            </div>
            <div>
                <span>Class</span>
                <strong><?= htmlspecialchars($class_name) ?></strong>
            </div>
            <div>
                <span>Fee Month</span>
                <strong><?= htmlspecialchars($month) ?></strong>
            </div>
            <div>
                <span>Issue Date</span>
                <strong><?= $issue_date ?></strong>
            </div>
            <div>
                <span>Due Date</span>
                <strong><?= $due_date ?></strong>
            </div>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Description</th>
                    <th style="text-align: right;">Amount (PKR)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>School Tuition & Monthly Fees (<?= htmlspecialchars($month) ?>)</td>
                    <td style="text-align: right;">Rs. <?= $amount ?></td>
                </tr>
                <tr class="total-row">
                    <td>Total Payable Amount</td>
                    <td style="text-align: right; color: <?= $is_paid ? '#10b981' : '#ef4444' ?>;">Rs. <?= $amount ?></td>
                </tr>
            </tbody>
        </table>

        <?php if (!$is_paid): ?>
            <div style="background: #fffbe3; border: 1px solid #ffe58f; padding: 10px; border-radius: 6px; font-size: 12px; color: #856404; text-align: center; margin-bottom: 20px;">
                ⚠️ Note: Please pay the fee on or before the due date to avoid late payment surcharge and portal blocking.
            </div>
        <?php else: ?>
            <div style="background: #ecfdf5; border: 1px solid #a7f3d0; padding: 10px; border-radius: 6px; font-size: 12px; color: #065f46; text-align: center; margin-bottom: 20px;">
                ✔ Payment received successfully. Thank you!
            </div>
        <?php endif; ?>

        <div class="footer">
            <p>This is a computer-generated document. No signature required.</p>
        </div>
    </div>

</body>
</html>