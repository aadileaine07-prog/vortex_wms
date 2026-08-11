<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit();
}

$id               = isset($_POST['id']) ? intval($_POST['id']) : 0;
$order_date       = mysqli_real_escape_string($conn, $_POST['order_date']);
$status           = mysqli_real_escape_string($conn, $_POST['status']);
$customer_name    = mysqli_real_escape_string($conn, $_POST['customer_name']);
$customer_phone   = mysqli_real_escape_string($conn, $_POST['customer_phone']);
$customer_email   = mysqli_real_escape_string($conn, $_POST['customer_email']);
$shipping_address = mysqli_real_escape_string($conn, $_POST['shipping_address']);

$sql = "UPDATE sales_orders SET
    order_date       = '$order_date',
    customer_name    = '$customer_name',
    customer_phone   = '$customer_phone',
    customer_email   = '$customer_email',
    shipping_address = '$shipping_address',
    status           = '$status'
WHERE id = '$id'";

if (mysqli_query($conn, $sql)) {
    $_SESSION['success'] = "Sales Order updated successfully!";
} else {
    $_SESSION['error'] = "Failed to update order: " . mysqli_error($conn);
}

header('Location: view.php?id=' . $id);
exit();
?>