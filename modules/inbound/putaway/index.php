<?php
session_start();

// Dynamic Project Root Path
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch GRNs ready for Putaway (Supports all QC/Inbound completed statuses)
$result = mysqli_query($conn, "
    SELECT 
        g.*,
        a.asn_number AS asn_code,
        COALESCE(a.supplier_name, s.supplier_name, 'N/A') AS supplier_name
    FROM grn g
    LEFT JOIN asn a ON g.asn_id = a.id
    LEFT JOIN suppliers s ON a.supplier_name = s.supplier_name
    WHERE g.status IN ('QC Passed', 'Passed', 'QC Completed', 'Received')
    ORDER BY g.id DESC
");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-boxes-packing text-primary me-2"></i>Putaway Management
                </h2>
                <p class="text-muted mb-0">Assign bin locations and putaway inspected inventory into warehouse stock</p>
            </div>
            <div>
                <a href="../qc/index.php" class="btn btn-outline-secondary px-3 me-2"><i class="fa-solid fa-arrow-left me-1"></i> Back to QC</a>
                <a href="/vortex_wms/dashboard.php" class="btn btn-primary px-3 fw-bold"><i class="fa-solid fa-chart-line me-1"></i> Dashboard</a>
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

        <!-- Table Card -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle border">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">ID</th>
                                <th>GRN No</th>
                                <th>Supplier</th>
                                <th>Received Date</th>
                                <th>Status</th>
                                <th width="180" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td>
                                            <span class="badge bg-primary font-monospace fs-6"><?= htmlspecialchars($row['grn_number'] ?? 'GRN-'.$row['id']); ?></span>
                                            <?php if (!empty($row['asn_code'])): ?>
                                                <br><small class="text-muted font-monospace">ASN: <?= htmlspecialchars($row['asn_code']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['supplier_name']); ?></strong>
                                        </td>
                                        <td>
                                            <?= !empty($row['received_date']) ? date("d M Y", strtotime($row['received_date'])) : '-'; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-success px-3 py-1">
                                                <i class="fa-solid fa-circle-check me-1"></i> <?= htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="text-center">
                                            <a href="process.php?id=<?= $row['id']; ?>" class="btn btn-success btn-sm fw-bold px-3">
                                                <i class="fa-solid fa-truck-ramp-box me-1"></i> Putaway Stock
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-boxes-packing fs-2 d-block mb-2 text-secondary"></i>
                                        No QC Passed GRN records found for Putaway.
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