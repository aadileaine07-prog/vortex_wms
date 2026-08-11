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

$grn_id = intval($_GET['id']);

$grn = mysqli_query($conn,"
SELECT
    g.*,
    a.supplier_name
FROM grn g
LEFT JOIN asn a
    ON g.asn_id = a.id
WHERE g.id='$grn_id'
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

if(isset($_POST['save'])){

    foreach($_POST['accepted_qty'] as $item_id=>$accepted){

        $accepted = intval($accepted);

        $rejected = intval($_POST['rejected_qty'][$item_id]);

        $reason = mysqli_real_escape_string(
            $conn,
            $_POST['remarks'][$item_id]
        );

        mysqli_query($conn,"
        UPDATE grn_items
        SET

        accepted_qty='$accepted',

        damaged_qty='$rejected',

        remarks='$reason'

        WHERE id='$item_id'
        ");

    }

   mysqli_query($conn,"
UPDATE grn
SET status='Completed'
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

<div class="card-header bg-info text-white">

<h3>Quality Inspection</h3>

</div>

<div class="card-body">

<form method="POST">

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

<?= isset($row['product_name']) ? $row['product_name'] : '-'; ?>

<td><?= $row['received_qty']; ?></td>

<td>

<input
type="number"
name="accepted_qty[<?= $row['id']; ?>]"
value="<?= isset($row['accepted_qty']) ? $row['accepted_qty'] : $row['received_qty']; ?>"
class="form-control"
required>

</td>

<td>

<input
type="number"
name="rejected_qty[<?= $row['id']; ?>]"
value="<?= isset($row['damaged_qty']) ? $row['damaged_qty'] : 0; ?>"
class="form-control"
required>

</td>

<td>

<input
type="text"
name="remarks[<?= $row['id']; ?>]"
value="<?= $row['remarks']; ?>"
class="form-control">

</td>

</tr>

<?php } ?>

</tbody>

</table>

<br>

<div class="row">

<div class="col-md-12 text-end">

<button
type="submit"
name="save"
class="btn btn-success">

Save QC

</button>

<a
href="index.php"
class="btn btn-secondary">

Back

</a>

</div>

</div>

</form>

</div>

</div>

<div class="card mt-3">

<div class="card-header bg-primary text-white">

QC Summary

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>

<th width="250">GRN No</th>

<td><?= $grn['grn_number']; ?></td>

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

<span class="badge bg-warning">

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