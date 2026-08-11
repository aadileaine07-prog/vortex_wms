<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['grn_id'])) {
    die("GRN ID Missing");
}

$grn_id = intval($_GET['grn_id']);

/* GRN Details */

$grn = mysqli_query($conn,"
SELECT g.*,a.asn_number
FROM grn g
LEFT JOIN asn a ON a.id=g.asn_id
WHERE g.id='$grn_id'
");

$grnData = mysqli_fetch_assoc($grn);

$asn_id = $grnData['asn_id'];

/* First Time Auto Load */

$check = mysqli_query($conn,"
SELECT COUNT(*) total
FROM grn_items
WHERE grn_id='$grn_id'
");

$total = mysqli_fetch_assoc($check);

if($total['total']==0){

    $items=mysqli_query($conn,"
    SELECT *
    FROM asn_items
    WHERE asn_id='$asn_id'
    ");

    while($row=mysqli_fetch_assoc($items)){

       while($row=mysqli_fetch_assoc($items)){

    $sql = "
    INSERT INTO grn_items
    (
        grn_id,
        asn_item_id,
        ordered_qty,
        received_qty,
        damaged_qty,
        accepted_qty,
        remarks
    )
    VALUES
    (
        '$grn_id',
        '".$row['id']."',
        '".$row['quantity']."',
        0,
        0,
        0,
        ''
    )";

    if(!mysqli_query($conn, $sql)){
        die(mysqli_error($conn));
    }

}

    }

}

$result=mysqli_query($conn,"
SELECT
g.*,
a.product_code,
a.product_name,
a.uom
FROM grn_items g

LEFT JOIN asn_items a
ON a.id=g.asn_item_id

WHERE g.grn_id='$grn_id'
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-success text-white">

<h3>

GRN Items

</h3>

</div>

<div class="card-body">

<h5>

GRN :

<?= $grnData['grn_number']; ?>

</h5>

<h6>

ASN :

<?= $grnData['asn_number']; ?>

</h6>

<hr>

<form action="save_items.php" method="POST">

<input
type="hidden"
name="grn_id"
value="<?= $grn_id; ?>">

<table class="table table-bordered">

<thead class="table-dark">

<tr>

<th>Code</th>

<th>Product</th>

<th>Ordered</th>

<th>Received</th>

<th>Damaged</th>

<th>Accepted</th>

<th>Remarks</th>

</tr>

</thead>

<tbody>

<?php while($row=mysqli_fetch_assoc($result)){ ?>

<tr>

<td>

<?= $row['product_code']; ?>

</td>

<td>

<?= $row['product_name']; ?>

</td>

<td>

<?= $row['ordered_qty']; ?>

</td>

<td>

<input
type="number"
name="received_qty[<?= $row['id']; ?>]"
value="<?= $row['received_qty']; ?>"
class="form-control">

</td>

<td>

<input
type="number"
name="damaged_qty[<?= $row['id']; ?>]"
value="<?= $row['damaged_qty']; ?>"
class="form-control">

</td>

<td>

<?= $row['accepted_qty']; ?>

</td>

<td>

<input
type="text"
name="remarks[<?= $row['id']; ?>]"
value="<?= $row['remarks']; ?>"
class="form-control">

</td>

</tr>

<?php } ?>

</tbody>

</table>

<button
class="btn btn-success">

Save Items

</button>

</form>

</div>

</div>

</div>

</div>

<?php include "../../../includes/footer.php"; ?>