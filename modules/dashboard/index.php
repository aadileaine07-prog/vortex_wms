<?php
session_start();

$projectRoot = dirname(__DIR__, 2);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$total_products_res = mysqli_query($conn, "SELECT COUNT(*) total FROM products");
$total_products = $total_products_res ? (mysqli_fetch_assoc($total_products_res)['total'] ?? 0) : 0;

$total_warehouses_res = mysqli_query($conn, "SELECT COUNT(*) total FROM warehouses");
$total_warehouses = $total_warehouses_res ? (mysqli_fetch_assoc($total_warehouses_res)['total'] ?? 0) : 0;

$total_inventory_res = mysqli_query($conn, "SELECT IFNULL(SUM(COALESCE(quantity, available_qty, 0)), 0) total FROM inventory");
$total_inventory = $total_inventory_res ? (mysqli_fetch_assoc($total_inventory_res)['total'] ?? 0) : 0;

$low_stock_res = mysqli_query($conn, "SELECT COUNT(*) total FROM inventory WHERE quantity <= 50 OR status='Low Stock'");
$low_stock = $low_stock_res ? (mysqli_fetch_assoc($low_stock_res)['total'] ?? 0) : 0;

$out_stock_res = mysqli_query($conn, "SELECT COUNT(*) total FROM inventory WHERE quantity = 0 OR status='Out of Stock'");
$out_stock = $out_stock_res ? (mysqli_fetch_assoc($out_stock_res)['total'] ?? 0) : 0;

$pending_dispatch_res = mysqli_query($conn, "SELECT COUNT(*) total FROM sales_orders WHERE status='Packed' OR status='Pending'");
$pending_dispatch = $pending_dispatch_res ? (mysqli_fetch_assoc($pending_dispatch_res)['total'] ?? 0) : 0;

$active_shipments_res = mysqli_query($conn, "SELECT COUNT(*) total FROM dispatch WHERE status='Dispatched'");
$active_shipments = $active_shipments_res ? (mysqli_fetch_assoc($active_shipments_res)['total'] ?? 0) : 0;

$warehouse_result = mysqli_query($conn, "
    SELECT COALESCE(w.warehouse_name, w.name, w.warehouse_code) AS name, 
           IFNULL(SUM(COALESCE(i.quantity, i.available_qty, 0)), 0) AS qty
    FROM warehouses w
    LEFT JOIN inventory i ON (i.location LIKE CONCAT('%', w.warehouse_code, '%') OR i.warehouse_code = w.warehouse_code)
    GROUP BY w.id
    ORDER BY name ASC
");

$warehouse_labels = [];
$warehouse_qty = [];
if ($warehouse_result) {
    while ($row = mysqli_fetch_assoc($warehouse_result)) {
        $warehouse_labels[] = $row['name'];
        $warehouse_qty[] = (int) $row['qty'];
    }
}

$status_result = mysqli_query($conn, "
    SELECT status, COUNT(*) count
    FROM sales_orders
    GROUP BY status
");

$status_labels = [];
$status_count = [];
if ($status_result) {
    while ($row = mysqli_fetch_assoc($status_result)) {
        $status_labels[] = $row['status'];
        $status_count[] = (int) $row['count'];
    }
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-chart-line text-primary me-2"></i>Executive Live Analytics</h2>
                <p class="text-muted mb-0">Real-time enterprise warehouse telemetry & tracking hub</p>
            </div>
            <div>
                <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 rounded-pill fw-bold">
                    <i class="fa-solid fa-circle-dot text-success me-1 fa-beat"></i> LIVE SYSTEM TELEMETRY ACTIVE
                </span>
            </div>
        </div>

        <!-- Key Metrics Cards -->
        <div class="row g-3 mb-4">
            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-0 bg-primary text-white rounded-3 h-100">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-semibold" style="font-size:11px;">Total Products</small>
                        <h2 class="fw-bold mb-0 mt-1"><?= $total_products; ?></h2>
                        <small style="font-size:11px;">Registered SKUs</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-0 bg-success text-white rounded-3 h-100">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-semibold" style="font-size:11px;">Warehouses</small>
                        <h2 class="fw-bold mb-0 mt-1"><?= $total_warehouses; ?></h2>
                        <small style="font-size:11px;">Active Hubs</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-0 bg-info text-white rounded-3 h-100">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-semibold" style="font-size:11px;">Total Inventory</small>
                        <h2 class="fw-bold mb-0 mt-1"><?= $total_inventory; ?></h2>
                        <small style="font-size:11px;">Units in Stock</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-0 bg-warning text-dark rounded-3 h-100">
                    <div class="card-body p-3">
                        <small class="text-dark-50 text-uppercase fw-semibold" style="font-size:11px;">Low Stock</small>
                        <h2 class="fw-bold mb-0 mt-1"><?= $low_stock; ?></h2>
                        <small style="font-size:11px;">Need Refill</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-0 bg-danger text-white rounded-3 h-100">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-semibold" style="font-size:11px;">Out of Stock</small>
                        <h2 class="fw-bold mb-0 mt-1"><?= $out_stock; ?></h2>
                        <small style="font-size:11px;">Zero Units</small>
                    </div>
                </div>
            </div>

            <div class="col-lg-2 col-md-4 col-sm-6">
                <div class="card shadow-sm border-0 bg-dark text-white rounded-3 h-100">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-semibold" style="font-size:11px;">Active Tracking</small>
                        <h2 class="fw-bold mb-0 mt-1"><?= $active_shipments; ?></h2>
                        <small style="font-size:11px;">Live Dispatches</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tables Row -->
        <div class="row g-4 mb-4">
            <!-- Recent Sales Orders -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-primary text-white py-3 fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-cart-shopping me-2"></i>Recent Sales Orders</span>
                        <a href="/vortex_wms/modules/outbound/sales_order/index.php" class="text-white text-decoration-none small">View All &rarr;</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Order No</th>
                                        <th>Customer</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $sales = mysqli_query($conn, "SELECT order_number, customer_name, status FROM sales_orders ORDER BY id DESC LIMIT 5");
                                    if ($sales && mysqli_num_rows($sales) > 0) {
                                        while($row = mysqli_fetch_assoc($sales)) {
                                            $st = strtolower($row['status']);
                                            $badgeBg = ($st == 'delivered' || $st == 'dispatched') ? 'bg-success' : (($st == 'packed' || $st == 'picking') ? 'bg-warning text-dark' : 'bg-info');
                                            echo "<tr>
                                                <td><strong>".htmlspecialchars($row['order_number'])."</strong></td>
                                                <td>".htmlspecialchars($row['customer_name'])."</td>
                                                <td><span class='badge {$badgeBg}'>".htmlspecialchars($row['status'])."</span></td>
                                            </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='3' class='text-center py-3 text-muted'>No sales orders found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Real-Time Tracked Dispatches -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-success text-white py-3 fw-bold d-flex justify-content-between align-items-center">
                        <span><i class="fa-solid fa-truck-fast me-2"></i>Real-Time Dispatch Tracking</span>
                        <a href="/vortex_wms/modules/outbound/dispatch/index.php" class="text-white text-decoration-none small">Track All &rarr;</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>Dispatch No</th>
                                        <th>Courier / Vehicle</th>
                                        <th>AWB / Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    $dispatch = mysqli_query($conn, "SELECT dispatch_number, courier_name, vehicle_number, tracking_number, status FROM dispatch ORDER BY id DESC LIMIT 5");
                                    if ($dispatch && mysqli_num_rows($dispatch) > 0) {
                                        while($row = mysqli_fetch_assoc($dispatch)) {
                                            $dnum = $row['dispatch_number'];
                                            $courier = !empty($row['courier_name']) ? $row['courier_name'] : 'Standard Logistics';
                                            $vehicle = !empty($row['vehicle_number']) ? ' <small class="text-muted">('.$row['vehicle_number'].')</small>' : '';
                                            $tracking = !empty($row['tracking_number']) ? $row['tracking_number'] : 'AWB-PENDING';
                                            echo "<tr>
                                                <td><strong><a href='/vortex_wms/modules/outbound/dispatch/index.php' class='text-decoration-none'>".htmlspecialchars($dnum)."</a></strong></td>
                                                <td>".htmlspecialchars($courier).$vehicle."</td>
                                                <td><code class='text-primary fw-bold'>".htmlspecialchars($tracking)."</code></td>
                                            </tr>";
                                        }
                                    } else {
                                        echo "<tr><td colspan='3' class='text-center py-3 text-muted'>No active tracked dispatches found.</td></tr>";
                                    }
                                    ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Visual Analytics Charts Row -->
        <div class="row g-4">
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white py-3 fw-bold border-bottom">
                        📊 Inventory Distribution by Warehouse
                    </div>
                    <div class="card-body p-3">
                        <canvas id="warehouseChart" height="110"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-3">
                    <div class="card-header bg-white py-3 fw-bold border-bottom">
                        🥧 Sales Order Status
                    </div>
                    <div class="card-body p-3">
                        <canvas id="statusChart" height="230"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<script>
new Chart(document.getElementById('warehouseChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($warehouse_labels); ?>,
        datasets: [{
            label: 'Total Units',
            data: <?= json_encode($warehouse_qty); ?>,
            backgroundColor: '#0d6efd',
            borderRadius: 6
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false } }
    }
});

new Chart(document.getElementById('statusChart'), {
    type: 'doughnut',
    data: {
        labels: <?= json_encode($status_labels); ?>,
        datasets: [{
            data: <?= json_encode($status_count); ?>,
            backgroundColor: ['#0d6efd', '#198754', '#ffc107', '#dc3545', '#6c757d']
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false
    }
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>