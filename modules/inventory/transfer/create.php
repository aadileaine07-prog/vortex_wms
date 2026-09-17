<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. STRICT ACTIVE WAREHOUSES FETCHING
   ========================================================================== */
$warehouses = [];
$whRes = @mysqli_query($conn, "SELECT warehouse_name FROM warehouse WHERE status = 'Active'");
if ($whRes && mysqli_num_rows($whRes) > 0) {
    while ($w = mysqli_fetch_assoc($whRes)) {
        if (!empty($w['warehouse_name'])) {
            $warehouses[] = $w['warehouse_name'];
        }
    }
}
if (empty($warehouses)) {
    $warehouses = ['Surat S1'];
}

/* ==========================================================================
   2. LIVE BIN CAPACITY & OCCUPANCY MAP
   ========================================================================== */
$binStockMap = [];
$occQuery = @mysqli_query($conn, "SELECT warehouse, bin_location, SUM(available_qty) AS current_stock FROM inventory WHERE available_qty > 0 GROUP BY warehouse, bin_location");
if ($occQuery) {
    while ($r = mysqli_fetch_assoc($occQuery)) {
        $key = strtoupper(trim($r['warehouse'])) . "___" . strtoupper(trim($r['bin_location']));
        $binStockMap[$key] = (int)$r['current_stock'];
    }
}

// Detect correct bin column name dynamically
$binCol = "bin_code";
$cTest = @mysqli_query($conn, "SHOW COLUMNS FROM bin_locations LIKE 'bin_code'");
if (!$cTest || mysqli_num_rows($cTest) === 0) {
    $cTest2 = @mysqli_query($conn, "SHOW COLUMNS FROM bin_locations LIKE 'bin_location'");
    if ($cTest2 && mysqli_num_rows($cTest2) > 0) {
        $binCol = "bin_location";
    }
}

$allMasterBins = [];
$masterBinRes = @mysqli_query($conn, "SELECT {$binCol} AS b_code, COALESCE(max_capacity, capacity, 150) AS max_cap FROM bin_locations WHERE warehouse_id = 12 OR warehouse_id IS NULL");
if (!$masterBinRes || mysqli_num_rows($masterBinRes) === 0) {
    $masterBinRes = @mysqli_query($conn, "SELECT {$binCol} AS b_code, COALESCE(max_capacity, capacity, 150) AS max_cap FROM bin_locations");
}

if ($masterBinRes && mysqli_num_rows($masterBinRes) > 0) {
    while ($b = mysqli_fetch_assoc($masterBinRes)) {
        if (!empty($b['b_code'])) {
            $allMasterBins[strtoupper(trim($b['b_code']))] = (int)$b['max_cap'];
        }
    }
}

// Absolute Fallback Bins if table is empty
if (empty($allMasterBins)) {
    $floors = ['L0', 'L1'];
    $aisles = ['A1', 'A2'];
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
   3. FETCH SOURCE ITEMS
   ========================================================================== */
$stockItems = @mysqli_query($conn, "SELECT id AS inv_id, product_id, product_code, product_name, warehouse, bin_location, available_qty FROM inventory WHERE available_qty > 0 ORDER BY id DESC");

/* ==========================================================================
   4. HANDLE TRANSFER SUBMISSION
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_transfer'])) {
    $inv_id        = intval($_POST['source_inv_id'] ?? 0);
    $product_id    = intval($_POST['source_product_id'] ?? 0);
    $from_wh       = mysqli_real_escape_string($conn, trim($_POST['from_warehouse'] ?? ''));
    $from_bin      = mysqli_real_escape_string($conn, strtoupper(trim($_POST['from_bin'] ?? '')));
    $to_wh         = mysqli_real_escape_string($conn, trim($_POST['to_warehouse'] ?? ''));
    $to_bin        = mysqli_real_escape_string($conn, strtoupper(trim($_POST['to_bin'] ?? '')));
    $qty           = intval($_POST['quantity'] ?? 0);

    $destKey = strtoupper($to_wh) . "___" . strtoupper($to_bin);
    $currentDestStock = $binStockMap[$destKey] ?? 0;
    $maxDestCap = $allMasterBins[$to_bin] ?? 150;
    $availableSpace = max(0, $maxDestCap - $currentDestStock);

    if ($inv_id <= 0 || $qty <= 0 || empty($to_wh) || empty($to_bin)) {
        $_SESSION['error'] = "Please fill in all required transfer fields.";
    } elseif ($qty > $availableSpace) {
        $_SESSION['error'] = "Capacity Exceeded! Bin <strong>{$to_bin}</strong> only has space for <strong>{$availableSpace} Units</strong>.";
    } else {
        mysqli_begin_transaction($conn);
        try {
            $srcChk = mysqli_query($conn, "SELECT * FROM inventory WHERE id = '$inv_id' AND available_qty >= '$qty' LIMIT 1");
            if (!$srcChk || mysqli_num_rows($srcChk) === 0) {
                throw new Exception("Source inventory record not found or quantity insufficient.");
            }
            $srcRow = mysqli_fetch_assoc($srcChk);
            $newSrcQty = (int)$srcRow['available_qty'] - $qty;

            if ($newSrcQty <= 0) {
                mysqli_query($conn, "DELETE FROM inventory WHERE id = '$inv_id'");
            } else {
                mysqli_query($conn, "UPDATE inventory SET available_qty = '$newSrcQty' WHERE id = '$inv_id'");
            }

            $destChk = mysqli_query($conn, "SELECT id, available_qty FROM inventory WHERE product_id = '{$srcRow['product_id']}' AND warehouse = '$to_wh' AND bin_location = '$to_bin' LIMIT 1");
            if ($destChk && mysqli_num_rows($destChk) > 0) {
                $destRow = mysqli_fetch_assoc($destChk);
                $newDestQty = (int)$destRow['available_qty'] + $qty;
                mysqli_query($conn, "UPDATE inventory SET available_qty = '$newDestQty' WHERE id = '{$destRow['id']}'");
            } else {
                $pId   = $srcRow['product_id'];
                $pCode = mysqli_real_escape_string($conn, $srcRow['product_code']);
                $pName = mysqli_real_escape_string($conn, $srcRow['product_name']);
                $batch = mysqli_real_escape_string($conn, $srcRow['batch_no'] ?? 'BAT-' . date('Ymd'));
                mysqli_query($conn, "
                    INSERT INTO inventory (product_id, product_code, product_name, warehouse, bin_location, batch_no, available_qty, status)
                    VALUES ('$pId', '$pCode', '$pName', '$to_wh', '$to_bin', '$batch', '$qty', 'In Stock')
                ");
            }

            mysqli_commit($conn);
            $_SESSION['success'] = "Successfully relocated <strong>{$qty} Units</strong> to Bin <strong>[{$to_bin}]</strong> ({$to_wh}).";
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
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-right-left text-primary me-2"></i>Stock Relocation & Bin Transfer</h2>
            <p class="text-muted mb-0">Dynamic capacity-validated bin allocation</p>
        </div>
        <a href="../index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory</a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-body p-4">
            <form method="POST" id="transferForm">
                <input type="hidden" name="source_inv_id" id="sourceInvId" value="">

                <div class="row g-4">
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">Select Live Source Inventory Item *</label>
                        <select name="source_product_id" id="sourceItemSelect" class="form-select border-2 fw-semibold" required onchange="updateSourceDetails()">
                            <option value="">-- Choose In-Stock Inventory Record --</option>
                            <?php if ($stockItems && mysqli_num_rows($stockItems) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($stockItems)): ?>
                                    <option 
                                        value="<?= $row['product_id']; ?>"
                                        data-invid="<?= $row['inv_id']; ?>"
                                        data-wh="<?= htmlspecialchars($row['warehouse']); ?>"
                                        data-bin="<?= htmlspecialchars($row['bin_location']); ?>"
                                        data-qty="<?= (int)$row['available_qty']; ?>"
                                    >
                                        <?= htmlspecialchars($row['product_name']); ?> (<?= htmlspecialchars($row['product_code']); ?>) &bull; [📍 Bin: <?= htmlspecialchars($row['bin_location']); ?>] &bull; Avail: <?= $row['available_qty']; ?> Units (<?= htmlspecialchars($row['warehouse']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Relocation Date *</label>
                        <input type="date" name="transfer_date" class="form-control border-2 fw-semibold" value="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Origin Warehouse</small>
                            <input type="text" name="from_warehouse" id="sourceWarehouse" class="form-control-plaintext fw-bold text-dark py-0" readonly placeholder="Auto-populated">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Origin Bin Location</small>
                            <input type="text" name="from_bin" id="sourceBin" class="form-control-plaintext font-monospace fw-bold text-primary fs-6 py-0" readonly placeholder="Auto-populated">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border border-start border-4 border-primary">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Available In Source Bin</small>
                            <div class="fs-5 fw-bold text-primary font-monospace" id="currentStockDisplay">0 Units</div>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Destination Warehouse *</label>
                        <select name="to_warehouse" id="destWarehouse" class="form-select border-2 fw-semibold" required onchange="renderAvailableBins()">
                            <option value="">-- Choose Active Target Warehouse --</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= htmlspecialchars($wh); ?>" <?= ($wh === 'Surat S1') ? 'selected' : ''; ?>><?= htmlspecialchars($wh); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted d-flex justify-content-between">
                            <span>Destination Coordinate (Bin) *</span>
                            <span class="text-success fw-bold" id="spaceIndicator">Select Warehouse First</span>
                        </label>
                        <select name="to_bin" id="destBinSelect" class="form-select border-2 font-monospace fw-bold fs-6 text-primary" required onchange="validateBinCapacity()">
                            <option value="">-- Select Destination Warehouse First --</option>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Transfer Quantity *</label>
                        <div class="input-group">
                            <input type="number" name="quantity" id="transferQty" class="form-control border-2 font-monospace fw-bold text-center fs-5" min="1" placeholder="0" required oninput="validateBinCapacity()">
                            <span class="input-group-text bg-light border-2">Units</span>
                        </div>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">Transfer Reason / Movement Log</label>
                        <input type="text" name="notes" class="form-control border-2" value="Internal Relocation">
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                    <a href="../index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="execute_transfer" id="submitBtn" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill">
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
let selectedBinMaxSpace = 999999;

function updateSourceDetails() {
    const sel = document.getElementById('sourceItemSelect');
    if (sel.selectedIndex > 0) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('sourceInvId').value = opt.getAttribute('data-invid') || '';
        document.getElementById('sourceWarehouse').value = opt.getAttribute('data-wh') || '';
        document.getElementById('sourceBin').value = opt.getAttribute('data-bin') || '';
        const qty = opt.getAttribute('data-qty') || 0;
        document.getElementById('currentStockDisplay').innerText = qty + ' Units';
    } else {
        document.getElementById('sourceInvId').value = '';
        document.getElementById('sourceWarehouse').value = '';
        document.getElementById('sourceBin').value = '';
        document.getElementById('currentStockDisplay').innerText = '0 Units';
    }
}

function renderAvailableBins() {
    const targetWh = document.getElementById('destWarehouse').value.trim().toUpperCase();
    const binSelect = document.getElementById('destBinSelect');
    binSelect.innerHTML = '<option value="">-- Choose Validated Bin --</option>';
    
    if (!targetWh) {
        document.getElementById('spaceIndicator').innerText = "Select Warehouse First";
        return;
    }

    let availableCount = 0;
    for (const [binCode, maxCap] of Object.entries(allMasterBins)) {
        const mapKey = targetWh + "___" + binCode.toUpperCase();
        const currentUnits = binStockMap[mapKey] || 0;
        const freeSpace = maxCap - currentUnits;

        if (freeSpace <= 0) continue;

        const opt = document.createElement('option');
        opt.value = binCode;
        opt.setAttribute('data-freespace', freeSpace);
        opt.setAttribute('data-maxcap', maxCap);
        
        if (currentUnits === 0) {
            opt.textContent = `${binCode} • [VACANT • Max Cap: ${maxCap} Units]`;
        } else {
            opt.textContent = `${binCode} • [Free Space: ${freeSpace} / ${maxCap} Units]`;
        }
        
        binSelect.appendChild(opt);
        availableCount++;
    }

    document.getElementById('spaceIndicator').innerText = availableCount + " Bins Available";
    validateBinCapacity();
}

function validateBinCapacity() {
    const binSelect = document.getElementById('destBinSelect');
    const qtyInput = document.getElementById('transferQty');
    const submitBtn = document.getElementById('submitBtn');
    
    if (binSelect.selectedIndex > 0) {
        const opt = binSelect.options[binSelect.selectedIndex];
        selectedBinMaxSpace = parseInt(opt.getAttribute('data-freespace')) || 150;
        const enteredQty = parseInt(qtyInput.value) || 0;

        if (enteredQty > selectedBinMaxSpace) {
            document.getElementById('spaceIndicator').innerHTML = `<span class="text-danger">Exceeds Space! Only ${selectedBinMaxSpace} Units left</span>`;
            qtyInput.classList.add('is-invalid');
            submitBtn.disabled = true;
        } else {
            document.getElementById('spaceIndicator').innerHTML = `<span class="text-success">Available Space: ${selectedBinMaxSpace} Units</span>`;
            qtyInput.classList.remove('is-invalid');
            submitBtn.disabled = false;
        }
    } else {
        selectedBinMaxSpace = 999999;
        qtyInput.classList.remove('is-invalid');
        submitBtn.disabled = false;
    }
}

document.addEventListener("DOMContentLoaded", function() {
    const destWh = document.getElementById('destWarehouse').value;
    if (destWh) {
        renderAvailableBins();
    }
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>