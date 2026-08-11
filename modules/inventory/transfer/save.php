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

$inventory_id = intval($_POST['inventory_id']);

$transfer_date = $_POST['transfer_date'];

$from_warehouse = mysqli_real_escape_string($conn,$_POST['from_warehouse']);

$from_bin = mysqli_real_escape_string($conn,$_POST['from_bin']);

$to_warehouse = mysqli_real_escape_string($conn,$_POST['to_warehouse']);

$to_bin = mysqli_real_escape_string($conn,$_POST['to_bin']);

$quantity = intval($_POST['quantity']);

$remarks = mysqli_real_escape_string($conn,$_POST['remarks']);

$user = $_SESSION['employee_id'];

/* Get Source Inventory */

$inventory = mysqli_query($conn,"
SELECT *
FROM inventory
WHERE id='$inventory_id'
");

if(mysqli_num_rows($inventory)==0){

    die("Inventory Not Found");

}

$item = mysqli_fetch_assoc($inventory);

if($quantity > $item['available_qty']){

    die("Insufficient Stock");

}

$product_id   = $item['product_id'];
$product_code = $item['product_code'];
$product_name = $item['product_name'];

/* Save Transfer */

mysqli_query($conn,"
INSERT INTO stock_transfer
(
product_id,
product_code,
product_name,
from_warehouse,
from_bin,
to_warehouse,
to_bin,
quantity,
remarks,
transfer_date,
created_by
)

VALUES
(
'$product_id',
'$product_code',
'$product_name',
'$from_warehouse',
'$from_bin',
'$to_warehouse',
'$to_bin',
'$quantity',
'$remarks',
'$transfer_date',
'$user'
)
");

/* Update Source Inventory */

$newQty = $item['available_qty'] - $quantity;

$status = "In Stock";

if($newQty <= 0){
    $status = "Out of Stock";
}elseif($newQty <= 10){
    $status = "Low Stock";
}

mysqli_query($conn,"
UPDATE inventory
SET
available_qty='$newQty',
status='$status'
WHERE id='$inventory_id'
");

/* Check Destination Inventory */

$check = mysqli_query($conn,"
SELECT *
FROM inventory
WHERE product_id='$product_id'
AND warehouse='$to_warehouse'
AND bin_location='$to_bin'
");

if(mysqli_num_rows($check)>0){

    $dest = mysqli_fetch_assoc($check);

    $destQty = $dest['available_qty'] + $quantity;

    $destStatus = "In Stock";

    if($destQty <= 0){
        $destStatus = "Out of Stock";
    }elseif($destQty <= 10){
        $destStatus = "Low Stock";
    }

    mysqli_query($conn,"
    UPDATE inventory
    SET
    available_qty='$destQty',
    status='$destStatus'
    WHERE id='".$dest['id']."'
    ");

}else{

    mysqli_query($conn,"
    INSERT INTO inventory
    (
        product_id,
        product_code,
        product_name,
        warehouse,
        bin_location,
        available_qty,
        reserved_qty,
        status
    )
    VALUES
    (
        '$product_id',
        '$product_code',
        '$product_name',
        '$to_warehouse',
        '$to_bin',
        '$quantity',
        '0',
        'In Stock'
    )
    ");

}

header("Location:index.php");
exit();