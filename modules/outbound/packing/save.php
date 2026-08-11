```php
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

$picking_id = intval($_POST['picking_id']);

$sales_order_id = intval($_POST['sales_order_id']);

$packing_number = mysqli_real_escape_string($conn,$_POST['packing_number']);

$packing_date = $_POST['packing_date'];

$packer_name = mysqli_real_escape_string($conn,$_POST['packer_name']);

mysqli_query($conn,
"INSERT INTO packing
(
    sales_order_id,
    picking_id,
    packing_number,
    packer_name,
    packing_date,
    status
)
VALUES
(
    '$sales_order_id',
    '$picking_id',
    '$packing_number',
    '$packer_name',
    '$packing_date',
    'Completed'
)");

$packing_id = mysqli_insert_id($conn);

foreach($_POST['picking_item_id'] as $key => $picking_item_id){

    $picking_item_id = intval($picking_item_id);

    $packed_qty = intval($_POST['packed_qty'][$key]);

    $item = mysqli_query($conn,
    "SELECT *
    FROM picking_items
    WHERE id='$picking_item_id'");

    if(mysqli_num_rows($item)==0){
        continue;
    }

    $row = mysqli_fetch_assoc($item);

    mysqli_query($conn,
    "INSERT INTO packing_items
    (
        packing_id,
        picking_item_id,
        product_id,
        product_code,
        product_name,
        warehouse,
        bin_location,
        picked_qty,
        packed_qty
    )
    VALUES
    (
        '$packing_id',
        '".$row['id']."',
        '".$row['product_id']."',
        '".$row['product_code']."',
        '".$row['product_name']."',
        '".$row['warehouse']."',
        '".$row['bin_location']."',
        '".$row['picked_qty']."',
        '$packed_qty'
    )");

    mysqli_query($conn,
    "UPDATE sales_order_items
    SET packed_qty='$packed_qty'
    WHERE id='".$row['sales_order_item_id']."'");

}

/* Update Sales Order Status */

mysqli_query($conn,
"UPDATE sales_orders
SET status='Packed'
WHERE id='$sales_order_id'");

/* Redirect */

header("Location:index.php");
exit();

?>
