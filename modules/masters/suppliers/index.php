<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Multi-Level Project Root Detection (3 levels up)
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. SAFE & DIRECT SUPPLIER QUERY (Strict Mode Safe)
   ========================================================================== */

$query = "
    SELECT 
        s.*,
        COALESCE(s.contact, '-') AS final_contact,
        COALESCE(s.gst_number, '-') AS final_gstin,
        (SELECT COUNT(id) FROM purchase_orders WHERE supplier_id = s.id) AS total_pos
    FROM suppliers s
    ORDER BY s.id DESC
";

$result = mysqli_query($conn, $query);

// Metric Summary Counters
$cnt_total    = 0;
$cnt_active   = 0;
$cnt_inactive = 0;
$suppliers_list = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $suppliers_list[] = $row;
        $cnt_total++;
        $isActive = (strcasecmp($row['status'] ?? 'Active', 'Active') === 0 || ($row['status'] ?? '') === '1');
        if ($isActive) {
            $cnt_active++;
        } else {
            $cnt_inactive++;
        }
    }
}

// Single Unified Header Include
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header & Action Controls -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-industry text-primary me-2"></i>Supplier Master Directory
            </h2>
            <p class="text-muted mb-0">Manage verified vendor profiles, tax GSTIN credentials, and procurement ledgers</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button onclick="exportSupplierCSV()" class="btn btn-outline-success fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
            <a href="create.php" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Add Supplier
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

    <!-- Executive KPI Summary Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-bold text-uppercase">Total Registered Vendors</small>
                        <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($cnt_total); ?></h3>
                        <small class="text-primary fw-semibold">Master Catalog Entries</small>
                    </div>
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4">
                        <i class="fa-solid fa-building-user fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-bold text-uppercase">Active Suppliers</small>
                        <h3 class="fw-bold text-success mb-0 mt-1"><?= number_format($cnt_active); ?></h3>
                        <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Approved for Procurement</small>
                    </div>
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-4">
                        <i class="fa-solid fa-truck-ramp-box fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-bold text-uppercase">Inactive / On-Hold</small>
                        <h3 class="fw-bold text-danger mb-0 mt-1"><?= number_format($cnt_inactive); ?></h3>
                        <small class="text-danger fw-semibold"><i class="fa-solid fa-ban me-1"></i>Orders Suspended</small>
                    </div>
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-4">
                        <i class="fa-solid fa-user-xmark fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">

            <!-- Search and Status Filter Controls -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search Vendor Code, Company Name, Contact Person, GSTIN...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="statusFilter" class="form-select border-2">
                        <option value="">All Vendor Statuses</option>
                        <option value="Active">🟢 Active</option>
                        <option value="Inactive">🔴 Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="supplierTable">
                    <thead class="table-light">
                        <tr>
                            <th width="70">ID</th>
                            <th>Code</th>
                            <th>Supplier / Company</th>
                            <th>Key Contact</th>
                            <th>Contact Phone</th>
                            <th>GSTIN / Tax ID</th>
                            <th class="text-center">Linked POs</th>
                            <th class="text-center">Status</th>
                            <th width="140" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($suppliers_list)): ?>
                            <?php foreach ($suppliers_list as $row): ?>
                                <?php 
                                    $isActive = (strcasecmp($row['status'] ?? 'Active', 'Active') === 0 || ($row['status'] ?? '') === '1');
                                    $statusCategory = $isActive ? 'Active' : 'Inactive';
                                    $statusBadge = $isActive 
                                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill"><i class="fa-solid fa-circle-check me-1"></i>Active</span>'
                                        : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill"><i class="fa-solid fa-ban me-1"></i>Inactive</span>';
                                ?>
                                <tr>
                                    <td><strong>#<?= $row['id']; ?></strong></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fs-6 px-3 py-1">
                                            <?= htmlspecialchars($row['supplier_code']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['supplier_name']); ?></div>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($row['email'] ?: 'No Email'); ?></small>
                                    </td>
                                    <td><?= htmlspecialchars($row['contact_person'] ?? '-'); ?></td>
                                    <td><span class="font-monospace small"><?= htmlspecialchars($row['final_contact']); ?></span></td>
                                    <td>
                                        <code class="font-monospace fw-bold text-dark text-uppercase">
                                            <?= htmlspecialchars($row['final_gstin']); ?>
                                        </code>
                                    </td>
                                    <td class="text-center">
                                        <a href="../../purchase_orders/index.php" class="badge bg-light text-dark border text-decoration-none font-monospace px-2 py-1">
                                            <i class="fa-solid fa-file-invoice me-1 text-primary"></i><?= (int)($row['total_pos'] ?? 0); ?> PO(s)
                                        </a>
                                    </td>
                                    <td class="text-center status-cell" data-status="<?= $statusCategory; ?>">
                                        <?= $statusBadge; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-outline-info btn-sm rounded-circle" title="View Profile"><i class="fa-solid fa-eye"></i></a>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-outline-warning btn-sm rounded-circle text-dark" title="Edit Supplier"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-outline-danger btn-sm rounded-circle" onclick="return confirm('⚠️ Delete supplier record for <?= htmlspecialchars(addslashes($row['supplier_name'])); ?>?');" title="Delete Supplier"><i class="fa-solid fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-industry fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                    No suppliers registered in the database. Click <strong>Add Supplier</strong> to onboard a vendor.
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
document.addEventListener("DOMContentLoaded", function() {
    const searchInput  = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");

    function applyFilter() {
        const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const statusVal = statusFilter ? statusFilter.value.toLowerCase().trim() : "";
        const rows      = document.querySelectorAll("#supplierTable tbody tr");

        rows.forEach(row => {
            if (row.querySelector("td[colspan]")) return;

            const text       = row.innerText.toLowerCase();
            const statusCell = row.querySelector(".status-cell");
            const statusText = statusCell ? (statusCell.getAttribute("data-status") || "").toLowerCase() : "";

            const matchSearch = (searchVal === "" || text.includes(searchVal));
            const matchStatus = (statusVal === "" || statusText === statusVal);

            row.style.display = (matchSearch && matchStatus) ? "" : "none";
        });
    }

    if (searchInput) searchInput.addEventListener("keyup", applyFilter);
    if (statusFilter) statusFilter.addEventListener("change", applyFilter);
});

function exportSupplierCSV() {
    let csv = ["ID,Supplier Code,Supplier Name,Email,Contact Person,Phone,GSTIN,Linked POs,Status"];
    const rows = document.querySelectorAll("#supplierTable tbody tr");

    rows.forEach(r => {
        const cols = r.querySelectorAll("td");
        if (cols.length >= 8) {
            const rowData = [
                `"${cols[0].innerText.trim()}"`,
                `"${cols[1].innerText.trim()}"`,
                `"${cols[2].querySelector('.fw-bold') ? cols[2].querySelector('.fw-bold').innerText.trim() : ''}"`,
                `"${cols[2].querySelector('small') ? cols[2].querySelector('small').innerText.trim() : ''}"`,
                `"${cols[3].innerText.trim()}"`,
                `"${cols[4].innerText.trim()}"`,
                `"${cols[5].innerText.trim()}"`,
                `"${cols[6].innerText.trim()}"`,
                `"${cols[7].innerText.trim()}"`
            ];
            csv.push(rowData.join(","));
        }
    });

    const blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `Suppliers_Master_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>