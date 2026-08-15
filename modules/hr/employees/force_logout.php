<?php
session_start();

// Path matching index.php
require_once "../../../config/database.php";

if (file_exists("../../../config/logger.php")) {
    require_once "../../../config/logger.php";
}

// Security Check: Only Admin/Super Admin/Manager
if (!isset($_SESSION['employee_id']) || !in_array($_SESSION['role'], ['Admin', 'Super Admin', 'Manager'])) {
    $_SESSION['error'] = "Unauthorized access!";
    header("Location: index.php");
    exit();
}

if (isset($_GET['id'])) {
    $target_id = intval($_GET['id']);

    // Admin khud ko force logout nahi kar sakta
    if ($target_id == $_SESSION['user_id']) {
        $_SESSION['error'] = "You cannot force logout yourself!";
        header("Location: index.php");
        exit();
    }

    // Database me force_logout = 1 set karein
    $query = "UPDATE employees SET force_logout = 1 WHERE id = '$target_id'";
    if (mysqli_query($conn, $query)) {
        $_SESSION['success'] = "Employee marked for Force Logout successfully.";
        
        if (function_exists('logActivity')) {
            logActivity($conn, 'Authentication', 'LOGOUT', "Force logout triggered for Employee ID: $target_id");
        }
    } else {
        $_SESSION['error'] = "Failed to initiate force logout.";
    }
}

header("Location: index.php");
exit();
?>