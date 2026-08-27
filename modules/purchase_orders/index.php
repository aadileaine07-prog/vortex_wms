<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. FETCH PURCHASE ORDERS WITH SUPPLIER & ITEM COUNTS
   ========================================================================== */

$query = "
    SELECT 
        po.*,
        COALESCE(s.supplier_name, 'Unassigned Vendor') AS supplier_name,
        COALESCE(s.supplier_code, 'VEND-00') AS supplier_code,
        (SELECT COUNT(id) FROM purchase_order_items WHERE po_id = po.id) AS total_items_count
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    ORDER BY po.id DESC
";

$result = @mysqli_query($conn, $query);

// Metric counters for Executive KPI Tiles
$totalPOs       = 0;
$pendingPOs     = 0;
$receivedPOs    = 0;
$totalPOValuation = 0.00;

$poList = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $poList[] = $row;
        $totalPOs++;
        $st = strtolower(trim($row['status'] ?? 'pending'));
        $amt = floatval($row['total_amount'] ?? 0);
        $totalPOValuation += $amt;

        if (in_array($st, ['received', 'completed'])) {
            $receivedPOs++;
        } elseif ($st !== 'cancelled') {
            $pendingPOs++;
        }
    }
}

// Unified Header Include
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header & Action Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Purchase Orders (PO)
            </h2>
            <p class="text-muted mb-0">Manage supplier procurement contracts, delivery timelines, and inbound receiving status</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button onclick="exportPOCSV()" class="btn btn-outline-success fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
            <a href="create.php" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-cart-plus me-1"></i> Create New PO
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
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary h-100">
                <small class="text-muted fw-bold text-uppercase">Total Orders Issued</small>
                <div class="fs-3 fw-bold text-dark my-1"><?= number_format($totalPOs); ?> <span class="fs-6 text-muted fw-normal">Orders</span></div>
                <small class="text-primary fw-semibold"><i class="fa-solid fa-list-check me-1"></i>Lifetime Procurement</small>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning h-100">
                <small class="text-muted fw-bold text-uppercase">Awaiting Inbound / GRN</small>
                <div class="fs-3 fw-bold text-warning my-1"><?= number_format($pendingPOs); ?> <span class="fs-6 text-muted fw-normal">Pending</span></div>
                <small class="text-muted fw-semibold">Orders in transit or open</small>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success h-100">
                <small class="text-muted fw-bold text-uppercase">Fulfilled / Received</small>
                <div class="fs-3 fw-bold text-success my-1"><?= number_format($receivedPOs); ?> <span class="fs-6 text-muted fw-normal">Completed</span></div>
                <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Stock Ingested</small>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-dark h-100">
                <small class="text-muted fw-bold text-uppercase">Total Purchase Valuation</small>
                <div class="fs-3 fw-bold text-dark my-1">₹<?= number_format($totalPOValuation, 2); ?></div>
                <small class="text-muted fw-semibold">Procurement commitment</small>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">

            <!-- Search and Filter Bar -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search PO Number, Supplier Name, or Vendor Code...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="statusFilter" class="form-select border-2">
                        <option value="">All PO Statuses</option>
                        <option value="Pending">🟡 Pending / Inbound</option>
                        <option value="Received">🟢 Received / Completed</option>
                        <option value="Cancelled">🔴 Cancelled</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="poTable">
                    <thead class="table-light">
                        <tr>
                            <th width="70">ID</th>
                            <th>PO Number</th>
                            <th>Supplier Details</th>
                            <th>Order Date</th>
                            <th>Expected Delivery</th>
                            <th class="text-center">Line Items</th>
                            <th class="text-end">Total Amount</th>
                            <th class="text-center">Status</th>
                            <th width="140" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($poList)): ?>
                            <?php foreach ($poList as $row): ?>
                                <?php
                                    $st = $row['status'] ?? 'Pending';
                                    $statusLower = strtolower(trim($st));
                                    
                                    if (in_array($statusLower, ['completed', 'received'])) {
                                        $badgeHtml = '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill"><i class="fa-solid fa-circle-check me-1"></i>Received</span>';
                                        $statusCategory = 'Received';
                                    } elseif ($statusLower === 'cancelled') {
                                        $badgeHtml = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill"><i class="fa-solid fa-circle-xmark me-1"></i>Cancelled</span>';
                                        $statusCategory = 'Cancelled';
                                    } else {
                                        $badgeHtml = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill"><i class="fa-solid fa-clock me-1"></i>Pending</span>';
                                        $statusCategory = 'Pending';
                                    }
                                ?>
                                <tr>
                                    <td><strong>#<?= $row['id']; ?></strong></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fs-6 px-3 py-1">
                                            <?= htmlspecialchars($row['po_number']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['supplier_name']); ?></div>
                                        <code class="text-muted small font-monospace"><?= htmlspecialchars($row['supplier_code']); ?></code>
                                    </td>
                                    <td><small class="text-muted"><?= date("d M Y", strtotime($row['order_date'])); ?></small></td>
                                    <td>
                                        <?php if (!empty($row['expected_date']) && $row['expected_date'] !== '0000-00-00'): ?>
                                            <span class="small fw-semibold text-dark"><?= date("d M Y", strtotime($row['expected_date'])); ?></span>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                            <?= (int)($row['total_items_count'] ?? 0); ?> SKU(s)
                                        </span>
                                    </td>
                                    <td class="text-end font-monospace fw-bold text-dark">
                                        ₹<?= number_format((float)$row['total_amount'], 2); ?>
                                    </td>
                                    <td class="text-center status-cell" data-status="<?= $statusCategory; ?>">
                                        <?= $badgeHtml; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-outline-info btn-sm rounded-circle" title="View PO Voucher"><i class="fa-solid fa-eye"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-outline-danger btn-sm rounded-circle" onclick="return confirm('⚠️ Delete Purchase Order #<?= htmlspecialchars($row['po_number']); ?>?');" title="Delete PO"><i class="fa-solid fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-file-invoice-dollar fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                    No Purchase Orders found. Click <strong>Create New PO</strong> to issue procurement contracts.
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
// Filter PO Ledger by Search Input and Status Dropdown
document.addEventListener("DOMContentLoaded", function() {
    const searchInput  = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");

    function applyFilter() {
        const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const statusVal = statusFilter ? statusFilter.value.toLowerCase().trim() : "";
        const rows      = document.querySelectorAll("#poTable tbody tr");

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

// CSV Exporter for Purchase Orders
function exportPOCSV() {
    let csv = ["ID,PO Number,Supplier,Supplier Code,Order Date,Expected Date,Items,Total Amount,Status"];
    const rows = document.querySelectorAll("#poTable tbody tr");

    rows.forEach(r => {
        const cols = r.querySelectorAll("td");
        if (cols.length >= 8) {
            const rowData = [
                `"${cols[0].innerText.trim()}"`,
                `"${cols[1].innerText.trim()}"`,
                `"${cols[2].querySelector('.fw-bold') ? cols[2].querySelector('.fw-bold').innerText.trim() : ''}"`,
                `"${cols[2].querySelector('code') ? cols[2].querySelector('code').innerText.trim() : ''}"`,
                `"${cols[3].innerText.trim()}"`,
                `"${cols[4].innerText.trim()}"`,
                `"${cols[5].innerText.trim()}"`,
                `"${cols[6].innerText.replace(/₹|,/g, '').trim()}"`,
                `"${cols[7].innerText.trim()}"`
            ];
            csv.push(rowData.join(","));
        }
    });

    const blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `Purchase_Orders_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>