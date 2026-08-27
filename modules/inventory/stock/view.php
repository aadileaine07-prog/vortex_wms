<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Inventory Item ID is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// 1. Dynamic Warehouse Table Resolution
$whTable = "warehouse";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouse'");
if (!$chkTable || mysqli_num_rows($chkTable) == 0) {
    $whTable = "warehouses";
}

$whNameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) == 0) {
    $whNameCol = "name";
}

// 2. Comprehensive Inventory Query with Master Joins
$query = "
    SELECT 
        i.*,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(p.category, 'General') AS product_category,
        COALESCE(p.unit_price, 0.00) AS unit_price,
        COALESCE(w.{$whNameCol}, i.warehouse, 'Main Facility') AS final_warehouse,
        COALESCE(b.max_units, b.max_capacity, 100) AS bin_max_units,
        COALESCE(b.max_weight_kg, 500) AS bin_max_weight,
        COALESCE(i.batch_no, i.batch_number, 'N/A') AS final_batch
    FROM inventory i
    LEFT JOIN products p ON p.id = i.product_id
    LEFT JOIN `{$whTable}` w ON (w.id = i.warehouse_id OR w.{$whNameCol} = i.warehouse)
    LEFT JOIN bin_locations b ON b.bin_code = i.bin_location
    WHERE i.id = '$id'
    LIMIT 1
";

$result = @mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Inventory record #{$id} not found.";
    header("Location: index.php");
    exit();
}

$item = mysqli_fetch_assoc($result);

// Quantity & Valuation Calculations
$available_qty = (int)($item['available_qty'] ?? 0);
$reserved_qty  = (int)($item['reserved_qty'] ?? 0);
$total_qty     = $available_qty + $reserved_qty;
$bin_limit     = (int)$item['bin_max_units'];
$occupancy     = ($bin_limit > 0) ? min(100, round(($total_qty / $bin_limit) * 100, 1)) : 0;
$unit_price    = (float)$item['unit_price'];
$total_value   = $total_qty * $unit_price;

// Single Layout Include
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Stock Item Ledger Specification
            </h2>
            <p class="text-muted mb-0">Record Identifier: <code class="fw-bold text-primary font-monospace">#<?= $item['id']; ?></code> &bull; Product: <strong><?= htmlspecialchars($item['final_product_name']); ?></strong></p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button type="button" onclick="window.print();" class="btn btn-outline-dark fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Stock Slip
            </button>
            <a href="../adjustment/create.php?inventory_id=<?= $item['id']; ?>" class="btn btn-warning fw-bold text-dark rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-sliders me-1"></i> Adjust Stock
            </a>
            <a href="edit.php?id=<?= $item['id']; ?>" class="btn btn-primary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Record
            </a>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Ledger
            </a>
        </div>
    </div>

    <!-- Metric KPI Cards Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted small fw-semibold text-uppercase">Available for Picking</small>
                        <div class="fs-3 fw-bold text-primary my-1"><?= number_format($available_qty); ?> <span class="fs-6 text-muted fw-normal">Units</span></div>
                        <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Ready for Dispatch</small>
                    </div>
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4">
                        <i class="fa-solid fa-box-open fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted small fw-semibold text-uppercase">Reserved (Allocated)</small>
                        <div class="fs-3 fw-bold text-warning my-1"><?= number_format($reserved_qty); ?> <span class="fs-6 text-muted fw-normal">Units</span></div>
                        <small class="text-warning fw-semibold"><i class="fa-solid fa-lock me-1"></i>Locked in Orders</small>
                    </div>
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-4">
                        <i class="fa-solid fa-cart-flatbed fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-dark">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted small fw-semibold text-uppercase">Total Physical Balance</small>
                        <div class="fs-3 fw-bold text-dark my-1"><?= number_format($total_qty); ?> <span class="fs-6 text-muted fw-normal">Units</span></div>
                        <small class="text-muted fw-semibold">On-Hand Stock</small>
                    </div>
                    <div class="p-3 bg-dark bg-opacity-10 text-dark rounded-4">
                        <i class="fa-solid fa-cubes-stacked fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted small fw-semibold text-uppercase">Valuation Estimate</small>
                        <div class="fs-3 fw-bold text-success my-1">$<?= number_format($total_value, 2); ?></div>
                        <small class="text-muted fw-semibold">@ $<?= number_format($unit_price, 2); ?> / Unit</small>
                    </div>
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-4">
                        <i class="fa-solid fa-dollar-sign fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row g-4">
        
        <!-- Left: Bin Capacity & Stock Status -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-4 h-100">
                <h6 class="fw-bold text-dark mb-3">
                    <i class="fa-solid fa-shield-halved text-primary me-2"></i>Storage Coordinate Health
                </h6>

                <div class="text-center py-3 mb-3">
                    <?php if ($available_qty <= 0): ?>
                        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-4 d-inline-block mb-2">
                            <i class="fa-solid fa-circle-xmark fa-2x"></i>
                        </div>
                        <h5 class="fw-bold text-danger mb-0">Out of Stock</h5>
                        <small class="text-muted">Zero units available for picking</small>
                    <?php elseif ($available_qty <= 10): ?>
                        <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-4 d-inline-block mb-2">
                            <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
                        </div>
                        <h5 class="fw-bold text-warning mb-0">Low Stock Alert</h5>
                        <small class="text-muted">Reorder required soon</small>
                    <?php else: ?>
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-4 d-inline-block mb-2">
                            <i class="fa-solid fa-circle-check fa-2x"></i>
                        </div>
                        <h5 class="fw-bold text-success mb-0">Stock Level Healthy</h5>
                        <small class="text-muted">Adequate inventory available</small>
                    <?php endif; ?>
                </div>

                <!-- Bin Space Gauge -->
                <div class="p-3 bg-light rounded-4 border mb-4">
                    <div class="d-flex justify-content-between align-items-center mb-1">
                        <small class="text-muted fw-bold">Bin Utilization</small>
                        <span class="small fw-bold text-primary font-monospace"><?= $occupancy; ?>%</span>
                    </div>
                    <div class="progress rounded-pill mb-2" style="height: 8px;">
                        <div class="progress-bar <?= ($occupancy > 85) ? 'bg-danger' : (($occupancy > 60) ? 'bg-warning' : 'bg-primary'); ?>" style="width: <?= $occupancy; ?>%;"></div>
                    </div>
                    <div class="d-flex justify-content-between text-muted" style="font-size: 11px;">
                        <span>Current: <strong><?= $total_qty; ?></strong> Units</span>
                        <span>Capacity: <strong><?= $bin_limit; ?></strong> Units</span>
                    </div>
                </div>

                <div class="d-grid gap-2">
                    <a href="../bin_map.php" class="btn btn-outline-info rounded-pill fw-bold">
                        <i class="fa-solid fa-border-all me-1"></i> View Visual Bin Map
                    </a>
                </div>
            </div>
        </div>

        <!-- Right: Technical Specification Ledger -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-circle-info text-primary me-2"></i>Product & Coordinate Details
                    </h5>
                    <span class="badge bg-light text-secondary border font-monospace"><?= htmlspecialchars($item['product_category']); ?></span>
                </div>

                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <tbody>
                                <tr>
                                    <th width="30%" class="bg-light text-muted small fw-bold text-uppercase">Catalog SKU</th>
                                    <td><code class="fs-6 fw-bold text-primary font-monospace"><?= htmlspecialchars($item['final_sku']); ?></code></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Product Name</th>
                                    <td><strong class="fs-6 text-dark"><?= htmlspecialchars($item['final_product_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Storage Facility</th>
                                    <td>
                                        <i class="fa-solid fa-warehouse text-primary me-2"></i>
                                        <strong><?= htmlspecialchars($item['final_warehouse']); ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Bin Coordinate</th>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fs-6 px-3 py-1">
                                            <i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($item['bin_location'] ?? 'L0-A1-001-01-A'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Batch / Lot Tracking</th>
                                    <td><span class="badge bg-light text-dark border font-monospace fs-6"><?= htmlspecialchars($item['final_batch']); ?></span></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Shelf Life / Expiry (FEFO)</th>
                                    <td>
                                        <?php if (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00'): ?>
                                            <strong class="text-dark"><?= date('d M Y', strtotime($item['expiry_date'])); ?></strong>
                                        <?php else: ?>
                                            <span class="text-muted">Non-Perishable / Not Configured</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light p-3 rounded-bottom-4 border-0 d-flex justify-content-between align-items-center">
                    <small class="text-muted">Last ledger synchronization verified from Vortex WMS Core</small>
                    <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                        <i class="fa-solid fa-arrow-left me-1"></i> Stock List
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>