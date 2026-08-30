<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

$projectRoot = __DIR__;
require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. LIVE COUNTERS & METRICS (SAFE SCHEMA HANDLING)
   ========================================================================== */

// Total Master Products
$totProducts = (int)(@mysqli_fetch_array(@mysqli_query($conn, "SELECT COUNT(*) FROM products"))[0] ?? 0);

// Total On-Hand Stock (from Inventory)
$totStock = (int)(@mysqli_fetch_array(@mysqli_query($conn, "SELECT SUM(available_qty) FROM inventory"))[0] ?? 0);

// Low Stock Alert (Threshold <= 10 or min_stock)
$lowStock = (int)(@mysqli_fetch_array(@mysqli_query($conn, "SELECT COUNT(*) FROM inventory WHERE available_qty <= 10"))[0] ?? 0);

// Pending Inbound Dock Shipments
$pendingInbound = (int)(@mysqli_fetch_array(@mysqli_query($conn, "SELECT COUNT(*) FROM inbound_shipments WHERE LOWER(COALESCE(putaway_status, 'Pending')) != 'completed'"))[0] ?? 0);

// Total Active Gujarat Facilities
$whTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'") && mysqli_num_rows(@mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'")) > 0 ? 'warehouses' : 'warehouse';
$totWarehouses = (int)(@mysqli_fetch_array(@mysqli_query($conn, "SELECT COUNT(*) FROM `{$whTable}` WHERE LOWER(COALESCE(status, 'Active')) = 'active' OR status = '1'"))[0] ?? 5);

/* ==========================================================================
   2. RECENT STOCK TRANSFERS & MOVEMENTS
   ========================================================================== */

$recentTransfers = [];
$chkTransfers = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_transfers'");
if ($chkTransfers && mysqli_num_rows($chkTransfers) > 0) {
    $resTransfers = @mysqli_query($conn, "
        SELECT 
            COALESCE(transfer_no, CONCAT('TRF-', id)) AS transfer_no,
            COALESCE(product_name, 'Stock Item') AS product_name,
            COALESCE(product_code, 'SKU-00') AS product_code,
            COALESCE(from_bin, 'DOCK-A') AS from_bin,
            COALESCE(to_bin, 'RACK-01') AS to_bin,
            COALESCE(quantity, 0) AS quantity,
            COALESCE(transfer_date, created_at, NOW()) AS transfer_date
        FROM stock_transfers 
        ORDER BY id DESC 
        LIMIT 5
    ");
    if ($resTransfers && mysqli_num_rows($resTransfers) > 0) {
        while ($tRow = mysqli_fetch_assoc($resTransfers)) {
            $recentTransfers[] = $tRow;
        }
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-gauge-high text-primary me-2"></i>WMS Central Dashboard
            </h2>
            <p class="text-muted mb-0">Live real-time operations overview across <?= $totWarehouses; ?> Gujarat distribution hubs</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/vortex_wms/modules/inbound/create.php" class="btn btn-primary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New GRN Intake
            </a>
            <a href="/vortex_wms/modules/inventory/transfer/create.php" class="btn btn-outline-secondary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-right-left me-1"></i> Transfer Stock
            </a>
        </div>
    </div>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary">
                <small class="text-muted fw-bold text-uppercase">MASTER CATALOG</small>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($totProducts); ?> <span class="fs-6 text-muted fw-normal">SKUs</span></h3>
                <small class="text-primary fw-semibold"><a href="/vortex_wms/modules/masters/products/index.php" class="text-decoration-none">View Products &rarr;</a></small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
                <small class="text-muted fw-bold text-uppercase">TOTAL ON-HAND STOCK</small>
                <h3 class="fw-bold text-success mb-0 mt-1"><?= number_format($totStock); ?> <span class="fs-6 text-muted fw-normal">Units</span></h3>
                <small class="text-success fw-semibold"><a href="/vortex_wms/modules/inventory/index.php" class="text-decoration-none">Live Inventory &rarr;</a></small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning">
                <small class="text-muted fw-bold text-uppercase">PENDING INBOUND DOCK</small>
                <h3 class="fw-bold text-warning mb-0 mt-1"><?= number_format($pendingInbound); ?> <span class="fs-6 text-muted fw-normal">Shipments</span></h3>
                <small class="text-warning fw-semibold"><a href="/vortex_wms/modules/inbound/index.php" class="text-decoration-none">Execute Putaway &rarr;</a></small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger">
                <small class="text-muted fw-bold text-uppercase">CRITICAL ALERTS</small>
                <h3 class="fw-bold text-danger mb-0 mt-1"><?= number_format($lowStock); ?> <span class="fs-6 text-muted fw-normal">Low Stock</span></h3>
                <small class="text-danger fw-semibold"><a href="/vortex_wms/modules/inventory/alerts.php" class="text-decoration-none">Stock Alerts &rarr;</a></small>
            </div>
        </div>
    </div>

    <!-- Recent Movements Ledger -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Recent Stock Relocations & Transfers
            </h5>
            <a href="/vortex_wms/modules/inventory/transfer/index.php" class="btn btn-sm btn-outline-secondary rounded-pill px-3">Full Movement Ledger</a>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Transfer #</th>
                            <th>Product Details</th>
                            <th>Origin Bin</th>
                            <th>Target Bin</th>
                            <th class="text-center">Units Moved</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($recentTransfers)): ?>
                            <?php foreach ($recentTransfers as $t): ?>
                                <tr>
                                    <td><code class="text-primary font-monospace fw-bold"><?= htmlspecialchars($t['transfer_no']); ?></code></td>
                                    <td>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($t['product_name']); ?></strong>
                                        <code class="text-muted font-monospace small"><?= htmlspecialchars($t['product_code']); ?></code>
                                    </td>
                                    <td><span class="badge bg-danger-subtle text-danger font-monospace"><?= htmlspecialchars($t['from_bin']); ?></span></td>
                                    <td><span class="badge bg-success-subtle text-success font-monospace"><?= htmlspecialchars($t['to_bin']); ?></span></td>
                                    <td class="text-center font-monospace fw-bold"><?= number_format((int)$t['quantity']); ?> Units</td>
                                    <td><small class="text-muted"><?= date('d M Y', strtotime($t['transfer_date'])); ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-4 text-muted">No stock movements recorded recently.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>