<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch Strictly Active Warehouses
$warehouses = [];
$whTable = "warehouse";
$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouse'");
if (!$chk || mysqli_num_rows($chk) == 0) {
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

$whSql = "SELECT MIN(id) AS id, `{$nameCol}` AS warehouse_name, `{$codeCol}` AS warehouse_code 
          FROM `{$whTable}` 
          WHERE (LOWER(status) = 'active' OR status = '1')
          GROUP BY `{$nameCol}`, `{$codeCol}`
          ORDER BY id ASC";
          
$whRes = @mysqli_query($conn, $whSql);
if ($whRes && mysqli_num_rows($whRes) > 0) {
    while ($r = mysqli_fetch_assoc($whRes)) {
        $warehouses[] = $r;
    }
}

// Check Column Schema
$tableCols = [];
$colRes = @mysqli_query($conn, "SHOW COLUMNS FROM `bin_locations`");
if ($colRes) {
    while ($col = mysqli_fetch_assoc($colRes)) {
        $tableCols[] = strtolower($col['Field']);
    }
}

function hasCol($colName, $cols) { return in_array(strtolower($colName), $cols); }

$hasZoneName  = hasCol('zone_name', $tableCols);
$hasZone      = hasCol('zone', $tableCols);
$hasWeight    = hasCol('max_weight_kg', $tableCols);
$hasUnits     = hasCol('max_units', $tableCols);
$hasMaxCap    = hasCol('max_capacity', $tableCols);
$hasMaxCapKg  = hasCol('max_capacity_kg', $tableCols);
$hasWhId      = hasCol('warehouse_id', $tableCols);

// 1. HANDLE SMART RANGE GENERATION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_range'])) {
    $warehouseId   = intval($_POST['warehouse_id'] ?? 0);
    $zoneCategory  = trim($_POST['zone_category'] ?? 'Zone A');
    $floorLevel    = strtoupper(trim($_POST['floor_level'] ?? 'L0'));
    $areaCode      = strtoupper(trim($_POST['area_code'] ?? 'A1'));
    $aisleStart    = intval($_POST['aisle_start'] ?? 1);
    $aisleEnd      = intval($_POST['aisle_end'] ?? 1);
    $rackStart     = intval($_POST['rack_start'] ?? 1);
    $rackEnd       = intval($_POST['rack_end'] ?? 1);
    $shelfStart    = strtoupper(trim($_POST['shelf_start'] ?? 'A'));
    $shelfEnd      = strtoupper(trim($_POST['shelf_end'] ?? 'A'));
    $maxWeight     = floatval($_POST['max_weight_kg'] ?? 500);
    $maxUnits      = intval($_POST['max_units'] ?? 100);

    if ($warehouseId <= 0) {
        $_SESSION['error'] = "Please select a valid active warehouse.";
        header("Location: bulk_add.php");
        exit();
    }

    $shelvesList   = range($shelfStart, $shelfEnd);
    $insertedCount = 0;
    $skippedCount  = 0;

    for ($a = $aisleStart; $a <= $aisleEnd; $a++) {
        $aisleStr = str_pad($a, 3, '0', STR_PAD_LEFT);
        for ($r = $rackStart; $r <= $rackEnd; $r++) {
            $rackStr = str_pad($r, 2, '0', STR_PAD_LEFT);
            foreach ($shelvesList as $s) {
                $binCode = "{$floorLevel}-{$areaCode}-{$aisleStr}-{$rackStr}-{$s}";

                // Duplicate Check
                $chk = mysqli_query($conn, "SELECT id FROM bin_locations WHERE bin_code = '$binCode'");
                if ($chk && mysqli_num_rows($chk) > 0) {
                    $skippedCount++;
                    continue;
                }

                $fields = ["bin_code", "status"];
                $values = ["'$binCode'", "'Active'"];

                if ($hasZoneName)  { $fields[] = "`zone_name`"; $values[] = "'$zoneCategory'"; }
                elseif ($hasZone)  { $fields[] = "`zone`"; $values[] = "'$zoneCategory'"; }

                if ($hasWeight)    { $fields[] = "`max_weight_kg`"; $values[] = "'$maxWeight'"; }
                if ($hasUnits)     { $fields[] = "`max_units`"; $values[] = "'$maxUnits'"; }
                if ($hasMaxCap)    { $fields[] = "`max_capacity`"; $values[] = "'$maxUnits'"; }
                if ($hasMaxCapKg)  { $fields[] = "`max_capacity_kg`"; $values[] = "'$maxWeight'"; }
                if ($hasWhId)      { $fields[] = "`warehouse_id`"; $values[] = "'$warehouseId'"; }

                $sql = "INSERT INTO bin_locations (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";

                if (mysqli_query($conn, $sql)) {
                    $insertedCount++;
                } else {
                    $skippedCount++;
                }
            }
        }
    }

    if ($insertedCount > 0) {
        $_SESSION['success'] = "Generated <strong>$insertedCount</strong> new bins (Weight: {$maxWeight}KG, Units: {$maxUnits})! (Skipped: <strong>$skippedCount</strong>)";
    } else {
        $_SESSION['error'] = "All <strong>$skippedCount</strong> bin codes already exist in database!";
    }

    header("Location: index.php");
    exit();
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-map-location-dot text-primary me-2"></i>Direct Bulk Location Generator
            </h2>
            <p class="text-muted mb-0">Auto-create matrix with separated <strong>Zone Groups</strong> & <strong>Weight + Unit Limits</strong></p>
        </div>
        <a href="index.php" class="btn btn-secondary px-3 fw-bold rounded-pill">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Locations
        </a>
    </div>

    <div class="card shadow-sm border-0 rounded-4 bg-white">
        <div class="card-header bg-white border-0 pt-4 px-4">
            <h5 class="fw-bold text-primary mb-1"><i class="fa-solid fa-sliders me-2"></i>Matrix Range Settings</h5>
            <p class="text-muted small mb-0">Coordinate pattern and independent zone categories will be assigned uniformly.</p>
        </div>
        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-4">
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Select Target Active Warehouse *</label>
                        <?php if (!empty($warehouses)): ?>
                            <select name="warehouse_id" class="form-select border-2 fw-semibold" required>
                                <option value="">-- Choose Active Warehouse --</option>
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?= $wh['id']; ?>">
                                        <?= htmlspecialchars($wh['warehouse_name']); ?> (<?= htmlspecialchars($wh['warehouse_code'] ?? 'WH'); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        <?php else: ?>
                            <div class="alert alert-warning py-2 mb-0 small">
                                <i class="fa-solid fa-triangle-exclamation me-1"></i> No Active Warehouse found.
                            </div>
                        <?php endif; ?>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Assign Zone Category (Independent) *</label>
                        <select name="zone_category" class="form-select border-2 fw-semibold" required>
                            <option value="Zone A" selected>Zone A (Standard Racks)</option>
                            <option value="Zone B">Zone B (High Density)</option>
                            <option value="Zone C">Zone C (Fast Moving)</option>
                            <option value="Zone D">Zone D (Heavy Items)</option>
                            <option value="Zone E">Zone E (Cold Storage)</option>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Floor / Level</label>
                        <input type="text" name="floor_level" class="form-control text-uppercase fw-bold text-center" value="L0" maxlength="3" required>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Area Code</label>
                        <input type="text" name="area_code" class="form-control text-uppercase fw-bold text-center" value="A1" maxlength="3" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Aisle Range (3 Digits)</label>
                        <div class="input-group">
                            <input type="number" name="aisle_start" class="form-control text-center" value="1" min="1" max="999" required>
                            <span class="input-group-text">to</span>
                            <input type="number" name="aisle_end" class="form-control text-center" value="5" min="1" max="999" required>
                        </div>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Rack Range (2 Digits)</label>
                        <div class="input-group">
                            <input type="number" name="rack_start" class="form-control text-center" value="1" min="1" max="99" required>
                            <span class="input-group-text">to</span>
                            <input type="number" name="rack_end" class="form-control text-center" value="4" min="1" max="99" required>
                        </div>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Shelf (Levels)</label>
                        <div class="input-group">
                            <input type="text" name="shelf_start" class="form-control text-center text-uppercase fw-bold" value="A" maxlength="1" required>
                            <span class="input-group-text">to</span>
                            <input type="text" name="shelf_end" class="form-control text-center text-uppercase fw-bold" value="D" maxlength="1" required>
                        </div>
                    </div>

                    <!-- Dual Capacity -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Max Weight per Bin (KG) *</label>
                        <input type="number" name="max_weight_kg" class="form-control text-center fw-bold" value="500" min="1" step="0.5" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Max Units per Bin (Quantity) *</label>
                        <input type="number" name="max_units" class="form-control text-center fw-bold" value="100" min="1" required>
                    </div>

                </div>

                <div class="mt-4 pt-3 border-top text-end">
                    <button type="submit" name="generate_range" class="btn btn-primary px-5 py-2 fw-bold shadow-sm rounded-pill" <?= empty($warehouses) ? 'disabled' : ''; ?>>
                        <i class="fa-solid fa-bolt me-1"></i> Generate & Save All Bins
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>