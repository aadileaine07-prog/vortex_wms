<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$adj_query = mysqli_query($conn, "SELECT * FROM stock_adjustment WHERE id = '$id'");
if (mysqli_num_rows($adj_query) == 0) {
    $_SESSION['error'] = "Adjustment record not found.";
    header("Location: index.php");
    exit();
}

$adj = mysqli_fetch_assoc($adj_query);
$inventory_id    = $adj['inventory_id'] ?? 0;
$product_code    = $adj['product_code'];
$adjustment_type = $adj['adjustment_type'];
$quantity        = intval($adj['quantity']);

// Revert Inventory Stock Logic
mysqli_begin_transaction($conn);

try {
    // Fetch Current Stock
    $inv_res = mysqli_query($conn, "SELECT id, available_qty FROM inventory WHERE id = '$inventory_id' OR product_code = '$product_code' LIMIT 1");
    if ($inv_res && mysqli_num_rows($inv_res) > 0) {
        $inv = mysqli_fetch_assoc($inv_res);
        $target_id   = $inv['id'];
        $current_qty = intval($inv['available_qty']);

        // Reverse quantities
        if ($adjustment_type == "Increase") {
            $reverted_qty = max(0, $current_qty - $quantity);
        } else {
            $reverted_qty = $current_qty + $quantity;
        }

        $status = "In Stock";
        if ($reverted_qty <= 0) $status = "Out of Stock";
        elseif ($reverted_qty <= 10) $status = "Low Stock";

        mysqli_query($conn, "UPDATE inventory SET available_qty = '$reverted_qty', status = '$status' WHERE id = '$target_id'");
    }

    // Delete Log Entry
    mysqli_query($conn, "DELETE FROM stock_adjustment WHERE id = '$id'");

    mysqli_commit($conn);
    $_SESSION['success'] = "Adjustment history deleted and inventory stock reverted successfully.";

} catch (Exception $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Failed to revert adjustment: " . $e->getMessage();
}

header("Location: index.php");
exit();
?>