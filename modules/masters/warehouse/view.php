<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = "Warehouse ID is missing.";
    header("Location: index.php");
    exit();
}

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

$whCodeCol = "warehouse_code";
$cChkCode = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_code'");
if (!$cChkCode || mysqli_num_rows($cChkCode) === 0) {
    $whCodeCol = "code";
}

$whLocCol = "address";
$cChkLoc = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'address'");
if (!$cChkLoc || mysqli_num_rows($cChkLoc) === 0) {
    $whLocCol = "location";
}

// Fetch Warehouse Details
$sql = "SELECT id, {$whCodeCol} AS wh_code, {$whNameCol} AS wh_name, COALESCE({$whLocCol}, 'Central Logistics Park') AS wh_location, COALESCE(status, 'Active') AS status FROM `{$whTable}` WHERE id = '$id' LIMIT 1";
$res = mysqli_query($conn, $sql);

if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['error'] = "Warehouse facility not found.";
    header("Location: index.php");
    exit();
}

$wh = mysqli_fetch_assoc($res);
$whName = mysqli_real_escape_string($conn, $wh['wh_name']);

// Bins in this warehouse
$binsRes = mysqli_query($conn, "SELECT * FROM bin_locations WHERE warehouse_id = '$id' OR warehouse = '$whName' ORDER BY bin_code ASC");

// Live Stock in this warehouse
$stockRes = mysqli_query($conn, "SELECT SUM(available_qty) AS tot_qty, COUNT(DISTINCT product_code) AS tot_skus FROM inventory WHERE warehouse_id = '$id' OR warehouse = '$whName'");
$stockStats = ($stockRes) ? mysqli_fetch_assoc($stockRes) : [];
$totalUnits = (int)($stockStats['tot_qty'] ?? 0);
$totalSKUs  = (int)($stockStats['tot_skus'] ?? 0);

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-warehouse text-primary me-2"></i>Warehouse Facility Details</h2>
            <p class="text-muted mb-0">Facility Identifier: <code class="fw-bold text-primary font-monospace"><?= htmlspecialchars($wh['wh_code']); ?></code></p>
        </div>
        <div class="d-flex gap-2">
            <a href="edit.php?id=<?= $wh['id']; ?>" class="btn btn-warning fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Facility
            </a>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Facilities
            </a>
        </div>
    </div>

    <!-- Facility Overview Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100">
                <div class="d-flex align-items-center mb-3">
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4 me-3">
                        <i class="fa-solid fa-building-circle-check fa-2x"></i>
                    </div>
                    <div>
                        <small class="text-muted fw-bold">WAREHOUSE NAME</small>
                        <h5 class="fw-bold text-dark mb-0"><?= htmlspecialchars($wh['wh_name']); ?></h5>
                    </div>
                </div>
                <hr>
                <div class="small mb-2"><strong class="text-muted">Code:</strong> <code class="fw-bold font-monospace"><?= htmlspecialchars($wh['wh_code']); ?></code></div>
                <div class="small mb-2"><strong class="text-muted">Address:</strong> <?= htmlspecialchars($wh['wh_location']); ?></div>
                <div class="small"><strong class="text-muted">Status:</strong> <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill"><?= htmlspecialchars($wh['status']); ?></span></div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100 border-start border-4 border-success">
                <small class="text-muted fw-bold text-uppercase">TOTAL STORED UNITS</small>
                <h2 class="fw-bold text-success my-2"><?= number_format($totalUnits); ?> <span class="fs-6 text-muted fw-normal">Units</span></h2>
                <small class="text-muted"><i class="fa-solid fa-boxes-stacked me-1"></i><?= $totalSKUs; ?> Unique SKUs currently stored</small>
            </div>
        </div>

        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-4 bg-white h-100 border-start border-4 border-primary">
                <small class="text-muted fw-bold text-uppercase">CONFIGURED STORAGE BINS</small>
                <h2 class="fw-bold text-primary my-2"><?= ($binsRes) ? mysqli_num_rows($binsRes) : 0; ?> <span class="fs-6 text-muted fw-normal">Bins</span></h2>
                <small class="text-muted"><a href="/vortex_wms/modules/masters/bin_locations/bulk_add.php" class="text-decoration-none">+ Add More Bins</a></small>
            </div>
        </div>
    </div>

    <!-- Storage Bins List -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-location-dot text-primary me-2"></i>Assigned Rack Bins</h5>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Bin Code</th>
                            <th>Zone</th>
                            <th class="text-center">Max Capacity</th>
                            <th class="text-center">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($binsRes && mysqli_num_rows($binsRes) > 0): ?>
                            <?php while ($b = mysqli_fetch_assoc($binsRes)): ?>
                                <tr>
                                    <td><code class="fw-bold text-primary font-monospace fs-6"><?= htmlspecialchars($b['bin_code']); ?></code></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($b['zone_name'] ?? 'Zone'); ?></span></td>
                                    <td class="text-center font-monospace"><?= (int)($b['capacity'] ?? ($b['max_capacity'] ?? 150)); ?> Units</td>
                                    <td class="text-center"><span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Active</span></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No storage bins created for this warehouse facility yet.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>