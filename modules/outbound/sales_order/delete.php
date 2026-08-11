<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Sales Order ID missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$check = mysqli_query($conn, "SELECT id FROM sales_orders WHERE id='$id'");
if (!$check || mysqli_num_rows($check) == 0) {
    $_SESSION['error'] = "Sales Order not found.";
    header("Location: index.php");
    exit();
}

// Delete items first then order
mysqli_query($conn, "DELETE FROM sales_order_items WHERE sales_order_id='$id'");

if (mysqli_query($conn, "DELETE FROM sales_orders WHERE id='$id'")) {
    $_SESSION['success'] = "Sales Order #{$id} deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete order: " . mysqli_error($conn);
}

header("Location: index.php");
exit();
?>