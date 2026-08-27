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

// 1. Dynamic Table Detection for Stock Adjustments
$adjTable = "stock_adjustments";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_adjustments'");
if (!$chkTable || mysqli_num_rows($chkTable) == 0) {
    $chkTable2 = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_adjustment'");
    if ($chkTable2 && mysqli_num_rows($chkTable2) > 0) {
        $adjTable = "stock_adjustment";
    } else {
        $adjTable = "inventory_adjustments";
    }
}

// 2. Dynamic Warehouse Table
$whTable = "warehouse";
$chkWh = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouse'");
if (!$chkWh || mysqli_num_rows($chkWh) == 0) {
    $whTable = "warehouses";
}

$whNameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) == 0) {
    $whNameCol = "name";
}

// 3. Detect Available Columns in Adjustment Table
$adjCols = [];
$cRes = @mysqli_query($conn, "SHOW COLUMNS FROM `{$adjTable}`");
if ($cRes) {
    while ($c = mysqli_fetch_assoc($cRes)) { $adjCols[] = strtolower($c['Field']); }
}

$typeCol   = in_array('adjustment_type', $adjCols) ? 'adjustment_type' : (in_array('type', $adjCols) ? 'type' : "'Increase'");
$dateCol   = in_array('adjustment_date', $adjCols) ? 'adjustment_date' : (in_array('created_at', $adjCols) ? 'created_at' : "NOW()");
$reasonCol = in_array('reason', $adjCols) ? 'reason' : "''";

// 4. Comprehensive Ledger Query with Fallbacks
$query = "
    SELECT 
        a.id,
        a.{$typeCol} AS adjustment_type,
        a.quantity,
        a.{$dateCol} AS adjustment_date,
        a.{$reasonCol} AS reason,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(w.{$whNameCol}, i.warehouse, 'Main Facility') AS final_warehouse,
        COALESCE(i.bin_location, 'L0-A1') AS final_bin
    FROM `{$adjTable}` a
    LEFT JOIN inventory i ON i.id = a.inventory_id
    LEFT JOIN products p ON (p.id = a.product_id OR p.id = i.product_id)
    LEFT JOIN `{$whTable}` w ON w.id = i.warehouse_id
    ORDER BY a.id DESC
";

$result = @mysqli_query($conn, $query);

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header & Action Controls -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-sliders text-warning me-2"></i>Stock Adjustment Ledger
            </h2>
            <p class="text-muted mb-0">Audit adjustments, manual reconciliations, and inventory write-offs</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <button onclick="exportAdjustmentCSV()" class="btn btn-outline-success fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
            <a href="create.php" class="btn btn-warning fw-bold text-dark rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Adjustment
            </a>
            <a href="../index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
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

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Adjustment Ledger Table Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">

            <!-- Search & Filters -->
            <div class="row g-3 mb-4">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search SKU, Product Name, or Coordinate...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="typeFilter" class="form-select border-2">
                        <option value="">All Adjustment Types</option>
                        <option value="Increase">➕ Increase (+)</option>
                        <option value="Decrease">➖ Decrease (-)</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="adjustmentTable">
                    <thead class="table-light">
                        <tr>
                            <th width="70">ID</th>
                            <th>Product / SKU</th>
                            <th>Warehouse</th>
                            <th>Bin Coordinate</th>
                            <th class="text-center">Action Type</th>
                            <th class="text-center">Adjusted Qty</th>
                            <th>Date</th>
                            <th>Reason / Note</th>
                            <th width="120" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <?php 
                                $isIncrease = (strcasecmp($row['adjustment_type'], 'Increase') === 0);
                                $typeBadge = $isIncrease 
                                    ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill"><i class="fa-solid fa-arrow-up me-1"></i>Increase</span>'
                                    : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill"><i class="fa-solid fa-arrow-down me-1"></i>Decrease</span>';
                                ?>
                                <tr>
                                    <td><strong>#<?= $row['id']; ?></strong></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['final_product_name']); ?></div>
                                        <code class="text-primary font-monospace small"><?= htmlspecialchars($row['final_sku']); ?></code>
                                    </td>
                                    <td><?= htmlspecialchars($row['final_warehouse']); ?></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                            <?= htmlspecialchars($row['final_bin']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center type-cell" data-type="<?= $isIncrease ? 'Increase' : 'Decrease'; ?>">
                                        <?= $typeBadge; ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge <?= $isIncrease ? 'bg-success' : 'bg-danger'; ?> fs-6 px-3 py-1 rounded-pill font-monospace">
                                            <?= $isIncrease ? '+' : '-'; ?><?= (int)$row['quantity']; ?>
                                        </span>
                                    </td>
                                    <td><small class="text-muted"><?= date("d M Y", strtotime($row['adjustment_date'])); ?></small></td>
                                    <td><small class="text-dark fw-semibold text-truncate d-inline-block" style="max-width: 180px;" title="<?= htmlspecialchars($row['reason']); ?>"><?= htmlspecialchars($row['reason'] ?: 'Manual Recount'); ?></small></td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-outline-info btn-sm rounded-circle" title="View Spec Sheet"><i class="fa-solid fa-eye"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-outline-danger btn-sm rounded-circle" onclick="return confirm('Revert stock changes and delete this adjustment log?');" title="Revert & Delete"><i class="fa-solid fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-sliders fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                    No stock adjustments found. Click <strong>New Adjustment</strong> to log inventory changes.
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
// Filter Adjustments by Search and Action Type
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("searchInput");
    const typeFilter  = document.getElementById("typeFilter");

    function applyFilter() {
        const searchVal = searchInput.value.toLowerCase().trim();
        const typeVal   = typeFilter.value.toLowerCase().trim();
        const rows      = document.querySelectorAll("#adjustmentTable tbody tr");

        rows.forEach(row => {
            const text       = row.innerText.toLowerCase();
            const typeCell   = row.querySelector(".type-cell");
            const typeText   = typeCell ? (typeCell.getAttribute("data-type") || "").toLowerCase() : "";

            const matchSearch = (searchVal === "" || text.includes(searchVal));
            const matchType   = (typeVal === "" || typeText === typeVal);

            row.style.display = (matchSearch && matchType) ? "" : "none";
        });
    }

    if (searchInput) searchInput.addEventListener("keyup", applyFilter);
    if (typeFilter)  typeFilter.addEventListener("change", applyFilter);
});

// CSV Exporter
function exportAdjustmentCSV() {
    let csv = ["ID,Product,SKU,Warehouse,Bin,Type,Quantity,Date,Reason"];
    const rows = document.querySelectorAll("#adjustmentTable tbody tr");
    
    rows.forEach(r => {
        const cols = r.querySelectorAll("td");
        if (cols.length >= 8) {
            const rowData = [
                `"${cols[0].innerText.trim()}"`,
                `"${cols[1].querySelector('.fw-bold') ? cols[1].querySelector('.fw-bold').innerText.trim() : ''}"`,
                `"${cols[1].querySelector('code') ? cols[1].querySelector('code').innerText.trim() : ''}"`,
                `"${cols[2].innerText.trim()}"`,
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
    link.download = `Stock_Adjustments_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>