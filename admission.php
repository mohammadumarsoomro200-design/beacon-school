<?php
ob_start();
error_reporting(0);
ini_set('display_errors', 0);

header('Content-Type: application/json; charset=utf-8');
ob_clean();

// --- DATABASE CONFIGURATION ---
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'beacon_school';

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
    ]);
} catch (PDOException $e) {
    echo json_encode(['status' => 'error', 'message' => 'DB Connection Failed: ' . $e->getMessage()]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $student_name = trim($_POST['student_name'] ?? '');
    $parent_name  = trim($_POST['parent_name'] ?? '');
    $class_level  = trim($_POST['class_level'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $message      = trim($_POST['message'] ?? '');

    if (empty($student_name) || empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Student name and phone are required.']);
        exit;
    }

    try {
        // Correct Table Name: admission_enquiries
        $stmt = $pdo->prepare("INSERT INTO admission_enquiries (student_name, parent_name, class_level, phone, email, message, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Pending', NOW())");
        $stmt->execute([$student_name, $parent_name, $class_level, $phone, $email, $message]);

        echo json_encode([
            'status' => 'success',
            'message' => 'Thank you! Your enquiry has been submitted successfully.'
        ]);
        exit;

    } catch (PDOException $e) {
        echo json_encode(['status' => 'error', 'message' => 'Database Query Error: ' . $e->getMessage()]);
        exit;
    }
}
?>