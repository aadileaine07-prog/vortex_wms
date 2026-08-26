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

// 1. DYNAMIC & STRICT ACTIVE WAREHOUSE FETCHER (No Duplicates)
$warehouses = [];
if (isset($conn)) {
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
}

// 2. DETECT ALL AVAILABLE COLUMNS IN bin_locations TABLE
$tableCols = [];
$colRes = @mysqli_query($conn, "SHOW COLUMNS FROM `bin_locations`");
if ($colRes) {
    while ($col = mysqli_fetch_assoc($colRes)) {
        $tableCols[] = strtolower($col['Field']);
    }
}

// Helper to check column existence
function hasCol($colName, $cols) {
    return in_array(strtolower($colName), $cols);
}

// Determine actual column names
$zoneCol = hasCol('zone_name', $tableCols) ? 'zone_name' : (hasCol('zone', $tableCols) ? 'zone' : '');
$capCol  = hasCol('max_capacity', $tableCols) ? 'max_capacity' : (hasCol('max_capacity_kg', $tableCols) ? 'max_capacity_kg' : '');
$hasWhId = hasCol('warehouse_id', $tableCols);
$hasAisle = hasCol('aisle', $tableCols);
$hasRack  = hasCol('rack', $tableCols);
$hasShelf = hasCol('shelf', $tableCols);
$hasBin   = hasCol('bin', $tableCols);

// 3. HANDLE RANGE GENERATION (Pattern: L0-A1-001-01-A)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['generate_range'])) {
    $warehouseId = intval($_POST['warehouse_id'] ?? 0);
    $floorLevel  = strtoupper(trim($_POST['floor_level'] ?? 'L0'));
    $zone        = strtoupper(trim($_POST['zone_code'] ?? 'A1'));
    $aisleStart  = intval($_POST['aisle_start'] ?? 1);
    $aisleEnd    = intval($_POST['aisle_end'] ?? 1);
    $rackStart   = intval($_POST['rack_start'] ?? 1);
    $rackEnd     = intval($_POST['rack_end'] ?? 1);
    $shelfStart  = strtoupper(trim($_POST['shelf_start'] ?? 'A'));
    $shelfEnd    = strtoupper(trim($_POST['shelf_end'] ?? 'A'));
    $capacity    = floatval($_POST['default_capacity'] ?? 500);

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
                $binCode = "{$floorLevel}-{$zone}-{$aisleStr}-{$rackStr}-{$s}";

                // Duplicate Check
                $chk = mysqli_query($conn, "SELECT id FROM bin_locations WHERE bin_code = '$binCode'");
                if ($chk && mysqli_num_rows($chk) > 0) {
                    $skippedCount++;
                    continue;
                }

                $zoneName = "{$floorLevel}-{$zone}";

                // Dynamically build INSERT fields & values
                $fields = ["bin_code", "status"];
                $values = ["'$binCode'", "'Active'"];

                if (!empty($zoneCol)) {
                    $fields[] = "`$zoneCol`";
                    $values[] = "'$zoneName'";
                }
                if (!empty($capCol)) {
                    $fields[] = "`$capCol`";
                    $values[] = "'$capacity'";
                }
                if ($hasWhId) {
                    $fields[] = "`warehouse_id`";
                    $values[] = "'$warehouseId'";
                }
                if ($hasAisle) {
                    $fields[] = "`aisle`";
                    $values[] = "'Aisle $aisleStr'";
                }
                if ($hasRack) {
                    $fields[] = "`rack`";
                    $values[] = "'Rack $rackStr'";
                }
                if ($hasShelf) {
                    $fields[] = "`shelf`";
                    $values[] = "'Shelf $s'";
                }
                if ($hasBin) {
                    $fields[] = "`bin`";
                    $values[] = "'$s'";
                }

                $sql = "INSERT INTO bin_locations (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";

                if (mysqli_query($conn, $sql)) {
                    $insertedCount++;
                } else {
                    $skippedCount++;
                }
            }
        }
    }

    $_SESSION['bulk_msg'] = "Directly generated <strong>$insertedCount</strong> active bin locations! (Skipped: $skippedCount)";
    header("Location: index.php");
    exit();
}

// 4. HANDLE MANUAL MULTI-ROW SUBMISSION
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_manual_rows'])) {
    $warehouseId = intval($_POST['warehouse_id'] ?? 0);
    $binCodes    = $_POST['bin_code'] ?? [];
    $zones       = $_POST['zone'] ?? [];
    $aisles      = $_POST['aisle'] ?? [];
    $racks       = $_POST['rack'] ?? [];
    $shelves     = $_POST['shelf'] ?? [];
    $capacities  = $_POST['max_capacity_kg'] ?? [];

    if ($warehouseId <= 0) {
        $_SESSION['error'] = "Please select a valid active warehouse.";
        header("Location: bulk_add.php");
        exit();
    }

    $insertedCount = 0;
    $skippedCount  = 0;

    foreach ($binCodes as $idx => $rawCode) {
        $binCode = strtoupper(trim($rawCode));
        if (empty($binCode)) continue;

        $chk = mysqli_query($conn, "SELECT id FROM bin_locations WHERE bin_code = '$binCode'");
        if ($chk && mysqli_num_rows($chk) > 0) {
            $skippedCount++;
            continue;
        }

        $zoneVal  = mysqli_real_escape_string($conn, trim($zones[$idx] ?? 'L0-A1'));
        $aisleVal = mysqli_real_escape_string($conn, trim($aisles[$idx] ?? '001'));
        $rackVal  = mysqli_real_escape_string($conn, trim($racks[$idx] ?? '01'));
        $shelfVal = mysqli_real_escape_string($conn, trim($shelves[$idx] ?? 'A'));
        $capVal   = floatval($capacities[$idx] ?? 500);

        $fields = ["bin_code", "status"];
        $values = ["'$binCode'", "'Active'"];

        if (!empty($zoneCol)) {
            $fields[] = "`$zoneCol`";
            $values[] = "'$zoneVal'";
        }
        if (!empty($capCol)) {
            $fields[] = "`$capCol`";
            $values[] = "'$capVal'";
        }
        if ($hasWhId) {
            $fields[] = "`warehouse_id`";
            $values[] = "'$warehouseId'";
        }
        if ($hasAisle) {
            $fields[] = "`aisle`";
            $values[] = "'$aisleVal'";
        }
        if ($hasRack) {
            $fields[] = "`rack`";
            $values[] = "'$rackVal'";
        }
        if ($hasShelf) {
            $fields[] = "`shelf`";
            $values[] = "'$shelfVal'";
        }
        if ($hasBin) {
            $fields[] = "`bin`";
            $values[] = "'$shelfVal'";
        }

        $sql = "INSERT INTO bin_locations (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";

        if (mysqli_query($conn, $sql)) {
            $insertedCount++;
        } else {
            $skippedCount++;
        }
    }

    $_SESSION['bulk_msg'] = "Directly saved <strong>$insertedCount</strong> custom bin locations! (Skipped: $skippedCount)";
    header("Location: index.php");
    exit();
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-map-location-dot text-primary me-2"></i>Direct Bulk Location Generator
                </h2>
                <p class="text-muted mb-0">Generate warehouse bins in standard format: <code>L0-A1-001-01-A</code></p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3 fw-bold rounded-pill">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Locations
            </a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
                <i class="fa-solid fa-circle-exclamation me-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <ul class="nav nav-pills mb-4 gap-2" id="bulkTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-bold px-4 py-2 rounded-pill shadow-sm" id="range-tab" data-bs-toggle="pill" data-bs-target="#rangeTabPane" type="button">
                    <i class="fa-solid fa-wand-magic-sparkles me-2"></i>Smart Range Generator (L0-A1-001-01-A)
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold px-4 py-2 rounded-pill shadow-sm" id="manual-tab" data-bs-toggle="pill" data-bs-target="#manualTabPane" type="button">
                    <i class="fa-solid fa-table-cells-large me-2"></i>Direct Multi-Row Table Entry
                </button>
            </li>
        </ul>

        <div class="tab-content" id="bulkTabContent">
            
            <!-- OPTION 1: SMART RANGE GENERATOR -->
            <div class="tab-pane fade show active" id="rangeTabPane">
                <div class="card shadow-sm border-0 rounded-4 bg-white">
                    <div class="card-header bg-white border-0 pt-4 px-4">
                        <h5 class="fw-bold text-primary mb-1"><i class="fa-solid fa-sliders me-2"></i>Matrix Range Settings</h5>
                        <p class="text-muted small mb-0">Select an active facility and set grid bounds to create all permutations automatically.</p>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            <div class="row g-4">
                                
                                <div class="col-md-12">
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
                                            <i class="fa-solid fa-triangle-exclamation me-1"></i> No Active Warehouse found in database.
                                        </div>
                                    <?php endif; ?>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted">Floor / Level</label>
                                    <input type="text" name="floor_level" class="form-control text-uppercase fw-bold text-center" value="L0" maxlength="3" required>
                                </div>

                                <div class="col-md-2">
                                    <label class="form-label small fw-bold text-muted">Zone Code</label>
                                    <input type="text" name="zone_code" class="form-control text-uppercase fw-bold text-center" value="A1" maxlength="3" required>
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

                                <div class="col-md-12">
                                    <label class="form-label small fw-bold text-muted">Capacity per Bin (KG)</label>
                                    <input type="number" name="default_capacity" class="form-control text-center w-25" value="500" min="1" required>
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

            <!-- OPTION 2: MULTI-ROW FORM ENTRY -->
            <div class="tab-pane fade" id="manualTabPane">
                <div class="card shadow-sm border-0 rounded-4 bg-white">
                    <div class="card-header bg-white border-0 pt-4 px-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-table me-2 text-primary"></i>Custom Direct Table</h5>
                            <small class="text-muted">Type coordinate rows below</small>
                        </div>
                        <button type="button" class="btn btn-outline-success btn-sm fw-bold rounded-pill" onclick="addNewGridRow()">
                            <i class="fa-solid fa-plus me-1"></i> Add Another Row
                        </button>
                    </div>
                    <div class="card-body p-4">
                        <form method="POST">
                            
                            <div class="mb-3">
                                <label class="form-label small fw-bold text-muted">Select Target Active Warehouse *</label>
                                <?php if (!empty($warehouses)): ?>
                                    <select name="warehouse_id" class="form-select border-2 fw-semibold" required>
                                        <option value="">-- Choose Active Warehouse --</option>
                                        <?php foreach ($warehouses as $wh): ?>
                                            <option value="<?= $wh['id']; ?>"><?= htmlspecialchars($wh['warehouse_name']); ?> (<?= htmlspecialchars($wh['warehouse_code'] ?? 'WH'); ?>)</option>
                                        <?php endforeach; ?>
                                    </select>
                                <?php else: ?>
                                    <div class="alert alert-warning py-2 mb-0 small">
                                        <i class="fa-solid fa-triangle-exclamation me-1"></i> No Active Warehouse found.
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-bordered align-middle" id="manualTable">
                                    <thead class="table-light">
                                        <tr>
                                            <th style="width: 25%;">Bin Code (L0-A1-001-01-A) *</th>
                                            <th>Zone</th>
                                            <th>Aisle</th>
                                            <th>Rack</th>
                                            <th>Shelf</th>
                                            <th>Cap (KG)</th>
                                            <th style="width: 50px;">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td><input type="text" name="bin_code[]" class="form-control font-monospace text-uppercase" placeholder="L0-A1-001-01-A" required></td>
                                            <td><input type="text" name="zone[]" class="form-control" placeholder="L0-A1" value="L0-A1"></td>
                                            <td><input type="text" name="aisle[]" class="form-control" placeholder="001" value="001"></td>
                                            <td><input type="text" name="rack[]" class="form-control" placeholder="01" value="01"></td>
                                            <td><input type="text" name="shelf[]" class="form-control" placeholder="A" value="A"></td>
                                            <td><input type="number" name="max_capacity_kg[]" class="form-control text-center" value="500"></td>
                                            <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeGridRow(this)"><i class="fa-solid fa-trash"></i></button></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="mt-4 text-end d-flex justify-content-between align-items-center">
                                <button type="button" class="btn btn-light fw-bold" onclick="addNewGridRow()">+ Add Row</button>
                                <button type="submit" name="save_manual_rows" class="btn btn-success px-5 py-2 fw-bold shadow-sm rounded-pill" <?= empty($warehouses) ? 'disabled' : ''; ?>>
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save Locations
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<script>
function addNewGridRow() {
    const table = document.querySelector('#manualTable tbody');
    const tr = document.createElement('tr');
    tr.innerHTML = `
        <td><input type="text" name="bin_code[]" class="form-control font-monospace text-uppercase" placeholder="L0-A1-001-01-A" required></td>
        <td><input type="text" name="zone[]" class="form-control" placeholder="L0-A1" value="L0-A1"></td>
        <td><input type="text" name="aisle[]" class="form-control" placeholder="001" value="001"></td>
        <td><input type="text" name="rack[]" class="form-control" placeholder="01" value="01"></td>
        <td><input type="text" name="shelf[]" class="form-control" placeholder="A" value="A"></td>
        <td><input type="number" name="max_capacity_kg[]" class="form-control text-center" value="500"></td>
        <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm" onclick="removeGridRow(this)"><i class="fa-solid fa-trash"></i></button></td>
    `;
    table.appendChild(tr);
}

function removeGridRow(btn) {
    const rows = document.querySelectorAll('#manualTable tbody tr');
    if (rows.length > 1) {
        btn.closest('tr').remove();
    } else {
        alert("At least one row is required.");
    }
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>