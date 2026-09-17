<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") 
        ? dirname(__DIR__, 2) 
        : (file_exists(__DIR__ . "/../../../../config/database.php") ? dirname(__DIR__, 4) : dirname(__DIR__, 1)));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($conn) || !($conn instanceof mysqli)) {
    $_SESSION['error'] = "Database connection unavailable.";
    header("Location: index.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$id          = intval($_POST['id'] ?? 0);
$old_photo   = $_POST['old_photo'] ?? 'default.png';

if ($id <= 0) {
    $_SESSION['error'] = "Invalid Employee ID.";
    header("Location: index.php");
    exit();
}

$full_name   = trim($_POST['full_name'] ?? '');
$mobile      = trim($_POST['mobile'] ?? '');
$email       = trim($_POST['email'] ?? '');
$gender      = $_POST['gender'] ?? 'Male';
$dob         = !empty($_POST['dob']) ? $_POST['dob'] : NULL;
$department  = trim($_POST['department'] ?? '');
$designation = trim($_POST['designation'] ?? '');
$role        = trim($_POST['role'] ?? '');
$warehouse   = trim($_POST['warehouse'] ?? '');
$shift       = $_POST['shift'] ?? 'General';
$username    = trim($_POST['username'] ?? '');
$password    = $_POST['password'] ?? '';
$joining_date = !empty($_POST['joining_date']) ? $_POST['joining_date'] : NULL;
$status      = $_POST['status'] ?? 'Active';
$address     = trim($_POST['address'] ?? '');
$remarks     = trim($_POST['remarks'] ?? '');

/* Duplicate Validation */
$checkStmt = $conn->prepare("SELECT id FROM employees WHERE (username = ? OR (email != '' AND email = ?) OR (mobile != '' AND mobile = ?)) AND id != ?");
if ($checkStmt) {
    $checkStmt->bind_param("sssi", $username, $email, $mobile, $id);
    $checkStmt->execute();
    $checkRes = $checkStmt->get_result();
    if ($checkRes && $checkRes->num_rows > 0) {
        $_SESSION['error'] = "Username, Email or Mobile already exists on another account.";
        $checkStmt->close();
        header("Location: edit.php?id=" . $id);
        exit();
    }
    $checkStmt->close();
}

/* Photo Upload Handling */
$photo = $old_photo;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($ext, $allowed)) {
        $photo = time() . "_" . rand(1000, 9999) . "." . $ext;
        $uploadDir = $projectRoot . "/assets/images/employees/";
        
        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        move_uploaded_file($_FILES['photo']['tmp_name'], $uploadDir . $photo);

        if ($old_photo !== "default.png" && file_exists($uploadDir . $old_photo)) {
            @unlink($uploadDir . $old_photo);
        }
    }
}

/* Update Statement */
$stmt = null;
if (!empty($password)) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        UPDATE employees SET 
        photo = ?, full_name = ?, mobile = ?, email = ?, gender = ?, dob = ?, 
        department = ?, designation = ?, role = ?, warehouse = ?, shift = ?, 
        username = ?, password = ?, joining_date = ?, status = ?, address = ?, remarks = ? 
        WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param(
            "sssssssssssssssssi", 
            $photo, $full_name, $mobile, $email, $gender, $dob, 
            $department, $designation, $role, $warehouse, $shift, 
            $username, $hashed_password, $joining_date, $status, $address, $remarks, $id
        );
    }
} else {
    $stmt = $conn->prepare("
        UPDATE employees SET 
        photo = ?, full_name = ?, mobile = ?, email = ?, gender = ?, dob = ?, 
        department = ?, designation = ?, role = ?, warehouse = ?, shift = ?, 
        username = ?, joining_date = ?, status = ?, address = ?, remarks = ? 
        WHERE id = ?
    ");
    if ($stmt) {
        $stmt->bind_param(
            "ssssssssssssssssi", 
            $photo, $full_name, $mobile, $email, $gender, $dob, 
            $department, $designation, $role, $warehouse, $shift, 
            $username, $joining_date, $status, $address, $remarks, $id
        );
    }
}

if ($stmt && $stmt->execute()) {
    $_SESSION['success'] = "Employee updated successfully.";
} else {
    $_SESSION['error'] = "Update failed: " . ($stmt ? $stmt->error : $conn->error);
}

if ($stmt) {
    $stmt->close();
}

header("Location: index.php");
exit();
?>