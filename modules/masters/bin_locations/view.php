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
    $_SESSION['error'] = "Bin Location ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$whTable = "warehouses";
$whChk = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$whChk || mysqli_num_rows($whChk) == 0) {
    $whTable = "warehouse";
}

$binQuery = mysqli_query($conn, "
    SELECT 
        b.*,
        COALESCE(w.warehouse_name, 'Unassigned') AS warehouse_name,
        COALESCE(w.warehouse_code, 'WH') AS warehouse_code
    FROM bin_locations b
    LEFT JOIN {$whTable} w ON w.id = b.warehouse_id
    WHERE b.id = '$id'
");

if (!$binQuery || mysqli_num_rows($binQuery) == 0) {
    $_SESSION['error'] = "Bin Location Not Found.";
    header("Location: index.php");
    exit();
}

$bin = mysqli_fetch_assoc($binQuery);

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-circle-info text-primary me-2"></i>Bin Location Details</h2>
                <p class="text-muted mb-0">Coordinate Code: <code class="fw-bold text-primary font-monospace"><?= htmlspecialchars($bin['bin_code'] ?? ''); ?></code></p>
            </div>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back to Roster</a>
        </div>

        <div class="card shadow-sm border-0 rounded-4 bg-white">
            <div class="card-body p-4">

                <table class="table table-bordered align-middle mb-0">
                    <tr>
                        <th width="220" class="bg-light text-muted small text-uppercase">Target Warehouse</th>
                        <td><strong><?= htmlspecialchars($bin['warehouse_name'] ?? 'N/A'); ?></strong> (<?= htmlspecialchars($bin['warehouse_code'] ?? 'WH'); ?>)</td>
                    </tr>
                    <tr>
                        <th class="bg-light text-muted small text-uppercase">Bin Code</th>
                        <td><span class="badge bg-primary-subtle text-primary font-monospace fs-6 px-3 py-1 rounded-pill"><?= htmlspecialchars($bin['bin_code'] ?? ''); ?></span></td>
                    </tr>
                    <tr>
                        <th class="bg-light text-muted small text-uppercase">Zone / Area</th>
                        <td><?= htmlspecialchars($bin['zone'] ?? ($bin['zone_name'] ?? 'L0-A1')); ?></td>
                    </tr>
                    <tr>
                        <th class="bg-light text-muted small text-uppercase">Max Capacity</th>
                        <td><span class="badge bg-info-subtle text-dark fs-6 px-3 py-1 rounded-pill"><?= floatval($bin['max_capacity_kg'] ?? ($bin['max_capacity'] ?? 500)); ?> KG</span></td>
                    </tr>
                    <tr>
                        <th class="bg-light text-muted small text-uppercase">Status</th>
                        <td>
                            <?php if (($bin['status'] ?? 'Active') === 'Active'): ?>
                                <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <div class="d-flex justify-content-between align-items-center mt-4 pt-2">
                    <a href="index.php" class="btn btn-light px-4 rounded-pill">Back</a>
                    <div class="d-flex gap-2">
                        <a href="edit.php?id=<?= $bin['id']; ?>" class="btn btn-warning px-4 fw-bold rounded-pill"><i class="fa-solid fa-pen me-1"></i> Edit</a>
                        <a href="delete.php?id=<?= $bin['id']; ?>" class="btn btn-danger px-4 fw-bold rounded-pill" onclick="return confirm('Delete this Bin Location?');"><i class="fa-solid fa-trash me-1"></i> Delete</a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>