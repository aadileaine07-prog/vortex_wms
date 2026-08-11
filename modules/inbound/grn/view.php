<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id'])) {
    die("GRN ID Missing");
}

$id = intval($_GET['id']);

$grn = mysqli_query($conn,"
SELECT *
FROM grn
WHERE id='$id'
");

if(mysqli_num_rows($grn)==0){
    die("GRN Not Found");
}

$grn=mysqli_fetch_assoc($grn);

$items = mysqli_query($conn,"
SELECT
g.*,
a.product_name,
a.product_code
FROM grn_items g
LEFT JOIN asn_items a
ON g.asn_item_id = a.id
WHERE g.grn_id='$id'
ORDER BY g.id ASC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h3>GRN Details</h3>

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>
    <th width="200">GRN No</th>
    <td><?= $grn['grn_number']; ?></td>
</tr>

<tr>
    <th>Supplier</th>
    <td>--</td>
</tr>

<tr>
    <th>ASN No</th>
    <td><?= $grn['asn_id']; ?></td>
</tr>

<tr>
    <th>Received Date</th>
    <td><?= $grn['received_date']; ?></td>
</tr>

<tr>
    <th>Status</th>
    <td><?= $grn['status']; ?></td>
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

<h4 class="mt-4">GRN Items</h4>

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>Product</th>

<th>Ordered</th>

<th>Received</th>

<th>Damaged</th>

<th>Accepted</th>

<th>Remarks</th>

</tr>

</thead>

<tbody>

<?php

if(mysqli_num_rows($items)>0){

while($row=mysqli_fetch_assoc($items)){

?>

<tr>

<td><?= $row['id']; ?></td>

<td><?= $row['product_name']; ?></td>

<td><?= $row['ordered_qty']; ?></td>

<td><?= $row['received_qty']; ?></td>

<td><?= $row['damaged_qty']; ?></td>

<td><?= $row['accepted_qty']; ?></td>

<td><?= $row['remarks']; ?></td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="7" class="text-center">

No Items Found

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

<a
href="edit.php?id=<?= $grn['id']; ?>"
class="btn btn-warning">

Edit GRN

</a>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>