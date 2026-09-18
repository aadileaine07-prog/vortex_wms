<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = dirname(__DIR__, 2);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";
include $projectRoot . "/includes/header.php";

// Access Control: Allow Inventory, Warehouse, Operations, Admin & Super Admin
allowRoles(['Inventory', 'Warehouse', 'Operations']);

// Handle Search & Filters
$search = trim($_GET['search'] ?? '');
$whereSql = "WHERE 1=1";
if (!empty($search)) {
    $s = mysqli_real_escape_string($conn, $search);
    $whereSql .= " AND (product_name LIKE '%$s%' OR product_code LIKE '%$s%' OR warehouse LIKE '%$s%' OR bin_location LIKE '%$s%')";
}

// Metrics Calculations
$total_in_stock_res = mysqli_query($conn, "SELECT IFNULL(SUM(COALESCE(available_qty, quantity, 0)), 0) total FROM inventory");
$total_in_stock = $total_in_stock_res ? (mysqli_fetch_assoc($total_in_stock_res)['total'] ?? 0) : 0;

$reserved_res = mysqli_query($conn, "SELECT IFNULL(SUM(COALESCE(reserved_qty, 0)), 0) total FROM inventory");
$reserved_units = $reserved_res ? (mysqli_fetch_assoc($reserved_res)['total'] ?? 0) : 0;

$low_stock_res = mysqli_query($conn, "SELECT COUNT(*) total FROM inventory WHERE COALESCE(available_qty, quantity, 0) <= 10 AND COALESCE(available_qty, quantity, 0) > 0");
$low_stock_count = $low_stock_res ? (mysqli_fetch_assoc($low_stock_res)['total'] ?? 0) : 0;

$out_stock_res = mysqli_query($conn, "SELECT COUNT(*) total FROM inventory WHERE COALESCE(available_qty, quantity, 0) = 0");
$out_stock_count = $out_stock_res ? (mysqli_fetch_assoc($out_stock_res)['total'] ?? 0) : 0;

include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Header & Action Buttons -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1 text-dark"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Inventory Management</h2>
                <p class="text-muted mb-0">Live stock ledger, bin allocations & multi-location balances</p>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <a href="transfer/create.php" class="btn btn-primary fw-bold shadow-sm rounded-pill px-4">
                    <i class="fa-solid fa-right-left me-1"></i> Transfer Stock
                </a>
                <a href="adjustment/create.php" class="btn btn-outline-secondary fw-bold rounded-pill px-3">
                    <i class="fa-solid fa-sliders me-1"></i> Adjustment
                </a>
                <a href="inbound/create.php" class="btn btn-success fw-bold rounded-pill px-3 text-white">
                    <i class="fa-solid fa-plus me-1"></i> Add Stock (GRN)
                </a>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
                <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Key KPI Metrics Cards with Hover Effects -->
        <div class="row g-4 mb-4">
            
            <div class="col-xl-3 col-md-6">
                <div class="card stat-card p-3 h-100 shadow-sm border-0 rounded-4 bg-white border-start border-4 border-primary hover-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">Total In-Stock Units</span>
                            <h2 class="fw-bold my-1 text-dark"><?= number_format($total_in_stock); ?></h2>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal">Active Ledger Balance</span>
                        </div>
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4">
                            <i class="fa-solid fa-database fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card p-3 h-100 shadow-sm border-0 rounded-4 bg-white border-start border-4 border-warning hover-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">Reserved for Orders</span>
                            <h2 class="fw-bold my-1 text-dark"><?= number_format($reserved_units); ?></h2>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-normal">Allocated in Picking</span>
                        </div>
                        <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-4">
                            <i class="fa-solid fa-lock fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card p-3 h-100 shadow-sm border-0 rounded-4 bg-white border-start border-4 border-info hover-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">Low Stock Alert</span>
                            <h2 class="fw-bold my-1 text-dark"><?= number_format($low_stock_count); ?></h2>
                            <span class="badge bg-info-subtle text-info border border-info-subtle fw-normal">Threshold &le; 10 Units</span>
                        </div>
                        <div class="p-3 bg-info bg-opacity-10 text-info rounded-4">
                            <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-xl-3 col-md-6">
                <div class="card stat-card p-3 h-100 shadow-sm border-0 rounded-4 bg-white border-start border-4 border-danger hover-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">Out of Stock</span>
                            <h2 class="fw-bold my-1 text-dark"><?= number_format($out_stock_count); ?></h2>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fw-normal">Replenishment Needed</span>
                        </div>
                        <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-4">
                            <i class="fa-solid fa-ban fa-2x"></i>
                        </div>
                    </div>
                </div>
            </div>

        </div>

        <!-- Inventory List Table Card -->
        <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-3">
                <form method="GET" class="row g-3 align-items-center">
                    <div class="col-md-6">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-end-0 rounded-start-pill ps-3"><i class="fa-solid fa-search text-muted"></i></span>
                            <input type="text" name="search" class="form-control border-start-0 bg-light rounded-end-pill py-2" placeholder="Search SKU, Product Title, Bin..." value="<?= htmlspecialchars($search); ?>">
                        </div>
                    </div>
                    <div class="col-md-6 text-md-end">
                        <button type="submit" class="btn btn-dark px-4 fw-bold rounded-pill shadow-sm">Filter Stock</button>
                        <?php if (!empty($search)): ?>
                            <a href="index.php" class="btn btn-outline-secondary rounded-pill ms-2 px-3 fw-bold">Reset</a>
                        <?php endif; ?>
                    </div>
                </form>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0 align-middle">
                        <thead class="table-light text-uppercase fs-7">
                            <tr>
                                <th class="ps-4">ID</th>
                                <th>SKU / Product Details</th>
                                <th>Warehouse</th>
                                <th>Bin Location</th>
                                <th>Batch #</th>
                                <th>Available</th>
                                <th>Reserved</th>
                                <th>Status</th>
                                <th class="text-end pe-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $query = "SELECT * FROM inventory $whereSql ORDER BY id DESC";
                            $result = mysqli_query($conn, $query);

                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                                    $invId = $row['id'];
                                    $pName = htmlspecialchars($row['product_name'] ?? 'Unnamed Item');
                                    $pCode = htmlspecialchars($row['product_code'] ?? 'SKU-N/A');
                                    $wh = htmlspecialchars($row['warehouse'] ?? 'Surat S1');
                                    $bin = htmlspecialchars($row['bin_location'] ?? $row['bin_code'] ?? 'N/A');
                                    $batch = htmlspecialchars($row['batch_no'] ?? 'BAT-GEN');
                                    $avail = (int)($row['available_qty'] ?? $row['quantity'] ?? 0);
                                    $reserved = (int)($row['reserved_qty'] ?? 0);
                                    
                                    $status = $row['status'] ?? ($avail > 0 ? 'In Stock' : 'Out of Stock');
                                    $badgeBg = ($status === 'In Stock') ? 'bg-success-subtle text-success border border-success-subtle' : (($status === 'Low Stock') ? 'bg-warning-subtle text-warning border border-warning-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle');

                                    echo "<tr>
                                        <td class='ps-4 fw-bold text-muted'>#{$invId}</td>
                                        <td>
                                            <div class='fw-bold text-dark'>{$pName}</div>
                                            <small class='text-muted font-monospace'>{$pCode}</small>
                                        </td>
                                        <td><span class='fw-semibold text-secondary'>{$wh}</span></td>
                                        <td><code class='text-primary fw-bold'>[📍 {$bin}]</code></td>
                                        <td><small class='text-muted'>{$batch}</small></td>
                                        <td><span class='fw-bold fs-6 text-dark'>{$avail}</span> Units</td>
                                        <td><span class='text-muted'>{$reserved}</span> Units</td>
                                        <td><span class='badge {$badgeBg} px-2 py-1 rounded-pill'>{$status}</span></td>
                                        <td class='text-end pe-4'>
                                            <a href='transfer/create.php' class='btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold'>Relocate</a>
                                        </td>
                                    </tr>";
                                }
                            } else {
                                echo "<tr><td colspan='9' class='text-center py-5 text-muted'><i class='fa-solid fa-box-open fs-2 mb-2 d-block opacity-50'></i>No inventory records found.</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<style>
.hover-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
}
</style>

<?php include $projectRoot . "/includes/footer.php"; ?>