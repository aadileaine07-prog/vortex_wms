<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] != "POST") {
    header("Location: index.php");
    exit();
}

$inventory_id    = intval($_POST['inventory_id']);
$adjustment_date = mysqli_real_escape_string($conn, $_POST['adjustment_date']);
$warehouse       = mysqli_real_escape_string($conn, $_POST['warehouse']);
$bin_location    = mysqli_real_escape_string($conn, $_POST['bin_location']);
$adjustment_type = mysqli_real_escape_string($conn, $_POST['adjustment_type']);
$quantity        = intval($_POST['quantity']);
$reason          = mysqli_real_escape_string($conn, $_POST['reason']);
$created_by      = $_SESSION['employee_id'];

if ($inventory_id <= 0 || $quantity <= 0) {
    $_SESSION['error'] = "Invalid Product or Quantity.";
    header("Location: create.php");
    exit();
}

// Fetch Inventory Item
$inventory_query = mysqli_query($conn, "SELECT * FROM inventory WHERE id='$inventory_id'");
if (mysqli_num_rows($inventory_query) == 0) {
    $_SESSION['error'] = "Inventory Item Not Found.";
    header("Location: create.php");
    exit();
}

$item = mysqli_fetch_assoc($inventory_query);
$product_id   = $item['product_id'] ?? 0;
$product_code = mysqli_real_escape_string($conn, $item['product_code']);
$product_name = mysqli_real_escape_string($conn, $item['product_name']);
$current_qty  = intval($item['available_qty']);

// Quantity Calculation
if ($adjustment_type == "Increase") {
    $new_qty = $current_qty + $quantity;
} else {
    if ($quantity > $current_qty) {
        $_SESSION['error'] = "Adjustment quantity cannot exceed available stock.";
        header("Location: create.php");
        exit();
    }
    $new_qty = $current_qty - $quantity;
}

// Status Calculation
$status = "In Stock";
if ($new_qty <= 0) {
    $status = "Out of Stock";
} elseif ($new_qty <= 10) {
    $status = "Low Stock";
}

// Transaction Execution
mysqli_begin_transaction($conn);

try {
    // 1. Update Main Inventory
    $update_sql = "UPDATE inventory SET available_qty = '$new_qty', status = '$status' WHERE id = '$inventory_id'";
    mysqli_query($conn, $update_sql);

    // 2. Insert Adjustment Audit History
    $log_sql = "INSERT INTO stock_adjustment 
                (inventory_id, product_id, product_code, product_name, warehouse, bin_location, adjustment_type, quantity, reason, adjustment_date, created_by)
                VALUES 
                ('$inventory_id', '$product_id', '$product_code', '$product_name', '$warehouse', '$bin_location', '$adjustment_type', '$quantity', '$reason', '$adjustment_date', '$created_by')";
    mysqli_query($conn, $log_sql);

    // Commit Transaction
    mysqli_commit($conn);
    $_SESSION['success'] = "Stock Adjustment of <b>$quantity units</b> saved successfully.";
    header("Location: index.php");
    exit();

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Failed to apply stock adjustment: " . $e->getMessage();
    header("Location: create.php");
    exit();
}
?>