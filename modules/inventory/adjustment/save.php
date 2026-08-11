<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if($_SERVER['REQUEST_METHOD'] != "POST"){
    header("Location:index.php");
    exit();
}

$inventory_id = intval($_POST['inventory_id']);
$adjustment_date = $_POST['adjustment_date'];

$warehouse = mysqli_real_escape_string($conn, $_POST['warehouse']);
$bin_location = mysqli_real_escape_string($conn, $_POST['bin_location']);
$adjustment_type = mysqli_real_escape_string($conn, $_POST['adjustment_type']);
$quantity = intval($_POST['quantity']);
$reason = mysqli_real_escape_string($conn, $_POST['reason']);

$created_by = $_SESSION['employee_id'];

/* Get Inventory Record */

$inventory = mysqli_query($conn,"
SELECT *
FROM inventory
WHERE id='$inventory_id'
");

if(mysqli_num_rows($inventory)==0){
    die("Inventory Record Not Found");
}

$item = mysqli_fetch_assoc($inventory);

$product_id   = $item['product_id'];
$product_code = $item['product_code'];
$product_name = $item['product_name'];
$current_qty  = $item['available_qty'];

/* Calculate New Quantity */

if($adjustment_type=="Increase"){

    $new_qty = $current_qty + $quantity;

}else{

    if($quantity > $current_qty){
        die("Adjustment quantity cannot be greater than available stock.");
    }

    $new_qty = $current_qty - $quantity;

}

/* Stock Status */

$status = "In Stock";

if($new_qty <= 0){

    $status = "Out of Stock";

}elseif($new_qty <= 10){

    $status = "Low Stock";

}

/* Update Inventory */

mysqli_query($conn,"
UPDATE inventory
SET
available_qty='$new_qty',
status='$status'
WHERE id='$inventory_id'
");

/* Save Adjustment History */

mysqli_query($conn,"
INSERT INTO stock_adjustment
(
    product_id,
    product_code,
    product_name,
    warehouse,
    bin_location,
    adjustment_type,
    quantity,
    reason,
    adjustment_date,
    created_by
)
VALUES
(
    '$product_id',
    '$product_code',
    '$product_name',
    '$warehouse',
    '$bin_location',
    '$adjustment_type',
    '$quantity',
    '$reason',
    '$adjustment_date',
    '$created_by'
)
");

header("Location:index.php");
exit();
?>