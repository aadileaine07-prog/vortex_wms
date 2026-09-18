<?php
session_start();

$projectRoot = dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";
include $projectRoot . "/includes/header.php";

// Access Control: Allow Outbound, Warehouse, Operations, Admin & Super Admin
allowRoles(['Outbound', 'Warehouse', 'Operations']);

$result = mysqli_query($conn,"
SELECT
p.id,
p.sales_order_id,
p.packing_number,
p.packing_date,
s.order_number,
s.customer_name
FROM packing p
INNER JOIN sales_orders s
ON p.sales_order_id=s.id
WHERE p.status='Completed'
AND s.status='Packed'
ORDER BY p.id DESC
");
?>

<div class="content">

<div class="container-fluid">

<div class="card shadow">

<div class="card-header bg-primary text-white">

<h3>Dispatch Orders</h3>

</div>

<div class="card-body">

<table class="table table-bordered table-hover">

<thead class="table-dark">

<tr>

<th>Packing No</th>

<th>Sales Order</th>

<th>Customer</th>

<th>Packing Date</th>

<th>Action</th>

</tr>

</thead>

<tbody>
<?php while($row = mysqli_fetch_assoc($result)){ ?>

<tr>

<td><?= htmlspecialchars($row['packing_number']); ?></td>

<td><?= htmlspecialchars($row['order_number']); ?></td>

<td><?= htmlspecialchars($row['customer_name']); ?></td>

<td><?= htmlspecialchars($row['packing_date']); ?></td>

<td>

<a
href="start.php?id=<?= $row['id']; ?>"
class="btn btn-success btn-sm">

🚚 Start Dispatch

</a>

</td>

</tr>

<?php } ?>

</tbody>

</table>

</div>

</div>

</div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>