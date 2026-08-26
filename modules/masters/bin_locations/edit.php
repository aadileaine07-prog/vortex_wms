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

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);
$error = "";

// 1. Fetch Location Data
$stmt = $conn->prepare("SELECT * FROM bin_locations WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    die("Location record not found.");
}

// 2. Dynamic Warehouse Table & Column Detection
$whTable = "warehouses";
$whChk = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$whChk || mysqli_num_rows($whChk) == 0) {
    $whTable = "warehouse";
}

$nameCol = "warehouse_name";
$colChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$colChk || mysqli_num_rows($colChk) == 0) {
    $nameCol = "name";
}

$codeCol = "warehouse_code";
$codeChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_code'");
if (!$codeChk || mysqli_num_rows($codeChk) == 0) {
    $codeCol = "code";
}

// 3. Fetch ONLY STRICTLY ACTIVE Warehouses
$warehouses = [];
$whQuery = "SELECT id, `{$nameCol}` AS warehouse_name, `{$codeCol}` AS warehouse_code, status 
            FROM `{$whTable}` 
            WHERE LOWER(status) = 'active' OR status = '1' 
            ORDER BY warehouse_name ASC";
$whRes = @mysqli_query($conn, $whQuery);
if ($whRes && mysqli_num_rows($whRes) > 0) {
    while ($r = mysqli_fetch_assoc($whRes)) {
        $warehouses[] = $r;
    }
}

// 4. Parse Existing Bin Code (Pattern: L0-A1-001-01-A)
$binCodeParts = explode('-', $row['bin_code'] ?? '');
$currentLevel = $binCodeParts[0] ?? 'L0';
$currentZone  = $binCodeParts[1] ?? 'A1';
$currentAisle = $binCodeParts[2] ?? ($row['aisle'] ?? '001');
$currentRack  = $binCodeParts[3] ?? ($row['rack'] ?? '01');
$currentShelf = $binCodeParts[4] ?? ($row['shelf'] ?? 'A');

// Handle Update Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_location'])) {
    $bin_code        = strtoupper(trim($_POST['bin_code'] ?? ''));
    $level_floor     = strtoupper(trim($_POST['level_floor'] ?? 'L0'));
    $zone            = strtoupper(trim($_POST['zone'] ?? 'A1'));
    $aisle           = str_pad(trim($_POST['aisle'] ?? '1'), 3, '0', STR_PAD_LEFT);
    $rack            = str_pad(trim($_POST['rack'] ?? '1'), 2, '0', STR_PAD_LEFT);
    $shelf           = strtoupper(trim($_POST['shelf'] ?? 'A'));
    $max_capacity_kg = floatval($_POST['max_capacity_kg'] ?? 500);
    $status          = trim($_POST['status'] ?? 'Active');
    $warehouse_id    = intval($_POST['warehouse_id'] ?? 0);

    if (empty($bin_code)) {
        $error = "Bin Code cannot be empty.";
    } elseif ($warehouse_id <= 0) {
        $error = "Please select an active warehouse.";
    } else {
        // Check duplicate code excluding current record
        $chk = $conn->prepare("SELECT id FROM bin_locations WHERE bin_code = ? AND id != ?");
        $chk->bind_param("si", $bin_code, $id);
        $chk->execute();
        $chk->store_result();

        if ($chk->num_rows > 0) {
            $error = "Bin Code <strong>" . htmlspecialchars($bin_code) . "</strong> is already registered!";
        } else {
            $hasWhCol = false;
            $colChk = @mysqli_query($conn, "SHOW COLUMNS FROM bin_locations LIKE 'warehouse_id'");
            if ($colChk && mysqli_num_rows($colChk) > 0) {
                $hasWhCol = true;
            }

            $zoneLabel = "{$level_floor}-{$zone}";

            if ($hasWhCol) {
                $stmt = $conn->prepare("UPDATE bin_locations SET bin_code = ?, zone = ?, aisle = ?, rack = ?, shelf = ?, bin = ?, max_capacity_kg = ?, status = ?, warehouse_id = ? WHERE id = ?");
                $stmt->bind_param("ssssssdssi", $bin_code, $zoneLabel, $aisle, $rack, $shelf, $shelf, $max_capacity_kg, $status, $warehouse_id, $id);
            } else {
                $stmt = $conn->prepare("UPDATE bin_locations SET bin_code = ?, zone = ?, aisle = ?, rack = ?, shelf = ?, bin = ?, max_capacity_kg = ?, status = ? WHERE id = ?");
                $stmt->bind_param("ssssssdsi", $bin_code, $zoneLabel, $aisle, $rack, $shelf, $shelf, $max_capacity_kg, $status, $id);
            }

            if ($stmt->execute()) {
                $_SESSION['success'] = "Bin Location <strong>" . htmlspecialchars($bin_code) . "</strong> updated successfully!";
                header("Location: index.php");
                exit();
            } else {
                $error = "Error updating location: " . $conn->error;
            }
            $stmt->close();
        }
        $chk->close();
    }
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
                    <i class="fa-solid fa-pen-to-square text-primary me-2"></i>Edit Bin Location
                </h2>
                <p class="text-muted mb-0">Update storage bin coordinates & parameters (Pattern: <code>L0-A1-001-01-A</code>)</p>
            </div>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Locations
            </a>
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

                        <!-- STRICTLY ACTIVE WAREHOUSES ONLY -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Active Target Warehouse *</label>
                            <?php if (!empty($warehouses)): ?>
                                <select name="warehouse_id" class="form-select border-2 fw-semibold" required>
                                    <option value="">-- Choose Active Warehouse --</option>
                                    <?php foreach ($warehouses as $wh): ?>
                                        <option value="<?= $wh['id']; ?>" <?= (($row['warehouse_id'] ?? '') == $wh['id']) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($wh['warehouse_name'] ?? 'Facility'); ?> (<?= htmlspecialchars($wh['warehouse_code'] ?? 'WH'); ?>)
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            <?php else: ?>
                                <div class="alert alert-warning py-2 mb-0 small">
                                    <i class="fa-solid fa-triangle-exclamation me-1"></i> No Active Warehouse found. Please activate one in Masters.
                                </div>
                            <?php endif; ?>
                        </div>

                        <!-- Status -->
                        <div class="col-md-6">
                            <label class="form-label small fw-bold text-muted">Status *</label>
                            <select name="status" class="form-select border-2 fw-semibold">
                                <option value="Active" <?= (($row['status'] ?? 'Active') === 'Active') ? 'selected' : ''; ?>>Active (Ready for Putaway)</option>
                                <option value="Inactive" <?= (($row['status'] ?? '') === 'Inactive') ? 'selected' : ''; ?>>Inactive / Under Maintenance</option>
                            </select>
                        </div>

                        <!-- Coordinate Components (Null-Safe) -->
                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Floor / Level *</label>
                            <input type="text" id="levelInput" name="level_floor" class="form-control text-uppercase text-center fw-bold" value="<?= htmlspecialchars($currentLevel ?? 'L0'); ?>" maxlength="3" required oninput="generateBinCode()">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Zone Code *</label>
                            <input type="text" id="zoneInput" name="zone" class="form-control text-uppercase text-center fw-bold" value="<?= htmlspecialchars($currentZone ?? 'A1'); ?>" maxlength="3" required oninput="generateBinCode()">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Aisle (3 Digits) *</label>
                            <input type="number" id="aisleInput" name="aisle" class="form-control text-center font-monospace" value="<?= intval($currentAisle ?? 1); ?>" min="1" max="999" required oninput="generateBinCode()">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Rack (2 Digits) *</label>
                            <input type="number" id="rackInput" name="rack" class="form-control text-center font-monospace" value="<?= intval($currentRack ?? 1); ?>" min="1" max="99" required oninput="generateBinCode()">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Shelf (Level) *</label>
                            <input type="text" id="shelfInput" name="shelf" class="form-control text-uppercase text-center font-monospace fw-bold" value="<?= htmlspecialchars($currentShelf ?? 'A'); ?>" maxlength="2" required oninput="generateBinCode()">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label small fw-bold text-muted">Max Capacity (KG)</label>
                            <input type="number" name="max_capacity_kg" class="form-control text-center" value="<?= floatval($row['max_capacity_kg'] ?? 500); ?>" min="1" step="0.5" required>
                        </div>

                        <!-- Full Bin Code Preview (Null-Safe) -->
                        <div class="col-md-12">
                            <div class="p-3 bg-light rounded-4 border d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <div>
                                    <label class="form-label small fw-bold text-muted mb-0">Calculated Bin Code</label>
                                    <div class="small text-muted">Pattern: <code>LEVEL-ZONE-AISLE-RACK-SHELF</code></div>
                                </div>
                                <div style="min-width: 260px;">
                                    <input type="text" id="binCodeFinal" name="bin_code" class="form-control font-monospace text-uppercase fw-bold fs-4 text-primary text-center" value="<?= htmlspecialchars($row['bin_code'] ?? ''); ?>" required>
                                </div>
                            </div>
                        </div>

                    </div>

                    <div class="mt-4 pt-3 border-top text-end d-flex justify-content-end gap-2">
                        <a href="index.php" class="btn btn-light px-4 rounded-pill">Cancel</a>
                        <button type="submit" name="update_location" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill" <?= empty($warehouses) ? 'disabled' : ''; ?>>
                            <i class="fa-solid fa-floppy-disk me-1"></i> Update Location
                        </button>
                    </div>
                </form>
            </div>
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