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

$error = "";

// 1. Fetch Strictly Active Warehouses
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

// 2. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_location'])) {
    $warehouse_id  = intval($_POST['warehouse_id'] ?? 0);
    $bin_code      = strtoupper(trim($_POST['bin_code'] ?? ''));
    $zone_category = strtoupper(trim($_POST['zone_category'] ?? 'Zone A'));
    $max_weight_kg = floatval($_POST['max_weight_kg'] ?? 500);
    $max_units     = intval($_POST['max_units'] ?? 100);
    $status        = trim($_POST['status'] ?? 'Active');

    if (empty($bin_code)) {
        $error = "Bin Code is required.";
    } elseif ($warehouse_id <= 0) {
        $error = "Please select an Active Warehouse.";
    } else {
        // Pre-check duplicate
        $chkStmt = $conn->prepare("SELECT id FROM bin_locations WHERE bin_code = ?");
        $chkStmt->bind_param("s", $bin_code);
        $chkStmt->execute();
        $chkStmt->store_result();

        if ($chkStmt->num_rows > 0) {
            $error = "⚠️ Duplicate Error: Bin Code <strong>" . htmlspecialchars($bin_code) . "</strong> already exists!";
            $chkStmt->close();
        } else {
            $chkStmt->close();

            // Detect available columns
            $colRes = mysqli_query($conn, "SHOW COLUMNS FROM bin_locations");
            $cols = [];
            while ($c = mysqli_fetch_assoc($colRes)) { $cols[] = strtolower($c['Field']); }

            $fields = ["bin_code", "status", "warehouse_id"];
            $values = ["'$bin_code'", "'$status'", "'$warehouse_id'"];

            // Zone Name handling
            if (in_array('zone_name', $cols)) { $fields[] = '`zone_name`'; $values[] = "'$zone_category'"; }
            elseif (in_array('zone', $cols)) { $fields[] = '`zone`'; $values[] = "'$zone_category'"; }

            // Weight & Units Capacity handling
            if (in_array('max_weight_kg', $cols)) { $fields[] = '`max_weight_kg`'; $values[] = "'$max_weight_kg'"; }
            if (in_array('max_units', $cols)) { $fields[] = '`max_units`'; $values[] = "'$max_units'"; }
            if (in_array('max_capacity', $cols)) { $fields[] = '`max_capacity`'; $values[] = "'$max_units'"; }
            if (in_array('max_capacity_kg', $cols)) { $fields[] = '`max_capacity_kg`'; $values[] = "'$max_weight_kg'"; }

            $insertSql = "INSERT INTO bin_locations (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";

            if (mysqli_query($conn, $insertSql)) {
                $_SESSION['success'] = "Bin Location <strong>" . htmlspecialchars($bin_code) . "</strong> created with {$max_weight_kg} KG & {$max_units} Units capacity!";
                header("Location: index.php");
                exit();
            } else {
                $error = "Database Insert Error: " . mysqli_error($conn);
            }
        }
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-location-dot text-primary me-2"></i>Create New Bin Location
            </h2>
            <p class="text-muted mb-0">Format: <code>LEVEL-ZONE-AISLE-RACK-SHELF</code> | Dual Limits: <strong>Weight (KG) & Units (Qty)</strong></p>
        </div>
        <div class="d-flex gap-2">
            <a href="bulk_add.php" class="btn btn-outline-primary fw-bold shadow-sm rounded-pill">
                <i class="fa-solid fa-layer-group me-1"></i> Direct Bulk Generator
            </a>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Locations
            </a>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-exclamation me-2"></i> <?= $error; ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 bg-white">
        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-4">

                    <!-- Warehouse Selection -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Select Active Warehouse *</label>
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

                    <!-- Independent Zone Category Selection -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Zone Category (Independent) *</label>
                        <select name="zone_category" class="form-select border-2 fw-semibold" required>
                            <option value="Zone A" selected>Zone A (Standard Racking)</option>
                            <option value="Zone B">Zone B (High Density)</option>
                            <option value="Zone C">Zone C (Fast Moving)</option>
                            <option value="Zone D">Zone D (Heavy Pallets)</option>
                            <option value="Zone E">Zone E (Cold / Secure)</option>
                        </select>
                        <small class="text-muted">Alag Zone grouping categorize karne ke liye</small>
                    </div>

                    <!-- Bin Status -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Bin Status *</label>
                        <select name="status" class="form-select border-2 fw-semibold">
                            <option value="Active" selected>Active (Available for Putaway)</option>
                            <option value="Inactive">Inactive / Under Maintenance</option>
                        </select>
                    </div>

                    <!-- 5-Part Pattern Inputs -->
                    <div class="col-md-12">
                        <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-barcode me-2"></i>Coordinate Pattern Matrix</h6>
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Floor / Level *</label>
                        <input type="text" id="levelInput" class="form-control text-uppercase text-center fw-bold" value="L0" maxlength="3" required oninput="generateBinCode()">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Area Code *</label>
                        <input type="text" id="zoneInput" class="form-control text-uppercase text-center fw-bold" value="A1" maxlength="3" required oninput="generateBinCode()">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Aisle (3 Digits) *</label>
                        <input type="number" id="aisleInput" class="form-control text-center font-monospace" value="1" min="1" max="999" required oninput="generateBinCode()">
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Rack (2 Digits) *</label>
                        <input type="number" id="rackInput" class="form-control text-center font-monospace" value="1" min="1" max="99" required oninput="generateBinCode()">
                    </div>

                    <div class="col-md-2">
                        <label class="form-label small fw-bold text-muted">Shelf (Level) *</label>
                        <input type="text" id="shelfInput" class="form-control text-uppercase text-center font-monospace fw-bold" value="A" maxlength="2" required oninput="generateBinCode()">
                    </div>

                    <!-- Capacity Limits: Weight + Units -->
                    <div class="col-md-12">
                        <h6 class="fw-bold text-primary mb-2"><i class="fa-solid fa-weight-scale me-2"></i>Storage Capacity Limits</h6>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <label class="form-label small fw-bold text-dark">Max Weight Capacity (KG) *</label>
                            <div class="input-group">
                                <input type="number" name="max_weight_kg" class="form-control fw-bold text-center" value="500" min="1" step="0.5" required>
                                <span class="input-group-text bg-white">KG</span>
                            </div>
                            <small class="text-muted">Is weight se zyada heavy items putaway nahi honge</small>
                        </div>
                    </div>

                    <div class="col-md-6">
                        <div class="p-3 bg-light rounded-3 border">
                            <label class="form-label small fw-bold text-dark">Max Units / Items Capacity (Qty) *</label>
                            <div class="input-group">
                                <input type="number" name="max_units" class="form-control fw-bold text-center" value="100" min="1" required>
                                <span class="input-group-text bg-white">Units</span>
                            </div>
                            <small class="text-muted">Is bin me total kitne individual items fit ho sakte hain</small>
                        </div>
                    </div>

                    <!-- Final Bin Code Preview -->
                    <div class="col-md-12">
                        <div class="p-3 bg-primary bg-opacity-10 rounded-4 border border-primary border-opacity-25 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <label class="form-label small fw-bold text-primary mb-0">Generated Coordinate Code</label>
                                <div class="small text-muted">Auto-formatted: <code>[Level]-[Area]-[Aisle]-[Rack]-[Shelf]</code></div>
                            </div>
                            <div style="min-width: 260px;">
                                <input type="text" id="binCodeFinal" name="bin_code" class="form-control font-monospace text-uppercase fw-bold fs-4 text-primary text-center bg-white" value="L0-A1-001-01-A" required readonly>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="mt-4 pt-3 border-top text-end d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-light px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="save_location" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill" <?= empty($warehouses) ? 'disabled' : ''; ?>>
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Bin Location
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function generateBinCode() {
    const level = (document.getElementById('levelInput').value || 'L0').toUpperCase().trim();
    const zone  = (document.getElementById('zoneInput').value || 'A1').toUpperCase().trim();
    
    let aisleVal = parseInt(document.getElementById('aisleInput').value) || 1;
    const aisle = String(aisleVal).padStart(3, '0');

    let rackVal = parseInt(document.getElementById('rackInput').value) || 1;
    const rack = String(rackVal).padStart(2, '0');

    const shelf = (document.getElementById('shelfInput').value || 'A').toUpperCase().trim();

    document.getElementById('binCodeFinal').value = `${level}-${zone}-${aisle}-${rack}-${shelf}`;
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>