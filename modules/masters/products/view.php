<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Multi-Level Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Inventory item ID is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

/* ==========================================================================
   1. DYNAMIC WAREHOUSE & SCHEMA RESOLUTION
   ========================================================================== */

$whTable = "warehouse";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouse'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $whTable = "warehouses";
}

$whNameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) === 0) {
    $whNameCol = "name";
}

// 1. Primary Lookup: Match by inventory.id
$query = "
    SELECT 
        i.*,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(p.category, 'General') AS product_category,
        COALESCE(p.brand, '-') AS product_brand,
        COALESCE(p.uom, 'PCS') AS uom,
        COALESCE(p.selling_price, p.unit_price, 0.00) AS unit_price,
        COALESCE(w.{$whNameCol}, i.warehouse, 'Main Warehouse') AS final_warehouse
    FROM inventory i
    LEFT JOIN products p ON (p.id = i.product_id OR p.product_code = i.product_code)
    LEFT JOIN `{$whTable}` w ON (w.id = i.warehouse_id OR w.{$whNameCol} = i.warehouse)
    WHERE i.id = '$id'
    LIMIT 1
";

$result = @mysqli_query($conn, $query);

// 2. Secondary Lookup: If $id is a Product ID
if (!$result || mysqli_num_rows($result) === 0) {
    $queryByProd = "
        SELECT 
            i.*,
            COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
            COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
            COALESCE(p.category, 'General') AS product_category,
            COALESCE(p.brand, '-') AS product_brand,
            COALESCE(p.uom, 'PCS') AS uom,
            COALESCE(p.selling_price, p.unit_price, 0.00) AS unit_price,
            COALESCE(w.{$whNameCol}, i.warehouse, 'Main Warehouse') AS final_warehouse
        FROM inventory i
        LEFT JOIN products p ON (p.id = i.product_id OR p.product_code = i.product_code)
        LEFT JOIN `{$whTable}` w ON (w.id = i.warehouse_id OR w.{$whNameCol} = i.warehouse)
        WHERE i.product_id = '$id'
        LIMIT 1
    ";
    $result = @mysqli_query($conn, $queryByProd);
}

// 3. Fallback: If product exists in Catalog but has 0 records in inventory table yet
if (!$result || mysqli_num_rows($result) === 0) {
    $pCheck = @mysqli_query($conn, "SELECT * FROM products WHERE id = '$id' LIMIT 1");
    if ($pCheck && mysqli_num_rows($pCheck) > 0) {
        $pRow = mysqli_fetch_assoc($pCheck);
        $item = [
            'id'                 => 'N/A',
            'product_id'         => $pRow['id'],
            'final_product_name' => $pRow['product_name'],
            'final_sku'          => $pRow['sku'] ?? ($pRow['product_code'] ?? 'SKU-00'),
            'product_category'   => $pRow['category'] ?? 'General',
            'product_brand'      => $pRow['brand'] ?? '-',
            'uom'                => $pRow['uom'] ?? 'PCS',
            'unit_price'         => $pRow['selling_price'] ?? ($pRow['unit_price'] ?? 0.00),
            'final_warehouse'    => 'Not Assigned',
            'bin_location'       => 'Unallocated',
            'available_qty'      => 0,
            'reserved_qty'       => 0,
            'batch_no'           => 'N/A',
            'expiry_date'        => null,
            'status'             => 'Out of Stock'
        ];
    } else {
        $_SESSION['error'] = "Inventory item or Product record #{$id} not found.";
        header("Location: index.php");
        exit();
    }
} else {
    $item = mysqli_fetch_assoc($result);
}

$availQty = (int)($item['available_qty'] ?? 0);
$resvQty  = (int)($item['reserved_qty'] ?? 0);
$totalQty = $availQty + $resvQty;
$unitVal  = (float)($item['unit_price'] ?? 0.00);
$totalValuation = $availQty * $unitVal;

/* ==========================================================================
   2. RECENT ADJUSTMENT HISTORY
   ========================================================================== */

$adjTable = "stock_adjustments";
$chkAdj = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_adjustments'");
if (!$chkAdj || mysqli_num_rows($chkAdj) === 0) {
    $adjTable = "stock_adjustment";
}

$recentLogs = [];
$invIdParam = ($item['id'] !== 'N/A') ? "inventory_id = '{$item['id']}' OR " : "";
$logSql = "SELECT * FROM `{$adjTable}` WHERE {$invIdParam} product_code = '{$item['final_sku']}' ORDER BY id DESC LIMIT 5";
$logRes = @mysqli_query($conn, $logSql);
if ($logRes) {
    while ($l = mysqli_fetch_assoc($logRes)) {
        $recentLogs[] = $l;
    }
}

// Single Unified Header Include
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Action Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 d-print-none">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Inventory Coordinate Profile
            </h2>
            <p class="text-muted mb-0">Record ID: <code class="fw-bold text-primary font-monospace fs-6">#<?= $item['id']; ?></code> &bull; Bin Allocation: <span class="badge bg-primary-subtle text-primary border font-monospace"><?= htmlspecialchars($item['bin_location'] ?? 'Unallocated'); ?></span></p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button onclick="window.print();" class="btn btn-outline-dark fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Stock Tag
            </button>
            <?php if ($item['id'] !== 'N/A'): ?>
                <a href="stock_adjustment/create.php?inventory_id=<?= $item['id']; ?>" class="btn btn-warning fw-bold text-dark rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-sliders me-1"></i> Adjust Stock
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- Main Stock Grid -->
    <div class="row g-4">

        <!-- Left Column: Item Status Card -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white text-center p-4 mb-4">
                <div class="p-3 bg-light rounded-circle d-inline-flex justify-content-center align-items-center mx-auto mb-3" style="width: 80px; height: 80px;">
                    <i class="fa-solid fa-box-open text-primary fa-2x"></i>
                </div>

                <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($item['final_product_name']); ?></h4>
                <div class="mb-3">
                    <code class="fs-6 fw-bold text-primary font-monospace"><?= htmlspecialchars($item['final_sku']); ?></code>
                    <span class="badge bg-light text-secondary border ms-1"><?= htmlspecialchars($item['product_category']); ?></span>
                </div>

                <div class="my-2">
                    <?php
                    $st = strtolower(trim($item['status'] ?? ''));
                    if ($availQty <= 0 || $st === "out of stock") {
                        echo "<span class='badge bg-danger-subtle text-danger border border-danger-subtle fs-6 px-4 py-2 rounded-pill fw-bold'><i class='fa-solid fa-circle-xmark me-1'></i> Out Of Stock</span>";
                    } elseif ($availQty <= 10 || $st === "low stock") {
                        echo "<span class='badge bg-warning-subtle text-warning border border-warning-subtle fs-6 px-4 py-2 rounded-pill fw-bold'><i class='fa-solid fa-triangle-exclamation me-1'></i> Low Stock Warning</span>";
                    } else {
                        echo "<span class='badge bg-success-subtle text-success border border-success-subtle fs-6 px-4 py-2 rounded-pill fw-bold'><i class='fa-solid fa-circle-check me-1'></i> In Stock (Optimal)</span>";
                    }
                    ?>
                </div>

                <hr class="my-4">

                <!-- Stock Metrics Box -->
                <div class="row g-2 text-start">
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 11px;">Available</small>
                            <span class="fs-5 fw-bold text-primary font-monospace"><?= number_format($availQty); ?></span>
                            <small class="text-muted d-block"><?= htmlspecialchars($item['uom']); ?></small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <small class="text-muted d-block fw-bold text-uppercase" style="font-size: 11px;">Allocated / Resv</small>
                            <span class="fs-5 fw-bold text-warning font-monospace"><?= number_format($resvQty); ?></span>
                            <small class="text-muted d-block"><?= htmlspecialchars($item['uom']); ?></small>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="p-3 bg-primary bg-opacity-10 rounded-3 border border-primary border-opacity-25 mt-1">
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-dark fw-bold text-uppercase" style="font-size: 11px;">Total Physical Balance</small>
                                <span class="fs-5 fw-bold text-dark font-monospace"><?= number_format($totalQty); ?> <?= htmlspecialchars($item['uom']); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <?php if ($item['id'] !== 'N/A'): ?>
                    <div class="d-grid gap-2 mt-4 d-print-none">
                        <a href="stock_adjustment/create.php?inventory_id=<?= $item['id']; ?>" class="btn btn-warning fw-bold text-dark rounded-pill">
                            <i class="fa-solid fa-sliders me-1"></i> Stock Adjustment
                        </a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Right Column: Coordinates & Audit Details -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-warehouse text-primary me-2"></i>Storage Coordinate & Traceability
                    </h5>
                    <span class="badge bg-light text-secondary border font-monospace">BAY MAPPING</span>
                </div>

                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <tbody>
                                <tr>
                                    <th width="35%" class="bg-light text-muted small fw-bold text-uppercase">Assigned Warehouse</th>
                                    <td>
                                        <i class="fa-solid fa-building text-primary me-2"></i>
                                        <strong class="fs-6 text-dark"><?= htmlspecialchars($item['final_warehouse']); ?></strong>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Storage Coordinate (Bin)</th>
                                    <td>
                                        <span class="badge bg-primary fs-6 px-3 py-1 rounded-pill font-monospace">
                                            <i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($item['bin_location'] ?? 'Unallocated'); ?>
                                        </span>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Batch / Lot Tracking</th>
                                    <td>
                                        <code class="font-monospace fw-bold text-dark fs-6"><?= htmlspecialchars($item['batch_no'] ?? 'NO-BATCH'); ?></code>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Unit Commercial Rate</th>
                                    <td>
                                        <span class="font-monospace fw-bold text-dark fs-6">₹<?= number_format($unitVal, 2); ?></span>
                                        <span class="text-muted small">/ <?= htmlspecialchars($item['uom']); ?></span>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Estimated Coordinate Valuation</th>
                                    <td>
                                        <h5 class="fw-bold text-success font-monospace mb-0">₹<?= number_format($totalValuation, 2); ?></h5>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Recent Adjustment Logs Card -->
            <div class="card shadow-sm border-0 rounded-4 bg-white">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                    <h6 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-timeline text-primary me-2"></i>Recent Stock Adjustments
                    </h6>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Type</th>
                                    <th class="text-center">Units</th>
                                    <th>Audit Reason</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentLogs)): ?>
                                    <?php foreach ($recentLogs as $log): ?>
                                        <?php 
                                            $adjType = $log['adjustment_type'] ?? ($log['type'] ?? 'Increase');
                                            $isInc = (strcasecmp($adjType, 'Increase') === 0);
                                        ?>
                                        <tr>
                                            <td><small class="text-muted"><?= date("d M Y", strtotime($log['adjustment_date'] ?? ($log['created_at'] ?? 'now'))); ?></small></td>
                                            <td>
                                                <span class="badge <?= $isInc ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger'; ?> px-2 py-1 rounded-pill">
                                                    <?= $isInc ? '➕ Increase' : '➖ Decrease'; ?>
                                                </span>
                                            </td>
                                            <td class="text-center font-monospace fw-bold"><?= (int)($log['quantity'] ?? 0); ?></td>
                                            <td><small class="text-muted"><?= htmlspecialchars($log['reason'] ?? 'Audit recount'); ?></small></td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="4" class="text-center py-4 text-muted small">No recent adjustment entries found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        </div>

    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>