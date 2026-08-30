<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = "Inventory record ID is missing.";
    header("Location: index.php");
    exit();
}

/* ==========================================================================
   ROBUST INVENTORY & PRODUCT DATA RESOLUTION
   ========================================================================== */

$query = "
    SELECT 
        i.*,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'PRD-0001') AS final_sku,
        COALESCE(p.category, 'General') AS product_category,
        COALESCE(p.selling_price, p.cost_price, 0.00) AS unit_price,
        COALESCE(p.cost_price, 0.00) AS cost_price,
        COALESCE(p.uom, 'PCS') AS uom,
        COALESCE(i.warehouse, 'Main Warehouse - Section A') AS final_warehouse,
        COALESCE(i.bin_location, 'L0-A1-001-01-A') AS final_bin
    FROM inventory i
    LEFT JOIN products p ON (p.id = i.product_id OR p.product_code = i.product_code)
    WHERE i.id = '$id'
    LIMIT 1
";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Inventory item #{$id} not found in database.";
    header("Location: index.php");
    exit();
}

$item = mysqli_fetch_assoc($result);

// Dynamic Calculations
$availQty  = (int)($item['available_qty'] ?? 0);
$resvQty   = (int)($item['reserved_qty'] ?? 0);
$totalQty  = $availQty + $resvQty;
$maxLimit  = 150; // Standard Bin Unit Capacity (L0-A1 Standard)
$occupancy = ($maxLimit > 0) ? min(100, round(($totalQty / $maxLimit) * 100, 1)) : 0;
$unitPrice = (float)($item['unit_price'] ?? 0);
$valuation = $totalQty * $unitPrice;

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Navigation Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-box text-primary me-2"></i>Inventory Item Master
            </h2>
            <p class="text-muted mb-0">Record Reference: <code class="fw-bold text-primary font-monospace">#<?= $item['id']; ?></code></p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <button type="button" onclick="window.print();" class="btn btn-outline-dark fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Spec Sheet
            </button>
            <a href="transfer/create.php?inventory_id=<?= $item['id']; ?>" class="btn btn-outline-primary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-right-left me-1"></i> Shift Bin
            </a>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>
    </div>

    <div class="row g-4 mb-4">
        
        <!-- Left Column: Product Card -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white text-center p-4 h-100">
                <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-circle d-inline-flex align-items-center justify-content-center mx-auto mb-3" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-boxes-stacked fa-2x"></i>
                </div>
                
                <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($item['final_name']); ?></h4>
                <div class="mb-3">
                    <code class="fs-6 fw-bold text-primary font-monospace"><?= htmlspecialchars($item['final_sku']); ?></code>
                    <span class="badge bg-light text-secondary border ms-1 font-monospace"><?= htmlspecialchars($item['product_category']); ?></span>
                </div>

                <!-- Status Badge -->
                <div class="mb-4">
                    <?php if ($availQty <= 0): ?>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-6 px-3 py-2 rounded-pill">
                            <i class="fa-solid fa-circle-xmark me-1"></i> Out of Stock
                        </span>
                    <?php elseif ($availQty <= 10): ?>
                        <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-6 px-3 py-2 rounded-pill">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Low Stock
                        </span>
                    <?php else: ?>
                        <span class="badge bg-success-subtle text-success border border-success-subtle fs-6 px-3 py-2 rounded-pill">
                            <i class="fa-solid fa-circle-check me-1"></i> Healthy / In Stock
                        </span>
                    <?php endif; ?>
                </div>

                <!-- Bin Space Gauge -->
                <div class="p-3 bg-light rounded-4 border text-start mb-3">
                    <div class="d-flex justify-content-between mb-1">
                        <small class="text-muted fw-bold">Bin Space Occupancy</small>
                        <span class="small fw-bold text-primary"><?= $occupancy; ?>%</span>
                    </div>
                    <div class="progress rounded-pill" style="height: 8px;">
                        <div class="progress-bar <?= ($occupancy > 85) ? 'bg-danger' : (($occupancy > 60) ? 'bg-warning' : 'bg-primary'); ?>" style="width: <?= $occupancy; ?>%;"></div>
                    </div>
                    <small class="text-muted d-block mt-2 font-monospace"><?= $totalQty; ?> of <?= $maxLimit; ?> Max Allowed Units</small>
                </div>

                <div class="d-grid gap-2 mt-auto">
                    <a href="transfer/create.php?inventory_id=<?= $item['id']; ?>" class="btn btn-primary rounded-pill fw-bold shadow-sm">
                        <i class="fa-solid fa-dolly me-1"></i> Relocate This Item
                    </a>
                </div>
            </div>
        </div>

        <!-- Right Column: Storage Coordinates & Ledger -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-4">
                        <i class="fa-solid fa-warehouse text-primary me-2"></i>Storage Coordinate & Ledger Details
                    </h5>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 11px;">Warehouse Facility</small>
                                <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($item['final_warehouse']); ?></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 11px;">Bin Location Coordinate</small>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fs-6 px-3 py-1 mt-1">
                                    <?= htmlspecialchars($item['final_bin']); ?>
                                </span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border border-start border-4 border-primary">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 11px;">Available For Picking</small>
                                <span class="fw-bold text-primary fs-4"><?= number_format($availQty); ?></span> <span class="text-muted"><?= htmlspecialchars($item['uom']); ?></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border border-start border-4 border-warning">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 11px;">Allocated / Reserved</small>
                                <span class="fw-bold text-warning fs-4"><?= number_format($resvQty); ?></span> <span class="text-muted"><?= htmlspecialchars($item['uom']); ?></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 11px;">Batch / Lot Number</small>
                                <code class="fw-bold text-dark fs-6"><?= htmlspecialchars($item['batch_no'] ?? 'BAT-AUTO-01'); ?></code>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border">
                                <small class="text-muted d-block text-uppercase fw-semibold" style="font-size: 11px;">Shelf Life / Expiry Date</small>
                                <?php if (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00'): ?>
                                    <span class="fw-bold text-dark fs-6"><?= date('d M Y', strtotime($item['expiry_date'])); ?></span>
                                <?php else: ?>
                                    <span class="text-muted">Standard Shelf Life (Non-Perishable)</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Valuation Summary -->
                    <div class="mt-4 p-3 bg-light rounded-4 border d-flex justify-content-between align-items-center flex-wrap gap-2">
                        <div>
                            <small class="text-muted d-block fw-semibold">Estimated Stock Valuation</small>
                            <span class="fw-bold fs-5 text-success">₹<?= number_format($valuation, 2); ?></span>
                            <small class="text-muted">(@ ₹<?= number_format($unitPrice, 2); ?> / <?= htmlspecialchars($item['uom']); ?>)</small>
                        </div>
                        <div>
                            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 fw-bold">
                                Close
                            </a>
                        </div>
                    </div>

                </div>
            </div>
        </div>

    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>