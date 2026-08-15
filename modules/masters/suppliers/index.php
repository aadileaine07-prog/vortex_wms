<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch Suppliers with Linked Purchase Order Counts
$result = mysqli_query($conn, "
    SELECT 
        s.*,
        COUNT(po.id) AS total_pos
    FROM suppliers s
    LEFT JOIN purchase_orders po ON s.id = po.supplier_id
    GROUP BY s.id
    ORDER BY s.id DESC
");

// Metric Summary Counters
$cnt_total    = 0;
$cnt_active   = 0;
$cnt_inactive = 0;

$suppliers_list = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $suppliers_list[] = $row;
        $cnt_total++;
        if (($row['status'] ?? 'Active') == 'Active') {
            $cnt_active++;
        } else {
            $cnt_inactive++;
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
                    <i class="fa-solid fa-industry text-primary me-2"></i>Supplier Master
                </h2>
                <p class="text-muted mb-0">Manage vendor directory, tax GSTIN details, and linked purchase orders</p>
            </div>
            <div>
                <a href="add.php" class="btn btn-primary px-3 shadow-sm fw-bold">
                    <i class="fa-solid fa-plus me-1"></i> Add Supplier
                </a>
            </div>
        </div>

        <!-- Session Messages -->
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

        <!-- KPI Metrics Row -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-muted fw-bold">TOTAL SUPPLIERS</small>
                            <h3 class="fw-bold text-dark mb-0 mt-1"><?= $cnt_total; ?></h3>
                        </div>
                        <i class="fa-solid fa-building-user fa-2x text-primary opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-success fw-bold">ACTIVE VENDORS</small>
                            <h3 class="fw-bold text-success mb-0 mt-1"><?= $cnt_active; ?></h3>
                        </div>
                        <i class="fa-solid fa-circle-check fa-2x text-success opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-danger fw-bold">INACTIVE VENDORS</small>
                            <h3 class="fw-bold text-danger mb-0 mt-1"><?= $cnt_inactive; ?></h3>
                        </div>
                        <i class="fa-solid fa-ban fa-2x text-danger opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">

                <!-- Search Input -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search Supplier Code, Name, GSTIN...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="supplierTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">ID</th>
                                <th>Code</th>
                                <th>Supplier Name</th>
                                <th>Contact Person</th>
                                <th>Contact No</th>
                                <th>GSTIN</th>
                                <th>Linked POs</th>
                                <th>Status</th>
                                <th width="200" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($suppliers_list)): ?>
                                <?php foreach ($suppliers_list as $row): ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td><span class="badge bg-secondary font-monospace fs-6"><?= htmlspecialchars($row['supplier_code']); ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['supplier_name']); ?></strong>
                                            <small class="d-block text-muted"><?= htmlspecialchars($row['email'] ?: 'No Email'); ?></small>
                                        </td>
                                        <td><?= htmlspecialchars($row['contact_person'] ?? '-'); ?></td>
                                        <td><?= htmlspecialchars($row['contact'] ?? '-'); ?></td>
                                        <td><span class="font-monospace text-uppercase fw-semibold"><?= htmlspecialchars($row['gst_number'] ?? '-'); ?></span></td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <i class="fa-solid fa-file-invoice me-1 text-primary"></i><?= $row['total_pos']; ?> Orders
                                            </span>
                                        </td>
                                        <td>
                                            <?php if (($row['status'] ?? 'Active') == 'Active'): ?>
                                                <span class="badge bg-success px-2 py-1">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger px-2 py-1">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm text-white me-1" title="View Supplier"><i class="fa-solid fa-eye"></i></a>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm me-1 text-dark" title="Edit Supplier"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this supplier?');" title="Delete Supplier"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-industry fs-2 d-block mb-2 text-secondary"></i>
                                        No Suppliers Found in System
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
    let rows = document.querySelectorAll("#supplierTable tbody tr");

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});
</script>