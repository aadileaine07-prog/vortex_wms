<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: index.php");
    exit();
}

$order_number     = mysqli_real_escape_string($conn, $_POST['order_number']);
$order_date       = mysqli_real_escape_string($conn, $_POST['order_date']);
$customer_name    = mysqli_real_escape_string($conn, $_POST['customer_name']);
$customer_phone   = mysqli_real_escape_string($conn, $_POST['customer_phone']);
$customer_email   = mysqli_real_escape_string($conn, $_POST['customer_email']);
$shipping_address = mysqli_real_escape_string($conn, $_POST['shipping_address']);
$created_by       = mysqli_real_escape_string($conn, $_SESSION['employee_id']);

if (empty($_POST['product_id'])) {
    $_SESSION['error'] = "Please select at least one product.";
    header("Location: create.php");
    exit();
}

$insertOrder = mysqli_query($conn, "
    INSERT INTO sales_orders (
        order_number, customer_name, customer_phone, customer_email, shipping_address, order_date, status, created_by
    ) VALUES (
        '$order_number', '$customer_name', '$customer_phone', '$customer_email', '$shipping_address', '$order_date', 'Pending', '$created_by'
    )
");

if (!$insertOrder) {
    $_SESSION['error'] = "Failed to create sales order: " . mysqli_error($conn);
    header("Location: create.php");
    exit();
}

$sales_order_id = mysqli_insert_id($conn);

foreach ($_POST['product_id'] as $key => $pid) {
    $product_id  = intval($pid);
    $ordered_qty = intval($_POST['qty'][$key]);

    if ($product_id <= 0 || $ordered_qty <= 0) continue;

    $prodQuery = mysqli_query($conn, "SELECT * FROM inventory WHERE product_id='$product_id' OR id='$product_id'");
    
    if ($prodQuery && mysqli_num_rows($prodQuery) > 0) {
        $item = mysqli_fetch_assoc($prodQuery);
        $p_code = mysqli_real_escape_string($conn, $item['product_code']);
        $p_name = mysqli_real_escape_string($conn, $item['product_name']);
        $wh     = mysqli_real_escape_string($conn, $item['warehouse']);
        $bin    = mysqli_real_escape_string($conn, $item['bin_location']);

        mysqli_query($conn, "
            INSERT INTO sales_order_items (
                sales_order_id, product_id, product_code, product_name, warehouse, bin_location, ordered_qty
            ) VALUES (
                '$sales_order_id', '$product_id', '$p_code', '$p_name', '$wh', '$bin', '$ordered_qty'
            )
        ");
    }
}

$_SESSION['success'] = "Sales Order #{$order_number} created successfully!";
header("Location: index.php");
exit();
?>