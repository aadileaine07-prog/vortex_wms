<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$id          = intval($_POST['id']);
$old_photo   = $_POST['old_photo'];

$full_name   = trim($_POST['full_name']);
$mobile      = trim($_POST['mobile']);
$email       = trim($_POST['email']);
$gender      = $_POST['gender'];
$dob         = !empty($_POST['dob']) ? $_POST['dob'] : NULL;
$department  = trim($_POST['department']);
$designation = trim($_POST['designation']);
$role        = trim($_POST['role']);
$warehouse   = trim($_POST['warehouse']);
$shift       = $_POST['shift'];
$username    = trim($_POST['username']);
$password    = $_POST['password'];
$joining_date = !empty($_POST['joining_date']) ? $_POST['joining_date'] : NULL;
$status      = $_POST['status'];
$address     = trim($_POST['address']);
$remarks     = trim($_POST['remarks']);

/* Duplicate Validation */
$checkStmt = $conn->prepare("SELECT id FROM employees WHERE (username = ? OR (email != '' AND email = ?) OR (mobile != '' AND mobile = ?)) AND id != ?");
$checkStmt->bind_param("sssi", $username, $email, $mobile, $id);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    $_SESSION['error'] = "Username, Email or Mobile already exists on another account.";
    $checkStmt->close();
    header("Location: edit.php?id=" . $id);
    exit();
}
$checkStmt->close();

/* Photo Upload Handling */
$photo = $old_photo;
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];

    if (in_array($ext, $allowed)) {
        $photo = time() . "_" . rand(1000, 9999) . "." . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], "../../../assets/images/employees/" . $photo);

        if ($old_photo !== "default.png" && file_exists("../../../assets/images/employees/" . $old_photo)) {
            unlink("../../../assets/images/employees/" . $old_photo);
        }
    }
}

/* Update Statement */
if (!empty($password)) {
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    $stmt = $conn->prepare("
        UPDATE employees SET 
        photo = ?, full_name = ?, mobile = ?, email = ?, gender = ?, dob = ?, 
        department = ?, designation = ?, role = ?, warehouse = ?, shift = ?, 
        username = ?, password = ?, joining_date = ?, status = ?, address = ?, remarks = ? 
        WHERE id = ?
    ");
    $stmt->bind_param(
        "sssssssssssssssssi", 
        $photo, $full_name, $mobile, $email, $gender, $dob, 
        $department, $designation, $role, $warehouse, $shift, 
        $username, $hashed_password, $joining_date, $status, $address, $remarks, $id
    );
} else {
    $stmt = $conn->prepare("
        UPDATE employees SET 
        photo = ?, full_name = ?, mobile = ?, email = ?, gender = ?, dob = ?, 
        department = ?, designation = ?, role = ?, warehouse = ?, shift = ?, 
        username = ?, joining_date = ?, status = ?, address = ?, remarks = ? 
        WHERE id = ?
    ");
    $stmt->bind_param(
        "ssssssssssssssssi", 
        $photo, $full_name, $mobile, $email, $gender, $dob, 
        $department, $designation, $role, $warehouse, $shift, 
        $username, $joining_date, $status, $address, $remarks, $id
    );
}

if ($stmt->execute()) {
    $_SESSION['success'] = "Employee updated successfully.";
} else {
    $_SESSION['error'] = "Update failed: " . $stmt->error;
}

$stmt->close();
header("Location: index.php");
exit();