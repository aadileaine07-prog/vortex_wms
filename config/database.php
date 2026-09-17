<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

ini_set('display_errors', 1);
error_reporting(E_ALL);

$host     = "localhost";
$username = "root";
$password = "root";
$database = "vortex_wms";
$port     = 8889; // MAMP default port

// Database Connection with Port and Database Name
$conn = new mysqli($host, $username, $password, $database, $port);

// Connection Error Check
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");


/* =====================================================
   CENTRALIZED ACTIVITY LOGGER FOR NOTIFICATIONS
===================================================== */
function logSystemActivity($conn, $title, $message, $type = 'info') {
    $uid = $_SESSION['employee_id'] ?? 'NULL';
    $title   = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $type    = mysqli_real_escape_string($conn, $type);
    
    @mysqli_query($conn, "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) VALUES ($uid, '$title', '$message', '$type', 0, NOW())");
}


/* =====================================================
   ROLE & DEPARTMENT ACCESS CONTROL (RBAC)
===================================================== */
function checkPageAccess($allowedRoles = [], $allowedDepartments = []) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    // Agar login nahi hai
    if (!isset($_SESSION['employee_id'])) {
        header("Location: /vortex_wms/login.php");
        exit();
    }

    $userRole       = $_SESSION['role'] ?? 'Staff';          
    $userDepartment = $_SESSION['department'] ?? 'General'; 

    // 1. Super Admin aur Admin ke liye full access
    if ($userRole === 'Super Admin' || $userRole === 'Admin') {
        return true;
    }

    // 2. Role check karein
    if (!empty($allowedRoles) && !in_array($userRole, $allowedRoles)) {
        header("Location: /vortex_wms/dashboard.php?error=unauthorized_role");
        exit();
    }

    // 3. Department check karein (Jaise Outbound user sirf outbound page access kare)
    if (!empty($allowedDepartments) && !in_array($userDepartment, $allowedDepartments)) {
        header("Location: /vortex_wms/dashboard.php?error=unauthorized_department");
        exit();
    }

    return true;
}
?>