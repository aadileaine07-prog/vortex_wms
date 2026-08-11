<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$result = mysqli_query($conn,"
SELECT *
FROM grn
ORDER BY id DESC
");

include "../../../includes/header.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-info text-white">

<div class="d-flex justify-content-between">

<h3>Quality Check (QC)</h3>

<a
href="../grn/index.php"
class="btn btn-light">

Back GRN

</a>

</div>

</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>GRN Number</th>

<th>Supplier Name</th>

<th>Received Date</th>

<th>Status</th>

<th width="180">Action</th>

</tr>

</thead>

<tbody>

<?php

while($row=mysqli_fetch_assoc($result)){

?>

<tr>

<td><?= $row['id']; ?></td>

<td><?= $row['grn_number']; ?></td>

<td>N/A</td>

<td><?= $row['received_date']; ?></td>

<td><?= $row['status']; ?></td>

<td>

<a
href="inspect.php?id=<?= $row['id']; ?>"
class="btn btn-primary btn-sm">

Inspect

</a>

<a
href="view.php?id=<?= $row['id']; ?>"
class="btn btn-success btn-sm">

View

</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

<?php

if(mysqli_num_rows($result)==0){

?>

<tr>

<td colspan="6" class="text-center">

No GRN Available For QC

</td>

</tr>

<?php

}

?>

</tbody>

</table>

<br>

<div class="d-flex justify-content-between">

<a
href="../dashboard/index.php"
class="btn btn-secondary">

← Dashboard

</a>

<a
href="../putaway/index.php"
class="btn btn-success">

Next → Putaway

</a>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>