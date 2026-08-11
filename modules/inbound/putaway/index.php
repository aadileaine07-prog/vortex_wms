<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$result = mysqli_query($conn,"
SELECT
g.*,
a.asn_number
FROM grn g
LEFT JOIN asn a
ON g.asn_id = a.id
WHERE g.status='Completed'
ORDER BY g.id DESC
");
include "../../../includes/header.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<div class="d-flex justify-content-between">

<h3>Putaway</h3>

<a
href="../qc/index.php"
class="btn btn-light">

Back QC

</a>

</div>

</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>

<th>GRN No</th>

<th>Supplier</th>

<th>Received Date</th>

<th>Status</th>

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

<?= $row['grn_number']; ?>
--  

<td><?= $row['received_date']; ?></td>

<td>

<span class="badge bg-success">

<?= $row['status']; ?>

</span>

</td>

<td>

<a
href="assign.php?id=<?= $row['id']; ?>"
class="btn btn-primary btn-sm">

Assign Bin

</a>

<a
href="view.php?id=<?= $row['id']; ?>"
class="btn btn-success btn-sm">

View

</a>

</td>

</tr>

<?php

}

}else{

?>

<tr>

<td colspan="6" class="text-center">

No QC Completed GRN Found

</td>

</tr>

<?php } ?>

</tbody>

</table>

<br>

<div class="d-flex justify-content-between">

<a
href="../qc/index.php"
class="btn btn-secondary">

← Back QC

</a>

<a
href="../../dashboard/index.php"
class="btn btn-success">

Dashboard

</a>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>