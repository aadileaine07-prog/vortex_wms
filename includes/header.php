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
<script>
// Check every 5 seconds if the user has been force logged out
setInterval(function() {
    fetch('/vortex_wms/modules/hr/employees/check_logout_status.php')
        .then(response => response.json())
        .then(data => {
            if (data.force_logout === true) {
                window.location.href = '/vortex_wms/login.php?error=forced_logout';
            }
        })
        .catch(error => console.error('Logout check error:', error));
}, 5000);
</script>
<body>

<?php 
require_once __DIR__ . "/sidebar.php"; 
require_once __DIR__ . "/navbar.php"; 
?>

<div class="main-content">  