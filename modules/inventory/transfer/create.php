<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$products = mysqli_query($conn,"
SELECT *
FROM inventory
WHERE available_qty > 0
ORDER BY product_name ASC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>Create Stock Transfer</h3>

</div>

<div class="card-body">

<form
action="save.php"
method="POST">

<div class="row">

<div class="col-md-6 mb-3">

<label>

Product

</label>

<select
name="inventory_id"
id="inventory_id"
class="form-control"
required>

<option value="">

Select Product

</option>

<?php

while($row=mysqli_fetch_assoc($products)){

?>

<option

value="<?= $row['id']; ?>"

data-code="<?= $row['product_code']; ?>"

data-name="<?= $row['product_name']; ?>"

data-wh="<?= $row['warehouse']; ?>"

data-bin="<?= $row['bin_location']; ?>"

data-qty="<?= $row['available_qty']; ?>"

>

<?= $row['product_code']; ?>

-

<?= $row['product_name']; ?>

(Stock :

<?= $row['available_qty']; ?>)

</option>

<?php } ?>

</select>

</div>

<div class="col-md-6 mb-3">

<label>

Transfer Date

</label>

<input

type="date"

name="transfer_date"

class="form-control"

value="<?= date('Y-m-d'); ?>"

required>

</div>

<div class="col-md-4 mb-3">

<label>

From Warehouse

</label>

<input
type="text"
name="from_warehouse"
id="from_warehouse"
class="form-control"
readonly>

</div>

<div class="col-md-4 mb-3">

<label>

From Bin

</label>

<input
type="text"
name="from_bin"
id="from_bin"
class="form-control"
readonly>

</div>

<div class="col-md-4 mb-3">

<label>

Available Qty

</label>

<input
type="number"
id="available_qty"
class="form-control"
readonly>

</div>

<div class="col-md-6 mb-3">

<label>

To Warehouse

</label>

<input
type="text"
name="to_warehouse"
class="form-control"
required>

</div>

<div class="col-md-6 mb-3">

<label>

To Bin

</label>

<input
type="text"
name="to_bin"
class="form-control"
required>

</div>
<script>

document.getElementById("inventory_id").addEventListener("change", function(){

    let option = this.options[this.selectedIndex];

    document.getElementById("from_warehouse").value =
        option.getAttribute("data-wh") || "";

    document.getElementById("from_bin").value =
        option.getAttribute("data-bin") || "";

    document.getElementById("available_qty").value =
        option.getAttribute("data-qty") || "";

});

</script>

<div class="col-md-6 mb-3">

<label>

Transfer Quantity

</label>

<input
type="number"
name="quantity"
id="quantity"
class="form-control"
min="1"
required>

</div>

<div class="col-md-6 mb-3">

<label>

Remarks

</label>

<input
type="text"
name="remarks"
class="form-control"
placeholder="Optional">

</div>

</div>

<hr>

<div class="d-flex justify-content-between">

<a
href="index.php"
class="btn btn-secondary">

← Back

</a>

<button
type="submit"
class="btn btn-success">

Save Transfer

</button>

</div>

</form>

</div>

</div>

</div>

</div>

<script>

document.getElementById("quantity").addEventListener("input", function(){

    let available = parseInt(document.getElementById("available_qty").value) || 0;

    let qty = parseInt(this.value) || 0;

    if(qty > available){

        alert("Transfer quantity cannot be greater than available stock.");

        this.value = "";

    }

});

</script>

<?php include "../../../includes/footer.php"; ?>