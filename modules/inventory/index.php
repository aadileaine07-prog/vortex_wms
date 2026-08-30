<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Multi-Level Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. DETECT TABLES & FETCH REALTIME INVENTORY LEDGER
   ========================================================================== */

$whTable = "warehouses";
$chkWhTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$chkWhTable || mysqli_num_rows($chkWhTable) === 0) {
    $whTable = "warehouse";
}

$query = "
    SELECT 
        p.id AS prod_id,
        p.product_name,
        COALESCE(p.product_code, p.sku, CONCAT('PRD-', p.id)) AS final_sku,
        COALESCE(p.category, 'General') AS category,
        COALESCE(p.uom, 'PCS') AS uom,
        COALESCE(w.warehouse_name, i.warehouse, 'Surat Central Logistics Park') AS warehouse,
        COALESCE(i.bin_location, 'DOCK-INWARD') AS bin_location,
        COALESCE(i.batch_no, CONCAT('BAT-2026-', LPAD(p.id, 4, '0'))) AS batch_no,
        COALESCE(i.available_qty, 0) AS available_qty,
        COALESCE(i.reserved_qty, 0) AS reserved_qty,
        COALESCE(i.status, 'In Stock') AS stock_status
    FROM products p
    LEFT JOIN inventory i ON (i.product_id = p.id OR i.product_code = p.product_code)
    LEFT JOIN `{$whTable}` w ON (i.warehouse_id = w.id OR i.warehouse = w.warehouse_name OR i.warehouse = w.warehouse_code)
    ORDER BY p.id ASC
";

$result = mysqli_query($conn, $query);

$inventoryList   = [];
$totalStock      = 0;
$totalReserved   = 0;
$lowStockCount   = 0;
$outOfStockCount = 0;

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $inventoryList[] = $row;
        $avail = (int)$row['available_qty'];
        $resv  = (int)$row['reserved_qty'];
        
        $totalStock    += $avail;
        $totalReserved += $resv;

        if ($avail === 0) {
            $outOfStockCount++;
        } elseif ($avail <= 10) {
            $lowStockCount++;
        }
    }
}

$totalSkus = count($inventoryList);

// Single Layout Include
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Action Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Inventory Management
            </h2>
            <p class="text-muted mb-0">Live stock ledger, bin allocations & multi-location balances</p>
        </div>
        <div class="d-flex gap-2 align-items-center">
            <a href="transfer/create.php" class="btn btn-outline-secondary fw-semibold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-right-left me-1"></i> Transfer Stock
            </a>
            <a href="stock_adjustment/create.php" class="btn btn-outline-warning fw-semibold text-dark rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-sliders me-1"></i> Adjustment
            </a>
            <a href="/vortex_wms/modules/inbound/create.php" class="btn btn-success fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Add Stock (GRN)
            </a>
        </div>
    </div>

    <!-- 1. KPI Metric Summary Cards (Matches Screenshot Exact Styles) -->
    <div class="row g-3 mb-4">
        <!-- Total In-Stock -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 11px;">TOTAL IN-STOCK UNITS</small>
                        <div class="fs-3 fw-bold text-dark my-1"><?= number_format($totalStock); ?></div>
                        <small class="text-primary fw-semibold"><i class="fa-solid fa-cubes me-1"></i><?= number_format($totalSkus); ?> Catalog SKUs</small>
                    </div>
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4">
                        <i class="fa-solid fa-boxes-stacked fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Reserved For Orders -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 11px;">RESERVED FOR ORDERS</small>
                        <div class="fs-3 fw-bold text-dark my-1"><?= number_format($totalReserved); ?></div>
                        <small class="text-warning fw-semibold"><i class="fa-solid fa-cart-flatbed me-1"></i>Allocated in Picking</small>
                    </div>
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-4">
                        <i class="fa-solid fa-cart-flatbed fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Alert -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-danger">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 11px;">LOW STOCK ALERT</small>
                        <div class="fs-3 fw-bold text-danger my-1"><?= number_format($lowStockCount); ?></div>
                        <small class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i>Threshold &le; 10 Units</small>
                    </div>
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-4">
                        <i class="fa-solid fa-bell fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Out of Stock -->
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-secondary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted fw-bold text-uppercase" style="font-size: 11px;">OUT OF STOCK</small>
                        <div class="fs-3 fw-bold text-secondary my-1"><?= number_format($outOfStockCount); ?></div>
                        <small class="text-muted fw-semibold"><i class="fa-solid fa-ban me-1"></i>Replenishment Needed</small>
                    </div>
                    <div class="p-3 bg-secondary bg-opacity-10 text-secondary rounded-4">
                        <i class="fa-solid fa-box-open fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. Master Table Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">

            <!-- Search Filter Input -->
            <div class="row mb-4">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search SKU, Product Title, Bin...">
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="inventoryTable">
                    <thead class="table-light">
                        <tr>
                            <th width="70">ID</th>
                            <th>SKU / Product Details</th>
                            <th>Warehouse</th>
                            <th>Bin Location</th>
                            <th>Batch #</th>
                            <th class="text-center">Available</th>
                            <th class="text-center">Reserved</th>
                            <th class="text-center">Status</th>
                            <th width="120" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($inventoryList)): ?>
                            <?php foreach ($inventoryList as $row): ?>
                                <?php
                                    $availQty = (int)$row['available_qty'];
                                    $resvQty  = (int)$row['reserved_qty'];
                                    
                                    $badge = '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">In Stock</span>';
                                    if ($availQty === 0) {
                                        $badge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Out of Stock</span>';
                                    } elseif ($availQty <= 10) {
                                        $badge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">Low Stock</span>';
                                    }
                                ?>
                                <tr>
                                    <td><strong>#<?= $row['prod_id']; ?></strong></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['product_name']); ?></div>
                                        <code class="text-primary font-monospace small fw-bold"><?= htmlspecialchars($row['final_sku']); ?></code>
                                        <span class="badge bg-light text-secondary border ms-1 font-monospace" style="font-size:10px;"><?= htmlspecialchars($row['category']); ?></span>
                                    </td>
                                    <td>
                                        <span class="fw-semibold text-dark"><i class="fa-solid fa-building text-secondary me-1"></i><?= htmlspecialchars($row['warehouse']); ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fs-6 px-3 py-1">
                                            <?= htmlspecialchars($row['bin_location']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <small class="text-muted font-monospace fw-semibold"><?= htmlspecialchars($row['batch_no']); ?></small>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-primary fs-6 px-3 py-1 rounded-pill font-monospace"><?= number_format($availQty); ?> <?= htmlspecialchars($row['uom']); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-secondary-subtle text-dark border fs-6 px-3 py-1 rounded-pill font-monospace"><?= number_format($resvQty); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <?= $badge; ?>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="view.php?id=<?= $row['prod_id']; ?>" class="btn btn-outline-info btn-sm rounded-circle" title="View Profile"><i class="fa-solid fa-eye"></i></a>
                                            <a href="stock_adjustment/create.php?inventory_id=<?= $row['prod_id']; ?>" class="btn btn-outline-warning btn-sm rounded-circle text-dark" title="Adjust Stock"><i class="fa-solid fa-sliders"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    No records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>

        <!-- Footer Stats Bar -->
        <div class="card-footer bg-light p-3 rounded-bottom-4 border-0 d-flex justify-content-between align-items-center">
            <small class="text-muted">Showing live inventory ledger records from Vortex WMS Core</small>
            <button onclick="window.print()" class="btn btn-outline-dark btn-sm rounded-pill px-3 fw-bold">
                <i class="fa-solid fa-print me-1"></i> Print Stock Sheet
            </button>
        </div>
    </div>

</div>

<script>
// Search Filter
document.getElementById("searchInput").addEventListener("keyup", function() {
    let val = this.value.toLowerCase().trim();
    let rows = document.querySelectorAll("#inventoryTable tbody tr");
    rows.forEach(r => {
        if (r.querySelector("td[colspan]")) return;
        r.style.display = r.innerText.toLowerCase().includes(val) ? "" : "none";
    });
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>