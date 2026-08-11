<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id'])) {
    header("Location:index.php");
    exit();
}

$id = intval($_GET['id']);

if(isset($_POST['update'])){

    $received_date = $_POST['received_date'];
    $received_by = $_POST['received_by'];
    $vehicle_number = $_POST['vehicle_number'];
    $remarks = $_POST['remarks'];
    $status = $_POST['status'];

    mysqli_query($conn,"
    UPDATE grn
    SET

    received_date='$received_date',

    received_by='$received_by',

    vehicle_number='$vehicle_number',

    remarks='$remarks',

    status='$status'

    WHERE id='$id'
    ");

    header("Location:index.php");
    exit();
}

$result = mysqli_query($conn,"
SELECT *
FROM grn
WHERE id='$id'
");

$row = mysqli_fetch_assoc($result);

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-warning">

<h3>Edit GRN</h3>

</div>

<div class="card-body">

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">

<label>GRN Number</label>

<input
type="text"
class="form-control"
value="<?= $row['grn_number']; ?>"
readonly>

</div>

<div class="col-md-6 mb-3">

<label>Received Date</label>

<input
type="date"
name="received_date"
value="<?= $row['received_date']; ?>"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>Received By</label>

<input
type="text"
name="received_by"
value="<?= $row['received_by']; ?>"
class="form-control">

</div>

<div class="col-md-6 mb-3">

<label>Vehicle Number</label>

<input
type="text"
name="vehicle_number"
value="<?= $row['vehicle_number']; ?>"
class="form-control">

</div>

<div class="col-md-12 mb-3">

<label>Remarks</label>

<textarea
name="remarks"
class="form-control"><?= $row['remarks']; ?></textarea>

</div>

<div class="col-md-6 mb-3">

<label>Status</label>

<select
name="status"
class="form-control">

<option value="Pending"
<?= ($row['status']=="Pending")?"selected":"";?>>

Pending

</option>

<option value="Completed"
<?= ($row['status']=="Completed")?"selected":"";?>>

Completed

</option>

</select>

</div>

</div>

<button
class="btn btn-success"
name="update">

Update GRN

</button>

<a
href="index.php"
class="btn btn-secondary">

Cancel

</a>

</form>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>