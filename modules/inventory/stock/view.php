<?php
session_start();

// Dynamic Project Root
$projectRoot = dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// Simplified Query: Uses direct columns without missing ID joins
$query = mysqli_query($conn, "
    SELECT *
    FROM inventory
    WHERE id = '$id'
");

if (!$query || mysqli_num_rows($query) == 0) {
    die("<div class='alert alert-danger m-4'>Inventory Item Not Found!</div>");
}

$item = mysqli_fetch_assoc($query);

// Quantity calculations
$available_qty = intval($item['available_qty']);
$reserved_qty  = intval($item['reserved_qty'] ?? 0);
$total_qty     = $available_qty + $reserved_qty;

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
                    <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Stock Item Details
                </h2>
                <p class="text-muted mb-0">Detailed inventory overview for <strong><?= htmlspecialchars($item['product_name']); ?></strong></p>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary px-3 me-2">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
                </a>
                <a href="../edit.php?id=<?= $item['id']; ?>" class="btn btn-warning px-3 me-2 text-dark">
                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Item
                </a>
                <a href="../adjustment/create.php?inventory_id=<?= $item['id']; ?>" class="btn btn-success px-3">
                    <i class="fa-solid fa-sliders me-1"></i> Adjust Stock
                </a>
            </div>
        </div>

        <div class="row g-4">
            
            <!-- KPI Cards -->
            <div class="col-md-4">
                <div class="card shadow-sm border-0 rounded-4 bg-primary text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-uppercase fw-semibold text-white-50">Available Stock</small>
                            <h2 class="fw-bold mb-0 mt-1"><?= number_format($available_qty); ?></h2>
                        </div>
                        <i class="fa-solid fa-box-open fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-0 rounded-4 bg-warning text-dark p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-uppercase fw-semibold text-dark-50">Reserved Stock</small>
                            <h2 class="fw-bold mb-0 mt-1"><?= number_format($reserved_qty); ?></h2>
                        </div>
                        <i class="fa-solid fa-lock fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <div class="col-md-4">
                <div class="card shadow-sm border-0 rounded-4 bg-dark text-white p-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <small class="text-uppercase fw-semibold text-white-50">Total On-Hand</small>
                            <h2 class="fw-bold mb-0 mt-1"><?= number_format($total_qty); ?></h2>
                        </div>
                        <i class="fa-solid fa-warehouse fa-2x opacity-50"></i>
                    </div>
                </div>
            </div>

            <!-- Detail Information Table -->
            <div class="col-12">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center border-bottom">
                        <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-circle-info text-primary me-2"></i>Product Specification</h5>
                        <div>
                            <?php 
                            $status = $item['status'] ?? 'In Stock';
                            if ($status == 'In Stock' || $available_qty > 10): ?>
                                <span class="badge bg-success px-3 py-2 fs-6"><i class="fa-solid fa-circle-check me-1"></i> In Stock</span>
                            <?php elseif ($available_qty > 0): ?>
                                <span class="badge bg-warning text-dark px-3 py-2 fs-6"><i class="fa-solid fa-triangle-exclamation me-1"></i> Low Stock</span>
                            <?php else: ?>
                                <span class="badge bg-danger px-3 py-2 fs-6"><i class="fa-solid fa-circle-xmark me-1"></i> Out of Stock</span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="card-body p-4">
                        <div class="table-responsive">
                            <table class="table table-bordered align-middle">
                                <tbody>
                                    <tr>
                                        <th width="25%" class="bg-light fw-semibold">Inventory Record ID</th>
                                        <td><strong>#<?= $item['id']; ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light fw-semibold">Product Code</th>
                                        <td>
                                            <span class="badge bg-secondary font-monospace fs-6">
                                                <?= htmlspecialchars($item['product_code']); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light fw-semibold">Product Name</th>
                                        <td><strong class="fs-5 text-dark"><?= htmlspecialchars($item['product_name']); ?></strong></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light fw-semibold">Warehouse Location</th>
                                        <td>
                                            <i class="fa-solid fa-warehouse text-primary me-2"></i>
                                            <strong><?= htmlspecialchars($item['warehouse'] ?? 'N/A'); ?></strong>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light fw-semibold">Bin Location</th>
                                        <td>
                                            <i class="fa-solid fa-location-dot text-danger me-2"></i>
                                            <code class="fs-6 fw-bold text-dark"><?= htmlspecialchars($item['bin_location'] ?? 'N/A'); ?></code>
                                        </td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light fw-semibold">Available Units</th>
                                        <td><span class="badge bg-primary px-3 py-2 fs-6"><?= $available_qty; ?> Units</span></td>
                                    </tr>
                                    <tr>
                                        <th class="bg-light fw-semibold">Reserved Units</th>
                                        <td><span class="badge bg-warning text-dark px-3 py-2 fs-6"><?= $reserved_qty; ?> Units</span></td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>

                        <!-- Footer Actions -->
                        <div class="d-flex justify-content-between align-items-center mt-4 pt-2 border-top">
                            <a href="index.php" class="btn btn-outline-secondary px-4">
                                <i class="fa-solid fa-arrow-left me-1"></i> Back to Stock List
                            </a>
                            <div>
                                <button type="button" onclick="window.print();" class="btn btn-outline-primary me-2">
                                    <i class="fa-solid fa-print me-1"></i> Print Stock Slip
                                </button>
                                <a href="../edit.php?id=<?= $item['id']; ?>" class="btn btn-warning px-4 text-dark font-weight-bold">
                                    <i class="fa-solid fa-pen-to-square me-1"></i> Edit Stock
                                </a>
                            </div>
                        </div>

                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>