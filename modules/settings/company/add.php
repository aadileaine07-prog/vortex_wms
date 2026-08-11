<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if(isset($_POST['save'])){

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

    $sql = "INSERT INTO companies
    (
        company_code,
        company_name,
        gst_number,
        pan_number,
        email,
        phone,
        website,
        address,
        city,
        state,
        country,
        pincode,
        status
    )
    VALUES
    (
        '$company_code',
        '$company_name',
        '$gst_number',
        '$pan_number',
        '$email',
        '$phone',
        '$website',
        '$address',
        '$city',
        '$state',
        '$country',
        '$pincode',
        '$status'
    )";

    if(mysqli_query($conn,$sql)){
        header("Location:index.php");
        exit();
    }else{
        echo "Error : ".mysqli_error($conn);
    }

}
?>

<?php include "../../../includes/header.php"; ?>
<?php include "../../../includes/navbar.php"; ?>
<?php include "../../../includes/sidebar.php"; ?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-primary text-white">
<h3>Add Company</h3>
</div>

<div class="card-body">

<form method="POST">

<div class="row">

<div class="col-md-6 mb-3">
<label>Company Code</label>
<input type="text" name="company_code" class="form-control" required>
</div>

<div class="col-md-6 mb-3">
<label>Company Name</label>
<input type="text" name="company_name" class="form-control" required>
</div>

<div class="col-md-6 mb-3">
<label>GST Number</label>
<input type="text" name="gst_number" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>PAN Number</label>
<input type="text" name="pan_number" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Email</label>
<input type="email" name="email" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Phone</label>
<input type="text" name="phone" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Website</label>
<input type="text" name="website" class="form-control">
</div>

<div class="col-md-6 mb-3">
<label>Status</label>
<select name="status" class="form-control">
<option value="Active">Active</option>
<option value="Inactive">Inactive</option>
</select>
</div>

<div class="col-12 mb-3">
<label>Address</label>
<textarea name="address" class="form-control"></textarea>
</div>

<div class="col-md-4 mb-3">
<label>City</label>
<input type="text" name="city" class="form-control">
</div>

<div class="col-md-4 mb-3">
<label>State</label>
<input type="text" name="state" class="form-control">
</div>

<div class="col-md-4 mb-3">
<label>Country</label>
<input type="text" name="country" class="form-control" value="India">
</div>

<div class="col-md-4 mb-3">
<label>Pincode</label>
<input type="text" name="pincode" class="form-control">
</div>

</div>

<button type="submit" name="save" class="btn btn-success">
Save Company
</button>

<a href="index.php" class="btn btn-secondary">
Back
</a>

</form>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>