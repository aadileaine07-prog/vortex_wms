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

/* ==========================================================================
   FETCH INBOUND SHIPMENTS / GRN LEDGER
   ========================================================================== */

$tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'inbound_shipments'");
$hasTable = ($tableCheck && mysqli_num_rows($tableCheck) > 0);

$shipments = [];
if ($hasTable) {
    $sql = "
        SELECT 
            i.id,
            COALESCE(i.grn_no, CONCAT('GRN-', LPAD(i.id, 5, '0'))) AS grn_no,
            COALESCE(i.received_date, i.created_at, NOW()) AS received_date,
            COALESCE(i.supplier_name, 'Direct Inward') AS supplier_name,
            COALESCE(i.product_name, 'General Item') AS product_name,
            COALESCE(i.product_code, 'SKU-00') AS product_code,
            COALESCE(i.received_qty, 0) AS received_qty,
            COALESCE(i.qc_status, 'Pending') AS qc_status,
            COALESCE(i.putaway_status, 'Pending') AS putaway_status,
            COALESCE(w.warehouse_name, i.warehouse, 'Surat Central Hub') AS target_warehouse
        FROM inbound_shipments i
        LEFT JOIN warehouses w ON (i.warehouse_id = w.id OR i.warehouse = w.warehouse_code)
        ORDER BY i.id DESC
    ";
    $result = mysqli_query($conn, $sql);
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $shipments[] = $row;
        }
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <!-- Header & Action Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-receipt text-primary me-2"></i>Goods Receipts (GRN)</h2>
            <p class="text-muted mb-0">Inbound receipts, dock receiving & quality verification ledger</p>
        </div>
        <div class="d-flex gap-2">
            <a href="create.php" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Inward (GRN)
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 mb-4 shadow-sm" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 mb-4 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Master Table Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">
            
            <!-- Quick Search Bar -->
            <div class="row mb-4">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="inboundSearch" class="form-control border-start-0" placeholder="Search GRN #, Supplier, Product or SKU...">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="inboundTable">
                    <thead class="table-light">
                        <tr>
                            <th>GRN #</th>
                            <th>Date</th>
                            <th>Supplier</th>
                            <th>Product Details</th>
                            <th>Target Warehouse</th>
                            <th class="text-center">Recv Qty</th>
                            <th class="text-center">QC Status</th>
                            <th class="text-center">Putaway</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($shipments)): ?>
                            <?php foreach ($shipments as $r): ?>
                                <tr>
                                    <td><code class="fw-bold text-primary font-monospace fs-6"><?= htmlspecialchars($r['grn_no']); ?></code></td>
                                    <td><small class="text-muted"><?= date('d M Y', strtotime($r['received_date'])); ?></small></td>
                                    <td><strong><?= htmlspecialchars($r['supplier_name']); ?></strong></td>
                                    <td>
                                        <div class="fw-semibold text-dark"><?= htmlspecialchars($r['product_name']); ?></div>
                                        <small class="text-muted font-monospace"><i class="fa-solid fa-barcode me-1"></i><?= htmlspecialchars($r['product_code']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border px-2 py-1">
                                            <i class="fa-solid fa-warehouse text-secondary me-1"></i><?= htmlspecialchars($r['target_warehouse']); ?>
                                        </span>
                                    </td>
                                    <td class="text-center font-monospace fw-bold fs-6"><?= number_format($r['received_qty']); ?></td>
                                    <td class="text-center">
                                        <?php if (strtolower($r['qc_status']) === 'passed'): ?>
                                            <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Passed</span>
                                        <?php elseif (strtolower($r['qc_status']) === 'failed'): ?>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Failed</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-1 rounded-pill">Pending QC</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (strtolower($r['putaway_status']) === 'completed'): ?>
                                            <span class="badge bg-primary px-3 py-1 rounded-pill"><i class="fa-solid fa-check me-1"></i> Binned</span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary-subtle text-dark border px-3 py-1 rounded-pill">At Dock</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if (strtolower($r['qc_status']) === 'pending'): ?>
                                            <a href="qc.php?id=<?= $r['id']; ?>" class="btn btn-sm btn-outline-warning rounded-pill px-3 shadow-sm fw-semibold">QC Check</a>
                                        <?php elseif (strtolower($r['putaway_status']) === 'pending' && strtolower($r['qc_status']) === 'passed'): ?>
                                            <a href="putaway.php?id=<?= $r['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 shadow-sm fw-semibold">Putaway</a>
                                        <?php else: ?>
                                            <a href="view.php?id=<?= $r['id']; ?>" class="btn btn-sm btn-outline-info rounded-circle" title="View Inward Summary"><i class="fa-solid fa-eye"></i></a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box-open fa-3x text-secondary opacity-25 mb-3 d-block"></i>
                                    <h5>No Inbound Receipts Recorded</h5>
                                    <p class="small mb-3">Dock is empty. Click below to register incoming stock shipments.</p>
                                    <a href="create.php" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-plus me-1"></i> New Inward (GRN)</a>
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
document.getElementById("inboundSearch").addEventListener("keyup", function() {
    let filter = this.value.toLowerCase().trim();
    let rows = document.querySelectorAll("#inboundTable tbody tr");

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(filter) ? "" : "none";
    });
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>