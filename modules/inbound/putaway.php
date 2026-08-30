<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}
require_once $projectRoot . "/config/database.php";

$id = intval($_GET['id'] ?? 0);
$query = mysqli_query($conn, "SELECT * FROM inbound_shipments WHERE id = '$id' LIMIT 1");
if (!$query || mysqli_num_rows($query) === 0) {
    header("Location: index.php");
    exit();
}
$shipment = mysqli_fetch_assoc($query);

// Target Warehouse Resolution (by ID and Name)
$whId = (int)($shipment['warehouse_id'] ?? 1);
$whTable = "warehouses";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $whTable = "warehouse";
}

$whName = !empty($shipment['warehouse']) ? $shipment['warehouse'] : 'Surat Central Logistics Park';
$whLookup = mysqli_query($conn, "SELECT id, COALESCE(warehouse_name, name) AS wh_name FROM `{$whTable}` WHERE id = '$whId' OR warehouse_name = '" . mysqli_real_escape_string($conn, $whName) . "' LIMIT 1");
if ($whLookup && $wRow = mysqli_fetch_assoc($whLookup)) {
    $whId = (int)$wRow['id'];
    $whName = $wRow['wh_name'];
}
$targetWarehouse = $whName;

/* ==========================================================================
   1. REAL-TIME LIVE OCCUPIED BINS & DYNAMIC AVAILABLE SLOTS
   ========================================================================== */

$occupiedBins = [];
$whNameEscaped = mysqli_real_escape_string($conn, $targetWarehouse);
$occQuery = mysqli_query($conn, "
    SELECT bin_location, SUM(available_qty) AS current_stock 
    FROM inventory 
    WHERE (warehouse = '{$whNameEscaped}' OR warehouse_id = '$whId') AND available_qty > 0 
    GROUP BY bin_location
");
if ($occQuery) {
    while ($r = mysqli_fetch_assoc($occQuery)) {
        $occupiedBins[strtoupper(trim($r['bin_location']))] = (int)$r['current_stock'];
    }
}

// Fetch all standard bin locations filtered for this facility
$allWarehouseBins = [];
$masterBinQuery = @mysqli_query($conn, "
    SELECT bin_code, COALESCE(capacity, max_capacity, 150) AS max_cap 
    FROM bin_locations 
    WHERE (warehouse_id = '$whId' OR warehouse = '{$whNameEscaped}' OR warehouse IS NULL OR warehouse = '') 
      AND (status = 'Active' OR status IS NULL) 
    ORDER BY bin_code ASC
");

if ($masterBinQuery && mysqli_num_rows($masterBinQuery) > 0) {
    while ($b = mysqli_fetch_assoc($masterBinQuery)) {
        $allWarehouseBins[strtoupper(trim($b['bin_code']))] = (int)$b['max_cap'];
    }
} else {
    // Hierarchical Coordinates Generator (L0-A1-001-01-A standard fallback)
    $floors = ['L0', 'L1'];
    $aisles = ['A1', 'A2', 'B1', 'B2', 'C1'];
    foreach ($floors as $fl) {
        foreach ($aisles as $ais) {
            for ($rack = 1; $rack <= 3; $rack++) {
                for ($shelf = 1; $shelf <= 2; $shelf++) {
                    $code = sprintf("%s-%s-%03d-%02d-A", $fl, $ais, $rack, $shelf);
                    $allWarehouseBins[$code] = 150;
                }
            }
        }
    }
}

$emptyBins = [];
$partialBins = [];

foreach ($allWarehouseBins as $bin => $maxCap) {
    $currentUnits = $occupiedBins[$bin] ?? 0;
    $freeSpace = $maxCap - $currentUnits;

    if ($currentUnits === 0) {
        $emptyBins[$bin] = $maxCap;
    } elseif ($freeSpace > 0) {
        $partialBins[$bin] = ['occupied' => $currentUnits, 'free' => $freeSpace, 'cap' => $maxCap];
    }
}

$suggestedBin = !empty($emptyBins) ? array_key_first($emptyBins) : (!empty($partialBins) ? array_key_first($partialBins) : 'L0-A1-001-01-A');

/* ==========================================================================
   2. EXECUTE PUTAWAY INTO INVENTORY & UPDATE ALL MODULES
   ========================================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_putaway'])) {
    $target_bin = mysqli_real_escape_string($conn, strtoupper(trim($_POST['bin_location'] ?? $suggestedBin)));
    $qty = (int)(isset($shipment['accepted_qty']) && $shipment['accepted_qty'] > 0 ? $shipment['accepted_qty'] : $shipment['received_qty']);
    $pId = (int)($shipment['product_id'] ?? 0);
    $pCode = mysqli_real_escape_string($conn, $shipment['product_code']);
    $pName = mysqli_real_escape_string($conn, $shipment['product_name']);
    $wh = $whNameEscaped;
    $batch = mysqli_real_escape_string($conn, $shipment['batch_no'] ?? ('BAT-' . date('Ymd')));
    $exp = !empty($shipment['expiry_date']) ? "'{$shipment['expiry_date']}'" : "NULL";

    mysqli_begin_transaction($conn);
    try {
        // 1. Check if same item & bin exists in this warehouse
        $chkExist = mysqli_query($conn, "
            SELECT id, available_qty 
            FROM inventory 
            WHERE (product_id = '$pId' OR product_code = '$pCode') 
              AND (warehouse = '$wh' OR warehouse_id = '$whId') 
              AND bin_location = '$target_bin' 
            LIMIT 1
        ");

        if ($chkExist && mysqli_num_rows($chkExist) > 0) {
            $existRow = mysqli_fetch_assoc($chkExist);
            $newQty = (int)$existRow['available_qty'] + $qty;
            mysqli_query($conn, "UPDATE inventory SET available_qty = '$newQty', warehouse_id = '$whId', warehouse = '$wh', status = 'In Stock' WHERE id = '{$existRow['id']}'");
        } else {
            mysqli_query($conn, "
                INSERT INTO inventory (product_id, product_code, product_name, warehouse_id, warehouse, bin_location, batch_no, expiry_date, available_qty, reserved_qty, status)
                VALUES ('$pId', '$pCode', '$pName', '$whId', '$wh', '$target_bin', '$batch', $exp, '$qty', 0, 'In Stock')
            ");
        }

        // 2. Mark Putaway Completed in Inbound
        mysqli_query($conn, "UPDATE inbound_shipments SET putaway_status = 'Completed', bin_location = '$target_bin', qc_status = 'Passed' WHERE id = '$id'");

        // 3. Movement Ledger Log
        $chkMovTable = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_movements'");
        if ($chkMovTable && mysqli_num_rows($chkMovTable) > 0) {
            $empId = intval($_SESSION['employee_id'] ?? 1);
            @mysqli_query($conn, "
                INSERT INTO stock_movements (movement_type, reference_no, product_id, product_code, warehouse, bin_location, quantity, created_by)
                VALUES ('PUTAWAY', '{$shipment['grn_no']}', '$pId', '$pCode', '$wh', '$target_bin', '$qty', '$empId')
            ");
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Putaway completed! Allocated <strong>{$qty} Units</strong> into <strong>{$target_bin}</strong> ({$targetWarehouse}).";
        header("Location: index.php");
        exit();
    } catch (\Throwable $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "Putaway failed: " . $e->getMessage();
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-dolly text-primary me-2"></i>Execute Putaway Placement
            </h2>
            <p class="text-muted mb-0">Direct incoming dock cargo into validated empty warehouse storage coordinates</p>
        </div>
        <a href="index.php" class="btn btn-secondary rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Inbound
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 mb-4 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">
        <div class="col-xl-8 col-lg-9">
            <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-barcode text-primary me-2"></i>Cargo Verification & Bin Allocation
                    </h5>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill font-monospace">
                        <?= count($emptyBins); ?> EMPTY BINS AVAILABLE
                    </span>
                </div>

                <div class="card-body p-4">
                    <div class="p-3 bg-light rounded-4 border mb-4">
                        <div class="row g-2">
                            <div class="col-md-6">
                                <small class="text-muted text-uppercase fw-semibold d-block" style="font-size: 11px;">Product & SKU</small>
                                <strong class="text-dark fs-6"><?= htmlspecialchars($shipment['product_name']); ?></strong>
                                <code class="text-primary font-monospace small d-block"><?= htmlspecialchars($shipment['product_code']); ?></code>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted text-uppercase fw-semibold d-block" style="font-size: 11px;">Units to Putaway</small>
                                <span class="badge bg-primary fs-6 px-3 py-1 font-monospace mt-1">
                                    <?= number_format(isset($shipment['accepted_qty']) && $shipment['accepted_qty'] > 0 ? $shipment['accepted_qty'] : $shipment['received_qty']); ?> Units
                                </span>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted text-uppercase fw-semibold d-block" style="font-size: 11px;">Target Warehouse</small>
                                <strong class="text-dark d-block mt-1"><i class="fa-solid fa-building text-secondary me-1"></i><?= htmlspecialchars($targetWarehouse); ?></strong>
                            </div>
                        </div>
                    </div>

                    <form method="POST">
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark d-flex justify-content-between">
                                <span><i class="fa-solid fa-location-dot text-primary me-1"></i> Select Empty Storage Bin *</span>
                                <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Auto-Filtered for <?= htmlspecialchars($targetWarehouse); ?></small>
                            </label>

                            <select name="bin_location" id="binSelect" class="form-select border-2 font-monospace fw-bold fs-6 py-2" required onchange="syncManualInput(this.value)">
                                <optgroup label="🟢 100% Completely Empty Racks (Recommended)">
                                    <?php foreach ($emptyBins as $eBin => $cap): ?>
                                        <option value="<?= $eBin; ?>" <?= ($eBin === $suggestedBin) ? 'selected' : ''; ?>>
                                            Bin <?= $eBin; ?> &bull; [VACANT • Capacity: <?= $cap; ?> Units]
                                        </option>
                                    <?php endforeach; ?>
                                </optgroup>
                                <?php if (!empty($partialBins)): ?>
                                    <optgroup label="🟡 Partially Occupied Bins (Has Capacity)">
                                        <?php foreach ($partialBins as $pBin => $pData): ?>
                                            <option value="<?= $pBin; ?>">
                                                Bin <?= $pBin; ?> &bull; [Occupied: <?= $pData['occupied']; ?> • Free Space: <?= $pData['free']; ?> Units]
                                            </option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Quick Visual Slot Buttons -->
                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted text-uppercase mb-2">⚡ Quick Select Vacant Slots:</label>
                            <div class="d-flex flex-wrap gap-2">
                                <?php $counter = 0; foreach ($emptyBins as $quickBin => $cap): if (++$counter > 8) break; ?>
                                    <button type="button" class="btn btn-sm btn-outline-primary font-monospace fw-bold rounded-pill px-3 py-1 <?= ($quickBin === $suggestedBin) ? 'active' : ''; ?>" onclick="chooseQuickBin('<?= $quickBin; ?>', this)">
                                        <?= $quickBin; ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                            <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                            <button type="submit" name="execute_putaway" class="btn btn-success px-5 fw-bold shadow-sm rounded-pill">
                                <i class="fa-solid fa-check me-1"></i> Confirm & Putaway Stock
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function chooseQuickBin(binCode, btnElement) {
    const sel = document.getElementById('binSelect');
    if (sel) sel.value = binCode;
    document.querySelectorAll('.btn-outline-primary').forEach(b => b.classList.remove('active'));
    if (btnElement) btnElement.classList.add('active');
}

function syncManualInput(val) {
    document.querySelectorAll('.btn-outline-primary').forEach(b => {
        if (b.innerText.trim() === val) b.classList.add('active');
        else b.classList.remove('active');
    });
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>