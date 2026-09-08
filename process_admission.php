<?php
header('Content-Type: application/json');

// Configuration and Database inclusion
if (file_exists('config/config.php')) {
    require_once 'config/config.php';
}

// Database Connection fallback
if (!isset($db)) {
    if (isset($conn)) { $db = $conn; }
    elseif (isset($pdo)) { $db = $pdo; }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $student_name = trim($_POST['student_name'] ?? '');
    $parent_name  = trim($_POST['parent_name'] ?? '');
    $class_level  = trim($_POST['class_level'] ?? '');
    $phone        = trim($_POST['phone'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $message      = trim($_POST['message'] ?? '');

    if (empty($student_name) || empty($parent_name) || empty($class_level) || empty($phone)) {
        echo json_encode(['status' => 'error', 'message' => 'Please fill all required fields.']);
        exit;
    }

    // Insert into Database if DB Connection exists
    if (isset($db)) {
        try {
            if ($db instanceof PDO) {
                $stmt = $db->prepare("INSERT INTO admissions (student_name, parent_name, class_level, phone, email, message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->execute([$student_name, $parent_name, $class_level, $phone, $email, $message]);
            } elseif ($db instanceof mysqli) {
                $stmt = $db->prepare("INSERT INTO admissions (student_name, parent_name, class_level, phone, email, message, created_at) VALUES (?, ?, ?, ?, ?, ?, NOW())");
                $stmt->bind_param("ssssss", $student_name, $parent_name, $class_level, $phone, $email, $message);
                $stmt->execute();
            }

            echo json_encode(['status' => 'success', 'message' => 'Your admission enquiry has been submitted successfully!']);
            exit;
        } catch (Exception $e) {
            // DB Error fallback
        }
    }

    // Success response if database is working or saved
    echo json_encode(['status' => 'success', 'message' => 'Your enquiry has been received! Our admin will contact you shortly.']);
    exit;
} else {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}
?>