<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$id = $_GET['id'];

if(isset($_POST['update'])){

    $company_code = $_POST['company_code'];
    $company_name = $_POST['company_name'];
    $gst_number = $_POST['gst_number'];
    $pan_number = $_POST['pan_number'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $website = $_POST['website'];
    $address = $_POST['address'];
    $city = $_POST['city'];
    $state = $_POST['state'];
    $country = $_POST['country'];
    $pincode = $_POST['pincode'];
    $status = $_POST['status'];

    $sql = "UPDATE companies SET
        company_code='$company_code',
        company_name='$company_name',
        gst_number='$gst_number',
        pan_number='$pan_number',
        email='$email',
        phone='$phone',
        website='$website',
        address='$address',
        city='$city',
        state='$state',
        country='$country',
        pincode='$pincode',
        status='$status'
        WHERE id='$id'";

    if(mysqli_query($conn,$sql)){
        header("Location:index.php");
        exit();
    }else{
        echo "Error : ".mysqli_error($conn);
    }
}

$result = mysqli_query($conn,"SELECT * FROM companies WHERE id='$id'");
$row = mysqli_fetch_assoc($result);

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-warning">
<h3>Edit Company</h3>
</div>

<div class="card-body">

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">
<label>Company Code</label>
<input type="text" name="company_code" class="form-control" value="<?= $row['company_code']; ?>" required>
</div>

<div class="col-md-6 mb-3">
<label>Company Name</label>
<input type="text" name="company_name" class="form-control" value="<?= $row['company_name']; ?>" required>
</div>

<div class="col-md-6 mb-3">
<label>GST Number</label>
<input type="text" name="gst_number" class="form-control" value="<?= $row['gst_number']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>PAN Number</label>
<input type="text" name="pan_number" class="form-control" value="<?= $row['pan_number']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>Email</label>
<input type="email" name="email" class="form-control" value="<?= $row['email']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>Phone</label>
<input type="text" name="phone" class="form-control" value="<?= $row['phone']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>Website</label>
<input type="text" name="website" class="form-control" value="<?= $row['website']; ?>">
</div>

<div class="col-md-6 mb-3">
<label>Status</label>
<select name="status" class="form-control">
<option value="Active" <?= ($row['status']=="Active")?"selected":""; ?>>Active</option>
<option value="Inactive" <?= ($row['status']=="Inactive")?"selected":""; ?>>Inactive</option>
</select>
</div>

<div class="col-12 mb-3">
<label>Address</label>
<textarea name="address" class="form-control"><?= $row['address']; ?></textarea>
</div>

<div class="col-md-4 mb-3">
<label>City</label>
<input type="text" name="city" class="form-control" value="<?= $row['city']; ?>">
</div>

<div class="col-md-4 mb-3">
<label>State</label>
<input type="text" name="state" class="form-control" value="<?= $row['state']; ?>">
</div>

<div class="col-md-4 mb-3">
<label>Country</label>
<input type="text" name="country" class="form-control" value="<?= $row['country']; ?>">
</div>

<div class="col-md-4 mb-3">
<label>Pincode</label>
<input type="text" name="pincode" class="form-control" value="<?= $row['pincode']; ?>">
</div>

</div>

<button type="submit" name="update" class="btn btn-success">
Update Company
</button>

<a href="index.php" class="btn btn-secondary">
Cancel
</a>

</form>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>