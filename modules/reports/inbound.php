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

// Metrics
$totalPO = 0;
$pendingPO = 0;
$receivedPO = 0;

$poStats = @mysqli_query($conn, "SELECT status, COUNT(*) as cnt FROM purchase_orders GROUP BY status");
if ($poStats) {
    while ($r = mysqli_fetch_assoc($poStats)) {
        $st = strtolower($r['status']);
        $totalPO += $r['cnt'];
        if (in_array($st, ['received', 'completed', 'closed'])) $receivedPO += $r['cnt'];
        else $pendingPO += $r['cnt'];
    }
}

$inboundList = @mysqli_query($conn, "
    SELECT po.*, s.supplier_name 
    FROM purchase_orders po
    LEFT JOIN suppliers s ON s.id = po.supplier_id
    ORDER BY po.id DESC
");

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-file-arrow-down text-primary me-2"></i>Inbound Operations Report</h2>
            <p class="text-muted mb-0">Live Purchase Order fulfillment, GRN statuses & receiving ledger</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-primary rounded-pill px-3 shadow-sm"><i class="fa-solid fa-print me-1"></i> Print / PDF</button>
        </div>
    </div>

    <div class="row g-3 mb-4">
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-primary">
                <div class="text-muted small fw-bold">TOTAL PURCHASE ORDERS</div>
                <div class="fs-3 fw-bold text-dark my-1"><?= $totalPO; ?></div>
                <small class="text-muted">Issued to suppliers</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-warning">
                <div class="text-muted small fw-bold">PENDING / IN-TRANSIT</div>
                <div class="fs-3 fw-bold text-warning my-1"><?= $pendingPO; ?></div>
                <small class="text-muted">Awaiting dock receipt</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-success">
                <div class="text-muted small fw-bold">FULLY RECEIVED</div>
                <div class="fs-3 fw-bold text-success my-1"><?= $receivedPO; ?></div>
                <small class="text-muted">Putaway completed</small>
            </div>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 bg-white">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>PO Number</th>
                            <th>Supplier</th>
                            <th>Expected Date</th>
                            <th>Total Amount</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($inboundList && mysqli_num_rows($inboundList) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($inboundList)): ?>
                                <tr>
                                    <td><code class="fw-bold text-primary font-monospace"><?= htmlspecialchars($row['po_number']); ?></code></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($row['supplier_name'] ?? 'Vendor'); ?></td>
                                    <td><?= htmlspecialchars($row['expected_delivery_date'] ?? ($row['order_date'] ?? '-')); ?></td>
                                    <td class="fw-bold text-dark">$<?= number_format(floatval($row['total_amount'] ?? 0), 2); ?></td>
                                    <td>
                                        <?php 
                                        $s = strtolower($row['status'] ?? 'pending');
                                        if (in_array($s, ['received', 'completed'])) echo '<span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill">Received</span>';
                                        elseif ($s == 'cancelled') echo '<span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill">Cancelled</span>';
                                        else echo '<span class="badge bg-warning-subtle text-warning px-3 py-1 rounded-pill">Pending</span>';
                                        ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="5" class="text-center py-4 text-muted">No purchase orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>