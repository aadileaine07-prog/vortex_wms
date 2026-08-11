<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Bin Location ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$check = mysqli_query($conn, "SELECT id FROM bin_locations WHERE id='$id'");

if (!$check || mysqli_num_rows($check) == 0) {
    $_SESSION['error'] = "Bin Location Not Found.";
    header("Location: index.php");
    exit();
}

if (mysqli_query($conn, "DELETE FROM bin_locations WHERE id='$id'")) {
    $_SESSION['success'] = "Bin Location deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete bin location: " . mysqli_error($conn);
}

header("Location: index.php");
exit();
?>