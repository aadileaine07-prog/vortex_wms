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

$totalSO = 0; $shippedSO = 0; $pendingSO = 0;
$soStats = @mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM sales_orders GROUP BY status");
if ($soStats) {
    while ($r = mysqli_fetch_assoc($soStats)) {
        $st = strtolower($r['status']);
        $totalSO += $r['cnt'];
        if (in_array($st, ['shipped', 'delivered', 'completed'])) $shippedSO += $r['cnt'];
        else $pendingSO += $r['cnt'];
    }
}

$ordersList = @mysqli_query($conn, "SELECT * FROM sales_orders ORDER BY id DESC");

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-file-arrow-up text-warning me-2"></i>Outbound & Dispatch Report</h2>
            <p class="text-muted mb-0">Customer shipments, order picking rates, and manifest dispatching</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-primary rounded-pill px-3 shadow-sm"><i class="fa-solid fa-print me-1"></i> Print / PDF</button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-primary">
                <div class="text-muted small fw-bold">TOTAL OUTBOUND ORDERS</div>
                <div class="fs-3 fw-bold text-dark my-1"><?= $totalSO; ?></div>
                <small class="text-muted">Recorded customer shipments</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-warning">
                <div class="text-muted small fw-bold">PENDING / IN PICKING</div>
                <div class="fs-3 fw-bold text-warning my-1"><?= $pendingSO; ?></div>
                <small class="text-muted">Work in progress on floor</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-success">
                <div class="text-muted small fw-bold">DISPATCHED / COMPLETED</div>
                <div class="fs-3 fw-bold text-success my-1"><?= $shippedSO; ?></div>
                <small class="text-muted">Handed over to carrier</small>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 bg-white">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Order No</th>
                            <th>Customer Name</th>
                            <th>Total Amount</th>
                            <th>Order Date</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($ordersList && mysqli_num_rows($ordersList) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($ordersList)): ?>
                                <tr>
                                    <td><code class="fw-bold text-primary font-monospace"><?= htmlspecialchars($row['order_number']); ?></code></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($row['customer_name'] ?? 'Direct Customer'); ?></td>
                                    <td class="fw-bold text-dark">$<?= number_format(floatval($row['total_amount'] ?? 0), 2); ?></td>
                                    <td><?= htmlspecialchars($row['created_at'] ?? '-'); ?></td>
                                    <td>
                                        <?php 
                                        $s = strtolower($row['status'] ?? 'pending');
                                        if (in_array($s, ['shipped', 'delivered', 'completed'])) echo '<span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill">Shipped</span>';
                                        elseif ($s == 'cancelled') echo '<span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill">Cancelled</span>';
                                        else echo '<span class="badge bg-warning-subtle text-warning px-3 py-1 rounded-pill">Processing</span>';
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No outbound orders recorded.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>