<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$id = $_GET['id'];

$result = mysqli_query($conn,"SELECT * FROM companies WHERE id='$id'");
$row = mysqli_fetch_assoc($result);

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-info text-white">

<h3>Company Details</h3>

</div>

<div class="card-body">

<table class="table table-bordered">

<tr>
<th width="250">Company Code</th>
<td><?= $row['company_code']; ?></td>
</tr>

<tr>
<th>Company Name</th>
<td><?= $row['company_name']; ?></td>
</tr>

<tr>
<th>GST Number</th>
<td><?= $row['gst_number']; ?></td>
</tr>

<tr>
<th>PAN Number</th>
<td><?= $row['pan_number']; ?></td>
</tr>

<tr>
<th>Email</th>
<td><?= $row['email']; ?></td>
</tr>

<tr>
<th>Phone</th>
<td><?= $row['phone']; ?></td>
</tr>

<tr>
<th>Website</th>
<td><?= $row['website']; ?></td>
</tr>

<tr>
<th>Address</th>
<td><?= $row['address']; ?></td>
</tr>

<tr>
<th>City</th>
<td><?= $row['city']; ?></td>
</tr>

<tr>
<th>State</th>
<td><?= $row['state']; ?></td>
</tr>

<tr>
<th>Country</th>
<td><?= $row['country']; ?></td>
</tr>

<tr>
<th>Pincode</th>
<td><?= $row['pincode']; ?></td>
</tr>

<tr>
<th>Status</th>
<td><?= $row['status']; ?></td>
</tr>

</table>

<a href="index.php" class="btn btn-secondary">

Back

</a>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>