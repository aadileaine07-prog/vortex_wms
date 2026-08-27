<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) { 
    header("Location: /vortex_wms/login.php"); 
    exit(); 
}

require_once $projectRoot . "/config/database.php";

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

// 2. Check Expiry Column
$has_expiry = false;
$check_cols = @mysqli_query($conn, "SHOW COLUMNS FROM inventory LIKE 'expiry_date'");
if ($check_cols && mysqli_num_rows($check_cols) > 0) {
    $has_expiry = true;
}

// 3. Out of Stock Query (Qty = 0)
$out_of_stock = @mysqli_query($conn, "
    SELECT 
        i.*,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(w.{$whNameCol}, i.warehouse, 'Main Facility') AS final_warehouse,
        i.available_qty AS quantity,
        i.bin_location AS location
    FROM inventory i
    LEFT JOIN products p ON p.id = i.product_id
    LEFT JOIN `{$whTable}` w ON w.id = i.warehouse_id
    WHERE i.available_qty = 0 
    ORDER BY final_product_name ASC
");

// 4. Low Stock Query (Qty between 1 and 10)
$low_stock = @mysqli_query($conn, "
    SELECT 
        i.*,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(w.{$whNameCol}, i.warehouse, 'Main Facility') AS final_warehouse,
        i.available_qty AS quantity,
        i.bin_location AS location
    FROM inventory i
    LEFT JOIN products p ON p.id = i.product_id
    LEFT JOIN `{$whTable}` w ON w.id = i.warehouse_id
    WHERE i.available_qty > 0 AND i.available_qty <= 10 
    ORDER BY i.available_qty ASC
");

// 5. Expiry Queries
if ($has_expiry) {
    $expiring = @mysqli_query($conn, "
        SELECT 
            i.*,
            COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
            COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
            COALESCE(w.{$whNameCol}, i.warehouse, 'Main Facility') AS final_warehouse,
            i.available_qty AS quantity,
            i.bin_location AS location,
            COALESCE(i.batch_no, i.batch_number, 'BATCH-01') AS final_batch,
            DATEDIFF(i.expiry_date, CURDATE()) as days_left 
        FROM inventory i 
        LEFT JOIN products p ON p.id = i.product_id
        LEFT JOIN `{$whTable}` w ON w.id = i.warehouse_id
        WHERE i.expiry_date IS NOT NULL 
          AND i.expiry_date != '0000-00-00'
          AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
        ORDER BY i.expiry_date ASC
    ");

    $expired = @mysqli_query($conn, "
        SELECT 
            i.*,
            COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
            COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
            COALESCE(w.{$whNameCol}, i.warehouse, 'Main Facility') AS final_warehouse,
            i.available_qty AS quantity,
            i.bin_location AS location,
            COALESCE(i.batch_no, i.batch_number, 'BATCH-01') AS final_batch,
            DATEDIFF(i.expiry_date, CURDATE()) as days_left 
        FROM inventory i 
        LEFT JOIN products p ON p.id = i.product_id
        LEFT JOIN `{$whTable}` w ON w.id = i.warehouse_id
        WHERE i.expiry_date IS NOT NULL 
          AND i.expiry_date != '0000-00-00'
          AND i.expiry_date < CURDATE() 
        ORDER BY i.expiry_date ASC
    ");
} else {
    $expiring = false;
    $expired  = false;
}

// Counts
$cnt_out_of_stock = $out_of_stock ? mysqli_num_rows($out_of_stock) : 0;
$cnt_low_stock    = $low_stock ? mysqli_num_rows($low_stock) : 0;
$cnt_expiring     = $expiring ? mysqli_num_rows($expiring) : 0;
$cnt_expired      = $expired ? mysqli_num_rows($expired) : 0;

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-bell text-warning me-2"></i>Automated Inventory Alerts
            </h2>
            <p class="text-muted mb-0">Real-time alerts for replenishment thresholds, stockouts, and FEFO expiry tracking</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.location.reload()" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrows-rotate me-1"></i> Refresh Alerts
            </button>
            <a href="expiry.php" class="btn btn-outline-danger rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-hourglass-half me-1"></i> FEFO Schedule
            </a>
            <a href="/vortex_wms/modules/purchase_orders/index.php" class="btn btn-primary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Create Inbound PO
            </a>
        </div>
    </div>

    <!-- Metric KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-dark h-100 hover-card">
                <small class="text-dark fw-bold text-uppercase">⬛ Out of Stock</small>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($cnt_out_of_stock); ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                <small class="text-muted">Zero stock balance</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger h-100 hover-card">
                <small class="text-danger fw-bold text-uppercase">🔴 Low Stock (&le; 10)</small>
                <h3 class="fw-bold text-danger mb-0 mt-1"><?= number_format($cnt_low_stock); ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                <small class="text-danger fw-semibold">Reorder required</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning h-100 hover-card">
                <small class="text-warning fw-bold text-uppercase">🟠 Expiring (&le; 30 Days)</small>
                <h3 class="fw-bold text-warning mb-0 mt-1"><?= number_format($cnt_expiring); ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                <small class="text-muted">Priority pick on dispatch</small>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger h-100 hover-card">
                <small class="text-danger fw-bold text-uppercase">🛑 Expired Batches</small>
                <h3 class="fw-bold text-danger mb-0 mt-1"><?= number_format($cnt_expired); ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                <small class="text-danger fw-semibold">Immediate Quarantine</small>
            </div>
        </div>
    </div>

    <!-- Alert Tables Grid -->
    <div class="row g-4">
        
        <!-- Left Panel: Stock Depletion & Reorder Warnings -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Critical Stock Depletion
                        </h5>
                        <small class="text-muted">Items below safety reorder levels</small>
                    </div>
                    <span class="badge bg-danger rounded-pill px-3 py-1"><?= $cnt_out_of_stock + $cnt_low_stock; ?> Total Warnings</span>
                </div>
                
                <div class="card-body p-4 pt-2">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>SKU / Item</th>
                                    <th>Location</th>
                                    <th class="text-center">Qty</th>
                                    <th>Status</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Out of Stock Rows -->
                                <?php if ($out_of_stock && mysqli_num_rows($out_of_stock) > 0): ?>
                                    <?php while($row = mysqli_fetch_assoc($out_of_stock)): ?>
                                        <tr class="table-danger bg-opacity-25">
                                            <td>
                                                <strong class="d-block text-dark"><?= htmlspecialchars($row['final_product_name']); ?></strong>
                                                <code class="text-danger font-monospace"><?= htmlspecialchars($row['final_sku']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($row['location'] ?? 'L0-A1'); ?></span>
                                                <small class="d-block text-muted" style="font-size:11px;"><?= htmlspecialchars($row['final_warehouse']); ?></small>
                                            </td>
                                            <td class="text-center"><span class="badge bg-danger fs-6 px-2 py-1 rounded-pill">0</span></td>
                                            <td><span class="badge bg-danger-subtle text-danger px-2 py-1 rounded-pill">Depleted</span></td>
                                            <td class="text-end">
                                                <a href="/vortex_wms/modules/purchase_orders/index.php" class="btn btn-outline-danger btn-sm rounded-pill" title="Order Stock">
                                                    <i class="fa-solid fa-cart-plus"></i> PO
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>

                                <!-- Low Stock Rows -->
                                <?php if ($low_stock && mysqli_num_rows($low_stock) > 0): ?>
                                    <?php while($row = mysqli_fetch_assoc($low_stock)): ?>
                                        <tr>
                                            <td>
                                                <strong class="d-block text-dark"><?= htmlspecialchars($row['final_product_name']); ?></strong>
                                                <code class="text-primary font-monospace"><?= htmlspecialchars($row['final_sku']); ?></code>
                                            </td>
                                            <td>
                                                <span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($row['location'] ?? 'L0-A1'); ?></span>
                                                <small class="d-block text-muted" style="font-size:11px;"><?= htmlspecialchars($row['final_warehouse']); ?></small>
                                            </td>
                                            <td class="text-center"><span class="badge bg-warning text-dark fs-6 px-2 py-1 rounded-pill"><?= (int)$row['quantity']; ?></span></td>
                                            <td><span class="badge bg-warning-subtle text-warning px-2 py-1 rounded-pill">Low Stock</span></td>
                                            <td class="text-end">
                                                <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-outline-warning btn-sm rounded-pill" title="Edit Quantity">
                                                    <i class="fa-solid fa-pen"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>

                                <?php if ($cnt_out_of_stock == 0 && $cnt_low_stock == 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-circle-check text-success fs-2 d-block mb-2 opacity-75"></i>
                                            All stock balances are within safety thresholds!
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Panel: Batch Expiry & Quality Check Warnings -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold text-dark mb-0">
                            <i class="fa-solid fa-hourglass-half text-warning me-2"></i>Batch Expiry & FEFO Warnings
                        </h5>
                        <small class="text-muted">Perishable stock nearing or past expiration</small>
                    </div>
                    <span class="badge bg-warning text-dark rounded-pill px-3 py-1"><?= $cnt_expiring + $cnt_expired; ?> Batches</span>
                </div>

                <div class="card-body p-4 pt-2">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Item / SKU</th>
                                    <th>Batch #</th>
                                    <th>Expiry Date</th>
                                    <th>Days Left</th>
                                    <th class="text-end">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <!-- Expired Batches -->
                                <?php if ($expired && mysqli_num_rows($expired) > 0): ?>
                                    <?php while($row = mysqli_fetch_assoc($expired)): ?>
                                        <tr class="table-danger bg-opacity-25">
                                            <td>
                                                <strong class="d-block text-dark"><?= htmlspecialchars($row['final_product_name']); ?></strong>
                                                <code class="text-danger font-monospace"><?= htmlspecialchars($row['final_sku']); ?></code>
                                            </td>
                                            <td><span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace"><?= htmlspecialchars($row['final_batch']); ?></span></td>
                                            <td><?= date('d M Y', strtotime($row['expiry_date'])); ?></td>
                                            <td><strong class="text-danger"><?= abs((int)$row['days_left']); ?>d Ago</strong></td>
                                            <td class="text-end">
                                                <span class="badge bg-danger rounded-pill px-2 py-1">Quarantine</span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>

                                <!-- Expiring Soon Batches -->
                                <?php if ($expiring && mysqli_num_rows($expiring) > 0): ?>
                                    <?php while($row = mysqli_fetch_assoc($expiring)): ?>
                                        <tr>
                                            <td>
                                                <strong class="d-block text-dark"><?= htmlspecialchars($row['final_product_name']); ?></strong>
                                                <code class="text-primary font-monospace"><?= htmlspecialchars($row['final_sku']); ?></code>
                                            </td>
                                            <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($row['final_batch']); ?></span></td>
                                            <td><?= date('d M Y', strtotime($row['expiry_date'])); ?></td>
                                            <td><strong class="text-warning"><?= (int)$row['days_left']; ?> Days</strong></td>
                                            <td class="text-end">
                                                <span class="badge bg-warning text-dark rounded-pill px-2 py-1">FEFO Pick</span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>

                                <?php if ($cnt_expiring == 0 && $cnt_expired == 0): ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-shield-halved text-success fs-2 d-block mb-2 opacity-75"></i>
                                            No perishable batches nearing expiration!
                                        </td>
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

<style>
.hover-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
}
</style>

<?php include $projectRoot . "/includes/footer.php"; ?>