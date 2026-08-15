<?php
session_start();

// Dynamic Project Root (Auto-detects depth regardless of folder structure)
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) { 
    header("Location: /vortex_wms/login.php"); 
    exit(); 
}

require_once $projectRoot . "/config/database.php";

// Safe Query: Uses exact column names from your inventory table (product_code, product_name, available_qty)
$abc_data = mysqli_query($conn, "
    SELECT 
        id,
        COALESCE(product_code, 'N/A') AS sku, 
        COALESCE(product_name, 'Unnamed Item') AS item_name, 
        COALESCE(warehouse, 'N/A') AS warehouse,
        COALESCE(bin_location, 'N/A') AS bin_location,
        COALESCE(available_qty, 0) AS quantity,
        CASE 
            WHEN COALESCE(available_qty, 0) >= 500 THEN 'A (Fast Moving)'
            WHEN COALESCE(available_qty, 0) >= 100 THEN 'B (Moderate)'
            ELSE 'C (Slow Moving)'
        END as abc_category
    FROM inventory 
    ORDER BY available_qty DESC
");

// Calculate Category Counts for Summary Cards
$catA_count = 0;
$catB_count = 0;
$catC_count = 0;

$rows = [];
if ($abc_data && mysqli_num_rows($abc_data) > 0) {
    while ($r = mysqli_fetch_assoc($abc_data)) {
        $rows[] = $r;
        if (str_starts_with($r['abc_category'], 'A')) $catA_count++;
        elseif (str_starts_with($r['abc_category'], 'B')) $catB_count++;
        else $catC_count++;
    }
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-chart-pie text-primary me-2"></i>ABC Inventory Velocity Report</h2>
                <p class="text-muted mb-0">Fast-moving vs Slow-moving Stock Analysis based on available stock quantity</p>
            </div>
            <a href="../index.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory</a>
        </div>

        <!-- Summary KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card shadow-sm border-0 rounded-4 bg-success text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-uppercase fw-semibold text-white-50">Category A (Fast Moving)</small>
                            <h2 class="fw-bold mb-0 mt-1"><?= $catA_count; ?> Items</h2>
                            <small>Stock ≥ 500 Units</small>
                        </div>
                        <i class="fa-solid fa-bolt fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-0 rounded-4 bg-warning text-dark p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-uppercase fw-semibold text-dark-50">Category B (Moderate)</small>
                            <h2 class="fw-bold mb-0 mt-1"><?= $catB_count; ?> Items</h2>
                            <small>Stock 100 - 499 Units</small>
                        </div>
                        <i class="fa-solid fa-gauge-simple-high fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-0 rounded-4 bg-secondary text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-uppercase fw-semibold text-white-50">Category C (Slow Moving)</small>
                            <h2 class="fw-bold mb-0 mt-1"><?= $catC_count; ?> Items</h2>
                            <small>Stock &lt; 100 Units</small>
                        </div>
                        <i class="fa-solid fa-turtle fa-3x opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Table Card -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle border">
                        <thead class="table-dark">
                            <tr>
                                <th>SKU / Code</th>
                                <th>Item Name</th>
                                <th>Warehouse</th>
                                <th>Bin Location</th>
                                <th width="140">Available Qty</th>
                                <th width="220">ABC Velocity Category</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($rows)): ?>
                                <?php foreach ($rows as $row): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary font-monospace fs-6"><?= htmlspecialchars($row['sku']); ?></span></td>
                                        <td><strong><?= htmlspecialchars($row['item_name']); ?></strong></td>
                                        <td><?= htmlspecialchars($row['warehouse']); ?></td>
                                        <td><code><?= htmlspecialchars($row['bin_location']); ?></code></td>
                                        <td><span class="fw-bold text-primary fs-6"><?= number_format($row['quantity']); ?></span></td>
                                        <td>
                                            <?php if (str_starts_with($row['abc_category'], 'A')): ?>
                                                <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-bolt me-1"></i> Category A (Fast)</span>
                                            <?php elseif (str_starts_with($row['abc_category'], 'B')): ?>
                                                <span class="badge bg-warning text-dark px-3 py-2 fs-6"><i class="fa-solid fa-gauge-simple-high me-1"></i> Category B (Moderate)</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary px-3 py-2 fs-6"><i class="fa-solid fa-turtle me-1"></i> Category C (Slow)</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-box-open fs-2 mb-2 d-block"></i>
                                        No Inventory Items Found
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