<?php
// admin/pages/homework.php
require_once __DIR__ . '/../../config/config.php';

// Check Admin / Teacher Login
if (!user() || (($_SESSION['user']['role'] ?? '') !== 'admin' && ($_SESSION['user']['role'] ?? '') !== 'teacher')) {
    header("Location: ../login.php");
    exit();
}

$db_conn = function_exists('db') ? db() : ($db_conn ?? $conn ?? $db ?? null);

// Automatic Foreign Key Drop and Structure Fix
try {
    // 1. Drop foreign key constraint causing the error
    $db_conn->exec("ALTER TABLE `homework` DROP FOREIGN KEY `homework_ibfk_2`;");
} catch (Throwable $e) {}

try {
    $db_conn->exec("ALTER TABLE `homework` DROP FOREIGN KEY `homework_ibfk_1`;");
} catch (Throwable $e) {}

try {
    // 2. Ensure table structure is compatible
    $db_conn->exec("CREATE TABLE IF NOT EXISTS `homework` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `class_id` INT NOT NULL DEFAULT 0,
        `subject` VARCHAR(100) NULL,
        `subject_id` INT NULL,
        `title` VARCHAR(255) NOT NULL,
        `description` TEXT NULL,
        `due_date` DATE NULL,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

    // Check & Add missing columns
    $existing_cols = [];
    $col_stmt = $db_conn->query("SHOW COLUMNS FROM `homework`");
    if ($col_stmt) {
        $existing_cols = $col_stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    if (!in_array('subject', $existing_cols)) {
        $db_conn->exec("ALTER TABLE `homework` ADD `subject` VARCHAR(100) NULL AFTER `class_id`;");
    }
    if (!in_array('subject_id', $existing_cols)) {
        $db_conn->exec("ALTER TABLE `homework` ADD `subject_id` INT NULL DEFAULT 0 AFTER `subject`;");
    }
    if (!in_array('class_id', $existing_cols)) {
        $db_conn->exec("ALTER TABLE `homework` ADD `class_id` INT NOT NULL DEFAULT 0 AFTER `id`;");
    }
    if (!in_array('due_date', $existing_cols)) {
        $db_conn->exec("ALTER TABLE `homework` ADD `due_date` DATE NULL AFTER `description`;");
    }
} catch (Throwable $e) {}

$msg = '';
$msg_type = '';

// Handle Homework Post
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_homework'])) {
    $class_id = (int)($_POST['class_id'] ?? 0);
    $subject  = trim($_POST['subject'] ?? '');
    $title    = trim($_POST['title'] ?? '');
    $due_date = !empty($_POST['due_date']) ? $_POST['due_date'] : null;
    $desc     = trim($_POST['description'] ?? '');

    if ($class_id > 0 && !empty($title)) {
        try {
            $stmt = $db_conn->prepare("INSERT INTO homework (class_id, subject, subject_id, title, description, due_date) VALUES (?, ?, 0, ?, ?, ?)");
            $stmt->execute([$class_id, $subject, $title, $desc, $due_date]);
            $msg = "Homework posted successfully!";
            $msg_type = "success";
        } catch (Throwable $e) {
            $msg = "Error posting homework: " . $e->getMessage();
            $msg_type = "danger";
        }
    } else {
        $msg = "Please select a Class and enter Title.";
        $msg_type = "warning";
    }
}

// Fetch Dynamic Classes
$classes = [];
try {
    $c_stmt = $db_conn->query("SELECT * FROM classes ORDER BY id ASC");
    $classes = $c_stmt ? $c_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {}

// Fetch Recent Homeworks
$recent_homeworks = [];
try {
    $h_stmt = $db_conn->query("
        SELECT h.*, COALESCE(CONCAT(c.class_name, ' ', COALESCE(c.section, '')), 'All Classes') as class_title 
        FROM homework h 
        LEFT JOIN classes c ON h.class_id = c.id 
        ORDER BY h.id DESC LIMIT 10
    ");
    $recent_homeworks = $h_stmt ? $h_stmt->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Manage Homework | Admin</title>
    <style>
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f4f7fe; margin: 0; padding: 20px; }
        .container { max-width: 1100px; margin: 0 auto; display: grid; grid-template-columns: 1fr 1fr; gap: 20px; }
        .card { background: #fff; padding: 25px; border-radius: 12px; box-shadow: 0 4px 12px rgba(0,0,0,0.05); }
        .card h2 { margin-top: 0; color: #001f3f; border-bottom: 2px solid #f0f0f0; padding-bottom: 10px; font-size: 20px; }
        .form-group { margin-bottom: 15px; }
        label { display: block; font-weight: 600; font-size: 13px; margin-bottom: 5px; color: #475569; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #cbd5e1; border-radius: 6px; box-sizing: border-box; font-size: 14px; }
        .btn { background: #a17a00; color: #fff; border: none; padding: 11px 20px; border-radius: 6px; font-weight: bold; cursor: pointer; width: 100%; }
        .btn:hover { background: #856400; }
        .alert { padding: 10px 15px; border-radius: 6px; margin-bottom: 15px; font-size: 14px; }
        .alert-success { background: #d1fae5; color: #065f46; }
        .alert-danger { background: #fee2e2; color: #991b1b; }
        .alert-warning { background: #fef3c7; color: #92400e; }
        .hw-item { background: #f8fafc; border-left: 4px solid #a17a00; padding: 12px; margin-bottom: 10px; border-radius: 4px; }
        .hw-title { font-weight: bold; color: #1e293b; font-size: 15px; }
        .hw-meta { font-size: 12px; color: #64748b; margin-top: 4px; }
    </style>
</head>
<body>

<div class="container">
    <div class="card">
        <h2>Post Homework</h2>

        <?php if (!empty($msg)): ?>
            <div class="alert alert-<?= $msg_type ?>"><?= htmlspecialchars($msg) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="form-group">
                <label>Class & Section</label>
                <select name="class_id" required>
                    <option value="">-- Select Class --</option>
                    <?php foreach ($classes as $c): ?>
                        <?php 
                            $c_name = $c['class_name'] ?? $c['name'] ?? $c['title'] ?? 'Class';
                            $c_sec  = $c['section'] ?? $c['sec'] ?? '';
                        ?>
                        <option value="<?= $c['id'] ?>"><?= htmlspecialchars($c_name . ($c_sec ? ' - ' . $c_sec : '')) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="form-group">
                <label>Subject</label>
                <input type="text" name="subject" placeholder="e.g. English, Math, Drawing" required>
            </div>

            <div class="form-group">
                <label>Title</label>
                <input type="text" name="title" placeholder="Homework Title" required>
            </div>

            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" required>
            </div>

            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="4" placeholder="Enter details or instructions..."></textarea>
            </div>

            <button type="submit" name="post_homework" class="btn">Post Homework</button>
        </form>
    </div>

    <div class="card">
        <h2>Recent Homework</h2>
        <?php if (!empty($recent_homeworks)): ?>
            <?php foreach ($recent_homeworks as $hw): ?>
                <div class="hw-item">
                    <div class="hw-title"><?= htmlspecialchars($hw['title']) ?></div>
                    <div class="hw-meta">
                        <strong>Class:</strong> <?= htmlspecialchars($hw['class_title']) ?><br>
                        <strong>Subject:</strong> <?= htmlspecialchars($hw['subject'] ?? 'N/A') ?> | 
                        <strong>Due:</strong> <?= htmlspecialchars($hw['due_date'] ?? 'N/A') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <p style="color: #888; text-align: center;">No homework posted yet.</p>
        <?php endif; ?>
    </div>
</div>

</body>
</html>