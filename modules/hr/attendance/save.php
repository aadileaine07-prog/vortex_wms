<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $employee_id = intval($_POST['employee_id']);
    $attendance_date = mysqli_real_escape_string($conn, $_POST['attendance_date']);
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $check_in = !empty($_POST['check_in']) ? "'" . mysqli_real_escape_string($conn, $_POST['check_in']) . "'" : "NULL";
    $check_out = !empty($_POST['check_out']) ? "'" . mysqli_real_escape_string($conn, $_POST['check_out']) . "'" : "NULL";
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    // Check if attendance already logged for this employee on this date
    $checkQuery = mysqli_query($conn, "SELECT id FROM attendance WHERE employee_id = '$employee_id' AND attendance_date = '$attendance_date' LIMIT 1");
    if ($checkQuery && mysqli_num_rows($checkQuery) > 0) {
        $_SESSION['error'] = "Attendance record for this employee already exists on this date.";
        header("Location: mark.php");
        exit();
    }

    $insertQuery = "
        INSERT INTO attendance (employee_id, attendance_date, status, check_in, check_out, remarks)
        VALUES ('$employee_id', '$attendance_date', '$status', $check_in, $check_out, '$remarks')
    ";

    if (mysqli_query($conn, $insertQuery)) {
        $_SESSION['success'] = "Attendance marked successfully.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to record attendance: " . mysqli_error($conn);
        header("Location: mark.php");
        exit();
    }
} else {
    header("Location: mark.php");
    exit();
}
?>