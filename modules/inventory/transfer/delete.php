<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if(!isset($_GET['id'])){
    die("Transfer ID Missing");
}

$id = intval($_GET['id']);

$result = mysqli_query($conn,"
SELECT *
FROM stock_transfer
WHERE id='$id'
");

if(mysqli_num_rows($result)==0){
    die("Transfer Not Found");
}

$row = mysqli_fetch_assoc($result);

$product_id = $row['product_id'];

$qty = $row['quantity'];

$from_warehouse = $row['from_warehouse'];

$from_bin = $row['from_bin'];

$to_warehouse = $row['to_warehouse'];

$to_bin = $row['to_bin'];

/* Return Stock to Source */

$source = mysqli_query($conn,"
SELECT *
FROM inventory
WHERE product_id='$product_id'
AND warehouse='$from_warehouse'
AND bin_location='$from_bin'
");

if(mysqli_num_rows($source)>0){

    $src = mysqli_fetch_assoc($source);

    $newQty = $src['available_qty'] + $qty;

    $status = "In Stock";

    if($newQty<=0){
        $status="Out of Stock";
    }elseif($newQty<=10){
        $status="Low Stock";
    }

    mysqli_query($conn,"
    UPDATE inventory
    SET
    available_qty='$newQty',
    status='$status'
    WHERE id='".$src['id']."'
    ");

}

/* Remove Stock from Destination */

$destination = mysqli_query($conn,"
SELECT *
FROM inventory
WHERE product_id='$product_id'
AND warehouse='$to_warehouse'
AND bin_location='$to_bin'
");

if(mysqli_num_rows($destination)>0){

    $dest = mysqli_fetch_assoc($destination);

    $newQty = $dest['available_qty'] - $qty;

    if($newQty < 0){
        $newQty = 0;
    }

    $status = "In Stock";

    if($newQty<=0){
        $status="Out of Stock";
    }elseif($newQty<=10){
        $status="Low Stock";
    }

    mysqli_query($conn,"
    UPDATE inventory
    SET
    available_qty='$newQty',
    status='$status'
    WHERE id='".$dest['id']."'
    ");

}

/* Delete Transfer Record */

mysqli_query($conn,"
DELETE FROM stock_transfer
WHERE id='$id'
");

header("Location:index.php");
exit();