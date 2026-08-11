<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if($_SERVER['REQUEST_METHOD']!="POST"){
    header("Location:index.php");
    exit();
}

$sales_order_id = intval($_POST['sales_order_id']);

$packing_id = intval($_POST['packing_id']);

$dispatch_number = mysqli_real_escape_string($conn,$_POST['dispatch_number']);

$dispatch_date = $_POST['dispatch_date'];

$courier_name = mysqli_real_escape_string($conn,$_POST['courier_name']);

$vehicle_number = mysqli_real_escape_string($conn,$_POST['vehicle_number']);

$tracking_number = mysqli_real_escape_string($conn,$_POST['tracking_number']);

mysqli_query($conn,"
INSERT INTO dispatch
(
    sales_order_id,
    packing_id,
    dispatch_number,
    courier_name,
    vehicle_number,
    tracking_number,
    dispatch_date,
    status
)
VALUES
(
    '$sales_order_id',
    '$packing_id',
    '$dispatch_number',
    '$courier_name',
    '$vehicle_number',
    '$tracking_number',
    '$dispatch_date',
    'Dispatched'
)
");

$dispatch_id = mysqli_insert_id($conn);

foreach($_POST['packing_item_id'] as $key => $packing_item_id){

    $packing_item_id = intval($packing_item_id);

    $dispatch_qty = intval($_POST['dispatch_qty'][$key]);

    $item = mysqli_query($conn,"
    SELECT *
    FROM packing_items
    WHERE id='$packing_item_id'
    ");

    if(mysqli_num_rows($item)==0){
        continue;
    }

    $row = mysqli_fetch_assoc($item);

    /* Save Dispatch Item */

    mysqli_query($conn,"
    INSERT INTO dispatch_items
    (
        dispatch_id,
        packing_item_id,
        product_id,
        product_code,
        product_name,
        warehouse,
        bin_location,
        packed_qty,
        dispatched_qty
    )
    VALUES
    (
        '$dispatch_id',
        '".$row['id']."',
        '".$row['product_id']."',
        '".$row['product_code']."',
        '".$row['product_name']."',
        '".$row['warehouse']."',
        '".$row['bin_location']."',
        '".$row['packed_qty']."',
        '$dispatch_qty'
    )
    ");

    /* Update Sales Order Item */

    mysqli_query($conn,"
    UPDATE sales_order_items
    SET dispatched_qty='$dispatch_qty'
    WHERE id='".$row['picking_item_id']."'
    ");

    /* Update Inventory */

    $inventory = mysqli_query($conn,"
    SELECT *
    FROM inventory
    WHERE product_id='".$row['product_id']."'
    AND warehouse='".$row['warehouse']."'
    AND bin_location='".$row['bin_location']."'
    ");

    if(mysqli_num_rows($inventory)>0){

        $inv = mysqli_fetch_assoc($inventory);

        $new_qty = $inv['available_qty'] - $dispatch_qty;

        if($new_qty < 0){
            $new_qty = 0;
        }

        $status = "In Stock";

        if($new_qty <= 0){
            $status = "Out of Stock";
        }elseif($new_qty <= 10){
            $status = "Low Stock";
        }

        mysqli_query($conn,"
        UPDATE inventory
        SET
            available_qty='$new_qty',
            status='$status'
        WHERE id='".$inv['id']."'
        ");

    }

}

/* Update Sales Order Status */
mysqli_query($conn, "
UPDATE sales_orders
SET status='Dispatched'
WHERE id='$sales_order_id'");

/* Update Packing Status */
mysqli_query($conn, "
UPDATE packing
SET status='Completed'
WHERE id='$packing_id'");

/* Redirect */
header("Location:index.php");
exit();
?>
