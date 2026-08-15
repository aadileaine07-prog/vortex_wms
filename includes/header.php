<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 🟢 Database & Logger Connection Auto-Include
if (!isset($conn)) {
    $db_path = __DIR__ . "/../config/database.php";
    if (file_exists($db_path)) {
        require_once $db_path;
    }
}

if (isset($_SESSION['user_id']) && isset($conn)) {
    $current_user_id = $_SESSION['user_id'];

    // 1. 🟢 Live Activity Tracker (Online status update ke liye)
    mysqli_query($conn, "UPDATE employees SET last_activity = NOW() WHERE id = '$current_user_id'");

    // 2. ⚡ Force Logout Guard Check
    $chk_res = mysqli_query($conn, "SELECT force_logout FROM employees WHERE id = '$current_user_id'");
    if ($chk_res && $user_chk = mysqli_fetch_assoc($chk_res)) {
        if ($user_chk['force_logout'] == 1) {
            
            // 🕒 Auto Attendance Check-Out Time Record
            $today          = date("Y-m-d");
            $check_out_time = date("H:i:s");

            $att_res = mysqli_query($conn, "SELECT check_in FROM attendance WHERE employee_id = '$current_user_id' AND attendance_date = '$today'");
            if ($att_res && mysqli_num_rows($att_res) > 0) {
                $att_data = mysqli_fetch_assoc($att_res);
                $check_in_time = $att_data['check_in'];

                if (!empty($check_in_time)) {
                    $time1 = new DateTime($check_in_time);
                    $time2 = new DateTime($check_out_time);
                    $interval = $time1->diff($time2);
                    $working_hours = $interval->format('%h hrs %i mins');
                } else {
                    $working_hours = "N/A";
                }

                $remarks = "Force Logged Out by Admin. Total Shift: " . $working_hours;
                mysqli_query($conn, "UPDATE attendance SET check_out = '$check_out_time', remarks = '$remarks' WHERE employee_id = '$current_user_id' AND attendance_date = '$today'");
            }

            // Reset Flag and Destroy Session
            mysqli_query($conn, "UPDATE employees SET force_logout = 0 WHERE id = '$current_user_id'");
            
            session_unset();
            session_destroy();

            header("Location: /vortex_wms/login.php?error=forced_logout");
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

<!-- Bootstrap -->
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

<!-- Font Awesome -->
<link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css" rel="stylesheet">

<!-- Google Font -->
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">

<!-- Main CSS -->
<link rel="stylesheet" href="/vortex_wms/assets/css/style.css">

<!-- Sidebar CSS -->
<link rel="stylesheet" href="/vortex_wms/assets/css/sidebar.css">

<!-- Navbar CSS -->
<link rel="stylesheet" href="/vortex_wms/assets/css/navbar.css">

<!-- Custom Theme -->
<link rel="stylesheet" href="/vortex_wms/assets/css/dashboard.css">

<!-- Favicon -->
<link rel="icon" type="image/png" href="/vortex_wms/assets/images/logo.png">

<!-- Sidebar JS -->
<script src="/vortex_wms/assets/js/sidebar.js" defer></script>

<style>

body{
    margin:0;
    padding:0;
    font-family:'Poppins',sans-serif;
    background:#f4f6f9;
}

.main-content{

    margin-left:270px;
    margin-top:70px;

    padding:25px;

    transition:.3s;

}

@media(max-width:992px){

.main-content{

    margin-left:0;

}

}

</style>

</head>

<body>

<?php include_once "sidebar.php"; ?>

<?php include_once "navbar.php"; ?>

<div class="main-content">