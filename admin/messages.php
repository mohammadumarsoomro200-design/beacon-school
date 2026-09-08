<?php
// Include admin session and DB connection
if (file_exists(__DIR__ . '/../includes/init.php')) {
    require_once __DIR__ . '/../includes/init.php';
} elseif (file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
} else {
    session_start();
}

if (!function_exists('db')) {
    function db() {
        global $pdo;
        if (isset($pdo)) return $pdo;
        return new PDO('mysql:host=localhost;dbname=beacon_school;charset=utf8mb4', 'root', '', [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
    }
}

$pdo = db();

// Auto create contact_messages table if not exists
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS `contact_messages` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(255) NOT NULL,
        `phone` VARCHAR(50),
        `email` VARCHAR(255) NOT NULL,
        `subject` VARCHAR(255),
        `message` TEXT NOT NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
} catch (Exception $e) {}

// Fetch Messages
$messages = [];
try {
    $messages = $pdo->query("SELECT * FROM contact_messages ORDER BY id DESC")->fetchAll();
} catch (Exception $e) {}

function safe_e($val) {
    return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Contact Messages - Admin Panel</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f8fafc; margin: 0; padding: 20px; color: #334155; }
        .container { max-width: 1000px; margin: 0 auto; background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.05); }
        h2 { margin-top: 0; color: #0b2239; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; }
        table { width: 100%; border-collapse: collapse; margin-top: 15px; font-size: 14px; }
        th, td { text-align: left; padding: 10px; border-bottom: 1px solid #e2e8f0; }
        th { background: #f1f5f9; color: #475569; }
        .no-data { text-align: center; color: #94a3b8; padding: 30px; font-style: italic; }
    </style>
</head>
<body>

<div class="container">
    <h2>📬 Website Contact Messages</h2>
    <?php if (!empty($messages)): ?>
        <table>
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Name</th>
                    <th>Phone / Email</th>
                    <th>Subject</th>
                    <th>Message</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($messages as $msg): ?>
                <tr>
                    <td><?=safe_e(date('d-M-Y h:i A', strtotime($msg['created_at'])))?></td>
                    <td><b><?=safe_e($msg['name'])?></b></td>
                    <td>
                        <?=safe_e($msg['phone'])?><br>
                        <small style="color:#64748b;"><?=safe_e($msg['email'])?></small>
                    </td>
                    <td><?=safe_e($msg['subject'])?></td>
                    <td><?=nl2br(safe_e($msg['message']))?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php else: ?>
        <div class="no-data">Abhi tak koi message nahi aaya.</div>
    <?php endif; ?>
</div>

</body>
</html>