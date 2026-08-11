<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if(!isset($_GET['id'])){
    die("Adjustment ID Missing");
}

$id = intval($_GET['id']);

$result = mysqli_query($conn,"
SELECT *
FROM stock_adjustment
WHERE id='$id'
");

if(mysqli_num_rows($result)==0){
    die("Adjustment Not Found");
}

$row = mysqli_fetch_assoc($result);

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h3>Stock Adjustment Details</h3>

</div>

<div class="card-body">
<table class="table table-bordered">

<tr>
<th width="220">Adjustment ID</th>
<td><?= $row['id']; ?></td>
</tr>

<tr>
<th>Product Code</th>
<td><?= $row['product_code']; ?></td>
</tr>

<tr>
<th>Product Name</th>
<td><?= $row['product_name']; ?></td>
</tr>

<tr>
<th>Warehouse</th>
<td><?= $row['warehouse']; ?></td>
</tr>

<tr>
<th>Bin Location</th>
<td><?= $row['bin_location']; ?></td>
</tr>

<tr>
<th>Adjustment Type</th>
<td>

<?php if($row['adjustment_type']=="Increase"){ ?>

<span class="badge bg-success">

Increase

</span>

<?php }else{ ?>

<span class="badge bg-danger">

Decrease

</span>

<?php } ?>

</td>
</tr>

<tr>
<th>Quantity</th>
<td>

<span class="badge bg-primary">

<?= $row['quantity']; ?>

</span>

</td>
</tr>

<tr>
<th>Reason</th>
<td>

<?= !empty($row['reason']) ? $row['reason'] : "-"; ?>

</td>
</tr>

<tr>
<th>Adjustment Date</th>
<td><?= $row['adjustment_date']; ?></td>
</tr>

<tr>
<th>Created By</th>
<td><?= $row['created_by']; ?></td>
</tr>

</table>
<br>

<div class="d-flex justify-content-between">

<a
href="index.php"
class="btn btn-secondary">

← Back

</a>

<div>

<button
type="button"
onclick="window.print();"
class="btn btn-primary">

🖨 Print

</button>

<a
href="create.php"
class="btn btn-success">

+ New Adjustment

</a>

</div>

</div>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>