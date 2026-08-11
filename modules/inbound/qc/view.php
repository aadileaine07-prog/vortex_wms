<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id'])) {
    die("QC ID Missing");
}

$grn_id = intval($_GET['id']);

$grn = mysqli_query($conn,"
SELECT
g.*,
a.supplier_name
FROM grn g
LEFT JOIN asn a ON a.id = g.asn_id
WHERE g.id='$grn_id'
");

if(mysqli_num_rows($grn)==0){
    die("GRN Not Found");
}

$grn = mysqli_fetch_assoc($grn);

$items = mysqli_query($conn,"
SELECT
g.*,
a.product_name
FROM grn_items g
LEFT JOIN asn_items a
ON a.id = g.asn_item_id
WHERE g.grn_id='$grn_id'
ORDER BY g.id ASC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>QC Details</h3>

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>

<th width="220">GRN No</th>

<?= $grn['grn_number']; ?>

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

<td><?= $grn['status']; ?></td>

</tr>

</table>

<h4 class="mt-4">QC Items</h4>

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>Product</th>

<th>Received Qty</th>

<th>Accepted Qty</th>

<th>Rejected Qty</th>

<th>Remarks</th>

</tr>

</thead>

<tbody>

<?php while($row=mysqli_fetch_assoc($items)){ ?>

<tr>

<td><?= $row['id']; ?></td>

<td><?= $row['product_name']; ?></td>

<td><?= $row['received_qty']; ?></td>

<td><?= $row['accepted_qty']; ?></td>

<td><?= $row['damaged_qty']; ?></td>

<td><?= $row['remarks']; ?></td>

</tr>

<?php } ?>

<?php
