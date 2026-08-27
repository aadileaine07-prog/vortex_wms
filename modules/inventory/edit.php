<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// 1. Dynamic Warehouse Table Resolution
$whTable = "warehouse";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouse'");
if (!$chkTable || mysqli_num_rows($chkTable) == 0) {
    $whTable = "warehouses";
}

$nameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) == 0) {
    $nameCol = "name";
}

$codeCol = "warehouse_code";
$cdChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_code'");
if (!$cdChk || mysqli_num_rows($cdChk) == 0) {
    $codeCol = "code";
}

// 2. Fetch Active Warehouses for Selection
$warehouses = [];
$whSql = "SELECT MIN(id) AS id, `{$nameCol}` AS warehouse_name, `{$codeCol}` AS warehouse_code 
          FROM `{$whTable}` 
          WHERE (LOWER(status) = 'active' OR status = '1')
          GROUP BY `{$nameCol}`, `{$codeCol}`
          ORDER BY id ASC";
$whRes = @mysqli_query($conn, $whSql);
if ($whRes) {
    while ($r = mysqli_fetch_assoc($whRes)) {
        $warehouses[] = $r;
    }
}

// 3. Fetch Specific Inventory Item Data
$query = "
    SELECT 
        i.*,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(i.warehouse_id, 0) AS final_wh_id,
        COALESCE(w.{$nameCol}, i.warehouse, 'Main Facility') AS final_wh_name
    FROM inventory i
    LEFT JOIN products p ON p.id = i.product_id
    LEFT JOIN `{$whTable}` w ON w.id = i.warehouse_id
    WHERE i.id = $id
    LIMIT 1
";
$result = @mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Inventory item not found.";
    header("Location: index.php");
    exit();
}

$item = mysqli_fetch_assoc($result);

// 4. Fetch Active Bins of current warehouse
$activeBins = [];
$currentWhId = (int)$item['final_wh_id'];
$binSql = "SELECT bin_code, zone_name, COALESCE(max_units, max_capacity, 100) AS max_limit 
           FROM bin_locations 
           WHERE (warehouse_id = '$currentWhId' OR warehouse_id IS NULL OR warehouse_id = 0)
             AND (status = 'Active' OR status = '1')
           ORDER BY bin_code ASC";
$binRes = @mysqli_query($conn, $binSql);
if ($binRes) {
    while ($b = mysqli_fetch_assoc($binRes)) {
        $activeBins[] = $b;
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Stock Inventory
            </h2>
            <p class="text-muted mb-0">Modifying Ledger Item: <code class="fw-bold text-primary font-monospace">#<?= $item['id']; ?></code></p>
        </div>
        <div class="d-flex gap-2">
            <a href="view.php?id=<?= $item['id']; ?>" class="btn btn-outline-info fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-eye me-1"></i> View Details
            </a>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Ledger
            </a>
        </div>
    </div>

    <!-- Main Form Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white">
        <div class="card-body p-4">
            
            <form action="update.php" method="POST">
                <input type="hidden" name="id" value="<?= $item['id']; ?>">

                <div class="row g-4">

                    <!-- SKU (Read Only) -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Master SKU / Product Code</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-barcode text-muted"></i></span>
                            <input type="text" class="form-control border-2 bg-light font-monospace fw-bold text-primary" value="<?= htmlspecialchars($item['final_sku']); ?>" readonly>
                        </div>
                    </div>

                    <!-- Product Name -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Product / Item Name *</label>
                        <input type="text" name="product_name" class="form-control border-2 fw-semibold" value="<?= htmlspecialchars($item['final_name']); ?>" required>
                    </div>

                    <!-- Target Active Warehouse -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Facility Warehouse *</label>
                        <select name="warehouse_id" id="warehouseSelector" class="form-select border-2 fw-semibold" required onchange="loadAvailableBins(this.value)">
                            <option value="">-- Select Active Warehouse --</option>
                            <?php foreach ($warehouses as $wh): ?>
                                <option value="<?= $wh['id']; ?>" <?= ($item['final_wh_id'] == $wh['id'] || $item['final_wh_name'] == $wh['warehouse_name']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($wh['warehouse_name']); ?> (<?= htmlspecialchars($wh['warehouse_code'] ?? 'WH'); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <input type="hidden" name="warehouse" id="warehouseNameHidden" value="<?= htmlspecialchars($item['final_wh_name']); ?>">
                    </div>

                    <!-- Bin Location Coordinate Selector -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Bin Location Coordinate *</label>
                        <select name="bin_location" id="binSelector" class="form-select border-2 font-monospace fw-bold text-primary" required>
                            <option value="<?= htmlspecialchars($item['bin_location']); ?>" selected>
                                <?= htmlspecialchars($item['bin_location']); ?> (Current Assigned Bin)
                            </option>
                            <?php foreach ($activeBins as $b): ?>
                                <?php if ($b['bin_code'] !== $item['bin_location']): ?>
                                    <option value="<?= $b['bin_code']; ?>">
                                        <?= $b['bin_code']; ?> [<?= htmlspecialchars($b['zone_name'] ?? 'Zone'); ?> - Max <?= $b['max_limit']; ?> Qty]
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Quantities -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Available Quantity (Picking) *</label>
                        <div class="input-group">
                            <input type="number" name="available_qty" class="form-control border-2 fw-bold text-primary text-center fs-5" value="<?= (int)$item['available_qty']; ?>" min="0" required>
                            <span class="input-group-text bg-light border-2">Units</span>
                        </div>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Reserved Quantity (Allocated) *</label>
                        <div class="input-group">
                            <input type="number" name="reserved_qty" class="form-control border-2 fw-bold text-warning text-center fs-5" value="<?= (int)$item['reserved_qty']; ?>" min="0" required>
                            <span class="input-group-text bg-light border-2">Units</span>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Stock Status Override</label>
                        <select name="status" class="form-select border-2 fw-semibold">
                            <option value="In Stock" <?= ($item['status'] == 'In Stock') ? 'selected' : ''; ?>>In Stock (Healthy)</option>
                            <option value="Low Stock" <?= ($item['status'] == 'Low Stock') ? 'selected' : ''; ?>>Low Stock (Alert)</option>
                            <option value="Out of Stock" <?= ($item['status'] == 'Out of Stock' || $item['status'] == 'Out Of Stock') ? 'selected' : ''; ?>>Out of Stock (Depleted)</option>
                        </select>
                    </div>

                    <!-- Batch & Expiry -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Batch / Lot Tracking Number</label>
                        <input type="text" name="batch_no" class="form-control border-2 font-monospace" value="<?= htmlspecialchars($item['batch_no'] ?? ($item['batch_number'] ?? '')); ?>" placeholder="e.g. BATCH-2026-08">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Expiry / Shelf-Life Date (FEFO)</label>
                        <input type="date" name="expiry_date" class="form-control border-2" value="<?= (!empty($item['expiry_date']) && $item['expiry_date'] !== '0000-00-00') ? $item['expiry_date'] : ''; ?>">
                    </div>

                </div>

                <div class="mt-4 pt-3 border-top text-end d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-light px-4 rounded-pill">Cancel</a>
                    <button type="submit" class="btn btn-warning px-5 fw-bold shadow-sm rounded-pill text-dark">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Changes
                    </button>
                </div>

            </form>

        </div>
    </div>

</div>

<script>
function loadAvailableBins(whId) {
    const sel = document.getElementById('warehouseSelector');
    const hiddenName = document.getElementById('warehouseNameHidden');
    if (sel.selectedIndex >= 0) {
        hiddenName.value = sel.options[sel.selectedIndex].text.split('(')[0].trim();
    }

    if (!whId) return;

    fetch(`get_empty_bins.php?warehouse_id=${whId}`)
        .then(res => res.json())
        .then(data => {
            const binSelect = document.getElementById('binSelector');
            const currentVal = "<?= htmlspecialchars($item['bin_location']); ?>";
            binSelect.innerHTML = `<option value="${currentVal}">${currentVal} (Current Assigned Bin)</option>`;

            if (data && data.length > 0) {
                data.forEach(bin => {
                    if (bin.bin_code !== currentVal) {
                        const opt = document.createElement('option');
                        opt.value = bin.bin_code;
                        opt.textContent = `${bin.bin_code} [${bin.zone} - ${bin.available_space} Units Left]`;
                        binSelect.appendChild(opt);
                    }
                });
            }
        })
        .catch(err => console.error("Error fetching bins:", err));
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>