<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Stock Transfer ID is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

/* ==========================================================================
   1. DYNAMIC TABLE RESOLUTION (stock_transfers / stock_transfer)
   ========================================================================== */

$transferTable = "stock_transfers";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_transfers'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $chkTable2 = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_transfer'");
    if ($chkTable2 && mysqli_num_rows($chkTable2) > 0) {
        $transferTable = "stock_transfer";
    } else {
        $transferTable = "inventory_transfers";
    }
}

// Detect Warehouse Table
$whTable = "warehouse";
$chkWh = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouse'");
if (!$chkWh || mysqli_num_rows($chkWh) === 0) {
    $whTable = "warehouses";
}

$whNameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) === 0) {
    $whNameCol = "name";
}

// Detect Available Columns
$tCols = [];
$cRes = @mysqli_query($conn, "SHOW COLUMNS FROM `{$transferTable}`");
if ($cRes) {
    while ($c = mysqli_fetch_assoc($cRes)) {
        $tCols[] = strtolower($c['Field']);
    }
}

$dateCol = in_array('transfer_date', $tCols) ? 'transfer_date' : (in_array('created_at', $tCols) ? 'created_at' : "NOW()");
$remCol  = in_array('remarks', $tCols) ? 'remarks' : (in_array('reason', $tCols) ? 'reason' : "''");
$userCol = in_array('created_by', $tCols) ? 'created_by' : (in_array('transferred_by', $tCols) ? 'transferred_by' : "'1'");

/* ==========================================================================
   2. FETCH TRANSFER VOUCHER DETAILS WITH MASTER JOINS
   ========================================================================== */

$query = "
    SELECT 
        t.id,
        t.quantity,
        t.{$dateCol} AS transfer_date,
        t.{$remCol} AS remarks,
        t.{$userCol} AS user_identifier,
        COALESCE(p.product_name, t.product_name, i.product_name, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, p.product_code, t.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(p.category, 'General') AS product_category,
        COALESCE(t.from_warehouse, w1.{$whNameCol}, 'Main Warehouse') AS from_warehouse,
        COALESCE(t.from_bin, i.bin_location, 'L0-A1') AS from_bin,
        COALESCE(t.to_warehouse, w2.{$whNameCol}, 'Secondary Warehouse') AS to_warehouse,
        COALESCE(t.to_bin, 'L0-B1') AS to_bin,
        e.full_name AS employee_name
    FROM `{$transferTable}` t
    LEFT JOIN inventory i ON i.id = t.inventory_id
    LEFT JOIN products p ON (p.id = t.product_id OR p.id = i.product_id)
    LEFT JOIN `{$whTable}` w1 ON (w1.id = t.from_warehouse_id)
    LEFT JOIN `{$whTable}` w2 ON (w2.id = t.to_warehouse_id)
    LEFT JOIN employees e ON (e.id = t.{$userCol} OR e.employee_id = t.{$userCol})
    WHERE t.id = '$id'
    LIMIT 1
";

$result = @mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Transfer voucher #{$id} not found.";
    header("Location: index.php");
    exit();
}

$row = mysqli_fetch_assoc($result);

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-file-invoice text-primary me-2"></i>Stock Movement Voucher
            </h2>
            <p class="text-muted mb-0">Audit Slip Reference: <code class="fw-bold text-primary font-monospace">#<?= $row['id']; ?></code></p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button type="button" onclick="window.print();" class="btn btn-outline-dark fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Transfer Slip
            </button>
            <a href="create.php" class="btn btn-primary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Transfer
            </a>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Ledger
            </a>
        </div>
    </div>

    <!-- Main Voucher Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-9 col-lg-11 mx-auto">
        
        <!-- Header Ribbon -->
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fs-6 px-3 py-2 rounded-pill fw-bold">
                    <i class="fa-solid fa-dolly me-1"></i> COMPLETED TRANSFER VOUCHER
                </span>
            </div>
            <div class="text-muted small">
                <i class="fa-regular fa-calendar me-1"></i> Movement Date: <strong><?= date("d M Y", strtotime($row['transfer_date'])); ?></strong>
            </div>
        </div>

        <div class="card-body p-4">

            <!-- Visual Route Banner -->
            <div class="p-3 bg-light rounded-4 border mb-4">
                <div class="row align-items-center text-center text-md-start g-3">
                    
                    <!-- Source Point -->
                    <div class="col-md-5">
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size:11px;">Origin Coordinate (From)</small>
                        <strong class="fs-6 text-dark d-block"><?= htmlspecialchars($row['from_warehouse']); ?></strong>
                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle font-monospace mt-1 px-3 py-1">
                            <i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($row['from_bin']); ?>
                        </span>
                    </div>

                    <!-- Arrow Indicator -->
                    <div class="col-md-2 text-center">
                        <div class="d-inline-flex flex-column align-items-center">
                            <span class="badge bg-primary rounded-pill px-2 py-1 font-monospace mb-1"><?= number_format((int)$row['quantity']); ?> Units</span>
                            <i class="fa-solid fa-arrow-right-long fs-3 text-primary"></i>
                        </div>
                    </div>

                    <!-- Destination Point -->
                    <div class="col-md-5 text-md-end">
                        <small class="text-muted text-uppercase fw-bold d-block" style="font-size:11px;">Destination Coordinate (To)</small>
                        <strong class="fs-6 text-dark d-block"><?= htmlspecialchars($row['to_warehouse']); ?></strong>
                        <span class="badge bg-success-subtle text-success border border-success-subtle font-monospace mt-1 px-3 py-1">
                            <i class="fa-solid fa-location-dot me-1"></i><?= htmlspecialchars($row['to_bin']); ?>
                        </span>
                    </div>

                </div>
            </div>

            <!-- Detailed Specifications Table -->
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <tbody>
                        <tr>
                            <th width="30%" class="bg-light text-muted small fw-bold text-uppercase">Catalog SKU</th>
                            <td>
                                <code class="fs-6 fw-bold text-primary font-monospace"><?= htmlspecialchars($row['final_sku']); ?></code>
                                <span class="badge bg-light text-secondary border ms-2"><?= htmlspecialchars($row['product_category']); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted small fw-bold text-uppercase">Product Description</th>
                            <td><strong class="fs-6 text-dark"><?= htmlspecialchars($row['final_product_name']); ?></strong></td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted small fw-bold text-uppercase">Relocated Quantity</th>
                            <td>
                                <span class="badge bg-primary fs-5 px-3 py-2 rounded-pill font-monospace">
                                    <?= number_format((int)$row['quantity']); ?> Units
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted small fw-bold text-uppercase">Operational Remarks / Reason</th>
                            <td>
                                <div class="p-3 bg-light rounded-3 text-dark">
                                    <?= !empty($row['remarks']) ? nl2br(htmlspecialchars($row['remarks'])) : "<em class='text-muted'>Standard inventory rebalancing / direct putaway transfer.</em>"; ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted small fw-bold text-uppercase">Authorized Staff</th>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <i class="fa-solid fa-user-check text-success me-1"></i>
                                    <?= htmlspecialchars($row['employee_name'] ?? 'System Operator'); ?>
                                </div>
                                <small class="text-muted font-monospace">Employee ID #<?= htmlspecialchars($row['user_identifier']); ?></small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Footer Actions -->
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top flex-wrap gap-2">
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Ledger
                </a>
                <div class="d-flex gap-2">
                    <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-outline-danger rounded-pill px-3" onclick="return confirm('⚠️ Reverting this transfer will restore stock to origin coordinate. Proceed?');">
                        <i class="fa-solid fa-trash me-1"></i> Revert & Delete
                    </a>
                </div>
            </div>

        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>