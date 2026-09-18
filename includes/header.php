<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Fail-Safe Database auto-loader
if (!isset($conn)) {
    $dbPaths = [
        __DIR__ . "/../config/database.php",
        dirname(__DIR__, 2) . "/config/database.php",
        dirname(__DIR__, 3) . "/config/database.php"
    ];
    foreach ($dbPaths as $path) {
        if (file_exists($path)) {
            require_once $path;
            break;
        }
    }
}

// Authentication & Live Session Guard (Force Logout & Activity Update)
if (isset($_SESSION['employee_id']) && isset($conn)) {
    $current_user_id = mysqli_real_escape_string($conn, $_SESSION['employee_id']);

    // Update Activity
    mysqli_query($conn, "UPDATE employees SET last_activity = NOW() WHERE id = '$current_user_id'");

    // Check Force Logout
    $chk_res = mysqli_query($conn, "SELECT force_logout FROM employees WHERE id = '$current_user_id' LIMIT 1");
    if ($chk_res && mysqli_num_rows($chk_res) > 0) {
        $user_chk = mysqli_fetch_assoc($chk_res);
        if (isset($user_chk['force_logout']) && (int)$user_chk['force_logout'] === 1) {
            
            // Reset force_logout flag
            mysqli_query($conn, "UPDATE employees SET force_logout = 0 WHERE id = '$current_user_id'");
            
            // Destroy session properly
            session_unset();
            session_destroy();
            session_start();
            $_SESSION['error'] = "Your session has been terminated by an administrator.";
            
            header("Location: /vortex_wms/login.php");
            exit();
        }
    }
}

/**
 * Dynamic Department & Role Access Control Function
 * - Super Admin & Admin: Full Access
 * - Other Users: Access only if their department or role matches the allowed list
 */
function allowRoles($allowed_departments = []) {
    if (!isset($_SESSION['employee_id'])) {
        header("Location: /vortex_wms/login.php");
        exit();
    }

    $role = $_SESSION['role'] ?? '';
    $department = $_SESSION['department'] ?? '';

    // Super Admin aur Admin ko sabhi pages ka full access hai
    if (in_array($role, ['Super Admin', 'Admin'])) {
        return true;
    }

    // Agar user ka department ya role allowed list mein match hota hai
    if (in_array($department, $allowed_departments) || in_array($role, $allowed_departments)) {
        return true;
    }

    // Agar match nahi hua, toh access block karke dashboard par bhej do
    $_SESSION['error'] = "Access Denied: You are not authorized to access this module.";
    header("Location: /vortex_wms/dashboard.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VORTEX WMS | Enterprise Warehouse Management</title>

    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">
    <!-- Google Font -->
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

    <!-- Stylesheets -->
    <link rel="stylesheet" href="/vortex_wms/assets/css/style.css">
    <link rel="stylesheet" href="/vortex_wms/assets/css/sidebar.css">
    <link rel="stylesheet" href="/vortex_wms/assets/css/navbar.css">
    <link rel="stylesheet" href="/vortex_wms/assets/css/dashboard.css">
    <link rel="icon" type="image/png" href="/vortex_wms/assets/images/logo.png">

    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Poppins', sans-serif;
            background: #f4f6f9;
        }
        .main-content {
            margin-left: 260px;
            margin-top: 65px;
            padding: 24px;
            min-height: calc(100vh - 65px);
            transition: all 0.3s ease;
        }
        @media(max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 16px;
            }
        }
    </style>
</head>
<body>

<?php 
require_once __DIR__ . "/sidebar.php"; 
require_once __DIR__ . "/navbar.php"; 
?>

<div class="main-content">

<!-- Real-Time Force Logout Polling Script -->
<script>
setInterval(function() {
    fetch('/vortex_wms/modules/hr/employees/check_logout_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.force_lookup === true || data.force_logout === true) {
                window.location.href = '/vortex_wms/login.php?error=forced_logout';
            }
        })
        .catch(error => console.error('Logout check error:', error));
}, 5000);
</script>