<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Supplier ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$check = mysqli_query($conn, "SELECT id FROM suppliers WHERE id='$id'");

if (!$check || mysqli_num_rows($check) == 0) {
    $_SESSION['error'] = "Supplier Not Found.";
    header("Location: index.php");
    exit();
}

if (mysqli_query($conn, "DELETE FROM suppliers WHERE id='$id'")) {
    $_SESSION['success'] = "Supplier deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete supplier: " . mysqli_error($conn);
}

header("Location: index.php");
exit();
?>