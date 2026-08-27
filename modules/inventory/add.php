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

/* ==========================================================================
   1. FETCH MASTER PRODUCTS (All 100 SKUs)
   ========================================================================== */
$prodSql = "
    SELECT 
        id, 
        product_name, 
        COALESCE(sku, product_code, 'SKU-00') AS final_code,
        category,
        COALESCE(uom, 'PCS') AS uom
    FROM products 
    WHERE status = 'Active' OR status = '1'
    ORDER BY product_name ASC
";
$productsResult = mysqli_query($conn, $prodSql);

/* ==========================================================================
   2. DYNAMIC WAREHOUSES & BINS FETCH
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

$warehouses = [];
$whQuery = @mysqli_query($conn, "SELECT id, {$whNameCol} AS wh_name FROM `{$whTable}` ORDER BY id ASC");
if ($whQuery && mysqli_num_rows($whQuery) > 0) {
    while ($w = mysqli_fetch_assoc($whQuery)) {
        $warehouses[] = $w['wh_name'];
    }
}

// Default fallback agar warehouse table me data na ho
if (empty($warehouses)) {
    $warehouses = [
        'Main Warehouse - Section A',
        'Central Distribution Center',
        'North Logistics Hub',
        'Cold Storage Facility'
    ];
}

/* ==========================================================================
   3. HANDLE INVENTORY STOCK ADDITION
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stock'])) {
    $product_id    = intval($_POST['product_id'] ?? 0);
    $warehouse     = mysqli_real_escape_string($conn, trim($_POST['warehouse'] ?? 'Main Warehouse - Section A'));
    $bin_location  = mysqli_real_escape_string($conn, strtoupper(trim($_POST['bin_location'] ?? 'A1-01')));
    $available_qty = max(0, intval($_POST['available_qty'] ?? 0));
    $reserved_qty  = max(0, intval($_POST['reserved_qty'] ?? 0));
    $batch_no      = mysqli_real_escape_string($conn, trim($_POST['batch_no'] ?? ''));
    $expiry_date   = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;

    if ($product_id <= 0) {
        $_SESSION['error'] = "Please select a valid product from the catalog.";
    } else {
        // Fetch Product Details
        $pInfoRes = mysqli_query($conn, "SELECT * FROM products WHERE id = '$product_id' LIMIT 1");
        if ($pInfoRes && mysqli_num_rows($pInfoRes) > 0) {
            $pInfo = mysqli_fetch_assoc($pInfoRes);
            $pCode = mysqli_real_escape_string($conn, $pInfo['sku'] ?? ($pInfo['product_code'] ?? ''));
            $pName = mysqli_real_escape_string($conn, $pInfo['product_name']);
            $status = ($available_qty <= 0) ? 'Out of Stock' : (($available_qty <= 10) ? 'Low Stock' : 'In Stock');

            // Inventory Table Columns Detection
            $invCols = [];
            $cRes = @mysqli_query($conn, "SHOW COLUMNS FROM inventory");
            if ($cRes) {
                while ($c = mysqli_fetch_assoc($cRes)) { $invCols[] = strtolower($c['Field']); }
            }

            // Check if stock already exists in same warehouse and bin
            $checkExist = mysqli_query($conn, "SELECT id, available_qty FROM inventory WHERE (product_id = '$product_id' OR product_code = '$pCode') AND warehouse = '$warehouse' AND bin_location = '$bin_location' LIMIT 1");

            if ($checkExist && mysqli_num_rows($checkExist) > 0) {
                $existRow = mysqli_fetch_assoc($checkExist);
                $newQty = $existRow['available_qty'] + $available_qty;
                $updStatus = ($newQty <= 0) ? 'Out of Stock' : (($newQty <= 10) ? 'Low Stock' : 'In Stock');
                mysqli_query($conn, "UPDATE inventory SET available_qty = '$newQty', status = '$updStatus' WHERE id = '{$existRow['id']}'");
                $_SESSION['success'] = "Updated existing slot! Added <strong>{$available_qty} Units</strong> to {$bin_location}. Total on hand: <strong>{$newQty} Units</strong>.";
            } else {
                $fields = ["`product_id`", "`product_code`", "`product_name`", "`warehouse`", "`bin_location`", "`available_qty`", "`status`"];
                $values = ["'$product_id'", "'$pCode'", "'$pName'", "'$warehouse'", "'$bin_location'", "'$available_qty'", "'$status'"];

                if (in_array('batch_no', $invCols))     { $fields[] = "`batch_no`"; $values[] = "'$batch_no'"; }
                if (in_array('expiry_date', $invCols) && $expiry_date) { $fields[] = "`expiry_date`"; $values[] = "'$expiry_date'"; }
                if (in_array('reserved_qty', $invCols)) { $fields[] = "`reserved_qty`"; $values[] = "'$reserved_qty'"; }

                $insertSql = "INSERT INTO inventory (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
                if (mysqli_query($conn, $insertSql)) {
                    $_SESSION['success'] = "Stock entry of <strong>{$available_qty} Units</strong> successfully assigned to bin <strong>{$bin_location}</strong>.";
                } else {
                    $_SESSION['error'] = "Failed to insert inventory: " . mysqli_error($conn);
                }
            }

            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Selected product was not found in catalog.";
        }
    }
}

// Single Unified Header Include
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Header Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Add Inventory Stock
            </h2>
            <p class="text-muted mb-0">Record and allocate physical warehouse stock coordinates</p>
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

    <!-- Form Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-pallet text-primary me-2"></i>Stock Coordinate Intake Form
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill font-monospace">INVENTORY INWARD</span>
        </div>

        <div class="card-body p-4">
            <form method="POST" id="addStockForm">
                <div class="row g-4">

                    <!-- Select Product (Loaded from 100 SKUs) -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Select Catalog Product *</label>
                        <select name="product_id" id="productSelect" class="form-select border-2 fw-semibold" required onchange="generateBatch()">
                            <option value="">-- Choose Product SKU (<?= ($productsResult) ? mysqli_num_rows($productsResult) : 0; ?> Available) --</option>
                            <?php if ($productsResult && mysqli_num_rows($productsResult) > 0): ?>
                                <?php while ($p = mysqli_fetch_assoc($productsResult)): ?>
                                    <option value="<?= $p['id']; ?>" data-code="<?= htmlspecialchars($p['final_code']); ?>" data-cat="<?= htmlspecialchars($p['category']); ?>" data-uom="<?= htmlspecialchars($p['uom']); ?>">
                                        <?= htmlspecialchars($p['product_name']); ?> (<?= htmlspecialchars($p['final_code']); ?>) &bull; [<?= htmlspecialchars($p['category']); ?>]
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted">Master product list se SKU select karein</small>
                    </div>

                    <!-- Select Target Warehouse -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Select Target Warehouse *</label>
                        <select name="warehouse" id="warehouseSelect" class="form-select border-2 fw-semibold" required onchange="populateBins()">
                            <option value="">-- Choose Warehouse --</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= htmlspecialchars($wh); ?>" <?= ($wh === 'Main Warehouse - Section A') ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($wh); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Bin Location Coordinate -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Bin Location Coordinate *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-location-dot text-primary"></i></span>
                            <input type="text" name="bin_location" id="binInput" class="form-control border-2 font-monospace fw-bold text-uppercase" placeholder="e.g. A1-01, B2-04" value="A1-01" required list="binSuggestions">
                            <datalist id="binSuggestions">
                                <option value="A1-01">
                                <option value="A1-02">
                                <option value="B2-01">
                                <option value="B2-04">
                                <option value="C3-01">
                                <option value="D4-10">
                                <option value="E1-05">
                                <option value="F2-12">
                            </datalist>
                        </div>
                        <small class="text-muted">Storage rack & shelf position</small>
                    </div>

                    <!-- Available Quantity -->
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Available Quantity *</label>
                        <div class="input-group">
                            <input type="number" name="available_qty" class="form-control border-2 fw-bold text-center fs-5" value="50" min="1" required>
                            <span class="input-group-text bg-light border-2" id="uomLabel">Units</span>
                        </div>
                    </div>

                    <!-- Reserved Quantity -->
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Reserved Quantity</label>
                        <div class="input-group">
                            <input type="number" name="reserved_qty" class="form-control border-2 text-center" value="0" min="0">
                            <span class="input-group-text bg-light border-2">Units</span>
                        </div>
                    </div>

                    <!-- Batch / Lot Number -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Batch / Lot Tracking Number</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-barcode text-muted"></i></span>
                            <input type="text" name="batch_no" id="batchInput" class="form-control border-2 font-monospace text-uppercase" value="BAT-<?= date('Y'); ?>-001">
                        </div>
                    </div>

                    <!-- Expiry Date -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Expiry Date (FEFO Tracking)</label>
                        <input type="date" name="expiry_date" class="form-control border-2" value="<?= date('Y-m-d', strtotime('+180 days')); ?>">
                    </div>

                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="save_stock" class="btn btn-success px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Stock Entry
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function generateBatch() {
    const sel = document.getElementById('productSelect');
    if (sel.selectedIndex > 0) {
        const opt = sel.options[sel.selectedIndex];
        const pId = opt.value;
        const uom = opt.getAttribute('data-uom') || 'Units';
        document.getElementById('uomLabel').innerText = uom;
        document.getElementById('batchInput').value = `BAT-<?= date('Y'); ?>-${String(pId).padStart(4, '0')}`;
    }
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>