<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if(!isset($_GET['id'])){
    die("Packing ID Missing");
}

$packing_id = intval($_GET['id']);

$packing = mysqli_query($conn,"
SELECT
p.*,
s.order_number,
s.customer_name
FROM packing p
INNER JOIN sales_orders s
ON p.sales_order_id=s.id
WHERE p.id='$packing_id'
");

if(mysqli_num_rows($packing)==0){
    die("Packing Record Not Found");
}

$packing = mysqli_fetch_assoc($packing);

$items = mysqli_query($conn,"
SELECT *
FROM packing_items
WHERE packing_id='$packing_id'
ORDER BY id ASC
");

$dispatch_no = "DISP-".date("YmdHis");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>Start Dispatch</h3>

</div>

<div class="card-body">

<form action="save.php" method="POST">

<input
type="hidden"
name="packing_id"
value="<?= $packing_id; ?>">

<input
type="hidden"
name="sales_order_id"
value="<?= $packing['sales_order_id']; ?>">

<input
type="hidden"
name="dispatch_number"
value="<?= $dispatch_no; ?>">

<div class="row">

<div class="col-md-4">

<label>Dispatch Number</label>

<input
type="text"
class="form-control"
value="<?= $dispatch_no; ?>"
readonly>

</div>

<div class="col-md-4">

<label>Dispatch Date</label>

<input
type="date"
name="dispatch_date"
class="form-control"
value="<?= date('Y-m-d'); ?>"
required>

</div>

<div class="col-md-4">

<label>Courier Name</label>

<input
type="text"
name="courier_name"
class="form-control"
required>

</div>

</div>

<br>

<div class="row">

<div class="col-md-6">

<label>Vehicle Number</label>

<input
type="text"
name="vehicle_number"
class="form-control">

</div>

<div class="col-md-6">

<label>Tracking Number</label>

<input
type="text"
name="tracking_number"
class="form-control">

</div>

</div>

<br>

<table class="table table-bordered">

<thead class="table-dark">

<tr>

<th>Product</th>

<th>Warehouse</th>

<th>Bin</th>

<th>Packed Qty</th>

<th>Dispatch Qty</th>

</tr>

</thead>

<tbody>
<?php while($row = mysqli_fetch_assoc($items)){ ?>

<tr>

<td>

<?= $row['product_code']; ?>

-

<?= $row['product_name']; ?>

<input
type="hidden"
name="packing_item_id[]"
value="<?= $row['id']; ?>">

</td>

<td><?= $row['warehouse']; ?></td>

<td><?= $row['bin_location']; ?></td>

<td><?= $row['packed_qty']; ?></td>

<td>

<input
type="number"
name="dispatch_qty[]"
class="form-control"
min="0"
max="<?= $row['packed_qty']; ?>"
value="<?= $row['packed_qty']; ?>"
required>

</td>

</tr>

<?php } ?>

</tbody>

</table>

<br>

<div class="d-flex justify-content-between">

<a
href="index.php"
class="btn btn-secondary">

← Back

</a>

<button
type="submit"
class="btn btn-success">

🚚 Save Dispatch

</button>

</div>

</form>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>