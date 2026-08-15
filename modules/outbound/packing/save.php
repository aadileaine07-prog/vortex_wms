<?php
session_start();

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if ($_SERVER['REQUEST_METHOD'] != "POST") {
    header("Location: index.php");
    exit();
}

$picking_id     = intval($_POST['picking_id']);
$sales_order_id = intval($_POST['sales_order_id']);
$packing_number = mysqli_real_escape_string($conn, $_POST['packing_number']);
$packing_date   = mysqli_real_escape_string($conn, $_POST['packing_date']);
$packer_name    = mysqli_real_escape_string($conn, $_POST['packer_name']);

$conn->begin_transaction();

try {
    // 1. Insert Master Packing Record
    $insertPacking = mysqli_query($conn, "
        INSERT INTO packing (sales_order_id, picking_id, packing_number, packer_name, packing_date, status)
        VALUES ('$sales_order_id', '$picking_id', '$packing_number', '$packer_name', '$packing_date', 'Completed')
    ");

    if (!$insertPacking) {
        throw new Exception("Failed to insert packing: " . mysqli_error($conn));
    }

    $packing_id = mysqli_insert_id($conn);

    // 2. Loop Items
    if (isset($_POST['picking_item_id']) && is_array($_POST['picking_item_id'])) {
        foreach ($_POST['picking_item_id'] as $key => $pi_id) {
            $pi_id      = intval($pi_id);
            $packed_qty = intval($_POST['packed_qty'][$key]);

            $itemRes = mysqli_query($conn, "SELECT * FROM picking_items WHERE id = '$pi_id'");
            if (!$itemRes || mysqli_num_rows($itemRes) == 0) continue;

            $row = mysqli_fetch_assoc($itemRes);

            mysqli_query($conn, "
                INSERT INTO packing_items (packing_id, picking_item_id, product_id, product_code, product_name, warehouse, bin_location, picked_qty, packed_qty)
                VALUES ('$packing_id', '{$row['id']}', '{$row['product_id']}', '{$row['product_code']}', '{$row['product_name']}', '{$row['warehouse']}', '{$row['bin_location']}', '{$row['picked_qty']}', '$packed_qty')
            ");

            mysqli_query($conn, "
                UPDATE sales_order_items 
                SET packed_qty = '$packed_qty' 
                WHERE id = '{$row['sales_order_item_id']}'
            ");
        }
    }

    // 3. Mark Sales Order as Packed
    mysqli_query($conn, "UPDATE sales_orders SET status = 'Packed' WHERE id = '$sales_order_id'");

    $conn->commit();
    $_SESSION['success'] = "Packing Slip #$packing_number completed! Order is now ready for Dispatch.";
    header("Location: index.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Packing Error: " . $e->getMessage();
    header("Location: index.php");
    exit();
}