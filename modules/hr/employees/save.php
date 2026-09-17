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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name     = mysqli_real_escape_string($conn, $_POST['full_name']);
    $mobile        = mysqli_real_escape_string($conn, $_POST['mobile']);
    $email         = mysqli_real_escape_string($conn, $_POST['email']);
    $gender        = mysqli_real_escape_string($conn, $_POST['gender']);
    $dob           = mysqli_real_escape_string($conn, $_POST['dob']);
    $department    = mysqli_real_escape_string($conn, $_POST['department']);
    $designation   = mysqli_real_escape_string($conn, $_POST['designation']);
    $role          = mysqli_real_escape_string($conn, $_POST['role']);
    $warehouse     = mysqli_real_escape_string($conn, $_POST['warehouse']);
    $shift         = mysqli_real_escape_string($conn, $_POST['shift']);
    $joining_date  = mysqli_real_escape_string($conn, $_POST['joining_date']);
    $username      = mysqli_real_escape_string($conn, $_POST['username']);
    $password      = password_hash($_POST['password'], PASSWORD_DEFAULT); // Secure password hashing
    $status        = mysqli_real_escape_string($conn, $_POST['status']);
    $address       = mysqli_real_escape_string($conn, $_POST['address']);
    $remarks       = mysqli_real_escape_string($conn, $_POST['remarks']);

    /* ==========================================================================
       SMART SERVER-SIDE ID GENERATION (Z series vs VRTX series)
       ========================================================================== */
    $isManagement = ($role === 'Super Admin' || $role === 'Admin' || $role === 'Management' || strpos($role, 'Manager') !== false);
    
    if ($isManagement) {
        // Management ke liye 'Z' series
        $q = mysqli_query($conn, "SELECT emp_id FROM employees WHERE emp_id LIKE 'Z%' ORDER BY id DESC LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $lastId = mysqli_fetch_assoc($q)['emp_id'];
            $num = intval(substr($lastId, 1)) + 1;
            $employee_id = 'Z' . str_pad($num, 3, '0', STR_PAD_LEFT);
        } else {
            $employee_id = 'Z001';
        }
    } else {
        // Staff / Shop Floor ke liye 'VRTX' series
        $q = mysqli_query($conn, "SELECT emp_id FROM employees WHERE emp_id LIKE 'VRTX%' ORDER BY id DESC LIMIT 1");
        if ($q && mysqli_num_rows($q) > 0) {
            $lastId = mysqli_fetch_assoc($q)['emp_id'];
            $num = intval(substr($lastId, 4)) + 1;
            $employee_id = 'VRTX' . str_pad($num, 4, '0', STR_PAD_LEFT);
        } else {
            $employee_id = 'VRTX0001';
        }
    }

    // Safety Loop: Ensure generated ID is 100% unique in database
    $existsCheck = true;
    while ($existsCheck) {
        $chkDuplicate = mysqli_query($conn, "SELECT id FROM employees WHERE employee_id = '$employee_id' LIMIT 1");
        if ($chkDuplicate && mysqli_num_rows($chkDuplicate) > 0) {
            // Agar galti se match ho jaye toh increment karke next try karein
            if ($isManagement) {
                $num = intval(substr($employee_id, 1)) + 1;
                $employee_id = 'Z' . str_pad($num, 3, '0', STR_PAD_LEFT);
            } else {
                $num = intval(substr($employee_id, 4)) + 1;
                $employee_id = 'VRTX' . str_pad($num, 4, '0', STR_PAD_LEFT);
            }
        } else {
            $existsCheck = false;
        }
    }

    // Check if username already exists
    $chkUser = mysqli_query($conn, "SELECT id FROM employees WHERE username = '$username' LIMIT 1");
    if ($chkUser && mysqli_num_rows($chkUser) > 0) {
        $_SESSION['error'] = "Username '{$username}' is already taken. Please choose another.";
        header("Location: add.php");
        exit();
    }

    // Handle Profile Photo Upload
    $photoName = "";
    if (isset($_FILES['photo']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
        $fileTmpPath = $_FILES['photo']['tmp_name'];
        $fileName = $_FILES['photo']['name'];
        $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
        
        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($fileExtension, $allowedExtensions)) {
            $newFileName = 'EMP_' . time() . '.' . $fileExtension;
            $uploadFileDir = $projectRoot . "/assets/images/employees/";
            
            if (!is_dir($uploadFileDir)) {
                mkdir($uploadFileDir, 0755, true);
            }
            
            $dest_path = $uploadFileDir . $newFileName;
            if (move_uploaded_file($fileTmpPath, $dest_path)) {
                $photoName = $newFileName;
            }
        }
    }

    $insertQuery = "
        INSERT INTO employees (employee_id, full_name, mobile, email, gender, dob, department, designation, role, warehouse, shift, joining_date, username, password, status, address, remarks, photo)
        VALUES ('$employee_id', '$full_name', '$mobile', '$email', '$gender', '$dob', '$department', '$designation', '$role', '$warehouse', '$shift', '$joining_date', '$username', '$password', '$status', '$address', '$remarks', '$photoName')
    ";

    if (mysqli_query($conn, $insertQuery)) {
        $_SESSION['success'] = "Employee <strong>{$full_name}</strong> registered successfully with ID <strong>{$employee_id}</strong>.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to register employee: " . mysqli_error($conn);
        header("Location: add.php");
        exit();
    }
} else {
    header("Location: add.php");
    exit();
}
?>