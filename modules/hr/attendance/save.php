<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header("Location: index.php");
    exit();
}

$employee_id     = intval($_POST['employee_id']);
$attendance_date = mysqli_real_escape_string($conn, $_POST['attendance_date']);
$check_in        = mysqli_real_escape_string($conn, $_POST['check_in']);
$check_out       = !empty($_POST['check_out']) ? "'" . mysqli_real_escape_string($conn, $_POST['check_out']) . "'" : "NULL";
$status          = mysqli_real_escape_string($conn, $_POST['status']);
$remarks         = mysqli_real_escape_string($conn, $_POST['remarks']);

/* Duplicate Attendance Check */
$check = mysqli_query($conn, "
    SELECT id 
    FROM attendance 
    WHERE employee_id='$employee_id' AND attendance_date='$attendance_date'
");

if (mysqli_num_rows($check) > 0) {
    $_SESSION['error'] = "Attendance already marked for this employee on selected date.";
    header("Location: mark.php");
    exit();
}

/* Save Attendance Record */
$sql = "
    INSERT INTO attendance (employee_id, attendance_date, check_in, check_out, status, remarks)
    VALUES ('$employee_id', '$attendance_date', '$check_in', $check_out, '$status', '$remarks')
";

if (mysqli_query($conn, $sql)) {
    $_SESSION['success'] = "Attendance marked successfully!";
} else {
    $_SESSION['error'] = "Failed to save attendance: " . mysqli_error($conn);
}

header("Location: index.php");
exit();
?>