<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. DYNAMIC TABLE & SCHEMA RESOLUTION
   ========================================================================== */

// Detect Transfer Table Name
$transferTable = "stock_transfers";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_transfers'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $chkTable2 = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_transfer'");
    if ($chkTable2 && mysqli_num_rows($chkTable2) > 0) {
        $transferTable = "stock_transfer";
    } else {
        $transferTable = "inventory_transfers";
    }
}

// Detect Warehouse Table Name
$whTable = "warehouse";
$chkWh = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouse'");
if (!$chkWh || mysqli_num_rows($chkWh) === 0) {
    $whTable = "warehouses";
}

$whNameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) === 0) {
    $whNameCol = "name";
}

// Detect Columns of Transfer Table
$tCols = [];
$cRes = @mysqli_query($conn, "SHOW COLUMNS FROM `{$transferTable}`");
if ($cRes) {
    while ($c = mysqli_fetch_assoc($cRes)) {
        $tCols[] = strtolower($c['Field']);
    }
}

$dateCol = in_array('transfer_date', $tCols) ? 'transfer_date' : (in_array('created_at', $tCols) ? 'created_at' : "NOW()");
$remCol  = in_array('remarks', $tCols) ? 'remarks' : (in_array('reason', $tCols) ? 'reason' : "''");

/* ==========================================================================
   2. COMPREHENSIVE STOCK MOVEMENT QUERY
   ========================================================================== */

$query = "
    SELECT 
        t.id,
        t.quantity,
        t.{$dateCol} AS transfer_date,
        t.{$remCol} AS remarks,
        COALESCE(p.product_name, t.product_name, i.product_name, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, p.product_code, t.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(t.from_warehouse, w1.{$whNameCol}, 'Main Warehouse') AS from_warehouse,
        COALESCE(t.from_bin, i.bin_location, 'L0-A1') AS from_bin,
        COALESCE(t.to_warehouse, w2.{$whNameCol}, 'Secondary Warehouse') AS to_warehouse,
        COALESCE(t.to_bin, 'L0-B1') AS to_bin
    FROM `{$transferTable}` t
    LEFT JOIN inventory i ON i.id = t.inventory_id
    LEFT JOIN products p ON (p.id = t.product_id OR p.id = i.product_id)
    LEFT JOIN `{$whTable}` w1 ON (w1.id = t.from_warehouse_id)
    LEFT JOIN `{$whTable}` w2 ON (w2.id = t.to_warehouse_id)
    ORDER BY t.id DESC
";

$result = @mysqli_query($conn, $query);

// Summary KPI counts
$totalTransfers = 0;
$totalUnitsMoved = 0;

$transferRows = [];
if ($result && mysqli_num_rows($result) > 0) {
    while ($r = mysqli_fetch_assoc($result)) {
        $transferRows[] = $r;
        $totalTransfers++;
        $totalUnitsMoved += (int)$r['quantity'];
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header & Action Controls -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-right-left text-primary me-2"></i>Stock Movement Ledger
            </h2>
            <p class="text-muted mb-0">Track internal bin relocations, warehouse-to-warehouse transfers, and putaways</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button onclick="exportTransferCSV()" class="btn btn-outline-success fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
            <a href="create.php" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Transfer
            </a>
            <a href="../index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
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

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary h-100">
                <small class="text-muted fw-bold text-uppercase">Total Movements Logged</small>
                <div class="fs-3 fw-bold text-dark my-1"><?= number_format($totalTransfers); ?> <span class="fs-6 text-muted fw-normal">Vouchers</span></div>
                <small class="text-primary fw-semibold"><i class="fa-solid fa-arrows-spin me-1"></i>Transfer Ledger</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success h-100">
                <small class="text-muted fw-bold text-uppercase">Total Quantity Relocated</small>
                <div class="fs-3 fw-bold text-success my-1"><?= number_format($totalUnitsMoved); ?> <span class="fs-6 text-muted fw-normal">Units</span></div>
                <small class="text-muted">Inter-bin & Facility total</small>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">

            <!-- Search and Filter Toolbar -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search SKU, Product Name, Facility, or Bin Coordinate...">
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="transferTable">
                    <thead class="table-light">
                        <tr>
                            <th width="70">ID</th>
                            <th>Product / SKU</th>
                            <th>Origin (From)</th>
                            <th class="text-center" width="40"><i class="fa-solid fa-arrow-right text-muted"></i></th>
                            <th>Destination (To)</th>
                            <th class="text-center">Transferred Qty</th>
                            <th>Date</th>
                            <th>Remarks</th>
                            <th width="120" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($transferRows)): ?>
                            <?php foreach ($transferRows as $row): ?>
                                <tr>
                                    <td><strong>#<?= $row['id']; ?></strong></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['final_product_name']); ?></div>
                                        <code class="text-primary font-monospace small"><?= htmlspecialchars($row['final_sku']); ?></code>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-muted"><?= htmlspecialchars($row['from_warehouse']); ?></div>
                                        <span class="badge bg-light text-dark border font-monospace mt-1">
                                            <i class="fa-solid fa-location-dot me-1 text-danger"></i><?= htmlspecialchars($row['from_bin']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center text-primary">
                                        <i class="fa-solid fa-circle-arrow-right fs-5 opacity-75"></i>
                                    </td>
                                    <td>
                                        <div class="small fw-semibold text-muted"><?= htmlspecialchars($row['to_warehouse']); ?></div>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace mt-1">
                                            <i class="fa-solid fa-location-dot me-1 text-primary"></i><?= htmlspecialchars($row['to_bin']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary fs-6 px-3 py-1 rounded-pill font-monospace">
                                            <?= number_format((int)$row['quantity']); ?> Units
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?= date("d M Y", strtotime($row['transfer_date'])); ?></small></td>
                                    <td>
                                        <small class="text-dark fw-semibold text-truncate d-inline-block" style="max-width: 160px;" title="<?= htmlspecialchars($row['remarks']); ?>">
                                            <?= htmlspecialchars($row['remarks'] ?: 'Direct Transfer'); ?>
                                        </small>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-outline-info btn-sm rounded-circle" title="View Transfer Spec"><i class="fa-solid fa-eye"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-outline-danger btn-sm rounded-circle" onclick="return confirm('⚠️ Revert stock back to origin coordinate and delete this record?');" title="Revert & Delete"><i class="fa-solid fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-right-left fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                    No stock movement records found. Click <strong>New Transfer</strong> to relocate inventory items.
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
// Filter Transfers by Search Query
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("searchInput");

    if (searchInput) {
        searchInput.addEventListener("keyup", function() {
            const searchVal = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll("#transferTable tbody tr");

            rows.forEach(row => {
                if (row.querySelector("td[colspan]")) return;
                const text = row.innerText.toLowerCase();
                row.style.display = (searchVal === "" || text.includes(searchVal)) ? "" : "none";
            });
        });
    }
});

// CSV Exporter
function exportTransferCSV() {
    let csv = ["ID,Product,SKU,From Warehouse,From Bin,To Warehouse,To Bin,Quantity,Date,Remarks"];
    const rows = document.querySelectorAll("#transferTable tbody tr");
    
    rows.forEach(r => {
        const cols = r.querySelectorAll("td");
        if (cols.length >= 8) {
            const rowData = [
                `"${cols[0].innerText.trim()}"`,
                `"${cols[1].querySelector('.fw-bold') ? cols[1].querySelector('.fw-bold').innerText.trim() : ''}"`,
                `"${cols[1].querySelector('code') ? cols[1].querySelector('code').innerText.trim() : ''}"`,
                `"${cols[2].querySelector('.small') ? cols[2].querySelector('.small').innerText.trim() : ''}"`,
                `"${cols[2].querySelector('.badge') ? cols[2].querySelector('.badge').innerText.trim() : ''}"`,
                `"${cols[4].querySelector('.small') ? cols[4].querySelector('.small').innerText.trim() : ''}"`,
                `"${cols[4].querySelector('.badge') ? cols[4].querySelector('.badge').innerText.trim() : ''}"`,
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
    link.download = `Stock_Movements_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>