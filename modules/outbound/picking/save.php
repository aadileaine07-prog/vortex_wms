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

$sales_order_id = intval($_POST['sales_order_id']);
$picking_number = mysqli_real_escape_string($conn, $_POST['picking_number']);
$picking_date   = mysqli_real_escape_string($conn, $_POST['picking_date']);
$picker_name    = mysqli_real_escape_string($conn, $_POST['picker_name']);

if ($sales_order_id <= 0) {
    $_SESSION['error'] = "Invalid Sales Order ID.";
    header("Location: index.php");
    exit();
}

$insertPicking = mysqli_query($conn, "
    INSERT INTO picking (sales_order_id, picking_number, picker_name, picking_date, status)
    VALUES ('$sales_order_id', '$picking_number', '$picker_name', '$picking_date', 'Completed')
");

if (!$insertPicking) {
    $_SESSION['error'] = "Failed to record picking: " . mysqli_error($conn);
    header("Location: index.php");
    exit();
}

$picking_id = mysqli_insert_id($conn);

if (isset($_POST['sales_order_item_id']) && is_array($_POST['sales_order_item_id'])) {
    foreach ($_POST['sales_order_item_id'] as $key => $sales_order_item_id) {

        $sales_order_item_id = intval($sales_order_item_id);
        $picked_qty          = intval($_POST['picked_qty'][$key]);

        $itemQuery = mysqli_query($conn, "SELECT * FROM sales_order_items WHERE id='$sales_order_item_id'");

        if (!$itemQuery || mysqli_num_rows($itemQuery) == 0) {
            continue;
        }

        $row = mysqli_fetch_assoc($itemQuery);

        $p_id   = $row['product_id'];
        $p_code = mysqli_real_escape_string($conn, $row['product_code']);
        $p_name = mysqli_real_escape_string($conn, $row['product_name']);
        $wh     = mysqli_real_escape_string($conn, $row['warehouse']);
        $bin    = mysqli_real_escape_string($conn, $row['bin_location']);
        $o_qty  = intval($row['ordered_qty']);

        mysqli_query($conn, "
            INSERT INTO picking_items (
                picking_id, sales_order_item_id, product_id, product_code, product_name, warehouse, bin_location, ordered_qty, picked_qty
            ) VALUES (
                '$picking_id', '$sales_order_item_id', '$p_id', '$p_code', '$p_name', '$wh', '$bin', '$o_qty', '$picked_qty'
            )
        ");

        mysqli_query($conn, "
            UPDATE sales_order_items
            SET picked_qty = '$picked_qty'
            WHERE id = '$sales_order_item_id'
        ");
    }
}

/* Update Sales Order Status to Picking */
mysqli_query($conn, "
    UPDATE sales_orders
    SET status = 'Picking'
    WHERE id = '$sales_order_id'
");

$_SESSION['success'] = "Picking record #{$picking_number} saved successfully.";
header("Location: index.php");
exit();
?>