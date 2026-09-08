<?php
mysqli_report(MYSQLI_REPORT_OFF);
$conn = @mysqli_connect('localhost', 'root', '', 'beacon_school');

if (!function_exists('e')) {
    function e($val) {
        return htmlspecialchars($val ?? '', ENT_QUOTES, 'UTF-8');
    }
}

$message = '';
$message_type = '';

// Handle Teacher Assignment / Class Saving
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['save_class'])) {
        $class_id = intval($_POST['class_id'] ?? 0);
        $class_name = trim($_POST['class_name'] ?? '');
        $section = trim($_POST['section'] ?? '');
        $teacher_id = intval($_POST['teacher_id'] ?? 0);

        if ($class_id > 0) {
            // Update Existing Class with Teacher
            $query = "UPDATE classes SET class_name = '$class_name', section = '$section', teacher_id = '$teacher_id' WHERE id = '$class_id'";
            if (@mysqli_query($conn, $query)) {
                $message = "Class and Teacher updated successfully!";
                $message_type = "success";
            } else {
                // Try alternate column name 'class_teacher_id'
                $query2 = "UPDATE classes SET class_name = '$class_name', section = '$section', class_teacher_id = '$teacher_id' WHERE id = '$class_id'";
                if (@mysqli_query($conn, $query2)) {
                    $message = "Class and Teacher updated successfully!";
                    $message_type = "success";
                } else {
                    $message = "Error updating class.";
                    $message_type = "error";
                }
            }
        } else {
            // Insert New Class
            $query = "INSERT INTO classes (class_name, section, teacher_id) VALUES ('$class_name', '$section', '$teacher_id')";
            if (@mysqli_query($conn, $query)) {
                $message = "New Class created and Teacher assigned!";
                $message_type = "success";
            } else {
                $message = "Error adding class.";
                $message_type = "error";
            }
        }
    }
}

// Fetch Teachers
$teachersList = [];
$qT = @mysqli_query($conn, "SELECT id, name FROM teachers ORDER BY name ASC");
if ($qT) {
    while ($rT = mysqli_fetch_assoc($qT)) {
        $teachersList[] = $rT;
    }
}

// Fetch Classes
$classesList = [];
$qC = @mysqli_query($conn, "SELECT * FROM classes ORDER BY id ASC");
if ($qC) {
    while ($rC = mysqli_fetch_assoc($qC)) {
        $classesList[] = $rC;
    }
}
?>

<style>
.class-box { background: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.05); }
.alert-msg { padding: 12px; border-radius: 6px; margin-bottom: 15px; font-weight: 600; font-size: 14px; }
.alert-msg.success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
.alert-msg.error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }

.cls-table { width: 100%; border-collapse: collapse; margin-top: 15px; }
.cls-table th, .cls-table td { border: 1px solid #e0e0e0; padding: 12px; text-align: left; font-size: 14px; }
.cls-table th { background: #f8f9fa; font-weight: 600; color: #475569; }

.select-teacher { padding: 6px 10px; border-radius: 4px; border: 1px solid #cbd5e1; font-size: 13px; width: 100%; max-width: 220px; }
.btn-save { background: #0d6efd; color: white; border: none; padding: 6px 12px; border-radius: 4px; font-weight: bold; cursor: pointer; font-size: 13px; }
.btn-save:hover { background: #0b5ed7; }
</style>

<div class="class-box">
    <h2>Assign Classes & Class Teachers</h2>

    <?php if ($message): ?>
        <div class="alert-msg <?=e($message_type)?>"><?=e($message)?></div>
    <?php endif; ?>

    <table class="cls-table">
        <thead>
            <tr>
                <th width="10%">ID</th>
                <th width="30%">Class Name</th>
                <th width="20%">Section</th>
                <th width="30%">Assigned Teacher</th>
                <th width="10%">Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($classesList)): ?>
                <?php foreach ($classesList as $cls): ?>
                    <?php 
                        $currentTeacherId = $cls['teacher_id'] ?? $cls['class_teacher_id'] ?? 0;
                        $cName = $cls['class_name'] ?? $cls['name'] ?? '';
                    ?>
                    <tr>
                        <form method="POST" action="index.php?page=classes">
                            <input type="hidden" name="class_id" value="<?=$cls['id']?>">
                            <td>#<?=$cls['id']?></td>
                            <td><input type="text" name="class_name" value="<?=e($cName)?>" required style="padding:4px 8px; border:1px solid #ccc; border-radius:4px;"></td>
                            <td><input type="text" name="section" value="<?=e($cls['section'] ?? '')?>" style="padding:4px 8px; border:1px solid #ccc; border-radius:4px; width:80px;"></td>
                            <td>
                                <select name="teacher_id" class="select-teacher">
                                    <option value="0">-- Select Teacher --</option>
                                    <?php foreach ($teachersList as $t): ?>
                                        <option value="<?=$t['id']?>" <?=$currentTeacherId == $t['id'] ? 'selected' : ''?>>
                                            <?=e($t['name'])?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <button type="submit" name="save_class" class="btn-save">Save</button>
                            </td>
                        </form>
                    </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="5" style="text-align:center; color:#888;">No classes found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>