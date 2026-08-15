<?php
session_start();

$projectRoot = dirname(__DIR__, 2);

if (!isset($_SESSION['employee_id'])) { 
    header("Location: /vortex_wms/login.php"); 
    exit(); 
}

require_once $projectRoot . "/config/database.php";

$filter = $_GET['filter'] ?? 'ALL';

// Base SQL with FEFO Sorting (Sabse pehle expire hone wala item sabse upar)
$sql = "SELECT *, 
        DATEDIFF(expiry_date, CURDATE()) as days_left 
        FROM inventory 
        WHERE expiry_date IS NOT NULL";

if ($filter === 'EXPIRED') {
    $sql .= " AND expiry_date < CURDATE()";
} elseif ($filter === 'CRITICAL') {
    $sql .= " AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)";
} elseif ($filter === 'WARNING') {
    $sql .= " AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}

$sql .= " ORDER BY expiry_date ASC";
$result = mysqli_query($conn, $sql);

// Summary Metrics Counters
$expired_cnt  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM inventory WHERE expiry_date < CURDATE()"))['c'] ?? 0;
$critical_cnt = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM inventory WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)"))['c'] ?? 0;
$warning_cnt  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM inventory WHERE expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)"))['c'] ?? 0;
$safe_cnt     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) c FROM inventory WHERE expiry_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)"))['c'] ?? 0;

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-hourglass-half text-danger me-2"></i>FEFO & Stock Expiry Management</h2>
                <p class="text-muted mb-0">First-Expired-First-Out batch tracking and alert monitor</p>
            </div>
            <div>
                <a href="expiry.php" class="btn btn-outline-secondary btn-sm rounded-pill"><i class="fa-solid fa-rotate me-1"></i> Refresh Data</a>
            </div>
        </div>

        <!-- Metric KPI Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <a href="expiry.php?filter=EXPIRED" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-danger">
                        <small class="text-danger fw-bold">🔴 EXPIRED STOCK</small>
                        <h3 class="fw-extrabold text-danger mb-0 mt-1"><?= $expired_cnt; ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                    </div>
                </a>
            </div>
            <div class="col-md-3 col-6">
                <a href="expiry.php?filter=CRITICAL" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-warning">
                        <small class="text-warning fw-bold">🟠 CRITICAL (<= 15 Days)</small>
                        <h3 class="fw-extrabold text-warning mb-0 mt-1"><?= $critical_cnt; ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                    </div>
                </a>
            </div>
            <div class="col-md-3 col-6">
                <a href="expiry.php?filter=WARNING" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-info">
                        <small class="text-info fw-bold">🟡 NEAR EXPIRY (16-30 Days)</small>
                        <h3 class="fw-extrabold text-info mb-0 mt-1"><?= $warning_cnt; ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                    </div>
                </a>
            </div>
            <div class="col-md-3 col-6">
                <a href="expiry.php" class="text-decoration-none">
                    <div class="card border-0 shadow-sm rounded-3 p-3 bg-white border-start border-4 border-success">
                        <small class="text-success fw-bold">🟢 SAFE STOCK (> 30 Days)</small>
                        <h3 class="fw-extrabold text-success mb-0 mt-1"><?= $safe_cnt; ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                    </div>
                </a>
            </div>
        </div>

        <!-- Filter Bar & Table -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
                <h6 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check me-2 text-primary"></i>Batch Expiry Schedule (FEFO Order)</h6>
                <div class="btn-group btn-group-sm">
                    <a href="expiry.php" class="btn btn-outline-dark <?= $filter=='ALL'?'active':''; ?>">All</a>
                    <a href="expiry.php?filter=EXPIRED" class="btn btn-outline-danger <?= $filter=='EXPIRED'?'active':''; ?>">Expired</a>
                    <a href="expiry.php?filter=CRITICAL" class="btn btn-outline-warning <?= $filter=='CRITICAL'?'active':''; ?>">Critical</a>
                    <a href="expiry.php?filter=WARNING" class="btn btn-outline-info <?= $filter=='WARNING'?'active':''; ?>">Near Expiry</a>
                </div>
            </div>

            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>SKU</th>
                                <th>Item Name</th>
                                <th>Batch No</th>
                                <th>Location (Bin)</th>
                                <th>Available Qty</th>
                                <th>Expiry Date</th>
                                <th>Days Remaining</th>
                                <th>Status Badge</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while($row = mysqli_fetch_assoc($result)): 
                                    $days = (int)$row['days_left'];
                                    
                                    if ($days < 0) {
                                        $badge = '<span class="badge bg-danger px-3 py-2">Expired</span>';
                                        $rowClass = 'table-danger';
                                    } elseif ($days <= 15) {
                                        $badge = '<span class="badge bg-warning text-dark px-3 py-2">Critical Pick First</span>';
                                        $rowClass = 'table-warning';
                                    } elseif ($days <= 30) {
                                        $badge = '<span class="badge bg-info text-dark px-3 py-2">Near Expiry</span>';
                                        $rowClass = '';
                                    } else {
                                        $badge = '<span class="badge bg-success px-3 py-2">Fresh Stock</span>';
                                        $rowClass = '';
                                    }
                                ?>
                                    <tr class="<?= $rowClass; ?>">
                                        <td><strong><?= htmlspecialchars($row['sku']); ?></strong></td>
                                        <td><?= htmlspecialchars($row['item_name'] ?? 'N/A'); ?></td>
                                        <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['batch_no'] ?? 'BATCH-001'); ?></span></td>
                                        <td><i class="fa-solid fa-location-dot text-primary me-1"></i><?= htmlspecialchars($row['location'] ?? 'BIN-A1'); ?></td>
                                        <td><strong class="fs-6"><?= $row['quantity']; ?></strong> Units</td>
                                        <td><?= date('d M Y', strtotime($row['expiry_date'])); ?></td>
                                        <td>
                                            <?php if ($days < 0): ?>
                                                <strong class="text-danger"><?= abs($days); ?> Days Ago</strong>
                                            <?php else: ?>
                                                <strong class="text-dark"><?= $days; ?> Days</strong>
                                            <?php endif; ?>
                                        </td>
                                        <td><?= $badge; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-circle-check text-success fs-1 mb-2 d-block"></i>
                                        No stock matching the selected expiry condition.
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