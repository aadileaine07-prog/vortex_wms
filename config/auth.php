<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Ensure database connection is available
if (isset($projectRoot) && file_exists($projectRoot . "/config/database.php")) {
    require_once $projectRoot . "/config/database.php";
} elseif (file_exists("../../../config/database.php")) {
    require_once "../../../config/database.php";
} elseif (file_exists("../../config/database.php")) {
    require_once "../../config/database.php";
}

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

// Check if user is marked for force logout
if (isset($_SESSION['employee_id']) && isset($conn)) {
    $empId = $_SESSION['employee_id'];
    $chkQuery = mysqli_query($conn, "SELECT force_logout FROM employees WHERE id = '$empId' LIMIT 1");
    if ($chkQuery && mysqli_num_rows($chkQuery) > 0) {
        $userData = mysqli_fetch_assoc($chkQuery);
        if (isset($userData['force_logout']) && (int)$userData['force_logout'] === 1) {
            // Reset force_logout to 0 so they don't get stuck in a loop, then destroy session
            mysqli_query($conn, "UPDATE employees SET force_logout = 0 WHERE id = '$empId'");
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['error'] = "Your session has been terminated by an administrator.";
            header("Location: /vortex_wms/login.php");
            exit();
        }
    }
}
?>