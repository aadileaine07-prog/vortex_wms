<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if(isset($_POST['save'])){

    // Auto Generate GRN Number
    $result = mysqli_query($conn,"SELECT id FROM grn ORDER BY id DESC LIMIT 1");

    if(mysqli_num_rows($result)>0){
        $last = mysqli_fetch_assoc($result);
        $grn_number = "GRN".str_pad($last['id']+1,6,"0",STR_PAD_LEFT);
    }else{
        $grn_number = "GRN000001";
    }

    $asn_id = $_POST['asn_id'];
    $received_date = $_POST['received_date'];
    $received_by = $_POST['received_by'];
    $vehicle_number = $_POST['vehicle_number'];
    $remarks = $_POST['remarks'];

    $sql = "INSERT INTO grn
    (
        grn_number,
        asn_id,
        received_date,
        received_by,
        vehicle_number,
        remarks
    )
    VALUES
    (
        '$grn_number',
        '$asn_id',
        '$received_date',
        '$received_by',
        '$vehicle_number',
        '$remarks'
    )";

   if(mysqli_query($conn,$sql)){

    $grn_id = mysqli_insert_id($conn);

    header("Location:items.php?grn_id=".$grn_id);

    exit();

}else{

    echo mysqli_error($conn);

}
}

$selected_asn = "";

if(isset($_GET['asn_id'])){
    $selected_asn = intval($_GET['asn_id']);
}

$asn = mysqli_query($conn,"SELECT id,asn_number FROM asn ORDER BY id DESC");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>Create GRN</h3>

</div>

<div class="card-body">

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">

<label>Select ASN</label>

<select name="asn_id" class="form-control" required>

<option value="">-- Select ASN --</option>

<?php while($row=mysqli_fetch_assoc($asn)){ ?>

<option
value="<?= $row['id']; ?>"
<?= ($selected_asn == $row['id']) ? "selected" : ""; ?>>

<?= $row['asn_number']; ?>

</option>

<?php } ?>

</select>

</div>

<div class="col-md-6 mb-3">

<label>Received Date</label>

<input
type="date"
name="received_date"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label>Received By</label>

<input
type="text"
name="received_by"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label>Vehicle Number</label>

<input
type="text"
name="vehicle_number"
class="form-control">

</div>

<div class="col-12 mb-3">

<label>Remarks</label>

<textarea
name="remarks"
class="form-control"></textarea>

</div>

</div>

<button
type="submit"
name="save"
class="btn btn-success">

Save GRN

</button>

<a href="index.php"
class="btn btn-secondary">

Back

</a>

</form>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>