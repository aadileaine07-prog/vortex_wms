<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3 levels up: /modules/inventory/transfer/ -> /vortex_wms/
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
   1. DYNAMIC WAREHOUSES RESOLUTION
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

if (empty($warehouses)) {
    $warehouses = [
        'Main Warehouse - Section A',
        'Central Distribution Center',
        'North Logistics Hub',
        'Cold Storage Facility'
    ];
}

/* ==========================================================================
   2. FETCH SOURCE STOCK ITEMS (Inventory + Catalog Fallback)
   ========================================================================== */

$invQuery = "
    SELECT 
        i.id AS inv_id,
        i.product_id,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(i.warehouse, 'Main Warehouse - Section A') AS final_warehouse,
        COALESCE(i.bin_location, 'A1-01') AS bin_location,
        COALESCE(i.available_qty, 50) AS available_qty,
        COALESCE(p.uom, 'PCS') AS uom
    FROM products p
    LEFT JOIN inventory i ON (p.id = i.product_id OR p.product_code = i.product_code)
    WHERE p.status = 'Active' OR p.status = '1'
    ORDER BY final_product_name ASC
";
$stockItems = mysqli_query($conn, $invQuery);

/* ==========================================================================
   3. HANDLE TRANSFER TRANSACTION
   ========================================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['execute_transfer'])) {

    $product_id    = intval($_POST['source_product_id'] ?? 0);
    $from_wh       = mysqli_real_escape_string($conn, trim($_POST['from_warehouse'] ?? ''));
    $from_bin      = mysqli_real_escape_string($conn, strtoupper(trim($_POST['from_bin'] ?? '')));
    $to_wh         = mysqli_real_escape_string($conn, trim($_POST['to_warehouse'] ?? ''));
    $to_bin        = mysqli_real_escape_string($conn, strtoupper(trim($_POST['to_bin'] ?? '')));
    $qty           = intval($_POST['quantity'] ?? 0);
    $notes         = mysqli_real_escape_string($conn, trim($_POST['notes'] ?? 'Internal Relocation'));
    $transfer_date = !empty($_POST['transfer_date']) ? $_POST['transfer_date'] : date('Y-m-d');
    $user_id       = $_SESSION['employee_id'];

    if ($product_id <= 0 || $qty <= 0 || empty($to_wh) || empty($to_bin)) {
        $_SESSION['error'] = "Please fill in all mandatory transfer parameters.";
    } elseif ($from_wh === $to_wh && $from_bin === $to_bin) {
        $_SESSION['error'] = "Destination warehouse & bin cannot be identical to source origin.";
    } else {

        // Check Product Details
        $pRes = mysqli_query($conn, "SELECT id, product_code, product_name FROM products WHERE id = '$product_id' LIMIT 1");
        $pData = mysqli_fetch_assoc($pRes);
        $pCode = mysqli_real_escape_string($conn, $pData['product_code']);
        $pName = mysqli_real_escape_string($conn, $pData['product_name']);

        mysqli_begin_transaction($conn);

        try {
            // 1. Check or create source row in inventory
            $srcCheck = mysqli_query($conn, "SELECT id, available_qty FROM inventory WHERE (product_id = '$product_id' OR product_code = '$pCode') AND warehouse = '$from_wh' AND bin_location = '$from_bin' LIMIT 1");

            if ($srcCheck && mysqli_num_rows($srcCheck) > 0) {
                $srcRow = mysqli_fetch_assoc($srcCheck);
                if ($srcRow['available_qty'] < $qty) {
                    throw new Exception("Insufficient stock in source bin. Available: {$srcRow['available_qty']} Units.");
                }
                $newSrcQty = $srcRow['available_qty'] - $qty;
                $srcStatus = ($newSrcQty <= 0) ? 'Out of Stock' : (($newSrcQty <= 10) ? 'Low Stock' : 'In Stock');
                mysqli_query($conn, "UPDATE inventory SET available_qty = '$newSrcQty', status = '$srcStatus' WHERE id = '{$srcRow['id']}'");
            }

            // 2. Add into Destination Bin
            $destCheck = mysqli_query($conn, "SELECT id, available_qty FROM inventory WHERE (product_id = '$product_id' OR product_code = '$pCode') AND warehouse = '$to_wh' AND bin_location = '$to_bin' LIMIT 1");

            if ($destCheck && mysqli_num_rows($destCheck) > 0) {
                $destRow = mysqli_fetch_assoc($destCheck);
                $newDestQty = $destRow['available_qty'] + $qty;
                mysqli_query($conn, "UPDATE inventory SET available_qty = '$newDestQty', status = 'In Stock' WHERE id = '{$destRow['id']}'");
            } else {
                mysqli_query($conn, "
                    INSERT INTO inventory (product_id, product_code, product_name, warehouse, bin_location, available_qty, status)
                    VALUES ('$product_id', '$pCode', '$pName', '$to_wh', '$to_bin', '$qty', 'In Stock')
                ");
            }

            // 3. Log Transfer Table if exists
            $chkTransTable = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_transfers'");
            if ($chkTransTable && mysqli_num_rows($chkTransTable) > 0) {
                @mysqli_query($conn, "
                    INSERT INTO stock_transfers (product_id, product_code, from_warehouse, to_warehouse, from_bin, to_bin, quantity, transfer_date, reason, created_by)
                    VALUES ('$product_id', '$pCode', '$from_wh', '$to_wh', '$from_bin', '$to_bin', '$qty', '$transfer_date', '$notes', '$user_id')
                ");
            }

            mysqli_commit($conn);
            $_SESSION['success'] = "✅ Successfully transferred <strong>{$qty} Units</strong> of {$pName} from [{$from_wh} - {$from_bin}] to [{$to_wh} - {$to_bin}].";
            header("Location: ../index.php");
            exit();

        } catch (\Throwable $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Transfer failed: " . $e->getMessage();
        }
    }
}

// Single Unified Header Include
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Action Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-dolly text-primary me-2"></i>Create Stock Transfer
            </h2>
            <p class="text-muted mb-0">Relocate warehouse inventory across different storage zones and bin coordinates</p>
        </div>
        <div>
            <a href="../index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
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

    <!-- Main Transfer Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-route text-primary me-2"></i>Internal Stock Movement Entry
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill font-monospace">INTER-BIN / FACILITY</span>
        </div>

        <div class="card-body p-4">
            <form method="POST" id="transferForm">
                <div class="row g-4">

                    <!-- Select Source Product -->
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">Select Source Inventory Item *</label>
                        <select name="source_product_id" id="sourceItemSelect" class="form-select border-2 fw-semibold" required onchange="updateSourceDetails()">
                            <option value="">-- Choose Stock Record to Move --</option>
                            <?php if ($stockItems && mysqli_num_rows($stockItems) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($stockItems)): ?>
                                    <option 
                                        value="<?= $row['product_id']; ?>"
                                        data-wh="<?= htmlspecialchars($row['final_warehouse']); ?>"
                                        data-bin="<?= htmlspecialchars($row['bin_location']); ?>"
                                        data-qty="<?= (int)$row['available_qty']; ?>"
                                        data-uom="<?= htmlspecialchars($row['uom']); ?>"
                                        <?= ($row['inv_id'] == $preselected_inv_id) ? 'selected' : ''; ?>
                                    >
                                        <?= htmlspecialchars($row['final_product_name']); ?> (<?= htmlspecialchars($row['final_sku']); ?>) &bull; [<?= htmlspecialchars($row['final_warehouse']); ?> &bull; Bin: <?= htmlspecialchars($row['bin_location']); ?>] &bull; Avail: <?= $row['available_qty']; ?> <?= htmlspecialchars($row['uom']); ?>
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Movement Date -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Movement Date *</label>
                        <input type="date" name="transfer_date" class="form-control border-2 fw-semibold" value="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <!-- Source Warehouse (Read-only) -->
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Source Warehouse</small>
                            <input type="text" name="from_warehouse" id="sourceWarehouse" class="form-control-plaintext fw-bold text-dark py-0" readonly placeholder="Auto-populated">
                        </div>
                    </div>

                    <!-- Source Bin (Read-only) -->
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Source Bin Coordinate</small>
                            <input type="text" name="from_bin" id="sourceBin" class="form-control-plaintext font-monospace fw-bold text-primary py-0" readonly placeholder="Auto-populated">
                        </div>
                    </div>

                    <!-- Current Available Stock -->
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border border-start border-4 border-primary">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Current Available Units</small>
                            <div class="fs-5 fw-bold text-primary font-monospace" id="currentStockDisplay">0 Units</div>
                        </div>
                    </div>

                    <!-- Destination Warehouse -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Destination Warehouse *</label>
                        <select name="to_warehouse" id="destWarehouse" class="form-select border-2 fw-semibold" required>
                            <option value="">-- Target Warehouse --</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= htmlspecialchars($wh); ?>"><?= htmlspecialchars($wh); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Destination Bin Coordinate -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Destination Coordinate (Bin) *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-location-dot text-primary"></i></span>
                            <input type="text" name="to_bin" id="destBin" class="form-control border-2 font-monospace fw-bold text-uppercase" placeholder="e.g. B2-01, C1-05" required list="binOptions">
                            <datalist id="binOptions">
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
                    </div>

                    <!-- Transfer Quantity -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Transfer Quantity *</label>
                        <div class="input-group">
                            <input type="number" name="quantity" id="transferQty" class="form-control border-2 fw-bold text-center fs-5" min="1" placeholder="Enter Units" required oninput="calcResidual()">
                            <span class="input-group-text bg-light border-2" id="uomTag">Units</span>
                        </div>
                    </div>

                    <!-- Reason / Remarks -->
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">Transfer Reason / Remarks</label>
                        <input type="text" name="notes" class="form-control border-2" placeholder="e.g. Putaway optimization, Rebalancing, Quarantine shift" value="Internal Rebalancing">
                    </div>

                    <!-- Residual Stock Preview -->
                    <div class="col-12">
                        <div class="p-3 bg-light rounded-4 border d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <strong class="text-dark d-block">Source Bin Residual Balance:</strong>
                                <small class="text-muted">Remaining available units in origin coordinate after shift</small>
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
let currentMaxQty = 0;

function updateSourceDetails() {
    const sel = document.getElementById('sourceItemSelect');
    if (sel.selectedIndex > 0) {
        const opt = sel.options[sel.selectedIndex];
        document.getElementById('sourceWarehouse').value = opt.getAttribute('data-wh') || '';
        document.getElementById('sourceBin').value = opt.getAttribute('data-bin') || '';
        
        currentMaxQty = parseInt(opt.getAttribute('data-qty')) || 0;
        const uom = opt.getAttribute('data-uom') || 'Units';
        
        document.getElementById('currentStockDisplay').innerText = currentMaxQty + ' ' + uom;
        document.getElementById('uomTag').innerText = uom;
    } else {
        document.getElementById('sourceWarehouse').value = '';
        document.getElementById('sourceBin').value = '';
        currentMaxQty = 0;
        document.getElementById('currentStockDisplay').innerText = '0 Units';
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
    } else if (qty <= 0) {
        disp.innerText = currentMaxQty + " Units";
        disp.className = "fs-4 fw-bold font-monospace text-muted";
        qtyInput.classList.remove('is-invalid');
        btn.disabled = false;
    } else {
        disp.innerText = residual + " Units";
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