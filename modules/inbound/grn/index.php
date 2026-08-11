<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$result = mysqli_query($conn,"
SELECT g.*, a.asn_number
FROM grn g
LEFT JOIN asn a ON g.asn_id = a.id
ORDER BY g.id DESC
");

include "../../../includes/header.php";
?>

<div class="content">

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<h2>📦 Goods Receipt Note (GRN)</h2>

<a href="create.php" class="btn btn-primary">
+ Create GRN
</a>

</div>

<div class="card shadow">

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>
<th>GRN No.</th>
<th>ASN No.</th>
<th>Received Date</th>
<th>Received By</th>
<th>Status</th>
<th width="220">Action</th>

</tr>

</thead>

<tbody>

<?php if(mysqli_num_rows($result)>0){ ?>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?= $row['id']; ?></td>

<td><?= $row['grn_number']; ?></td>

<td><?= $row['asn_number']; ?></td>

<td><?= $row['received_date']; ?></td>

<td><?= $row['received_by']; ?></td>

<td>

<?php

if($row['status']=="Pending"){
    echo "<span class='badge bg-warning'>Pending</span>";
}else{
    echo "<span class='badge bg-success'>Completed</span>";
}

?>

</td>

<td>

<a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm">
View
</a>

<a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">
Edit
</a>

<a href="delete.php?id=<?= $row['id']; ?>"
class="btn btn-danger btn-sm"
onclick="return confirm('Are you sure you want to delete this GRN?');">
Delete
</a>

</td>

</tr>

<?php } ?>

<?php } else { ?>

<tr>

<td colspan="7" class="text-center">

No GRN Found

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>

