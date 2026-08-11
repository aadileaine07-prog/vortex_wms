<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$result = mysqli_query($conn, "SELECT * FROM companies ORDER BY id DESC");
?>

<?php include "../../../includes/header.php"; ?>
<?php include "../../../includes/navbar.php"; ?>
<?php include "../../../includes/sidebar.php"; ?>

<div class="content">

<div class="container-fluid">

<div class="d-flex justify-content-between align-items-center mb-4">

<h2>🏢 Company Master</h2>

<a href="add.php" class="btn btn-primary">
+ Add Company
</a>

</div>

<div class="card shadow">

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>ID</th>
<th>Code</th>
<th>Company Name</th>
<th>Phone</th>
<th>Email</th>
<th>Status</th>
<th width="220">Action</th>

</tr>

</thead>

<tbody>

<?php if(mysqli_num_rows($result)>0){ ?>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?= $row['id']; ?></td>

<td><?= $row['company_code']; ?></td>

<td><?= $row['company_name']; ?></td>

<td><?= $row['phone']; ?></td>

<td><?= $row['email']; ?></td>

<td><?= $row['status']; ?></td>

<td>

<a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm">
View
</a>

<a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">
Edit
</a>

<a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm">
Delete
</a>

</td>

</tr>

<?php } ?>

<?php } else { ?>

<tr>

<td colspan="7" class="text-center">

No Company Found

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