<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: add.php");
    exit();
}

/* Collect Data */
$full_name    = trim($_POST['full_name'] ?? '');
$mobile       = trim($_POST['mobile'] ?? '');
$email        = trim($_POST['email'] ?? '');
$gender       = $_POST['gender'] ?? 'Male';
$dob          = !empty($_POST['dob']) ? $_POST['dob'] : NULL;
$department   = $_POST['department'] ?? 'Warehouse';
$designation  = trim($_POST['designation'] ?? '');
$role         = $_POST['role'] ?? 'Picker';
$warehouse    = trim($_POST['warehouse'] ?? 'Main Warehouse');
$shift        = $_POST['shift'] ?? 'General';
$username     = trim($_POST['username'] ?? '');
$password     = $_POST['password'] ?? '';
$joining_date = !empty($_POST['joining_date']) ? $_POST['joining_date'] : NULL;
$status       = $_POST['status'] ?? 'Active';
$address      = trim($_POST['address'] ?? '');
$remarks      = trim($_POST['remarks'] ?? '');

/* Validate Unique Fields */
$checkStmt = $conn->prepare("SELECT id FROM employees WHERE username = ? OR (email != '' AND email = ?) OR (mobile != '' AND mobile = ?)");
$checkStmt->bind_param("sss", $username, $email, $mobile);
$checkStmt->execute();
if ($checkStmt->get_result()->num_rows > 0) {
    $_SESSION['error'] = "Username, Email or Mobile number is already registered.";
    $checkStmt->close();
    header("Location: add.php");
    exit();
}
$checkStmt->close();

/* Generate ID */
$managementRoles = [
    'Super Admin', 'Admin', 'HR Manager', 'HR Executive', 
    'Area Manager', 'Warehouse Manager', 'Inventory Manager', 
    'Operations Manager', 'QC Manager', 'IT Administrator'
];

if (in_array($role, $managementRoles)) {
    $q = mysqli_query($conn, "SELECT employee_id FROM employees WHERE employee_id LIKE 'Z%' ORDER BY id DESC LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        $num = (int)substr($row['employee_id'], 1);
        $employee_id = "Z" . str_pad($num + 1, 3, "0", STR_PAD_LEFT);
    } else {
        $employee_id = "Z001";
    }
} else {
    $q = mysqli_query($conn, "SELECT employee_id FROM employees WHERE employee_id LIKE 'VTX%' ORDER BY id DESC LIMIT 1");
    if ($q && mysqli_num_rows($q) > 0) {
        $row = mysqli_fetch_assoc($q);
        $num = (int)substr($row['employee_id'], 3);
        $employee_id = "VTX" . ($num + 1);
    } else {
        $employee_id = "VTX1001";
    }
}

/* Photo Upload */
$photo = "default.png";
if (isset($_FILES['photo']) && $_FILES['photo']['error'] === 0) {
    $ext = strtolower(pathinfo($_FILES['photo']['name'], PATHINFO_EXTENSION));
    $allowed = ['jpg', 'jpeg', 'png', 'webp'];
    if (in_array($ext, $allowed)) {
        $photo = time() . "_" . rand(1000, 9999) . "." . $ext;
        move_uploaded_file($_FILES['photo']['tmp_name'], "../../../assets/images/employees/" . $photo);
    }
}

/* Hash Password */
$hashed_password = password_hash($password, PASSWORD_DEFAULT);

/* Save Employee */
$stmt = $conn->prepare("
    INSERT INTO employees 
    (employee_id, photo, full_name, mobile, email, gender, dob, department, designation, role, shift, warehouse, username, password, joining_date, status, address, remarks) 
    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
");

$stmt->bind_param(
    "ssssssssssssssssss", 
    $employee_id, $photo, $full_name, $mobile, $email, $gender, 
    $dob, $department, $designation, $role, $shift, $warehouse, 
    $username, $hashed_password, $joining_date, $status, $address, $remarks
);

if ($stmt->execute()) {
    $_SESSION['success'] = "Employee created successfully. Generated ID: " . $employee_id;
    $stmt->close();
    header("Location: index.php");
    exit();
} else {
    $_SESSION['error'] = "Failed to create employee: " . $stmt->error;
    $stmt->close();
    header("Location: add.php");
    exit();
}