<?php
session_start();
require_once "config/database.php";
require_once "config/logger.php"; // Logger File Include

if (isset($_SESSION['user_id'])) {
    $user_pk_id     = $_SESSION['user_id'];
    $today          = date("Y-m-d");
    $check_out_time = date("H:i:s");

    // Fetch Check-In time for today's attendance
    $att_res = mysqli_query($conn, "SELECT check_in FROM attendance WHERE employee_id = '$user_pk_id' AND attendance_date = '$today'");

    if ($att_res && mysqli_num_rows($att_res) > 0) {
        $att_data = mysqli_fetch_assoc($att_res);
        $check_in_time = $att_data['check_in'];

        // Calculate total shift hours
        if (!empty($check_in_time)) {
            $time1 = new DateTime($check_in_time);
            $time2 = new DateTime($check_out_time);
            $interval = $time1->diff($time2);
            $working_hours = $interval->format('%h hrs %i mins');
        } else {
            $working_hours = "N/A";
        }

        // Update Check-Out time & remarks with shift duration
        $remarks = "Total Shift: " . $working_hours;
        mysqli_query($conn, "UPDATE attendance SET check_out = '$check_out_time', remarks = '$remarks' WHERE employee_id = '$user_pk_id' AND attendance_date = '$today'");
    }

    // Log Logout Activity
    if (function_exists('logActivity')) {
        logActivity($conn, 'Authentication', 'LOGOUT', 'Employee logged out & check-out updated.');
    }
}

// Clear and Destroy Session
session_unset();
session_destroy();

header("Location: login.php");
exit();
?>