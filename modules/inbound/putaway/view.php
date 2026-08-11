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
SELECT *
FROM grn_items
WHERE grn_id='$grn_id'
ORDER BY id ASC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>Putaway Details</h3>

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>

<th width="220">GRN No</th>

<td><?= $grn['grn_no']; ?></td>

</tr>

<tr>

<th>Supplier</th>

<td><?= $grn['supplier_name']; ?></td>

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

<h4 class="mt-4">Putaway Items</h4>

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>Product</th>

<th>Accepted Qty</th>

<th>Warehouse</th>

<th>Bin Location</th>

</tr>

</thead>

<tbody>

<?php while($row=mysqli_fetch_assoc($items)){ ?>

<tr>

<td><?= $row['id']; ?></td>

<td><?= $row['product_name']; ?></td>

<td><?= $row['accepted_qty']; ?></td>

<td><?= $row['warehouse']; ?></td>

<td><?= $row['bin_location']; ?></td>

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

<a
href="../../inventory/index.php"
class="btn btn-success">

Go To Inventory →

</a>

</div>

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

<td><?= $grn['grn_no']; ?></td>

</tr>

<tr>

<th>Supplier</th>

<td><?= $grn['supplier_name']; ?></td>

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