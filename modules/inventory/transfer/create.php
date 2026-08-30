<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Multi-level Root Detection
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$preselected_inv_id = intval($_GET['inventory_id'] ?? 0);

/* ==========================================================================
   1. DYNAMIC WAREHOUSES RESOLUTION (GUJARAT LOGISTICS HUBS)
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

$warehouses = [];
$whQuery = @mysqli_query($conn, "
    SELECT 
        id, 
        COALESCE(warehouse_code, CONCAT('WH-0', id)) AS wh_code, 
        {$whNameCol} AS wh_name, 
        COALESCE(city, 'Surat') AS city 
    FROM `{$whTable}` 
    WHERE status = 'Active' OR status = '1' OR status IS NULL 
    ORDER BY id ASC
");

if ($whQuery && mysqli_num_rows($whQuery) > 0) {
    while ($w = mysqli_fetch_assoc($whQuery)) {
        $warehouses[] = $w['wh_name'];
    }
}

if (empty($warehouses)) {
    $warehouses = [
        'Surat Central Logistics Park',
        'Ahmedabad Mega Distribution Center',
        'Vadodara FMCG & Chemical Hub',
        'Mundra Port Logistics Terminal',
        'Rajkot Industrial Supply Depot'
    ];
}

/* ==========================================================================
   2. LIVE BIN CAPACITY & OCCUPANCY MAP
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

// Master Bin Coordinates with Standard Capacity
$allMasterBins = [];
$masterBinRes = @mysqli_query($conn, "SELECT bin_code, COALESCE(capacity, max_capacity, 150) AS max_cap FROM bin_locations WHERE status = 'Active' OR status IS NULL");

if ($masterBinRes && mysqli_num_rows($masterBinRes) > 0) {
    while ($b = mysqli_fetch_assoc($masterBinRes)) {
        $allMasterBins[strtoupper(trim($b['bin_code']))] = (int)$b['max_cap'];
    }
} else {
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
   3. FETCH SOURCE ITEMS (STRICTLY AVAILABLE_QTY > 0 ONLY)
   ========================================================================== */

$invQuery = "
    SELECT 
        i.id AS inv_id,
        i.product_id,
        COALESCE(p.product_name, i.product_name, 'Catalog Product') AS final_product_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'PRD-1001') AS final_sku,
        COALESCE(w.{$whNameCol}, i.warehouse, 'Surat Central Logistics Park') AS final_warehouse,
        COALESCE(i.bin_location, 'DOCK-INWARD') AS bin_location,
        COALESCE(i.available_qty, 0) AS available_qty,
        COALESCE(p.uom, 'Units') AS uom
    FROM inventory i
    LEFT JOIN products p ON (p.id = i.product_id OR p.product_code = i.product_code)
    LEFT JOIN `{$whTable}` w ON (w.id = i.warehouse_id OR w.{$whNameCol} = i.warehouse)
    WHERE i.available_qty > 0
    ORDER BY final_product_name ASC
";
$stockItems = mysqli_query($conn, $invQuery);

/* ==========================================================================
   4. HANDLE TRANSFER EXECUTION & SOURCE ENTRY REMOVAL
   ========================================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_transfer'])) {

    $product_id    = intval($_POST['source_product_id'] ?? 0);
    $inv_id        = intval($_POST['source_inv_id'] ?? 0);
    $from_wh       = mysqli_real_escape_string($conn, trim($_POST['from_warehouse'] ?? ''));
    $from_bin      = mysqli_real_escape_string($conn, strtoupper(trim($_POST['from_bin'] ?? '')));
    $to_wh         = mysqli_real_escape_string($conn, trim($_POST['to_warehouse'] ?? ''));
    $to_bin        = mysqli_real_escape_string($conn, strtoupper(trim($_POST['to_bin'] ?? '')));
    $qty           = intval($_POST['quantity'] ?? 0);
    $notes         = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? 'Internal Relocation'));
    $transfer_date = !empty($_POST['transfer_date']) ? $_POST['transfer_date'] : date('Y-m-d');
    $user_id       = $_SESSION['employee_id'];

    $destKey = strtoupper($to_wh) . "___" . strtoupper($to_bin);
    $currentDestStock = $binStockMap[$destKey] ?? 0;
    $maxDestCap = $allMasterBins[$to_bin] ?? 150;
    $availableSpace = max(0, $maxDestCap - $currentDestStock);

    if ($product_id <= 0 || $qty <= 0 || empty($to_wh) || empty($to_bin)) {
        $_SESSION['error'] = "Please complete all required parameters.";
    } elseif ($from_wh === $to_wh && $from_bin === $to_bin) {
        $_SESSION['error'] = "Origin and destination coordinates cannot be identical.";
    } elseif ($qty > $availableSpace) {
        $_SESSION['error'] = "Capacity Exceeded! Bin <strong>{$to_bin}</strong> only has space for <strong>{$availableSpace} Units</strong> (Capacity: {$maxDestCap}).";
    } else {

        $pRes = mysqli_query($conn, "SELECT id, product_code, product_name FROM products WHERE id = '$product_id' LIMIT 1");
        $pData = ($pRes && mysqli_num_rows($pRes) > 0) ? mysqli_fetch_assoc($pRes) : [];
        $pCode = mysqli_real_escape_string($conn, $pData['product_code'] ?? 'PRD-' . $product_id);
        $pName = mysqli_real_escape_string($conn, $pData['product_name'] ?? 'Inventory Item');

        // Resolve Destination Warehouse ID
        $destWhId = 1;
        $destWhRes = mysqli_query($conn, "SELECT id FROM `{$whTable}` WHERE {$whNameCol} = '$to_wh' LIMIT 1");
        if ($destWhRes && $dwRow = mysqli_fetch_assoc($destWhRes)) {
            $destWhId = (int)$dwRow['id'];
        }

        mysqli_begin_transaction($conn);

        try {
            // 1. Fetch Source Record
            $srcCheck = mysqli_query($conn, "SELECT * FROM inventory WHERE id = '$inv_id' AND available_qty >= '$qty' LIMIT 1");

            if (!$srcCheck || mysqli_num_rows($srcCheck) === 0) {
                // Fallback check by composite keys
                $srcCheck = mysqli_query($conn, "SELECT * FROM inventory WHERE (product_id = '$product_id' OR product_code = '$pCode') AND (warehouse = '$from_wh' OR warehouse_id = '$destWhId') AND bin_location = '$from_bin' AND available_qty >= '$qty' LIMIT 1");
                if (!$srcCheck || mysqli_num_rows($srcCheck) === 0) {
                    throw new Exception("Source stock insufficient or location not found.");
                }
            }

            $srcRow = mysqli_fetch_assoc($srcCheck);
            $newSrcQty = (int)$srcRow['available_qty'] - $qty;

            // Agar pura stock move ho gaya toh record delete/clean karein
            if ($newSrcQty <= 0) {
                mysqli_query($conn, "DELETE FROM inventory WHERE id = '{$srcRow['id']}'");
            } else {
                $srcStatus = ($newSrcQty <= 10) ? 'Low Stock' : 'In Stock';
                mysqli_query($conn, "UPDATE inventory SET available_qty = '$newSrcQty', status = '$srcStatus' WHERE id = '{$srcRow['id']}'");
            }

            // 2. Add into Destination Coordinate
            $destCheck = mysqli_query($conn, "
                SELECT id, available_qty 
                FROM inventory 
                WHERE (product_id = '$product_id' OR product_code = '$pCode') 
                  AND (warehouse = '$to_wh' OR warehouse_id = '$destWhId') 
                  AND bin_location = '$to_bin' 
                LIMIT 1
            ");

            if ($destCheck && mysqli_num_rows($destCheck) > 0) {
                $destRow = mysqli_fetch_assoc($destCheck);
                $newDestQty = (int)$destRow['available_qty'] + $qty;
                mysqli_query($conn, "UPDATE inventory SET available_qty = '$newDestQty', warehouse_id = '$destWhId', warehouse = '$to_wh', status = 'In Stock' WHERE id = '{$destRow['id']}'");
            } else {
                $batchNo = !empty($srcRow['batch_no']) ? $srcRow['batch_no'] : ('BAT-' . date('Ymd'));
                $expVal  = !empty($srcRow['expiry_date']) ? "'{$srcRow['expiry_date']}'" : "NULL";

                mysqli_query($conn, "
                    INSERT INTO inventory (product_id, product_code, product_name, warehouse_id, warehouse, bin_location, batch_no, expiry_date, available_qty, reserved_qty, status)
                    VALUES ('$product_id', '$pCode', '$pName', '$destWhId', '$to_wh', '$to_bin', '$batchNo', $expVal, '$qty', 0, 'In Stock')
                ");
            }

            // 3. Log Audit Movement Record
            $chkMovTable = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_movements'");
            if ($chkMovTable && mysqli_num_rows($chkMovTable) > 0) {
                $empId = intval($_SESSION['employee_id'] ?? 1);
                @mysqli_query($conn, "
                    INSERT INTO stock_movements (movement_type, reference_no, product_id, product_code, warehouse, bin_location, quantity, created_by)
                    VALUES ('TRANSFER', 'TRF-" . date('Ymd') . "-" . rand(100, 999) . "', '$product_id', '$pCode', '$to_wh', '$to_bin', '$qty', '$empId')
                ");
            }

            mysqli_commit($conn);
            $_SESSION['success'] = "Relocated <strong>{$qty} Units</strong> of {$pName} from [{$from_bin}] to <strong>[{$to_bin}]</strong> ({$to_wh}).";
            header("Location: ../index.php");
            exit();

        } catch (\Throwable $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Transfer failed: " . $e->getMessage();
        }
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-right-left text-primary me-2"></i>Stock Relocation & Bin Transfer
            </h2>
            <p class="text-muted mb-0">Dynamic capacity-validated bin allocation (<code class="fw-bold text-primary font-monospace">L0-A1-001-01-A</code>)</p>
        </div>
        <a href="../index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-route text-primary me-2"></i>Stock Relocation Movement
            </h5>
            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill font-monospace">
                ACTIVE INVENTORY LINKED
            </span>
        </div>

        <div class="card-body p-4">
            <form method="POST" id="transferForm">
                
                <input type="hidden" name="source_inv_id" id="sourceInvId" value="">

                <div class="row g-4">

                    <!-- Select Source Stock Item -->
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">Select Live Source Inventory Item *</label>
                        <select name="source_product_id" id="sourceItemSelect" class="form-select border-2 fw-semibold" required onchange="updateSourceDetails()">
                            <option value="">-- Choose In-Stock Inventory Record --</option>
                            <?php if ($stockItems && mysqli_num_rows($stockItems) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($stockItems)): ?>
                                    <option 
                                        value="<?= $row['product_id']; ?>"
                                        data-invid="<?= $row['inv_id']; ?>"
                                        data-wh="<?= htmlspecialchars($row['final_warehouse']); ?>"
                                        data-bin="<?= htmlspecialchars($row['bin_location']); ?>"
                                        data-qty="<?= (int)$row['available_qty']; ?>"
                                        data-uom="<?= htmlspecialchars($row['uom']); ?>"
                                        <?= ($row['inv_id'] == $preselected_inv_id) ? 'selected' : ''; ?>
                                    >
                                        <?= htmlspecialchars($row['final_product_name']); ?> (<?= htmlspecialchars($row['final_sku']); ?>) &bull; [📍 Bin: <?= htmlspecialchars($row['bin_location']); ?>] &bull; Avail: <?= $row['available_qty']; ?> <?= htmlspecialchars($row['uom']); ?> (<?= htmlspecialchars($row['final_warehouse']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Movement Date -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Relocation Date *</label>
                        <input type="date" name="transfer_date" class="form-control border-2 fw-semibold" value="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <!-- Source Warehouse -->
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Origin Warehouse</small>
                            <input type="text" name="from_warehouse" id="sourceWarehouse" class="form-control-plaintext fw-bold text-dark py-0" readonly placeholder="Auto-populated">
                        </div>
                    </div>

                    <!-- Source Coordinate -->
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Origin Bin Location</small>
                            <input type="text" name="from_bin" id="sourceBin" class="form-control-plaintext font-monospace fw-bold text-primary fs-6 py-0" readonly placeholder="Auto-populated">
                        </div>
                    </div>

                    <!-- Current Available Units -->
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border border-start border-4 border-primary">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Available In Source Bin</small>
                            <div class="fs-5 fw-bold text-primary font-monospace" id="currentStockDisplay">0 Units</div>
                        </div>
                    </div>

                    <!-- Destination Warehouse -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Destination Warehouse *</label>
                        <select name="to_warehouse" id="destWarehouse" class="form-select border-2 fw-semibold" required onchange="renderAvailableBins()">
                            <option value="">-- Choose Target Warehouse --</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= htmlspecialchars($wh); ?>"><?= htmlspecialchars($wh); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Filtered Destination Coordinate Dropdown -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted d-flex justify-content-between">
                            <span>Destination Coordinate (Bin) *</span>
                            <span class="text-success fw-bold" id="spaceIndicator">Select Warehouse First</span>
                        </label>
                        <select name="to_bin" id="destBinSelect" class="form-select border-2 font-monospace fw-bold fs-6 text-primary" required onchange="validateBinSpace()">
                            <option value="">-- Select Destination Warehouse First --</option>
                        </select>
                        <small class="text-muted">Full bins are excluded. Only vacant and non-full bins are available.</small>
                    </div>

                    <!-- Transfer Units -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Transfer Quantity *</label>
                        <div class="input-group">
                            <input type="number" name="quantity" id="transferQty" class="form-control border-2 font-monospace fw-bold text-center fs-5" min="1" placeholder="0" required oninput="calcResidual()">
                            <span class="input-group-text bg-light border-2" id="uomTag">Units</span>
                        </div>
                    </div>

                    <!-- Transfer Reason -->
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">Transfer Reason / Movement Log</label>
                        <input type="text" name="notes" class="form-control border-2" placeholder="e.g. Rack consolidation, Fast-moving item shift" value="Internal Relocation">
                    </div>

                    <!-- Residual Stock Gauge -->
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-4 border d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <strong class="text-dark d-block">Source Bin Residual Balance:</strong>
                                <small class="text-muted">Emptying origin bin will purge its record from the active inventory list</small>
                            </div>
                            <div class="fs-4 fw-bold font-monospace text-primary" id="residualDisplay">0 Units</div>
                        </div>
                    </div>

                </div>

                <!-- Action Buttons -->
                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="../index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="execute_transfer" id="submitTransferBtn" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-check me-1"></i> Confirm & Execute Transfer
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
const allMasterBins = <?= json_encode($allMasterBins); ?>;
const binStockMap = <?= json_encode($binStockMap); ?>;

let currentMaxQty = 0;
let selectedBinMaxSpace = 999999;

function updateSourceDetails() {
    const sel = document.getElementById('sourceItemSelect');
    if (sel.selectedIndex > 0) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('sourceInvId').value = opt.getAttribute('data-invid') || '';
        document.getElementById('sourceWarehouse').value = opt.getAttribute('data-wh') || '';
        document.getElementById('sourceBin').value = opt.getAttribute('data-bin') || '';
        
        currentMaxQty = parseInt(opt.getAttribute('data-qty')) || 0;
        const uom = opt.getAttribute('data-uom') || 'Units';
        
        document.getElementById('currentStockDisplay').innerText = currentMaxQty + ' ' + uom;
        document.getElementById('uomTag').innerText = uom;
    } else {
        document.getElementById('sourceInvId').value = '';
        document.getElementById('sourceWarehouse').value = '';
        document.getElementById('sourceBin').value = '';
        currentMaxQty = 0;
        document.getElementById('currentStockDisplay').innerText = '0 Units';
    }
    calcResidual();
}

function renderAvailableBins() {
    const targetWh = document.getElementById('destWarehouse').value.trim().toUpperCase();
    const binSelect = document.getElementById('destBinSelect');
    binSelect.innerHTML = '<option value="">-- Choose Validated Empty/Available Bin --</option>';

    if (!targetWh) {
        document.getElementById('spaceIndicator').innerText = "Select Warehouse First";
        return;
    }

    let emptyCount = 0;
    let partialCount = 0;

    let optGroupEmpty = document.createElement('optgroup');
    optGroupEmpty.label = "🟢 100% Completely Empty Coordinates";

    let optGroupPartial = document.createElement('optgroup');
    optGroupPartial.label = "🟡 Partially Occupied (Has Spare Capacity)";

    for (const [binCode, maxCap] of Object.entries(allMasterBins)) {
        const mapKey = targetWh + "___" + binCode.toUpperCase();
        const currentUnits = binStockMap[mapKey] || 0;
        const freeSpace = maxCap - currentUnits;

        if (freeSpace <= 0) continue;

        const opt = document.createElement('option');
        opt.value = binCode;
        opt.setAttribute('data-freespace', freeSpace);

        if (currentUnits === 0) {
            opt.textContent = `${binCode} • [VACANT • Max Cap: ${maxCap} Units]`;
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
    const binSelect = document.getElementById('destBinSelect');
    if (binSelect.selectedIndex > 0) {
        const opt = binSelect.options[binSelect.selectedIndex];
        selectedBinMaxSpace = parseInt(opt.getAttribute('data-freespace')) || 150;
        document.getElementById('spaceIndicator').innerText = `Capacity Space: ${selectedBinMaxSpace} Units`;
    } else {
        selectedBinMaxSpace = 999999;
    }
    calcResidual();
}

function calcResidual() {
    const qtyInput = document.getElementById('transferQty');
    const qty = parseInt(qtyInput.value) || 0;
    const residual = currentMaxQty - qty;
    const disp = document.getElementById('residualDisplay');
    const btn = document.getElementById('submitTransferBtn');

    if (qty > currentMaxQty) {
        disp.innerText = "Deficit of " + Math.abs(residual) + " Units (Exceeds Available)";
        disp.className = "fs-4 fw-bold font-monospace text-danger";
        qtyInput.classList.add('is-invalid');
        btn.disabled = true;
    } else if (qty > selectedBinMaxSpace) {
        disp.innerText = `Exceeds Target Bin Capacity (Only ${selectedBinMaxSpace} Units fit)`;
        disp.className = "fs-4 fw-bold font-monospace text-danger";
        qtyInput.classList.add('is-invalid');
        btn.disabled = true;
    } else if (qty <= 0) {
        disp.innerText = currentMaxQty + " Units";
        disp.className = "fs-4 fw-bold font-monospace text-muted";
        qtyInput.classList.remove('is-invalid');
        btn.disabled = false;
    } else {
        disp.innerText = residual + " Units (Will move " + qty + " Units)";
        disp.className = "fs-4 fw-bold font-monospace text-success";
        qtyInput.classList.remove('is-invalid');
        btn.disabled = false;
    }
}

document.addEventListener("DOMContentLoaded", function() {
    if (document.getElementById('sourceItemSelect').selectedIndex > 0) {
        updateSourceDetails();
    }
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>