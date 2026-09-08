<?php
// PHP warnings/errors display hone se rokein taakay JSON break na ho
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');

// Database Configuration Include
if (file_exists('../config/config.php')) {
    require_once '../config/config.php';
} elseif (file_exists('../config/db.php')) {
    require_once '../config/db.php';
} elseif (file_exists('../db.php')) {
    require_once '../db.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Inputs Read Karna
    $student_name = trim($_POST['student_name'] ?? $_POST['student'] ?? '');
    $parent_name  = trim($_POST['parent_name'] ?? $_POST['parent'] ?? '');
    $class_level  = trim($_POST['class_level'] ?? $_POST['class'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $message      = trim($_POST['message'] ?? '');

    // Validation
    if (empty($student_name) || empty($phone)) {
        echo json_encode([
            'status' => 'error',
            'message' => 'Please fill in all required fields.'
        ]);
        exit;
    }

    // Database Instance Fetch
    $db_conn = $db ?? $conn ?? $pdo ?? null;

    if ($db_conn) {
        try {
            // PDO Connection
            if ($db_conn instanceof PDO) {
                $stmt = $db_conn->prepare("INSERT INTO admissions (student_name, parent_name, class_level, phone, email, message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$student_name, $parent_name, $class_level, $phone, $email, $message]);
            } 
            // MySQLi Connection
            elseif ($db_conn instanceof mysqli) {
                $stmt = $db_conn->prepare("INSERT INTO admissions (student_name, parent_name, class_level, phone, email, message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssssss", $student_name, $parent_name, $class_level, $phone, $email, $message);
                $stmt->execute();
            }

            echo json_encode([
                'status' => 'success',
                'message' => 'Thank you! Your admission enquiry has been submitted successfully.'
            ]);
            exit;

        } catch (Exception $e) {
            echo json_encode([
                'status' => 'error',
                'message' => 'Database Error: ' . $e->getMessage()
            ]);
            exit;
        }
    } else {
        echo json_encode([
            'status' => 'error',
            'message' => 'Database connection failed. Please check config file.'
        ]);
        exit;
    }

} else {
    echo json_encode([
        'status' => 'error',
        'message' => 'Invalid Request Method'
    ]);
    exit;
}
?>