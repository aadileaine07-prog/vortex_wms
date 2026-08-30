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

/* ==========================================================================
   1. DYNAMIC WAREHOUSE SCHEMA & DATA RESOLUTION
   ========================================================================== */

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

// Fetch Real Database Rows Only
$sql = "
    SELECT 
        id,
        {$whCodeCol} AS wh_code,
        {$whNameCol} AS wh_name,
        COALESCE({$whLocCol}, 'Central Logistics Park') AS wh_location,
        COALESCE(status, 'Active') AS status
    FROM `{$whTable}`
    ORDER BY id ASC
";
$result = mysqli_query($conn, $sql);

$warehouses = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($r = mysqli_fetch_assoc($result)) {
        $warehouses[] = $r;
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Header & Action Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-warehouse text-primary me-2"></i>Warehouse Facilities Master
            </h2>
            <p class="text-muted mb-0">Manage enterprise storage hubs, rack zone distribution & capacity limits</p>
        </div>
        <div class="d-flex gap-2">
            <a href="/vortex_wms/modules/masters/bin_locations/index.php" class="btn btn-outline-info fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-location-dot me-1"></i> Bin Coordinates
            </a>
            <a href="add.php" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Add Warehouse
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Master Table Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">

            <!-- Search Bar -->
            <div class="row mb-4">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search Warehouse Code, Name or Location...">
                    </div>
                </div>
            </div>

            <!-- Warehouse Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="warehouseTable">
                    <thead class="table-light">
                        <tr>
                            <th width="70">ID</th>
                            <th>Facility Code</th>
                            <th>Warehouse Name</th>
                            <th>Physical Location</th>
                            <th class="text-center">Total Configured Bins</th>
                            <th class="text-center">Status</th>
                            <th width="140" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($warehouses)): ?>
                            <?php foreach ($warehouses as $row): ?>
                                <?php
                                    $whId = (int)$row['id'];
                                    $whName = mysqli_real_escape_string($conn, $row['wh_name']);
                                    
                                    // Count bins configured for this warehouse
                                    $binCountRes = @mysqli_query($conn, "
                                        SELECT COUNT(*) 
                                        FROM bin_locations 
                                        WHERE warehouse_id = '$whId' OR warehouse = '$whName'
                                    ");
                                    $totalBins = ($binCountRes && $b = mysqli_fetch_array($binCountRes)) ? (int)$b[0] : 0;

                                    $isActive = strtolower($row['status']) === 'active' || $row['status'] == '1';
                                ?>
                                <tr>
                                    <td><strong>#<?= $row['id']; ?></strong></td>
                                    <td>
                                        <code class="text-primary font-monospace fw-bold fs-6"><?= htmlspecialchars($row['wh_code'] ?? ('WH-0' . $row['id'])); ?></code>
                                    </td>
                                    <td>
                                        <strong class="text-dark fs-6"><?= htmlspecialchars($row['wh_name']); ?></strong>
                                    </td>
                                    <td>
                                        <i class="fa-solid fa-location-dot text-secondary me-1"></i><?= htmlspecialchars($row['wh_location']); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-3 py-1 rounded-pill">
                                            <?= $totalBins; ?> Bins
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($isActive): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-outline-warning btn-sm rounded-circle text-dark" title="Edit Facility"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-outline-danger btn-sm rounded-circle" title="Delete Facility" onclick="return confirm('⚠️ Are you sure you want to remove this warehouse facility?');"><i class="fa-solid fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-warehouse fa-3x text-secondary opacity-25 mb-3 d-block"></i>
                                    <h5>No Warehouse Facilities Registered</h5>
                                    <p class="small mb-3">Database is clean. Click below to add your first storage hub.</p>
                                    <a href="add.php" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-plus me-1"></i> Add Warehouse</a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Footer Stats Bar -->
        <div class="card-footer bg-light p-3 rounded-bottom-4 border-0 d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing <strong><?= count($warehouses); ?></strong> enterprise distribution facilities</small>
            <a href="/vortex_wms/dashboard.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

</div>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    let value = this.value.toLowerCase().trim();
    let rows = document.querySelectorAll("#warehouseTable tbody tr");

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>