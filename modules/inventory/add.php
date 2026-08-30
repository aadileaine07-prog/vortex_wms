<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Multi-Level Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. FETCH MASTER PRODUCTS
   ========================================================================== */
$prodSql = "
    SELECT 
        id, 
        product_name, 
        COALESCE(sku, product_code, CONCAT('PRD-', id)) AS final_code,
        COALESCE(category, 'General') AS category,
        COALESCE(uom, 'PCS') AS uom
    FROM products 
    WHERE status IS NULL OR LOWER(status) = 'active' OR status = '1'
    ORDER BY product_name ASC
";
$productsResult = mysqli_query($conn, $prodSql);

/* ==========================================================================
   2. DYNAMIC WAREHOUSE LIST RESOLUTION
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
$whQuery = @mysqli_query($conn, "SELECT DISTINCT {$whNameCol} AS wh_name FROM `{$whTable}` WHERE {$whNameCol} IS NOT NULL");
if ($whQuery && mysqli_num_rows($whQuery) > 0) {
    while ($w = mysqli_fetch_assoc($whQuery)) {
        if (!empty($w['wh_name'])) {
            $warehouses[] = $w['wh_name'];
        }
    }
}

if (empty($warehouses)) {
    $warehouses = [
        'Main Warehouse - Section A',
        'Central Distribution Center',
        'North Logistics Hub',
        'Cold Storage Facility'
    ];
}

/* ==========================================================================
   3. LIVE BIN OCCUPANCY & CAPACITY RESOLUTION
   ========================================================================== */
$binStockMap = [];
$occQuery = mysqli_query($conn, "
    SELECT warehouse, bin_location, SUM(available_qty) AS current_stock 
    FROM inventory 
    WHERE available_qty > 0 
    GROUP BY warehouse, bin_location
");
if ($occQuery) {
    while ($r = mysqli_fetch_assoc($occQuery)) {
        $key = strtoupper(trim($r['warehouse'])) . "___" . strtoupper(trim($r['bin_location']));
        $binStockMap[$key] = (int)$r['current_stock'];
    }
}

$allMasterBins = [];
$masterBinRes = @mysqli_query($conn, "SELECT bin_code, COALESCE(capacity, 150) AS max_cap FROM bin_locations WHERE status = 'Active' OR status IS NULL");

if ($masterBinRes && mysqli_num_rows($masterBinRes) > 0) {
    while ($b = mysqli_fetch_assoc($masterBinRes)) {
        $allMasterBins[strtoupper(trim($b['bin_code']))] = (int)$b['max_cap'];
    }
} else {
    // Generate Standard Hierarchical Coordinates: L0-A1-001-01-A
    $floors = ['L0', 'L1'];
    $aisles = ['A1', 'A2', 'B1', 'B2', 'C1'];
    foreach ($floors as $fl) {
        foreach ($aisles as $ais) {
            for ($rack = 1; $rack <= 3; $rack++) {
                for ($shelf = 1; $shelf <= 2; $shelf++) {
                    $code = sprintf("%s-%s-%03d-%02d-A", $fl, $ais, $rack, $shelf);
                    $allMasterBins[$code] = 150;
                }
            }
        }
    }
}

/* ==========================================================================
   4. SAVE STOCK SUBMISSION
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_stock'])) {
    $product_id    = intval($_POST['product_id'] ?? 0);
    $warehouse     = mysqli_real_escape_string($conn, trim($_POST['warehouse'] ?? ''));
    $bin_location  = mysqli_real_escape_string($conn, strtoupper(trim($_POST['bin_location'] ?? '')));
    $available_qty = max(0, intval($_POST['available_qty'] ?? 0));
    $reserved_qty  = max(0, intval($_POST['reserved_qty'] ?? 0));
    $batch_no      = mysqli_real_escape_string($conn, trim($_POST['batch_no'] ?? ''));
    $expiry_date   = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;

    $destKey = strtoupper($warehouse) . "___" . strtoupper($bin_location);
    $currentDestStock = $binStockMap[$destKey] ?? 0;
    $maxDestCap = $allMasterBins[$bin_location] ?? 150;
    $availableSpace = max(0, $maxDestCap - $currentDestStock);

    if ($product_id <= 0 || empty($warehouse) || empty($bin_location) || $available_qty <= 0) {
        $_SESSION['error'] = "Please fill in all mandatory parameters.";
    } elseif ($available_qty > $availableSpace) {
        $_SESSION['error'] = "Capacity Overflow! Bin <strong>{$bin_location}</strong> only has space for <strong>{$availableSpace} Units</strong> (Capacity: {$maxDestCap}).";
    } else {
        $pInfoRes = mysqli_query($conn, "SELECT * FROM products WHERE id = '$product_id' LIMIT 1");
        
        if ($pInfoRes && mysqli_num_rows($pInfoRes) > 0) {
            $pInfo = mysqli_fetch_assoc($pInfoRes);
            $pCode = mysqli_real_escape_string($conn, $pInfo['sku'] ?? ($pInfo['product_code'] ?? 'PRD-' . $product_id));
            $pName = mysqli_real_escape_string($conn, $pInfo['product_name']);
            $status = ($available_qty <= 0) ? 'Out of Stock' : (($available_qty <= 10) ? 'Low Stock' : 'In Stock');

            $invCols = [];
            $cRes = @mysqli_query($conn, "SHOW COLUMNS FROM inventory");
            if ($cRes) {
                while ($c = mysqli_fetch_assoc($cRes)) { 
                    $invCols[] = strtolower($c['Field']); 
                }
            }

            // Check if slot already exists
            $checkExist = mysqli_query($conn, "SELECT id, available_qty FROM inventory WHERE (product_id = '$product_id' OR product_code = '$pCode') AND warehouse = '$warehouse' AND bin_location = '$bin_location' LIMIT 1");

            if ($checkExist && mysqli_num_rows($checkExist) > 0) {
                $existRow = mysqli_fetch_assoc($checkExist);
                $newQty = $existRow['available_qty'] + $available_qty;
                $updStatus = ($newQty <= 0) ? 'Out of Stock' : (($newQty <= 10) ? 'Low Stock' : 'In Stock');
                
                mysqli_query($conn, "UPDATE inventory SET available_qty = '$newQty', status = '$updStatus' WHERE id = '{$existRow['id']}'");
                $_SESSION['success'] = "Updated bin <strong>{$bin_location}</strong>! Added <strong>{$available_qty} Units</strong>. Total on hand: <strong>{$newQty} Units</strong>.";
            } else {
                $fields = ["`product_id`", "`product_code`", "`product_name`", "`warehouse`", "`bin_location`", "`available_qty`", "`status`"];
                $values = ["'$product_id'", "'$pCode'", "'$pName'", "'$warehouse'", "'$bin_location'", "'$available_qty'", "'$status'"];

                if (in_array('batch_no', $invCols)) {
                    $fields[] = "`batch_no`";
                    $values[] = "'$batch_no'";
                }
                if (in_array('expiry_date', $invCols) && $expiry_date) {
                    $fields[] = "`expiry_date`";
                    $values[] = "'$expiry_date'";
                }
                if (in_array('reserved_qty', $invCols)) {
                    $fields[] = "`reserved_qty`";
                    $values[] = "'$reserved_qty'";
                }

                $insertSql = "INSERT INTO inventory (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
                if (mysqli_query($conn, $insertSql)) {
                    $_SESSION['success'] = "Allocated <strong>{$available_qty} Units</strong> of {$pName} into bin <strong>{$bin_location}</strong>.";
                } else {
                    $_SESSION['error'] = "Inventory insert failed: " . mysqli_error($conn);
                }
            }

            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Product record not found.";
        }
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Header Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Add Inventory Stock
            </h2>
            <p class="text-muted mb-0">Record and allocate physical warehouse stock into available storage coordinates</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Stock Add Form -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-pallet text-primary me-2"></i>Stock Coordinate Intake Form
            </h5>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill font-monospace">
                CAPACITY-VALIDATED INTAKE
            </span>
        </div>

        <div class="card-body p-4">
            <form method="POST" id="addStockForm">
                <div class="row g-4">

                    <!-- Select Product -->
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
                        <select name="warehouse" id="warehouseSelect" class="form-select border-2 fw-semibold" required onchange="renderAvailableBins()">
                            <option value="">-- Choose Target Warehouse --</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= htmlspecialchars($wh); ?>">
                                    <?= htmlspecialchars($wh); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="text-muted">Target facility for stock intake</small>
                    </div>

                    <!-- Bin Location Coordinate Dropdown (Filtered Vacant/Non-Full) -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted d-flex justify-content-between">
                            <span>Bin Location Coordinate *</span>
                            <span class="text-success fw-bold" id="spaceIndicator">Select Warehouse First</span>
                        </label>
                        <select name="bin_location" id="binSelect" class="form-select border-2 font-monospace fw-bold fs-6 text-primary" required onchange="validateBinSpace()">
                            <option value="">-- Select Target Warehouse First --</option>
                        </select>
                        <small class="text-muted">Full bins are excluded. Only vacant and non-full bins are shown.</small>
                    </div>

                    <!-- Available Quantity -->
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Intake Quantity *</label>
                        <div class="input-group">
                            <input type="number" name="available_qty" id="intakeQty" class="form-control border-2 fw-bold text-center fs-5" value="50" min="1" required oninput="validateBinSpace()">
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
                            <input type="text" name="batch_no" id="batchInput" class="form-control border-2 font-monospace text-uppercase" value="BAT-<?= date('Y'); ?>-0001">
                        </div>
                    </div>

                    <!-- Expiry Date -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Expiry Date (FEFO Tracking)</label>
                        <input type="date" name="expiry_date" class="form-control border-2" value="<?= date('Y-m-d', strtotime('+180 days')); ?>">
                    </div>

                </div>

                <!-- Form Buttons -->
                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="save_stock" id="saveStockBtn" class="btn btn-success px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Stock Entry
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
const allMasterBins = <?= json_encode($allMasterBins); ?>;
const binStockMap = <?= json_encode($binStockMap); ?>;

let selectedBinMaxSpace = 999999;

function generateBatch() {
    const sel = document.getElementById('productSelect');
    if (sel && sel.selectedIndex > 0) {
        const opt = sel.options[sel.selectedIndex];
        const pId = opt.value;
        const uom = opt.getAttribute('data-uom') || 'Units';
        
        document.getElementById('uomLabel').innerText = uom;
        document.getElementById('batchInput').value = `BAT-<?= date('Y'); ?>-${String(pId).padStart(4, '0')}`;
    }
}

function renderAvailableBins() {
    const targetWh = document.getElementById('warehouseSelect').value.trim().toUpperCase();
    const binSelect = document.getElementById('binSelect');
    binSelect.innerHTML = '<option value="">-- Choose Available/Vacant Coordinate --</option>';

    if (!targetWh) {
        document.getElementById('spaceIndicator').innerText = "Select Warehouse First";
        return;
    }

    let emptyCount = 0;
    let partialCount = 0;

    let optGroupEmpty = document.createElement('optgroup');
    optGroupEmpty.label = "🟢 100% Completely Empty Coordinates";

    let optGroupPartial = document.createElement('optgroup');
    optGroupPartial.label = "🟡 Partially Occupied (Has Free Space)";

    for (const [binCode, maxCap] of Object.entries(allMasterBins)) {
        const mapKey = targetWh + "___" + binCode.toUpperCase();
        const currentUnits = binStockMap[mapKey] || 0;
        const freeSpace = maxCap - currentUnits;

        // Skip completely full bins
        if (freeSpace <= 0) continue;

        const opt = document.createElement('option');
        opt.value = binCode;
        opt.setAttribute('data-freespace', freeSpace);

        if (currentUnits === 0) {
            opt.textContent = `${binCode} • [VACANT • Capacity: ${maxCap} Units]`;
            optGroupEmpty.appendChild(opt);
            emptyCount++;
        } else {
            opt.textContent = `${binCode} • [Free Space: ${freeSpace} Units / Total: ${maxCap}]`;
            optGroupPartial.appendChild(opt);
            partialCount++;
        }
    }

    if (emptyCount > 0) binSelect.appendChild(optGroupEmpty);
    if (partialCount > 0) binSelect.appendChild(optGroupPartial);

    document.getElementById('spaceIndicator').innerText = `${emptyCount + partialCount} Bins Available`;
    validateBinSpace();
}

function validateBinSpace() {
    const binSelect = document.getElementById('binSelect');
    const qtyInput = document.getElementById('intakeQty');
    const saveBtn = document.getElementById('saveStockBtn');
    const qty = parseInt(qtyInput.value) || 0;

    if (binSelect.selectedIndex > 0) {
        const opt = binSelect.options[binSelect.selectedIndex];
        selectedBinMaxSpace = parseInt(opt.getAttribute('data-freespace')) || 150;
        document.getElementById('spaceIndicator').innerText = `Available Space: ${selectedBinMaxSpace} Units`;

        if (qty > selectedBinMaxSpace) {
            document.getElementById('spaceIndicator').innerText = `⚠️ Overflow! Only ${selectedBinMaxSpace} units fit`;
            document.getElementById('spaceIndicator').className = "text-danger fw-bold";
            qtyInput.classList.add('is-invalid');
            saveBtn.disabled = true;
        } else {
            document.getElementById('spaceIndicator').className = "text-success fw-bold";
            qtyInput.classList.remove('is-invalid');
            saveBtn.disabled = false;
        }
    } else {
        selectedBinMaxSpace = 999999;
        qtyInput.classList.remove('is-invalid');
        saveBtn.disabled = false;
    }
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>