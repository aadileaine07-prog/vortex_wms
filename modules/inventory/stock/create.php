<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") 
        ? dirname(__DIR__, 3) 
        : (file_exists(__DIR__ . "/../../../../config/database.php") ? dirname(__DIR__, 4) : dirname(__DIR__, 1)));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. DYNAMIC WAREHOUSE & SCHEMA RESOLUTION (GUJARAT HUBS SYNC)
   ========================================================================== */

$whTable = "warehouses";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $whTable = "warehouse";
}

$whNameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) === 0) {
    $whNameCol = "name";
}

$whCodeCol = "warehouse_code";
$cdChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_code'");
if (!$cdChk || mysqli_num_rows($cdChk) === 0) {
    $whCodeCol = "code";
}

// Fetch Active Warehouses List
$warehouses = [];
$whSql = "SELECT id, `{$whNameCol}` AS warehouse_name, `{$whCodeCol}` AS warehouse_code 
          FROM `{$whTable}` 
          WHERE (LOWER(COALESCE(status, 'Active')) = 'active' OR status = '1' OR status IS NULL)
          ORDER BY id ASC";
$whRes = @mysqli_query($conn, $whSql);
if ($whRes && mysqli_num_rows($whRes) > 0) {
    while ($w = mysqli_fetch_assoc($whRes)) {
        $warehouses[] = $w;
    }
}

// Fallback if empty
if (empty($warehouses)) {
    $warehouses = [
        ['id' => 1, 'warehouse_name' => 'Surat Central Logistics Park', 'warehouse_code' => 'WH-01'],
        ['id' => 2, 'warehouse_name' => 'Ahmedabad Mega Distribution Center', 'warehouse_code' => 'WH-02'],
        ['id' => 3, 'warehouse_name' => 'Vadodara FMCG & Chemical Hub', 'warehouse_code' => 'WH-03'],
        ['id' => 4, 'warehouse_name' => 'Mundra Port Logistics Terminal', 'warehouse_code' => 'WH-04'],
        ['id' => 5, 'warehouse_name' => 'Rajkot Industrial Supply Depot', 'warehouse_code' => 'WH-05']
    ];
}

// 2. Fetch Master Product Catalog
$products = [];
$prodSql = "SELECT 
                id, 
                product_name, 
                COALESCE(sku, product_code, CONCAT('PRD-', id)) AS sku_code, 
                COALESCE(category, 'General') AS category,
                COALESCE(unit_price, 0.00) AS price
            FROM products 
            WHERE status IS NULL OR LOWER(status) = 'active' OR status = '1'
            ORDER BY product_name ASC";
$prodRes = @mysqli_query($conn, $prodSql);
if ($prodRes && mysqli_num_rows($prodRes) > 0) {
    while ($p = mysqli_fetch_assoc($prodRes)) {
        $products[] = $p;
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header & Action Controls -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Create New Stock Inbound
            </h2>
            <p class="text-muted mb-0">Record physical stock intake, assign warehouse coordinate, and register batch info</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stock Intake Entry Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-dolly text-primary me-2"></i>Inventory Allocation Form
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill font-monospace">PUTAWAY ENTRY</span>
        </div>

        <div class="card-body p-4">
            <form action="save.php" method="POST" id="stockCreateForm">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_name" id="productNameHidden">
                <input type="hidden" name="product_code" id="productCodeHidden">
                <input type="hidden" name="warehouse" id="warehouseNameHidden">

                <div class="row g-4">

                    <!-- Catalog Product Selection -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Master Catalog Product *</label>
                        <select name="product_id" id="productSelect" class="form-select border-2 fw-semibold" required onchange="updateProductDetails()">
                            <option value="">-- Choose Product SKU (<?= count($products); ?> Items) --</option>
                            <?php foreach ($products as $p): ?>
                                <option value="<?= $p['id']; ?>" 
                                        data-name="<?= htmlspecialchars($p['product_name']); ?>" 
                                        data-code="<?= htmlspecialchars($p['sku_code']); ?>"
                                        data-price="<?= $p['price']; ?>">
                                    <?= htmlspecialchars($p['product_name']); ?> (<?= htmlspecialchars($p['sku_code']); ?>) &bull; [<?= htmlspecialchars($p['category']); ?>]
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Master product list se SKU choose karein</small>
                    </div>

                    <!-- Target Warehouse Facility -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Target Facility Warehouse *</label>
                        <select name="warehouse_id" id="warehouseSelect" class="form-select border-2 fw-semibold" required onchange="loadWarehouseBins(this.value)">
                            <option value="">-- Choose Warehouse Facility --</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id']; ?>" data-name="<?= htmlspecialchars($wh['warehouse_name']); ?>">
                                    <?= htmlspecialchars($wh['warehouse_name']); ?> (<?= htmlspecialchars($wh['warehouse_code'] ?? 'WH'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Target Storage Coordinate (Auto Populated) -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Target Storage Coordinate (Bin) *</label>
                        <select name="bin_location" id="binSelect" class="form-select border-2 font-monospace fw-bold text-primary" required>
                            <option value="">-- First Select Warehouse Above --</option>
                        </select>
                        <small id="binStatusHelp" class="text-muted">Empty slot coordinates auto-calculated</small>
                    </div>

                    <!-- Quantities -->
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Available Quantity (Intake) *</label>
                        <div class="input-group">
                            <input type="number" name="available_qty" id="available_qty" class="form-control border-2 fw-bold text-primary text-center fs-5" min="1" value="50" required>
                            <span class="input-group-text bg-light border-2">Units</span>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Initial Reserved Quantity</label>
                        <div class="input-group">
                            <input type="number" name="reserved_qty" class="form-control border-2 text-center" min="0" value="0" required>
                            <span class="input-group-text bg-light border-2">Units</span>
                        </div>
                    </div>

                    <!-- Batch & Expiry (FEFO Tracking) -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Batch / Lot Tracking Number</label>
                        <input type="text" name="batch_no" id="batchNoInput" class="form-control border-2 font-monospace text-uppercase" placeholder="e.g. BAT-<?= date('Y'); ?>-0001" value="BAT-<?= date('Y'); ?>-0001">
                        <small class="text-muted">Required for batch-wise audits & tracking</small>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Expiry Date (FEFO Tracking)</label>
                        <input type="date" name="expiry_date" class="form-control border-2" value="<?= date('Y-m-d', strtotime('+180 days')); ?>">
                        <small class="text-muted">Optional for non-perishable merchandise</small>
                    </div>

                </div>

                <div class="mt-4 pt-3 border-top text-end d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" id="saveBtn" class="btn btn-success px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Confirm & Save Stock Entry
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function updateProductDetails() {
    const sel = document.getElementById('productSelect');
    if (sel.selectedIndex > 0) {
        const opt = sel.options[sel.selectedIndex];
        const pId = opt.value;
        document.getElementById('productNameHidden').value = opt.getAttribute('data-name') || '';
        document.getElementById('productCodeHidden').value = opt.getAttribute('data-code') || '';
        document.getElementById('batchNoInput').value = `BAT-<?= date('Y'); ?>-${String(pId).padStart(4, '0')}`;
    }
}

function loadWarehouseBins(whId) {
    const whSel = document.getElementById('warehouseSelect');
    if (whSel.selectedIndex >= 0) {
        document.getElementById('warehouseNameHidden').value = whSel.options[whSel.selectedIndex].getAttribute('data-name') || '';
    }

    const binSelect = document.getElementById('binSelect');
    const binHelp = document.getElementById('binStatusHelp');

    if (!whId) {
        binSelect.innerHTML = '<option value="">-- First Select Warehouse Above --</option>';
        binHelp.innerHTML = 'Empty slot coordinates auto-calculated';
        return;
    }

    binHelp.innerHTML = '<span class="text-muted">🔍 Calculating vacant coordinates...</span>';

    fetch(`get_empty_bins.php?warehouse_id=${whId}`)
        .then(res => res.json())
        .then(bins => {
            binSelect.innerHTML = '';
            if (bins && bins.length > 0) {
                bins.forEach(b => {
                    const opt = document.createElement('option');
                    opt.value = b.bin_code;
                    opt.textContent = `${b.bin_code} [${b.zone} - ${b.available_space} Units Free Space]`;
                    binSelect.appendChild(opt);
                });
                binHelp.innerHTML = `<span class="text-success font-monospace">✔ ${bins.length} Storage Coordinates Available</span>`;
            } else {
                binSelect.innerHTML = '<option value="">No Active Bins Found</option>';
                binHelp.innerHTML = `<span class="text-danger">⚠️ All coordinates occupied or none created for this facility.</span>`;
            }
        })
        .catch(err => {
            console.error("Error loading bins:", err);
            binSelect.innerHTML = '<option value="">Error Loading Bins</option>';
        });
}

// Initial auto-load if first warehouse selected
document.addEventListener("DOMContentLoaded", function() {
    const whSel = document.getElementById('warehouseSelect');
    if (whSel && whSel.options.length > 1) {
        whSel.selectedIndex = 1;
        loadWarehouseBins(whSel.value);
    }
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>