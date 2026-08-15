<?php
session_start();

// Dynamic Project Root (Auto-detects path depth)
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) { 
    header("Location: /vortex_wms/login.php"); 
    exit(); 
}

require_once $projectRoot . "/config/database.php";

// Column Safety Check for Expiry & Batch
$has_expiry = false;
$check_cols = mysqli_query($conn, "SHOW COLUMNS FROM inventory LIKE 'expiry_date'");
if ($check_cols && mysqli_num_rows($check_cols) > 0) {
    $has_expiry = true;
}

// Fetch Alerts Data with correct column names
$out_of_stock = mysqli_query($conn, "
    SELECT *, product_code AS sku, product_name AS item_name, available_qty AS quantity, bin_location AS location 
    FROM inventory 
    WHERE available_qty = 0 
    ORDER BY product_name ASC
");

$low_stock = mysqli_query($conn, "
    SELECT *, product_code AS sku, product_name AS item_name, available_qty AS quantity, bin_location AS location 
    FROM inventory 
    WHERE available_qty > 0 AND available_qty <= 50 
    ORDER BY available_qty ASC
");

if ($has_expiry) {
    $expiring = mysqli_query($conn, "
        SELECT *, product_code AS sku, product_name AS item_name, available_qty AS quantity, bin_location AS location, 
               DATEDIFF(expiry_date, CURDATE()) as days_left 
        FROM inventory 
        WHERE expiry_date IS NOT NULL AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) 
        ORDER BY expiry_date ASC
    ");

    $expired = mysqli_query($conn, "
        SELECT *, product_code AS sku, product_name AS item_name, available_qty AS quantity, bin_location AS location, 
               DATEDIFF(expiry_date, CURDATE()) as days_left 
        FROM inventory 
        WHERE expiry_date IS NOT NULL AND expiry_date < CURDATE() 
        ORDER BY expiry_date ASC
    ");
} else {
    $expiring = false;
    $expired  = false;
}

// Count Metrics
$cnt_out_of_stock = $out_of_stock ? mysqli_num_rows($out_of_stock) : 0;
$cnt_low_stock    = $low_stock ? mysqli_num_rows($low_stock) : 0;
$cnt_expiring     = $expiring ? mysqli_num_rows($expiring) : 0;
$cnt_expired      = $expired ? mysqli_num_rows($expired) : 0;

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-bell text-warning me-2"></i>Automated Inventory Alerts</h2>
                <p class="text-muted mb-0">Real-time notifications for low stock, stockouts, and batch expirations</p>
            </div>
            <button onclick="window.location.reload()" class="btn btn-outline-secondary btn-sm rounded-pill px-3">
                <i class="fa-solid fa-rotate me-1"></i> Refresh Alerts
            </button>
        </div>

        <!-- Metric KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-dark">
                    <small class="text-dark fw-bold">⬛ OUT OF STOCK</small>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= $cnt_out_of_stock; ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger">
                    <small class="text-danger fw-bold">🔴 LOW STOCK (&le; 50)</small>
                    <h3 class="fw-bold text-danger mb-0 mt-1"><?= $cnt_low_stock; ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning">
                    <small class="text-warning fw-bold">🟠 EXPIRING SOON (30 Days)</small>
                    <h3 class="fw-bold text-warning mb-0 mt-1"><?= $cnt_expiring; ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger">
                    <small class="text-danger fw-bold">🛑 EXPIRED BATCHES</small>
                    <h3 class="fw-bold text-danger mb-0 mt-1"><?= $cnt_expired; ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                </div>
            </div>
        </div>

        <!-- Alert Tables Grid -->
        <div class="row g-4">
            
            <!-- Out of Stock / Low Stock Panel -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-danger text-white py-3 fw-bold d-flex justify-content-between align-items-center rounded-top-4">
                        <span><i class="fa-solid fa-triangle-exclamation me-2"></i>Stock Level Warnings</span>
                        <span class="badge bg-white text-danger fw-bold"><?= $cnt_out_of_stock + $cnt_low_stock; ?> Total</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>SKU / Code</th>
                                        <th>Item Name</th>
                                        <th>Available Qty</th>
                                        <th>Location</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Out of Stock Rows -->
                                    <?php if ($out_of_stock && mysqli_num_rows($out_of_stock) > 0): ?>
                                        <?php while($row = mysqli_fetch_assoc($out_of_stock)): ?>
                                            <tr class="table-dark">
                                                <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['sku'] ?? 'N/A'); ?></span></td>
                                                <td><strong><?= htmlspecialchars($row['item_name'] ?? 'Unnamed Item'); ?></strong></td>
                                                <td><span class="badge bg-danger">0 Units</span></td>
                                                <td>
                                                    <small class="d-block text-muted"><?= htmlspecialchars($row['warehouse'] ?? 'N/A'); ?></small>
                                                    <code><?= htmlspecialchars($row['location'] ?? 'N/A'); ?></code>
                                                </td>
                                                <td><span class="badge bg-dark">Out of Stock</span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>

                                    <!-- Low Stock Rows -->
                                    <?php if ($low_stock && mysqli_num_rows($low_stock) > 0): ?>
                                        <?php while($row = mysqli_fetch_assoc($low_stock)): ?>
                                            <tr>
                                                <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['sku'] ?? 'N/A'); ?></span></td>
                                                <td><strong><?= htmlspecialchars($row['item_name'] ?? 'Unnamed Item'); ?></strong></td>
                                                <td><span class="badge bg-danger px-2 py-1 fs-6"><?= $row['quantity']; ?> Units</span></td>
                                                <td>
                                                    <small class="d-block text-muted"><?= htmlspecialchars($row['warehouse'] ?? 'N/A'); ?></small>
                                                    <code><?= htmlspecialchars($row['location'] ?? 'N/A'); ?></code>
                                                </td>
                                                <td><span class="badge bg-warning text-dark">Low Stock</span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>

                                    <?php if ($cnt_out_of_stock == 0 && $cnt_low_stock == 0): ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted"><i class="fa-solid fa-circle-check text-success fs-4 me-2"></i>Stock levels are healthy!</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Expiry & Batch Warnings Panel -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-warning text-dark py-3 fw-bold d-flex justify-content-between align-items-center rounded-top-4">
                        <span><i class="fa-solid fa-hourglass-half me-2"></i>Batch Expiry Warnings</span>
                        <span class="badge bg-dark text-warning fw-bold"><?= $cnt_expiring + $cnt_expired; ?> Total</span>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>SKU / Code</th>
                                        <th>Batch No</th>
                                        <th>Expiry Date</th>
                                        <th>Days Left</th>
                                        <th>Action Badge</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <!-- Expired Items -->
                                    <?php if ($expired && mysqli_num_rows($expired) > 0): ?>
                                        <?php while($row = mysqli_fetch_assoc($expired)): ?>
                                            <tr class="table-danger">
                                                <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['sku'] ?? 'N/A'); ?></span></td>
                                                <td><span class="badge bg-secondary"><?= htmlspecialchars($row['batch_no'] ?? 'BATCH-001'); ?></span></td>
                                                <td><?= date('d M Y', strtotime($row['expiry_date'])); ?></td>
                                                <td><strong class="text-danger"><?= abs((int)$row['days_left']); ?> Days Ago</strong></td>
                                                <td><span class="badge bg-danger">Quarantine</span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>

                                    <!-- Expiring Soon Items -->
                                    <?php if ($expiring && mysqli_num_rows($expiring) > 0): ?>
                                        <?php while($row = mysqli_fetch_assoc($expiring)): ?>
                                            <tr>
                                                <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['sku'] ?? 'N/A'); ?></span></td>
                                                <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['batch_no'] ?? 'BATCH-001'); ?></span></td>
                                                <td><?= date('d M Y', strtotime($row['expiry_date'])); ?></td>
                                                <td><strong class="text-dark"><?= $row['days_left']; ?> Days</strong></td>
                                                <td><span class="badge bg-warning text-dark">Pick First (FEFO)</span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php endif; ?>

                                    <?php if ($cnt_expiring == 0 && $cnt_expired == 0): ?>
                                        <tr><td colspan="5" class="text-center py-4 text-muted"><i class="fa-solid fa-shield-halved text-success fs-4 me-2"></i>No batch expiry risks found!</td></tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>