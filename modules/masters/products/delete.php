<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Product ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$check = mysqli_query($conn, "SELECT id FROM products WHERE id='$id'");

if (!$check || mysqli_num_rows($check) == 0) {
    $_SESSION['error'] = "Product Not Found.";
    header("Location: index.php");
    exit();
}

if (mysqli_query($conn, "DELETE FROM products WHERE id='$id'")) {
    $_SESSION['success'] = "Product deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete product: " . mysqli_error($conn);
}

header("Location: index.php");
exit();
?>