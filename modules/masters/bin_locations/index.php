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

// 1. Dynamic Table & Column Identification
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

// 2. Fetch Active Warehouse Bins
$query = "
    SELECT 
        b.*,
        COALESCE(w.{$nameCol}, 'General Facility') AS warehouse_name,
        COALESCE(w.{$codeCol}, 'WH') AS warehouse_code
    FROM bin_locations b
    LEFT JOIN {$whTable} w ON w.id = b.warehouse_id
    WHERE (LOWER(w.status) = 'active' OR w.status = '1' OR b.warehouse_id IS NULL OR b.warehouse_id = 0)
    ORDER BY b.id DESC
";
$result = mysqli_query($conn, $query);

// Header includes Navbar and Sidebar automatically
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Bin Locations Master
            </h2>
            <p class="text-muted mb-0">Active coordinates & rack layout map (Pattern: <code>L0-A1-001-01-A</code>)</p>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="/vortex_wms/modules/inventory/bin_map.php" class="btn btn-outline-info fw-bold shadow-sm rounded-pill px-3">
                <i class="fa-solid fa-border-all me-1"></i> Visual Map
            </a>
            <a href="bulk_add.php" class="btn btn-primary fw-bold shadow-sm rounded-pill px-3">
                <i class="fa-solid fa-layer-group me-1"></i> Bulk Add / Range
            </a>
            <a href="create.php" class="btn btn-success fw-bold shadow-sm rounded-pill px-3">
                <i class="fa-solid fa-plus me-1"></i> Add Single Bin
            </a>
            <button type="button" class="btn btn-danger fw-bold shadow-sm rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#deleteAllModal">
                <i class="fa-solid fa-trash-can me-1"></i> Delete All Bins
            </button>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Master Table Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white">
        <div class="card-body p-4">

            <div class="row g-3 mb-3">
                <div class="col-md-4">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="binSearch" class="form-control border-start-0" placeholder="Search Bin Code, Zone, Warehouse...">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="binTable">
                    <thead class="table-light">
                        <tr>
                            <th width="60">ID</th>
                            <th>Bin Code</th>
                            <th>Target Warehouse</th>
                            <th>Zone / Area</th>
                            <th>Max Capacity</th>
                            <th>Status</th>
                            <th width="140" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><strong>#<?= $row['id']; ?></strong></td>
                                    <td>
                                        <code class="fs-6 fw-bold text-primary font-monospace"><?= htmlspecialchars($row['bin_code']); ?></code>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['warehouse_name']); ?></div>
                                        <small class="badge bg-light text-secondary border"><?= htmlspecialchars($row['warehouse_code']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-dark border font-monospace">
                                            <?= htmlspecialchars($row['zone_name'] ?? ($row['zone'] ?? 'L0-A1')); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><?= floatval($row['max_capacity'] ?? ($row['max_capacity_kg'] ?? 500)); ?></span> <small class="text-muted">KG</small>
                                    </td>
                                    <td>
                                        <?php if (($row['status'] ?? 'Active') === 'Active'): ?>
                                            <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill">Active</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-outline-info btn-sm rounded-circle" title="View"><i class="fa-solid fa-eye"></i></a>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-outline-warning btn-sm rounded-circle" title="Edit"><i class="fa-solid fa-pen"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-outline-danger btn-sm rounded-circle" onclick="return confirm('Delete bin <?= htmlspecialchars($row['bin_code']); ?>?');" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-boxes-stacked fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                    No active bin locations found. Click <strong>Add Single Bin</strong> or <strong>Bulk Add / Range</strong> to create coordinates.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>

<!-- Modal: Confirm Delete All -->
<div class="modal fade" id="deleteAllModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-danger text-white rounded-top-4 py-3">
                <h5 class="modal-title fw-bold"><i class="fa-solid fa-triangle-exclamation me-2"></i>Delete All Bin Locations?</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4 text-center">
                <i class="fa-solid fa-dumpster-fire fa-3x text-danger mb-3"></i>
                <h5 class="fw-bold text-dark">Are you absolutely sure?</h5>
                <p class="text-muted small mb-0">Yeh action saare bin locations ko permanently delete kar dega. Is action ko undo nahi kiya ja sakta.</p>
            </div>
            <div class="modal-footer bg-light rounded-bottom-4 border-0 d-flex justify-content-between">
                <button type="button" class="btn btn-secondary px-4 rounded-pill" data-bs-dismiss="modal">Cancel</button>
                <form action="purge_all.php" method="POST" class="m-0">
                    <button type="submit" name="confirm_purge_all" class="btn btn-danger px-4 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-trash me-1"></i> Yes, Delete Everything
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById("binSearch").addEventListener("keyup", function() {
    const value = this.value.toLowerCase().trim();
    const rows  = document.querySelectorAll("#binTable tbody tr");

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>