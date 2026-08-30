<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Inventory ID is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// 1. Dynamic Warehouse Table Resolution
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

// 2. Fetch Active Warehouses List (Gujarat Logistics Hubs)
$warehouses = [];
$whSql = "SELECT id, `{$whNameCol}` AS warehouse_name 
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
        ['id' => 1, 'warehouse_name' => 'Surat Central Logistics Park'],
        ['id' => 2, 'warehouse_name' => 'Ahmedabad Mega Distribution Center'],
        ['id' => 3, 'warehouse_name' => 'Vadodara FMCG & Chemical Hub'],
        ['id' => 4, 'warehouse_name' => 'Mundra Port Logistics Terminal'],
        ['id' => 5, 'warehouse_name' => 'Rajkot Industrial Supply Depot']
    ];
}

// 3. Fetch Specific Inventory Item Data
$query = "
    SELECT 
        i.*,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(w.id, i.warehouse_id, 1) AS final_wh_id,
        COALESCE(w.{$whNameCol}, i.warehouse, 'Surat Central Logistics Park') AS final_wh_name
    FROM inventory i
    LEFT JOIN products p ON (p.id = i.product_id OR p.product_code = i.product_code)
    LEFT JOIN `{$whTable}` w ON (w.id = i.warehouse_id OR w.{$whNameCol} = i.warehouse)
    WHERE i.id = '$id'
    LIMIT 1
";

$res = @mysqli_query($conn, $query);

if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['error'] = "Inventory item not found.";
    header("Location: index.php");
    exit();
}

$row = mysqli_fetch_assoc($res);

// 4. Fetch Active Bins of Current Warehouse
$activeBins = [];
$currentWhId = (int)$row['final_wh_id'];
$whNameEscaped = mysqli_real_escape_string($conn, $row['final_wh_name']);

$binSql = "SELECT bin_code, COALESCE(zone_name, 'General Zone') AS zone_name, COALESCE(capacity, max_units, max_capacity, 150) AS max_limit 
           FROM bin_locations 
           WHERE (warehouse_id = '$currentWhId' OR warehouse = '{$whNameEscaped}' OR warehouse_id IS NULL OR warehouse_id = 0)
             AND (LOWER(COALESCE(status, 'Active')) = 'active' OR status = '1' OR status IS NULL)
           ORDER BY bin_code ASC";
$binRes = @mysqli_query($conn, $binSql);
if ($binRes && mysqli_num_rows($binRes) > 0) {
    while ($b = mysqli_fetch_assoc($binRes)) {
        $activeBins[] = $b;
    }
} else {
    // Dynamic Standard Coordinates Fallback
    $floors = ['L0', 'L1'];
    $aisles = ['A1', 'A2', 'B1', 'B2', 'C1'];
    foreach ($floors as $fl) {
        foreach ($aisles as $ais) {
            for ($rack = 1; $rack <= 3; $rack++) {
                for ($shelf = 1; $shelf <= 2; $shelf++) {
                    $code = sprintf("%s-%s-%03d-%02d-A", $fl, $ais, $rack, $shelf);
                    $activeBins[] = [
                        'bin_code' => $code,
                        'zone_name' => 'Rack ' . $ais,
                        'max_limit' => 150
                    ];
                }
            }
        }
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Stock Inventory
            </h2>
            <p class="text-muted mb-0">Modifying Ledger Item: <code class="fw-bold text-primary font-monospace">#<?= $row['id']; ?></code></p>
        </div>
        <div class="d-flex gap-2">
            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-outline-info fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-eye me-1"></i> View Details
            </a>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Ledger
            </a>
        </div>
    </div>

    <!-- Main Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-9 col-lg-11 mx-auto mb-4">
        <div class="card-body p-4">
            
            <form action="save.php" method="POST">
                <input type="hidden" name="action" value="edit">
                <input type="hidden" name="id" value="<?= $row['id']; ?>">
                <input type="hidden" name="warehouse" id="warehouseNameHidden" value="<?= htmlspecialchars($row['final_wh_name']); ?>">

                <div class="row g-4">

                    <!-- SKU / Product Code -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Product Code / SKU *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-barcode text-muted"></i></span>
                            <input type="text" name="product_code" class="form-control border-2 bg-light font-monospace fw-bold text-primary" value="<?= htmlspecialchars($row['final_sku']); ?>" readonly>
                        </div>
                    </div>

                    <!-- Product Name -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Product Name *</label>
                        <input type="text" name="product_name" class="form-control border-2 fw-semibold" value="<?= htmlspecialchars($row['final_product_name']); ?>" required>
                    </div>

                    <!-- Target Warehouse Selector -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Target Facility Warehouse *</label>
                        <select name="warehouse_id" id="warehouseSelector" class="form-select border-2 fw-semibold" required onchange="loadWarehouseBins(this.value)">
                            <option value="">-- Select Warehouse --</option>
                            <?php foreach ($warehouses as $w): ?>
                                <option value="<?= $w['id']; ?>" data-name="<?= htmlspecialchars($w['warehouse_name']); ?>" <?= ($row['final_wh_id'] == $w['id'] || $row['final_wh_name'] == $w['warehouse_name']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($w['warehouse_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Target Bin Location Coordinate Selector -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Storage Bin Coordinate *</label>
                        <select name="bin_location" id="binSelector" class="form-select border-2 font-monospace fw-bold text-primary" required>
                            <option value="<?= htmlspecialchars($row['bin_location']); ?>" selected>
                                <?= htmlspecialchars($row['bin_location']); ?> (Current Assigned Bin)
                            </option>
                            <?php foreach ($activeBins as $b): ?>
                                <?php if ($b['bin_code'] !== $row['bin_location']): ?>
                                    <option value="<?= $b['bin_code']; ?>">
                                        <?= $b['bin_code']; ?> [<?= htmlspecialchars($b['zone_name'] ?? 'Zone'); ?> - Max <?= $b['max_limit']; ?> Units]
                                    </option>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </select>
                        <small id="binStatusHelp" class="text-muted">Storage coordinate allocation</small>
                    </div>

                    <!-- Quantities -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Available Quantity (Picking) *</label>
                        <div class="input-group">
                            <input type="number" name="available_qty" class="form-control border-2 fw-bold text-primary text-center fs-5" min="0" value="<?= (int)$row['available_qty']; ?>" required>
                            <span class="input-group-text bg-light border-2">Units</span>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Reserved Quantity (Allocated) *</label>
                        <div class="input-group">
                            <input type="number" name="reserved_qty" class="form-control border-2 fw-bold text-warning text-center fs-5" min="0" value="<?= (int)$row['reserved_qty']; ?>" required>
                            <span class="input-group-text bg-light border-2">Units</span>
                        </div>
                    </div>

                    <!-- Batch / Lot & Expiry -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Batch / Lot Tracking Number</label>
                        <input type="text" name="batch_no" class="form-control border-2 font-monospace text-uppercase" value="<?= htmlspecialchars($row['batch_no'] ?? ($row['batch_number'] ?? '')); ?>" placeholder="e.g. BATCH-2026-08">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Shelf Life / Expiry Date (FEFO)</label>
                        <input type="date" name="expiry_date" class="form-control border-2" value="<?= (!empty($row['expiry_date']) && $row['expiry_date'] !== '0000-00-00') ? $row['expiry_date'] : ''; ?>">
                    </div>

                </div>

                <div class="mt-4 pt-3 border-top text-end d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-light px-4 rounded-pill">Cancel</a>
                    <button type="submit" class="btn btn-warning px-5 fw-bold shadow-sm rounded-pill text-dark">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Update Stock Entry
                    </button>
                </div>

            </form>

        </div>
    </div>

</div>

<script>
function loadWarehouseBins(whId) {
    const whSel = document.getElementById('warehouseSelector');
    const hiddenName = document.getElementById('warehouseNameHidden');
    if (whSel.selectedIndex >= 0) {
        hiddenName.value = whSel.options[whSel.selectedIndex].getAttribute('data-name') || '';
    }

    if (!whId) return;

    fetch(`get_empty_bins.php?warehouse_id=${whId}`)
        .then(res => res.json())
        .then(data => {
            const binSelect = document.getElementById('binSelector');
            const currentVal = "<?= htmlspecialchars($row['bin_location']); ?>";
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
                document.getElementById('binStatusHelp').innerHTML = `<span class="text-success font-monospace">✔ ${data.length} Coordinates Loaded</span>`;
            } else {
                document.getElementById('binStatusHelp').innerHTML = `<span class="text-muted">No additional empty bins in this facility.</span>`;
            }
        })
        .catch(err => console.error("Error fetching bins:", err));
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>