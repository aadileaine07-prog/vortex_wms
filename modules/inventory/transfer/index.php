<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") 
        ? dirname(__DIR__, 2) 
        : (file_exists(__DIR__ . "/../../../../config/database.php") ? dirname(__DIR__, 4) : dirname(__DIR__, 1)));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. AUTO-DETECT & FETCH FROM `stock_transfers` OR `stock_transfer`
   ========================================================================== */

$transferTable = "stock_transfers";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_transfers'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $chkTable2 = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_transfer'");
    if ($chkTable2 && mysqli_num_rows($chkTable2) > 0) {
        $transferTable = "stock_transfer";
    } else {
        // Auto create table if missing
        @mysqli_query($conn, "
            CREATE TABLE IF NOT EXISTS `stock_transfers` (
              `id` INT AUTO_INCREMENT PRIMARY KEY,
              `transfer_no` VARCHAR(50) NOT NULL,
              `product_id` INT NOT NULL,
              `product_code` VARCHAR(50) NOT NULL,
              `product_name` VARCHAR(255) NOT NULL,
              `from_warehouse` VARCHAR(100) NOT NULL,
              `from_bin` VARCHAR(50) NOT NULL,
              `to_warehouse` VARCHAR(100) NOT NULL,
              `to_bin` VARCHAR(50) NOT NULL,
              `quantity` INT NOT NULL,
              `transfer_date` DATE NOT NULL,
              `reason` VARCHAR(255) NULL,
              `created_by` VARCHAR(50) NULL,
              `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
        $transferTable = "stock_transfers";
    }
}

// Column Detection
$tCols = [];
$cRes = @mysqli_query($conn, "SHOW COLUMNS FROM `{$transferTable}`");
if ($cRes) {
    while ($c = mysqli_fetch_assoc($cRes)) {
        $tCols[] = strtolower($c['Field']);
    }
}

$reasonField = in_array('reason', $tCols) ? 'reason' : (in_array('notes', $tCols) ? 'notes' : "''");
$dateField   = in_array('transfer_date', $tCols) ? 'transfer_date' : (in_array('created_at', $tCols) ? 'created_at' : "NOW()");

$transfersQuery = "
    SELECT 
        st.*,
        st.{$reasonField} AS movement_reason,
        st.{$dateField} AS movement_date,
        COALESCE(st.product_name, p.product_name, 'Catalog Item') AS final_product_name,
        COALESCE(st.product_code, p.product_code, p.sku, 'PRD-1001') AS final_product_code
    FROM `{$transferTable}` st
    LEFT JOIN products p ON (p.id = st.product_id OR p.product_code = st.product_code)
    ORDER BY st.id DESC
";

$result = @mysqli_query($conn, $transfersQuery);
$transfers = [];
$totalRelocatedQty = 0;

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $transfers[] = $row;
        $totalRelocatedQty += (int)$row['quantity'];
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Action Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-right-left text-primary me-2"></i>Stock Movement Ledger
            </h2>
            <p class="text-muted mb-0">Track internal bin relocations, warehouse-to-warehouse transfers, and putaways</p>
        </div>
        <div class="d-flex gap-2">
            <a href="create.php" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Transfer
            </a>
            <a href="../index.php" class="btn btn-secondary fw-semibold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Summary KPI Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-primary">
                <small class="text-muted fw-bold text-uppercase" style="font-size: 11px;">TOTAL MOVEMENTS LOGGED</small>
                <div class="fs-3 fw-bold text-dark my-1"><?= count($transfers); ?> Vouchers</div>
                <small class="text-primary fw-semibold"><i class="fa-solid fa-clock-rotate-left me-1"></i>Transfer Ledger</small>
            </div>
        </div>
        <div class="col-md-6 col-xl-3">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 border-start border-4 border-success">
                <small class="text-muted fw-bold text-uppercase" style="font-size: 11px;">TOTAL QUANTITY RELOCATED</small>
                <div class="fs-3 fw-bold text-success my-1"><?= number_format($totalRelocatedQty); ?> Units</div>
                <small class="text-muted">Inter-bin & Facility total</small>
            </div>
        </div>
    </div>

    <!-- Main Table Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">

            <!-- Search Filter -->
            <div class="row mb-3">
                <div class="col-md-5">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" id="searchInput" class="form-control border-start-0" placeholder="Search SKU, Product Name, Facility, or Bin Coordinate...">
                    </div>
                </div>
            </div>

            <!-- Ledger Table -->
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0" id="transferTable">
                    <thead class="table-light">
                        <tr>
                            <th width="100">Voucher #</th>
                            <th>Product / SKU</th>
                            <th>Origin (From)</th>
                            <th width="40" class="text-center text-muted">&rarr;</th>
                            <th>Destination (To)</th>
                            <th class="text-center">Transferred Qty</th>
                            <th>Date</th>
                            <th>Remarks</th>
                            <th width="70" class="text-center">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($transfers)): ?>
                            <?php foreach ($transfers as $t): ?>
                                <tr>
                                    <td>
                                        <code class="text-primary font-monospace fw-bold">#<?= htmlspecialchars($t['transfer_no'] ?? ('TRF-' . $t['id'])); ?></code>
                                    </td>
                                    <td>
                                        <strong class="text-dark d-block"><?= htmlspecialchars($t['final_product_name']); ?></strong>
                                        <code class="text-primary font-monospace small"><?= htmlspecialchars($t['final_product_code']); ?></code>
                                    </td>
                                    <td>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace px-2 py-1">
                                            <?= htmlspecialchars($t['from_bin']); ?>
                                        </span>
                                        <small class="text-muted d-block mt-1"><?= htmlspecialchars($t['from_warehouse']); ?></small>
                                    </td>
                                    <td class="text-center text-muted">
                                        <i class="fa-solid fa-arrow-right"></i>
                                    </td>
                                    <td>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace px-2 py-1">
                                            <?= htmlspecialchars($t['to_bin']); ?>
                                        </span>
                                        <small class="text-muted d-block mt-1"><?= htmlspecialchars($t['to_warehouse']); ?></small>
                                    </td>
                                    <td class="text-center font-monospace fw-bold text-dark">
                                        <?= number_format((int)$t['quantity']); ?> Units
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= date('d M Y', strtotime($t['movement_date'])); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-secondary border"><?= htmlspecialchars($t['movement_reason'] ?: 'Internal Relocation'); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <a href="delete.php?id=<?= $t['id']; ?>" class="btn btn-outline-danger btn-sm rounded-circle" onclick="return confirm('⚠️ Revert transferred quantity back to origin bin and delete voucher?');" title="Revert & Delete">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-right-left fa-3x text-secondary opacity-25 mb-3 d-block"></i>
                                    <h5>No stock movement records found</h5>
                                    <p class="small mb-3">Click below to relocate inventory between coordinates.</p>
                                    <a href="create.php" class="btn btn-primary rounded-pill px-4"><i class="fa-solid fa-plus me-1"></i> New Transfer</a>
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
document.getElementById("searchInput").addEventListener("keyup", function() {
    let value = this.value.toLowerCase().trim();
    let rows = document.querySelectorAll("#transferTable tbody tr");

    rows.forEach(function(row) {
        if (row.querySelector("td[colspan]")) return;
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>