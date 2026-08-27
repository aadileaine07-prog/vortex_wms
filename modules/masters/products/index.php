<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Multi-Level Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. DYNAMIC COLUMN DETECTION (products table)
   ========================================================================== */

$pCols = [];
$cRes = @mysqli_query($conn, "SHOW COLUMNS FROM products");
if ($cRes) {
    while ($c = mysqli_fetch_assoc($cRes)) {
        $pCols[] = strtolower($c['Field']);
    }
}

$skuCol = in_array('sku', $pCols) ? 'p.sku' : (in_array('product_code', $pCols) ? 'p.product_code' : "''");
$priceCol = in_array('selling_price', $pCols) ? 'p.selling_price' : (in_array('unit_price', $pCols) ? 'p.unit_price' : (in_array('price', $pCols) ? 'p.price' : "0.00"));
$mrpCol = in_array('mrp', $pCols) ? 'p.mrp' : (in_array('cost_price', $pCols) ? 'p.cost_price' : "0.00");
$uomCol = in_array('uom', $pCols) ? 'p.uom' : (in_array('unit', $pCols) ? 'p.unit' : "'PCS'");
$brandCol = in_array('brand', $pCols) ? 'p.brand' : "'-'";
$catCol = in_array('category', $pCols) ? 'p.category' : "'-'";

/* ==========================================================================
   2. FETCH PRODUCTS WITH LIVE INVENTORY BALANCE
   ========================================================================== */

$chkInv = @mysqli_query($conn, "SHOW TABLES LIKE 'inventory'");
$hasInventory = ($chkInv && mysqli_num_rows($chkInv) > 0);

if ($hasInventory) {
    $query = "
        SELECT 
            p.id,
            p.product_name,
            {$skuCol} AS final_sku,
            {$catCol} AS category,
            {$brandCol} AS brand,
            {$uomCol} AS uom,
            {$mrpCol} AS mrp,
            {$priceCol} AS selling_price,
            COALESCE(p.status, 'Active') AS status,
            (SELECT IFNULL(SUM(available_qty), 0) FROM inventory WHERE product_id = p.id OR product_code = {$skuCol}) AS on_hand_stock
        FROM products p
        ORDER BY p.id DESC
    ";
} else {
    $query = "
        SELECT 
            p.id,
            p.product_name,
            {$skuCol} AS final_sku,
            {$catCol} AS category,
            {$brandCol} AS brand,
            {$uomCol} AS uom,
            {$mrpCol} AS mrp,
            {$priceCol} AS selling_price,
            COALESCE(p.status, 'Active') AS status,
            0 AS on_hand_stock
        FROM products p
        ORDER BY p.id DESC
    ";
}

$result = @mysqli_query($conn, $query);

// Summary KPI counts
$cnt_total    = 0;
$cnt_active   = 0;
$cnt_inactive = 0;
$totalValuation = 0.00;
$products_list = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $products_list[] = $row;
        $cnt_total++;
        $isActive = (strcasecmp($row['status'] ?? 'Active', 'Active') === 0 || ($row['status'] ?? '') === '1');
        if ($isActive) {
            $cnt_active++;
        } else {
            $cnt_inactive++;
        }
        $totalValuation += ((float)($row['selling_price'] ?? 0) * (int)($row['on_hand_stock'] ?? 0));
    }
}

// Single Unified Layout Header
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header & Action Controls -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Product Master Catalog
            </h2>
            <p class="text-muted mb-0">Manage enterprise SKU registrations, pricing tiers, and warehouse inventory balances</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button onclick="exportProductCSV()" class="btn btn-outline-success fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
            <a href="create.php" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Add Product
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
                <small class="text-muted fw-bold text-uppercase">Total Catalog SKUs</small>
                <div class="fs-3 fw-bold text-dark my-1"><?= number_format($cnt_total); ?> <span class="fs-6 text-muted fw-normal">Products</span></div>
                <small class="text-primary fw-semibold"><i class="fa-solid fa-barcode me-1"></i>Active Catalog</small>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success h-100">
                <small class="text-muted fw-bold text-uppercase">Active For Trading</small>
                <div class="fs-3 fw-bold text-success my-1"><?= number_format($cnt_active); ?> <span class="fs-6 text-muted fw-normal">SKUs</span></div>
                <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Available for Sale/PO</small>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger h-100">
                <small class="text-muted fw-bold text-uppercase">Inactive / Discontinued</small>
                <div class="fs-3 fw-bold text-danger my-1"><?= number_format($cnt_inactive); ?> <span class="fs-6 text-muted fw-normal">SKUs</span></div>
                <small class="text-danger fw-semibold"><i class="fa-solid fa-ban me-1"></i>Suspended</small>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-dark h-100">
                <small class="text-muted fw-bold text-uppercase">Stock Valuation (Selling)</small>
                <div class="fs-3 fw-bold text-dark my-1">₹<?= number_format($totalValuation, 2); ?></div>
                <small class="text-muted fw-semibold">On-hand inventory estimate</small>
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
                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search SKU Code, Product Name, Category, or Brand...">
                    </div>
                </div>
                <div class="col-md-3">
                    <select id="statusFilter" class="form-select border-2">
                        <option value="">All Statuses</option>
                        <option value="Active">🟢 Active</option>
                        <option value="Inactive">🔴 Inactive</option>
                    </select>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="productTable">
                    <thead class="table-light">
                        <tr>
                            <th width="70">ID</th>
                            <th>Product Details</th>
                            <th>SKU Code</th>
                            <th>Category</th>
                            <th>Brand</th>
                            <th class="text-center">UOM</th>
                            <th class="text-center">On-Hand Stock</th>
                            <th class="text-end">MRP (₹)</th>
                            <th class="text-end">Selling Price (₹)</th>
                            <th class="text-center">Status</th>
                            <th width="140" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($products_list)): ?>
                            <?php foreach ($products_list as $row): ?>
                                <?php 
                                    $isActive = (strcasecmp($row['status'] ?? 'Active', 'Active') === 0 || ($row['status'] ?? '') === '1');
                                    $statusCategory = $isActive ? 'Active' : 'Inactive';
                                    $statusBadge = $isActive 
                                        ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill"><i class="fa-solid fa-circle-check me-1"></i>Active</span>'
                                        : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill"><i class="fa-solid fa-ban me-1"></i>Inactive</span>';
                                    
                                    $stockQty = (int)($row['on_hand_stock'] ?? 0);
                                    $stockBadge = ($stockQty > 10) 
                                        ? '<span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">' . number_format($stockQty) . ' ' . htmlspecialchars($row['uom']) . '</span>'
                                        : (($stockQty > 0) 
                                            ? '<span class="badge bg-warning-subtle text-warning border border-warning-subtle font-monospace">' . number_format($stockQty) . ' ' . htmlspecialchars($row['uom']) . '</span>'
                                            : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace">0 ' . htmlspecialchars($row['uom']) . '</span>');
                                ?>
                                <tr>
                                    <td><strong>#<?= $row['id']; ?></strong></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['product_name']); ?></div>
                                    </td>
                                    <td>
                                        <code class="text-primary font-monospace fw-bold"><?= htmlspecialchars($row['final_sku']); ?></code>
                                    </td>
                                    <td><span class="badge bg-light text-secondary border"><?= htmlspecialchars($row['category'] ?: 'General'); ?></span></td>
                                    <td><small class="text-dark fw-semibold"><?= htmlspecialchars($row['brand'] ?: '-'); ?></small></td>
                                    <td class="text-center"><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($row['uom'] ?: 'PCS'); ?></span></td>
                                    <td class="text-center"><?= $stockBadge; ?></td>
                                    <td class="text-end font-monospace text-muted">₹<?= number_format((float)($row['mrp'] ?? 0), 2); ?></td>
                                    <td class="text-end font-monospace fw-bold text-success">₹<?= number_format((float)($row['selling_price'] ?? 0), 2); ?></td>
                                    <td class="text-center status-cell" data-status="<?= $statusCategory; ?>">
                                        <?= $statusBadge; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-outline-info btn-sm rounded-circle" title="View Product"><i class="fa-solid fa-eye"></i></a>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-outline-warning btn-sm rounded-circle text-dark" title="Edit Product"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-outline-danger btn-sm rounded-circle" onclick="return confirm('⚠️ Delete product <?= htmlspecialchars(addslashes($row['product_name'])); ?>?');" title="Delete Product"><i class="fa-solid fa-trash"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="11" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-boxes-stacked fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                    No products registered in the master catalog. Click <strong>Add Product</strong> to register SKUs.
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
// Live Search and Status Filter
document.addEventListener("DOMContentLoaded", function() {
    const searchInput  = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");

    function applyFilter() {
        const searchVal = searchInput ? searchInput.value.toLowerCase().trim() : "";
        const statusVal = statusFilter ? statusFilter.value.toLowerCase().trim() : "";
        const rows      = document.querySelectorAll("#productTable tbody tr");

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

// CSV Exporter
function exportProductCSV() {
    let csv = ["ID,Product Name,SKU,Category,Brand,UOM,On Hand Stock,MRP,Selling Price,Status"];
    const rows = document.querySelectorAll("#productTable tbody tr");

    rows.forEach(r => {
        const cols = r.querySelectorAll("td");
        if (cols.length >= 10) {
            const rowData = [
                `"${cols[0].innerText.trim()}"`,
                `"${cols[1].querySelector('.fw-bold') ? cols[1].querySelector('.fw-bold').innerText.trim() : ''}"`,
                `"${cols[2].querySelector('code') ? cols[2].querySelector('code').innerText.trim() : ''}"`,
                `"${cols[3].innerText.trim()}"`,
                `"${cols[4].innerText.trim()}"`,
                `"${cols[5].innerText.trim()}"`,
                `"${cols[6].innerText.trim()}"`,
                `"${cols[7].innerText.replace(/₹|,/g, '').trim()}"`,
                `"${cols[8].innerText.replace(/₹|,/g, '').trim()}"`,
                `"${cols[9].innerText.trim()}"`
            ];
            csv.push(rowData.join(","));
        }
    });

    const blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `Products_Catalog_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>