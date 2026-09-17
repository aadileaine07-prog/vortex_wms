<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../../../config/database.php") ? dirname(__DIR__, 4) : dirname(__DIR__, 2));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   AUTO-DETECT ASN TABLE (asn_headers OR asn)
   ========================================================================== */
$asnTable = "asn_headers";
$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'asn_headers'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    $chk2 = @mysqli_query($conn, "SHOW TABLES LIKE 'asn'");
    if ($chk2 && mysqli_num_rows($chk2) > 0) {
        $asnTable = "asn";
    } else {
        @mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS `asn_headers` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `asn_number` VARCHAR(50) NOT NULL UNIQUE,
              `supplier_name` VARCHAR(150) NOT NULL,
              `expected_date` DATE NOT NULL,
              `item_code` VARCHAR(50) NULL,
              `expected_qty` INT DEFAULT 0,
              `status` VARCHAR(50) DEFAULT 'Pending',
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
        $asnTable = "asn_headers";
    }
}

$query = "SELECT * FROM `{$asnTable}` ORDER BY id DESC";
$result = @mysqli_query($conn, $query);

$totalAsn = 0;
$pendingAsn = 0;
$completedAsn = 0;
$asnList = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $asnList[] = $row;
        $totalAsn++;
        $st = strtolower(trim($row['status'] ?? 'pending'));
        if (in_array($st, ['completed', 'received', 'closed'])) {
            $completedAsn++;
        } else {
            $pendingAsn++;
        }
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Action Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-file-invoice text-primary me-2"></i>Advance Shipping Notices (ASN)
            </h2>
            <p class="text-muted mb-0">Track incoming shipments, supplier manifests, and scheduled dock arrivals</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="/vortex_wms/modules/tools/universal_import.php?module=asn" class="btn btn-outline-success fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Import CSV
            </a>
            <a href="create.php" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Create New ASN
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- KPI Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary h-100">
                <small class="text-muted fw-bold text-uppercase">Total ASNs Logged</small>
                <div class="fs-3 fw-bold text-dark my-1"><?= number_format($totalAsn); ?></div>
                <small class="text-primary fw-semibold"><i class="fa-solid fa-list-check me-1"></i>Inbound Pipeline</small>
            </div>
        </div>
        <div class="col-xl-4 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning h-100">
                <small class="text-muted fw-bold text-uppercase">Pending Arrivals</small>
                <div class="fs-3 fw-bold text-warning my-1"><?= number_format($pendingAsn); ?></div>
                <small class="text-muted fw-semibold">Awaiting dock receipt</small>
            </div>
        </div>
        <div class="col-xl-4 col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success h-100">
                <small class="text-muted fw-bold text-uppercase">Processed / Completed</small>
                <div class="fs-3 fw-bold text-success my-1"><?= number_format($completedAsn); ?></div>
                <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Converted to GRN</small>
            </div>
        </div>
    </div>

    <!-- Master Table Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">

            <!-- Search Bar -->
            <div class="row mb-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search ASN Number, Supplier, or Item Code...">
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="asnTable">
                    <thead class="table-light">
                        <tr>
                            <th width="70">ID</th>
                            <th>ASN Number</th>
                            <th>Supplier Name</th>
                            <th>Expected Date</th>
                            <th>Item Code</th>
                            <th class="text-center">Expected Qty</th>
                            <th class="text-center">Status</th>
                            <th width="140" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($asnList)): ?>
                            <?php foreach ($asnList as $row): ?>
                                <?php
                                    $st = strtolower(trim($row['status'] ?? 'pending'));
                                    $badge = ($st === 'completed' || $st === 'received')
                                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Completed</span>'
                                        : '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">Pending</span>';
                                ?>
                                <tr>
                                    <td><strong>#<?= $row['id']; ?></strong></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fs-6 px-3 py-1">
                                            <?= htmlspecialchars($row['asn_number']); ?>
                                        </span>
                                    </td>
                                    <td><strong class="text-dark"><?= htmlspecialchars($row['supplier_name']); ?></strong></td>
                                    <td><small class="text-muted"><?= date("d M Y", strtotime($row['expected_date'] ?? date('Y-m-d'))); ?></small></td>
                                    <td><code class="text-primary font-monospace"><?= htmlspecialchars($row['item_code'] ?? 'SKU-00'); ?></code></td>
                                    <td class="text-center font-monospace fw-bold"><?= number_format((int)($row['expected_qty'] ?? 0)); ?> Units</td>
                                    <td class="text-center"><?= $badge; ?></td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-outline-info btn-sm rounded-circle" title="View ASN"><i class="fa-solid fa-eye"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-outline-danger btn-sm rounded-circle" onclick="return confirm('Delete ASN #<?= htmlspecialchars($row['asn_number']); ?>?');" title="Delete"><i class="fa-solid fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-file-invoice fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                    No Advance Shipping Notices found. Click <strong>Create New ASN</strong> to log incoming shipments.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

</div>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    let val = this.value.toLowerCase().trim();
    let rows = document.querySelectorAll("#asnTable tbody tr");
    rows.forEach(r => {
        if (r.querySelector("td[colspan]")) return;
        r.style.display = r.innerText.toLowerCase().includes(val) ? "" : "none";
    });
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>