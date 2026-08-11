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

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h3>Stock Transfer Details</h3>

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>
<th width="220">Transfer ID</th>
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
<th>From Warehouse</th>
<td><?= $row['from_warehouse']; ?></td>
</tr>

<tr>
<th>From Bin</th>
<td><?= $row['from_bin']; ?></td>
</tr>

<tr>
<th>To Warehouse</th>
<td><?= $row['to_warehouse']; ?></td>
</tr>

<tr>
<th>To Bin</th>
<td><?= $row['to_bin']; ?></td>
</tr>

<tr>
<th>Transfer Quantity</th>
<td>

<span class="badge bg-primary">

<?= $row['quantity']; ?>

</span>

</td>
</tr>

<tr>
<th>Transfer Date</th>
<td><?= $row['transfer_date']; ?></td>
</tr>

<tr>
<th>Created By</th>
<td><?= $row['created_by']; ?></td>
</tr>

<tr>
<th>Remarks</th>
<td>

<?= !empty($row['remarks']) ? $row['remarks'] : '-'; ?>

</td>
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
onclick="window.print();"
class="btn btn-primary">

🖨 Print

</button>

<a
href="create.php"
class="btn btn-success">

+ New Transfer

</a>

</div>

</div>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>