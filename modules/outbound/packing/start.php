```php
<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if(!isset($_GET['id'])){
    die("Picking ID Missing");
}

$picking_id = intval($_GET['id']);

$picking = mysqli_query($conn,"
SELECT
p.*,
s.order_number,
s.customer_name
FROM picking p
INNER JOIN sales_orders s
ON p.sales_order_id=s.id
WHERE p.id='$picking_id'
");

if(mysqli_num_rows($picking)==0){
    die("Picking Record Not Found");
}

$picking = mysqli_fetch_assoc($picking);

$items = mysqli_query($conn,"
SELECT *
FROM picking_items
WHERE picking_id='$picking_id'
ORDER BY id ASC
");

$packing_no = "PACK-".date("YmdHis");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>Start Packing</h3>

</div>

<div class="card-body">

<form action="save.php" method="POST">

<input
type="hidden"
name="picking_id"
value="<?= $picking_id; ?>">

<input
type="hidden"
name="sales_order_id"
value="<?= $picking['sales_order_id']; ?>">

<input
type="hidden"
name="packing_number"
value="<?= $packing_no; ?>">

<div class="row">

<div class="col-md-4">

<label>Packing Number</label>

<input
type="text"
class="form-control"
value="<?= $packing_no; ?>"
readonly>

</div>

<div class="col-md-4">

<label>Packing Date</label>

<input
type="date"
name="packing_date"
class="form-control"
value="<?= date('Y-m-d'); ?>"
required>

</div>

<div class="col-md-4">

<label>Packer Name</label>

<input
type="text"
name="packer_name"
class="form-control"
required>

</div>

</div>

<br>

<table class="table table-bordered">

<thead class="table-dark">

<tr>

<th>Product</th>

<th>Warehouse</th>

<th>Bin</th>

<th>Picked Qty</th>

<th>Packed Qty</th>

</tr>

</thead>

<tbody>
```
```php
<?php while($row = mysqli_fetch_assoc($items)){ ?>

<tr>

<td>

<?= $row['product_code']; ?>

-

<?= $row['product_name']; ?>

<input
type="hidden"
name="picking_item_id[]"
value="<?= $row['id']; ?>">

</td>

<td><?= $row['warehouse']; ?></td>

<td><?= $row['bin_location']; ?></td>

<td><?= $row['picked_qty']; ?></td>

<td>

<input
type="number"
name="packed_qty[]"
class="form-control"
min="0"
max="<?= $row['picked_qty']; ?>"
value="<?= $row['picked_qty']; ?>"
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

Save Packing

</button>

</div>

</form>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>
```
