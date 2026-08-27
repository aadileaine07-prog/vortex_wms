<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) { 
    header("Location: /vortex_wms/login.php"); 
    exit(); 
}

require_once $projectRoot . "/config/database.php";

$filter = strtoupper($_GET['filter'] ?? 'ALL');

// Column fallback safety check
$tableCols = [];
$colRes = @mysqli_query($conn, "SHOW COLUMNS FROM `inventory`");
if ($colRes) {
    while ($c = mysqli_fetch_assoc($colRes)) {
        $tableCols[] = strtolower($c['Field']);
    }
}

$qtyCol  = in_array('available_qty', $tableCols) ? 'available_qty' : 'quantity';
$binCol  = in_array('bin_location', $tableCols) ? 'bin_location' : 'location';
$nameCol = in_array('product_name', $tableCols) ? 'product_name' : 'item_name';
$hasBatch = in_array('batch_no', $tableCols) || in_array('batch_number', $tableCols);
$batchCol = in_array('batch_no', $tableCols) ? 'batch_no' : (in_array('batch_number', $tableCols) ? 'batch_number' : "''");

// Base SQL with FEFO sorting
$sql = "SELECT i.*, 
        COALESCE(p.product_name, i.{$nameCol}, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, i.sku, 'SKU-00') AS final_sku,
        i.{$binCol} AS final_bin,
        i.{$qtyCol} AS final_qty,
        {$batchCol} AS final_batch,
        DATEDIFF(i.expiry_date, CURDATE()) as days_left 
        FROM inventory i 
        LEFT JOIN products p ON p.id = i.product_id
        WHERE i.expiry_date IS NOT NULL AND i.expiry_date != '0000-00-00'";

if ($filter === 'EXPIRED') {
    $sql .= " AND i.expiry_date < CURDATE()";
} elseif ($filter === 'CRITICAL') {
    $sql .= " AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)";
} elseif ($filter === 'WARNING') {
    $sql .= " AND i.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
}

$sql .= " ORDER BY i.expiry_date ASC";
$result = @mysqli_query($conn, $sql);

// Summary Metrics Counters
function getExpCount($conn, $query) {
    $res = @mysqli_query($conn, $query);
    if ($res && $r = mysqli_fetch_assoc($res)) {
        return (int)($r['c'] ?? 0);
    }
    return 0;
}

$expired_cnt  = getExpCount($conn, "SELECT COUNT(*) c FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date < CURDATE()");
$critical_cnt = getExpCount($conn, "SELECT COUNT(*) c FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 15 DAY)");
$warning_cnt  = getExpCount($conn, "SELECT COUNT(*) c FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)");
$safe_cnt     = getExpCount($conn, "SELECT COUNT(*) c FROM inventory WHERE expiry_date IS NOT NULL AND expiry_date != '0000-00-00' AND expiry_date > DATE_ADD(CURDATE(), INTERVAL 30 DAY)");

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-hourglass-half text-danger me-2"></i>FEFO & Stock Expiry Management
            </h2>
            <p class="text-muted mb-0">First-Expired-First-Out batch tracking, picking priority & shelf-life audit</p>
        </div>
        <div class="d-flex gap-2">
            <a href="expiry.php" class="btn btn-outline-secondary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrows-rotate me-1"></i> Refresh
            </a>
            <button onclick="window.print()" class="btn btn-primary rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Expiry Sheet
            </button>
        </div>
    </div>

    <!-- Metric KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6 col-6">
            <a href="expiry.php?filter=EXPIRED" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger h-100 hover-card">
                    <small class="text-danger fw-bold text-uppercase">🔴 Expired Stock</small>
                    <h3 class="fw-bold text-danger mb-0 mt-1"><?= number_format($expired_cnt); ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                    <small class="text-muted">Quarantine immediately</small>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <a href="expiry.php?filter=CRITICAL" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning h-100 hover-card">
                    <small class="text-warning fw-bold text-uppercase">🟠 Critical (&le; 15 Days)</small>
                    <h3 class="fw-bold text-warning mb-0 mt-1"><?= number_format($critical_cnt); ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                    <small class="text-muted">Priority pick for dispatch</small>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <a href="expiry.php?filter=WARNING" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-info h-100 hover-card">
                    <small class="text-info fw-bold text-uppercase">🟡 Near Expiry (16-30 Days)</small>
                    <h3 class="fw-bold text-info mb-0 mt-1"><?= number_format($warning_cnt); ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                    <small class="text-muted">Plan promotions / liquidation</small>
                </div>
            </a>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <a href="expiry.php" class="text-decoration-none">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success h-100 hover-card">
                    <small class="text-success fw-bold text-uppercase">🟢 Fresh Stock (&gt; 30 Days)</small>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= number_format($safe_cnt); ?> <span class="fs-6 text-muted fw-normal">Items</span></h3>
                    <small class="text-muted">Standard shelf life</small>
                </div>
            </a>
        </div>
    </div>

    <!-- Filter Bar & Table -->
    <div class="card shadow-sm border-0 rounded-4 bg-white">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-list-ol me-2 text-primary"></i>Batch Expiry Schedule (Strict FEFO Order)
                </h5>
                <small class="text-muted">Batches sorted by nearest expiration date</small>
            </div>
            <div class="d-flex gap-2 flex-wrap">
                <input type="text" id="expirySearch" class="form-control form-control-sm rounded-pill" placeholder="Search SKU, Product, Bin..." style="width: 200px;">
                <div class="btn-group btn-group-sm">
                    <a href="expiry.php" class="btn btn-outline-dark <?= $filter=='ALL'?'active fw-bold':''; ?>">All</a>
                    <a href="expiry.php?filter=EXPIRED" class="btn btn-outline-danger <?= $filter=='EXPIRED'?'active fw-bold':''; ?>">Expired</a>
                    <a href="expiry.php?filter=CRITICAL" class="btn btn-outline-warning <?= $filter=='CRITICAL'?'active fw-bold':''; ?>">Critical</a>
                    <a href="expiry.php?filter=WARNING" class="btn btn-outline-info <?= $filter=='WARNING'?'active fw-bold':''; ?>">Near Expiry</a>
                </div>
            </div>
        </div>

        <div class="card-body p-4 pt-2">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="expiryTable">
                    <thead class="table-light">
                        <tr>
                            <th>Priority</th>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Batch #</th>
                            <th>Bin Location</th>
                            <th class="text-center">Available</th>
                            <th>Expiry Date</th>
                            <th>Time Remaining</th>
                            <th class="text-center">FEFO Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php 
                            $rank = 1;
                            while($row = mysqli_fetch_assoc($result)): 
                                $days = (int)$row['days_left'];
                                
                                if ($days < 0) {
                                    $badge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Expired</span>';
                                    $priorityBadge = '<span class="badge bg-danger rounded-circle">!</span>';
                                } elseif ($days <= 15) {
                                    $badge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">Critical (Pick First)</span>';
                                    $priorityBadge = '<span class="badge bg-warning text-dark rounded-circle">' . $rank++ . '</span>';
                                } elseif ($days <= 30) {
                                    $badge = '<span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill">Near Expiry</span>';
                                    $priorityBadge = '<span class="badge bg-light text-secondary border rounded-circle">' . $rank++ . '</span>';
                                } else {
                                    $badge = '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Fresh Stock</span>';
                                    $priorityBadge = '<span class="badge bg-light text-secondary border rounded-circle">' . $rank++ . '</span>';
                                }
                            ?>
                                <tr>
                                    <td class="text-center fw-bold"><?= $priorityBadge; ?></td>
                                    <td><code class="fw-bold text-primary font-monospace"><?= htmlspecialchars($row['final_sku']); ?></code></td>
                                    <td><strong class="text-dark"><?= htmlspecialchars($row['final_product_name']); ?></strong></td>
                                    <td><span class="badge bg-light text-dark border font-monospace"><?= htmlspecialchars($row['final_batch'] ?: 'BATCH-01'); ?></span></td>
                                    <td>
                                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace">
                                            <?= htmlspecialchars($row['final_bin'] ?? 'L0-A1'); ?>
                                        </span>
                                    </td>
                                    <td class="text-center"><strong class="fs-6 text-dark"><?= number_format((int)$row['final_qty']); ?></strong></td>
                                    <td><?= date('d M Y', strtotime($row['expiry_date'])); ?></td>
                                    <td>
                                        <?php if ($days < 0): ?>
                                            <span class="text-danger fw-bold"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= abs($days); ?> Days Ago</span>
                                        <?php elseif ($days <= 15): ?>
                                            <span class="text-warning fw-bold"><i class="fa-solid fa-clock me-1"></i><?= $days; ?> Days Left</span>
                                        <?php else: ?>
                                            <span class="text-muted"><?= $days; ?> Days Left</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center"><?= $badge; ?></td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-circle-check text-success fs-1 mb-2 d-block opacity-75"></i>
                                    No stock items match the selected expiry criteria.
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
document.getElementById("expirySearch").addEventListener("keyup", function() {
    const val = this.value.toLowerCase().trim();
    const rows = document.querySelectorAll("#expiryTable tbody tr");
    rows.forEach(r => {
        r.style.display = r.innerText.toLowerCase().includes(val) ? "" : "none";
    });
});
</script>

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