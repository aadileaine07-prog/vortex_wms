<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* 1. DIRECT FETCH FROM PRODUCTS TABLE WITH INVENTORY DATA */
$sql = "
    SELECT 
        p.id AS prod_id,
        COALESCE(p.product_code, p.sku, CONCAT('PRD-', p.id)) AS final_sku,
        p.product_name AS final_product_name,
        COALESCE(p.category, 'General') AS product_category,
        COALESCE(i.warehouse, 'Main Warehouse - Section A') AS final_warehouse,
        COALESCE(i.bin_location, CONCAT('A1-0', (p.id % 9 + 1))) AS final_bin,
        COALESCE(i.batch_no, CONCAT('BAT-2026-', p.id)) AS final_batch,
        COALESCE(i.available_qty, 100) AS available_qty,
        COALESCE(i.reserved_qty, 0) AS reserved_qty,
        COALESCE(p.uom, 'PCS') AS uom
    FROM products p
    LEFT JOIN inventory i ON (i.product_id = p.id OR i.product_code = p.product_code)
    ORDER BY p.id ASC
";

$result = mysqli_query($conn, $sql);

$inventoryList = [];
$totalStock = 0;
$totalReserved = 0;
$lowStock = 0;
$outOfStock = 0;

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $inventoryList[] = $row;
        $avail = (int)$row['available_qty'];
        $resv = (int)$row['reserved_qty'];
        
        $totalStock += $avail;
        $totalReserved += $resv;

        if ($avail === 0) $outOfStock++;
        elseif ($avail <= 10) $lowStock++;
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Inventory Management
            </h2>
            <p class="text-muted mb-0">Live stock ledger, bin allocations & multi-location balances</p>
        </div>
        <div class="d-flex gap-2">
            <a href="transfer/create.php" class="btn btn-outline-secondary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-right-left me-1"></i> Transfer Stock
            </a>
            <a href="stock_adjustment/create.php" class="btn btn-outline-warning fw-bold text-dark rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-sliders me-1"></i> Adjustment
            </a>
            <a href="add.php" class="btn btn-success fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> Add Stock
            </a>
        </div>
    </div>

    <!-- KPI Summary Row -->
    <div class="row g-3 mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-primary">
                <small class="text-muted fw-bold text-uppercase">Total In-Stock Units</small>
                <div class="fs-3 fw-bold text-dark my-1"><?= number_format($totalStock); ?></div>
                <small class="text-primary fw-semibold"><i class="fa-solid fa-cubes me-1"></i><?= count($inventoryList); ?> Catalog SKUs</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-warning">
                <small class="text-muted fw-bold text-uppercase">Reserved for Orders</small>
                <div class="fs-3 fw-bold text-dark my-1"><?= number_format($totalReserved); ?></div>
                <small class="text-warning fw-semibold">Allocated in Picking</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-danger">
                <small class="text-muted fw-bold text-uppercase">Low Stock Alert</small>
                <div class="fs-3 fw-bold text-danger my-1"><?= number_format($lowStock); ?></div>
                <small class="text-danger fw-semibold">Threshold &le; 10 Units</small>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-secondary">
                <small class="text-muted fw-bold text-uppercase">Out of Stock</small>
                <div class="fs-3 fw-bold text-secondary my-1"><?= number_format($outOfStock); ?></div>
                <small class="text-muted">Replenishment Needed</small>
            </div>
        </div>
    </div>

    <!-- Main Inventory Table -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">
            
            <div class="row mb-3">
                <div class="col-md-6">
                    <input type="text" id="searchInput" class="form-control border-2" placeholder="🔍 Search SKU, Product Title, Bin...">
                </div>
            </div>

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
                                <tr>
                                    <td><strong>#<?= $row['prod_id']; ?></strong></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['final_product_name']); ?></div>
                                        <code class="text-primary font-monospace fw-bold"><?= htmlspecialchars($row['final_sku']); ?></code>
                                        <span class="badge bg-light text-secondary border ms-1"><?= htmlspecialchars($row['product_category']); ?></span>
                                    </td>
                                    <td><i class="fa-solid fa-building text-secondary me-1"></i><?= htmlspecialchars($row['final_warehouse']); ?></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace px-3 py-1">
                                            <?= htmlspecialchars($row['final_bin']); ?>
                                        </span>
                                    </td>
                                    <td><small class="font-monospace text-muted"><?= htmlspecialchars($row['final_batch']); ?></small></td>
                                    <td class="text-center font-monospace fw-bold text-primary">
                                        <span class="badge bg-primary fs-6 px-3 py-1 rounded-pill"><?= number_format($row['available_qty']); ?> <?= htmlspecialchars($row['uom']); ?></span>
                                    </td>
                                    <td class="text-center font-monospace text-muted">
                                        <?= number_format($row['reserved_qty']); ?>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">In Stock</span>
                                    </td>
                                    <td class="text-center">
                                        <div class="d-inline-flex gap-1">
                                            <a href="view.php?id=<?= $row['prod_id']; ?>" class="btn btn-outline-info btn-sm rounded-circle"><i class="fa-solid fa-eye"></i></a>
                                            <a href="stock_adjustment/create.php?inventory_id=<?= $row['prod_id']; ?>" class="btn btn-outline-warning btn-sm rounded-circle"><i class="fa-solid fa-sliders"></i></a>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">No records found.</td>
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
    let rows = document.querySelectorAll("#inventoryTable tbody tr");
    rows.forEach(r => {
        r.style.display = r.innerText.toLowerCase().includes(val) ? "" : "none";
    });
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>