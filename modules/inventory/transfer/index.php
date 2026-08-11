<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$result = mysqli_query($conn,"
SELECT *
FROM stock_transfer
ORDER BY id DESC
");

include "../../../includes/header.php";
?>

<div class="content">

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<div>

<h2 class="fw-bold">

🚚 Stock Transfer

</h2>

<p class="text-muted">

Manage Warehouse & Bin Transfers

</p>

</div>

<div>

<a
href="create.php"
class="btn btn-success">

+ New Transfer

</a>

</div>

</div>

<div class="card shadow">

<div class="card-body">

<div class="row mb-3">

<div class="col-md-6">

<input
type="text"
id="searchInput"
class="form-control"
placeholder="Search Product...">

</div>

</div>

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th width="60">ID</th>

<th>Product</th>

<th>From Warehouse</th>

<th>From Bin</th>

<th>To Warehouse</th>

<th>To Bin</th>

<th width="100">Qty</th>

<th width="130">Date</th>

<th width="180">Action</th>

</tr>

</thead>

<tbody>

<?php

if(mysqli_num_rows($result)>0){

while($row=mysqli_fetch_assoc($result)){

?>

<tr>

<td><?= $row['id']; ?></td>

<td>

<strong><?= $row['product_name']; ?></strong>

<br>

<small class="text-muted">

<?= $row['product_code']; ?>

</small>

</td>

<td><?= $row['from_warehouse']; ?></td>

<td><?= $row['from_bin']; ?></td>

<td><?= $row['to_warehouse']; ?></td>

<td><?= $row['to_bin']; ?></td>

<td>

<span class="badge bg-primary">

<?= $row['quantity']; ?>

</span>

</td>

<td><?= $row['transfer_date']; ?></td>

<td>

<a
href="view.php?id=<?= $row['id']; ?>"
class="btn btn-info btn-sm">

View

</a>

<a
href="delete.php?id=<?= $row['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Delete this transfer?');">

Delete

</a>

</td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="9" class="text-center">

No Stock Transfers Found

</td>

</tr>

<?php } ?>

</tbody>

</table>
</div>

<div class="card-footer">

<div class="d-flex justify-content-between">

<a
href="../index.php"
class="btn btn-secondary">

← Inventory

</a>

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

<?php include "../../../includes/footer.php"; ?>

<script>

document.addEventListener("DOMContentLoaded", function () {

    const input = document.getElementById("searchInput");

    input.addEventListener("keyup", function () {

        let filter = this.value.toLowerCase();

        let rows = document.querySelectorAll("tbody tr");

        rows.forEach(function(row){

            row.style.display = row.innerText.toLowerCase().includes(filter)
                ? ""
                : "none";

        });

    });

});

</script>