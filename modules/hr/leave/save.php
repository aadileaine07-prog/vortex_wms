<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] == "POST") {

    $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
    $leave_type  = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $from_date   = mysqli_real_escape_string($conn, $_POST['from_date']);
    $to_date     = mysqli_real_escape_string($conn, $_POST['to_date']);
    $total_days  = mysqli_real_escape_string($conn, $_POST['total_days']);
    $reason      = mysqli_real_escape_string($conn, $_POST['reason']);

    $insert = mysqli_query($conn, "
        INSERT INTO leaves (employee_id, leave_type, from_date, to_date, total_days, reason, status)
        VALUES ('$employee_id', '$leave_type', '$from_date', '$to_date', '$total_days', '$reason', 'Pending')
    ");

    if ($insert) {
        $_SESSION['success'] = "Leave application submitted successfully.";
    } else {
        $_SESSION['error'] = "Failed to submit leave request: " . mysqli_error($conn);
    }

    header("Location: index.php");
    exit();

} else {
    header("Location: add.php");
    exit();
}
?>