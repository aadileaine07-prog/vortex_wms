<?php
session_start();

// Dynamic Project Root Path Resolution
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch ASN Records with Supplier Details
$result = mysqli_query($conn, "
    SELECT 
        a.*,
        COALESCE(s.supplier_code, 'N/A') AS supplier_code
    FROM asn a
    LEFT JOIN suppliers s ON (a.supplier_name = s.supplier_name OR a.po_id = s.id)
    ORDER BY a.id DESC
");

// Metric Counters
$cnt_total     = 0;
$cnt_pending   = 0;
$cnt_completed = 0;

$asn_list = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $asn_list[] = $row;
        $cnt_total++;
        $st = $row['status'] ?? 'Pending';
        if ($st == 'Pending' || $st == 'In Transit') {
            $cnt_pending++;
        } else {
            $cnt_completed++;
        }
    }
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-truck-ramp-box text-primary me-2"></i>Advance Shipping Notice (ASN)
                </h2>
                <p class="text-muted mb-0">Track pre-arrival shipment notices, link POs, and generate GRNs</p>
            </div>
            <div>
                <a href="create.php" class="btn btn-primary px-3 shadow-sm fw-bold">
                    <i class="fa-solid fa-plus me-1"></i> Create New ASN
                </a>
            </div>
        </div>

        <!-- Session Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Summary KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted fw-bold">TOTAL ASNs</small>
                            <h3 class="fw-bold text-dark mb-0 mt-1"><?= $cnt_total; ?></h3>
                        </div>
                        <i class="fa-solid fa-boxes-packing fa-2x text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-warning fw-bold">PENDING INBOUND</small>
                            <h3 class="fw-bold text-warning mb-0 mt-1"><?= $cnt_pending; ?></h3>
                        </div>
                        <i class="fa-solid fa-clock-rotate-left fa-2x text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-success fw-bold">RECEIVED / COMPLETED</small>
                            <h3 class="fw-bold text-success mb-0 mt-1"><?= $cnt_completed; ?></h3>
                        </div>
                        <i class="fa-solid fa-circle-check fa-2x text-success opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">

                <!-- Search Input Bar -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search ASN No, Supplier, Invoice...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="asnTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">ID</th>
                                <th>ASN Number</th>
                                <th>Supplier</th>
                                <th>Invoice / Ref No</th>
                                <th>Expected Date</th>
                                <th>Status</th>
                                <th width="260" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($asn_list)): ?>
                                <?php foreach ($asn_list as $row): ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td>
                                            <span class="badge bg-secondary font-monospace fs-6"><?= htmlspecialchars($row['asn_number']); ?></span>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['supplier_name']); ?></strong>
                                        </td>
                                        <td>
                                            <span class="font-monospace text-dark"><?= htmlspecialchars($row['invoice_number'] ?? 'N/A'); ?></span>
                                        </td>
                                        <td>
                                            <?= !empty($row['expected_date']) ? date("d M Y", strtotime($row['expected_date'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <?php 
                                                $st = $row['status'] ?? 'Pending';
                                                if ($st == "Pending" || $st == "In Transit") {
                                                    echo '<span class="badge bg-warning text-dark px-3 py-1"><i class="fa-solid fa-truck me-1"></i> Pending Inbound</span>';
                                                } elseif ($st == "Received" || $st == "GRN Created") {
                                                    echo '<span class="badge bg-info text-dark px-3 py-1"><i class="fa-solid fa-receipt me-1"></i> GRN Created</span>';
                                                } else {
                                                    echo '<span class="badge bg-success px-3 py-1"><i class="fa-solid fa-circle-check me-1"></i> Completed</span>';
                                                }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <!-- Action Buttons -->
                                            <?php if ($st == "Pending" || $st == "In Transit"): ?>
                                                <a href="../grn/create.php?asn_id=<?= $row['id']; ?>" class="btn btn-success btn-sm me-1 fw-bold" title="Generate GRN for Goods Receipt">
                                                    <i class="fa-solid fa-receipt me-1"></i> Receive (GRN)
                                                </a>
                                            <?php endif; ?>

                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm text-white me-1" title="View Details"><i class="fa-solid fa-eye"></i></a>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm me-1 text-dark" title="Edit ASN"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this ASN record?');" title="Delete ASN"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-truck-ramp-box fs-2 d-block mb-2 text-secondary"></i>
                                        No Advance Shipping Notices Found. Click "Create New ASN" to start.
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll("#asnTable tbody tr");

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});
</script>