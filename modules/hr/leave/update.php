<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id          = intval($_POST['id']);
    $employee_id = mysqli_real_escape_string($conn, $_POST['employee_id']);
    $leave_type  = mysqli_real_escape_string($conn, $_POST['leave_type']);
    $status      = mysqli_real_escape_string($conn, $_POST['status']);
    $from_date   = mysqli_real_escape_string($conn, $_POST['from_date']);
    $to_date     = mysqli_real_escape_string($conn, $_POST['to_date']);
    $total_days  = mysqli_real_escape_string($conn, $_POST['total_days']);
    $reason      = mysqli_real_escape_string($conn, $_POST['reason']);

    if ($id <= 0) {
        $_SESSION['error'] = "Invalid Leave Record ID.";
        header("Location: index.php");
        exit();
    }

    $sql = "
        UPDATE leaves SET
            employee_id = '$employee_id',
            leave_type  = '$leave_type',
            status      = '$status',
            from_date   = '$from_date',
            to_date     = '$to_date',
            total_days  = '$total_days',
            reason      = '$reason'
        WHERE id = '$id'
    ";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Leave application #{$id} updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update record: " . mysqli_error($conn);
    }

    header("Location: index.php");
    exit();

} else {
    header("Location: index.php");
    exit();
}
?>