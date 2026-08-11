<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

$total_products = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total FROM products
"))['total'];

$total_warehouses = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total FROM warehouse
"))['total'];

$total_inventory = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT IFNULL(SUM(available_qty),0) total FROM inventory
"))['total'];

$low_stock = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM inventory
WHERE status='Low Stock'
"))['total'];

$out_stock = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM inventory
WHERE status='Out of Stock'
"))['total'];

$pending_dispatch = mysqli_fetch_assoc(mysqli_query($conn,"
SELECT COUNT(*) total
FROM sales_orders
WHERE status='Packed'
"))['total'];

$warehouse_result = mysqli_query($conn,"
SELECT w.name, IFNULL(SUM(i.available_qty),0) qty
FROM warehouse w
LEFT JOIN inventory i ON i.warehouse_id = w.id
GROUP BY w.id, w.name
ORDER BY w.name
");

$warehouse_labels = [];
$warehouse_qty = [];
while ($row = mysqli_fetch_assoc($warehouse_result)) {
    $warehouse_labels[] = $row['name'];
    $warehouse_qty[] = (int) $row['qty'];
}

$status_result = mysqli_query($conn,"
SELECT status, COUNT(*) count
FROM sales_orders
GROUP BY status
");

$status_labels = [];
$status_count = [];
while ($row = mysqli_fetch_assoc($status_result)) {
    $status_labels[] = $row['status'];
    $status_count[] = (int) $row['count'];
}

include "../../includes/header.php";

?>

<div class="content">

<div class="container-fluid">

<h2 class="mb-4">
📊 Dashboard Analytics
</h2>

<div class="row">

<div class="col-lg-3 col-md-6 mb-4">
    <div class="card shadow border-0 bg-primary text-white">
        <div class="card-body">
            <h5>Total Products</h5>
            <h2><?= $total_products; ?></h2>
            <small>Registered Products</small>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6 mb-4">
    <div class="card shadow border-0 bg-success text-white">
        <div class="card-body">
            <h5>Warehouses</h5>
            <h2><?= $total_warehouses; ?></h2>
            <small>Total Warehouses</small>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6 mb-4">
    <div class="card shadow border-0 bg-info text-white">
        <div class="card-body">
            <h5>Total Inventory</h5>
            <h2><?= $total_inventory; ?></h2>
            <small>Available Quantity</small>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6 mb-4">
    <div class="card shadow border-0 bg-warning text-dark">
        <div class="card-body">
            <h5>Low Stock</h5>
            <h2><?= $low_stock; ?></h2>
            <small>Need Refill</small>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6 mb-4">
    <div class="card shadow border-0 bg-danger text-white">
        <div class="card-body">
            <h5>Out of Stock</h5>
            <h2><?= $out_stock; ?></h2>
            <small>No Inventory</small>
        </div>
    </div>
</div>

<div class="col-lg-3 col-md-6 mb-4">
    <div class="card shadow border-0 bg-dark text-white">
        <div class="card-body">
            <h5>Pending Dispatch</h5>
            <h2><?= $pending_dispatch; ?></h2>
            <small>Ready to Ship</small>
        </div>
    </div>
</div>

<hr>

<div class="row">

    <!-- Recent Sales Orders -->
    <div class="col-lg-6 mb-4">

        <div class="card shadow">

            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">🛒 Recent Sales Orders</h5>
            </div>

            <div class="card-body">

                <table class="table table-bordered table-hover">

                    <thead>

                    <tr>
                        <th>Order No</th>
                        <th>Customer</th>
                        <th>Status</th>
                    </tr>

                    </thead>

                    <tbody>

                    <?php

                    $sales = mysqli_query($conn,"
                    SELECT order_number,customer_name,status
                    FROM sales_orders
                    ORDER BY id DESC
                    LIMIT 5
                    ");

                    while($row=mysqli_fetch_assoc($sales)){
                    ?>

                    <tr>

                        <td><?= $row['order_number']; ?></td>

                        <td><?= $row['customer_name']; ?></td>

                        <td><?= $row['status']; ?></td>

                    </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

    <!-- Recent Dispatch -->

    <div class="col-lg-6 mb-4">

        <div class="card shadow">

            <div class="card-header bg-success text-white">
                <h5 class="mb-0">🚚 Recent Dispatch</h5>
            </div>

            <div class="card-body">

                <table class="table table-bordered table-hover">

                    <thead>

                    <tr>

                        <th>Dispatch No</th>

                        <th>Courier</th>

                        <th>Date</th>

                    </tr>

                    </thead>

                    <tbody>

                    <?php

                    $dispatch = mysqli_query($conn,"
                    SELECT
                    dispatch_number,
                    courier_name,
                    dispatch_date
                    FROM dispatch
                    ORDER BY id DESC
                    LIMIT 5
                    ");

                    while($row=mysqli_fetch_assoc($dispatch)){
                    ?>

                    <tr>

                        <td><?= $row['dispatch_number']; ?></td>

                        <td><?= $row['courier_name']; ?></td>

                        <td><?= $row['dispatch_date']; ?></td>

                    </tr>

                    <?php } ?>

                    </tbody>

                </table>

            </div>

        </div>

    </div>

</div>

</div>

</div>

<div class="row mb-4">

    <div class="col-lg-8">

        <div class="card shadow">

            <div class="card-header bg-primary text-white">
                <h5 class="mb-0">📊 Inventory by Warehouse</h5>
            </div>

            <div class="card-body">

                <canvas id="warehouseChart" height="120"></canvas>

            </div>

        </div>

    </div>

    <div class="col-lg-4">

        <div class="card shadow">

            <div class="card-header bg-success text-white">
                <h5 class="mb-0">🥧 Sales Order Status</h5>
            </div>

            <div class="card-body">

                <canvas id="statusChart"></canvas>

            </div>

        </div>

    </div>

</div>
<script>

const warehouseChart = new Chart(
document.getElementById('warehouseChart'),
{
    type:'bar',

    data:{

        labels:<?= json_encode($warehouse_labels); ?>,

        datasets:[{

            label:'Inventory',

            data:<?= json_encode($warehouse_qty); ?>

        }]
    },

    options:{
        responsive:true
    }

});

const statusChart = new Chart(
document.getElementById('statusChart'),
{
    type:'pie',

    data:{

        labels:<?= json_encode($status_labels); ?>,

        datasets:[{

            data:<?= json_encode($status_count); ?>

        }]
    },

    options:{
        responsive:true
    }

});

</script>
<?php include "../../includes/footer.php"; ?>