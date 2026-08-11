<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if(!isset($_GET['id'])){
    die("GRN ID Missing");
}

$grn_id = intval($_GET['id']);

$grn = mysqli_query($conn,"
SELECT *
FROM grn
WHERE id='$grn_id'
");

if(mysqli_num_rows($grn)==0){
    die("GRN Not Found");
}

$grn = mysqli_fetch_assoc($grn);

$items = mysqli_query($conn,"
SELECT
g.*,
a.product_name,
a.product_code
FROM grn_items g
LEFT JOIN asn_items a
ON g.asn_item_id = a.id
WHERE g.grn_id='$grn_id'
ORDER BY g.id ASC
");

if(isset($_POST['save'])){

    foreach($_POST['warehouse'] as $item_id=>$warehouse){

        $warehouse = mysqli_real_escape_string($conn,$warehouse);

        $bin = mysqli_real_escape_string(
            $conn,
            $_POST['bin'][$item_id]
        );

        mysqli_query($conn,"
        UPDATE grn_items
        SET

        warehouse='$warehouse',

        bin_location='$bin'

        WHERE id='$item_id'
        ");

        /* Get Product Details */

$item = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT
g.accepted_qty,
a.product_id,
a.product_code,
a.product_name
FROM grn_items g
LEFT JOIN asn_items a
ON a.id = g.asn_item_id
WHERE g.id='$item_id'
"));

$product_id   = $item['product_id'];
$product_code = $item['product_code'];
$product_name = $item['product_name'];
$qty          = $item['accepted_qty'];

/* Check Existing Inventory */

$check = mysqli_query($conn,"
SELECT *
FROM inventory
WHERE product_id='$product_id'
AND warehouse='$warehouse'
AND bin_location='$bin'
");

if(mysqli_num_rows($check)>0){

    $inv = mysqli_fetch_assoc($check);

    $newQty = $inv['available_qty'] + $qty;

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
    WHERE id='".$inv['id']."'
    ");

}else{

    $status = "In Stock";

    if($qty <= 0){
        $status = "Out of Stock";
    }elseif($qty <= 10){
        $status = "Low Stock";
    }

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
        '$warehouse',
        '$bin',
        '$qty',
        '0',
        '$status'
    )
    ");

}

    }

    mysqli_query($conn,"
    UPDATE grn
    SET status='Putaway Completed'
    WHERE id='$grn_id'
    ");

    header("Location:index.php");
    exit();

}

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>Assign Warehouse & Bin</h3>

</div>

<div class="card-body">

<form method="POST">

<table class="table table-bordered">

<thead class="table-dark">

<tr>

<th>Product</th>

<th>Accepted Qty</th>

<th>Warehouse</th>

<th>Bin Location</th>

</tr>

</thead>

<tbody>

<?php while($row=mysqli_fetch_assoc($items)){ ?>

<tr>

<td><?= $row['product_name']; ?></td>

<td><?= $row['accepted_qty']; ?></td>

<td>

<input
type="text"
name="warehouse[<?= $row['id']; ?>]"
class="form-control"
required>

</td>

<td>

<input
type="text"
name="bin[<?= $row['id']; ?>]"
class="form-control"
required>

</td>

</tr>

<?php } ?>

</tbody>

</table>

<br>

<div class="d-flex justify-content-between">

<a
href="index.php"
class="btn btn-secondary">

← Back

</a>

<button
type="submit"
name="save"
class="btn btn-success">

Complete Putaway

</button>

</div>

</form>

</div>

</div>

<div class="card mt-3">

<div class="card-header bg-primary text-white">

Putaway Summary

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>

<th width="220">GRN No</th>

<?= $grn['grn_number']; ?>

</tr>

<tr>

<th>Supplier</th>

--

</tr>

<tr>

<th>Received Date</th>

<td><?= $grn['received_date']; ?></td>

</tr>

<tr>

<th>Status</th>

<td>

<span class="badge bg-success">

<?= $grn['status']; ?>

</span>

</td>

</tr>

</table>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>