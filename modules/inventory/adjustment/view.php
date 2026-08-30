<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../../../config/database.php") ? dirname(__DIR__, 4) : dirname(__DIR__, 2));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Stock Adjustment ID is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// 1. Dynamic Table Detection
$adjTable = "stock_adjustments";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_adjustments'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $chkTable2 = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_adjustment'");
    if ($chkTable2 && mysqli_num_rows($chkTable2) > 0) {
        $adjTable = "stock_adjustment";
    } else {
        $adjTable = "inventory_adjustments";
    }
}

// 2. Dynamic Warehouse Table Resolution
$whTable = "warehouses";
$chkWh = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$chkWh || mysqli_num_rows($chkWh) === 0) {
    $whTable = "warehouse";
}

$whNameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) === 0) {
    $whNameCol = "name";
}

// 3. Detect Available Columns in Adjustment Table
$adjCols = [];
$cRes = @mysqli_query($conn, "SHOW COLUMNS FROM `{$adjTable}`");
if ($cRes) {
    while ($c = mysqli_fetch_assoc($cRes)) { 
        $adjCols[] = strtolower($c['Field']); 
    }
}

$typeCol   = in_array('adjustment_type', $adjCols) ? 'adjustment_type' : (in_array('type', $adjCols) ? 'type' : "'Increase'");
$dateCol   = in_array('adjustment_date', $adjCols) ? 'adjustment_date' : (in_array('created_at', $adjCols) ? 'created_at' : "NOW()");
$reasonCol = in_array('reason', $adjCols) ? 'reason' : "''";
$userCol   = in_array('created_by', $adjCols) ? 'created_by' : (in_array('adjusted_by', $adjCols) ? 'adjusted_by' : "'1'");

// 4. Fetch Adjustment Record with Safe Joins
$query = "
    SELECT 
        a.id,
        a.{$typeCol} AS adjustment_type,
        COALESCE(a.quantity, 0) AS quantity,
        COALESCE(a.{$dateCol}, NOW()) AS adjustment_date,
        COALESCE(a.{$reasonCol}, '') AS reason,
        COALESCE(a.{$userCol}, '1') AS user_identifier,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(p.category, 'General') AS product_category,
        COALESCE(w.{$whNameCol}, i.warehouse, 'Surat Central Logistics Park') AS final_warehouse,
        COALESCE(i.bin_location, 'DOCK-INWARD') AS final_bin,
        COALESCE(i.available_qty, 0) AS current_stock_balance,
        COALESCE(e.full_name, e.name, 'Warehouse Auditor') AS employee_name
    FROM `{$adjTable}` a
    LEFT JOIN inventory i ON (i.id = a.inventory_id)
    LEFT JOIN products p ON (p.id = a.product_id OR p.id = i.product_id OR p.product_code = i.product_code)
    LEFT JOIN `{$whTable}` w ON (w.id = i.warehouse_id OR w.{$whNameCol} = i.warehouse)
    LEFT JOIN employees e ON (e.id = a.{$userCol} OR e.employee_id = a.{$userCol})
    WHERE a.id = $id
    LIMIT 1
";

$result = @mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Adjustment record #{$id} not found.";
    header("Location: index.php");
    exit();
}

$row = mysqli_fetch_assoc($result);
$isIncrease = (strcasecmp($row['adjustment_type'], 'Increase') === 0);

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Navigation Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-file-lines text-primary me-2"></i>Stock Adjustment Voucher
            </h2>
            <p class="text-muted mb-0">Audit Record Reference: <code class="fw-bold text-primary font-monospace">#<?= $row['id']; ?></code></p>
        </div>
        <div class="d-flex gap-2">
            <button type="button" onclick="window.print();" class="btn btn-outline-dark fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Voucher
            </button>
            <a href="create.php" class="btn btn-warning fw-bold text-dark rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Adjustment
            </a>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Ledger
            </a>
        </div>
    </div>

    <!-- Voucher Details Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-9 col-lg-11 mx-auto">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
            <div>
                <span class="badge <?= $isIncrease ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-danger-subtle text-danger border border-danger-subtle'; ?> fs-6 px-3 py-2 rounded-pill fw-bold">
                    <i class="fa-solid <?= $isIncrease ? 'fa-arrow-up' : 'fa-arrow-down'; ?> me-1"></i>
                    <?= $isIncrease ? 'SURPLUS STOCK INCREASE (+)' : 'DEFICIT STOCK DECREASE (-)'; ?>
                </span>
            </div>
            <div class="text-muted small">
                <i class="fa-regular fa-calendar me-1"></i> Transaction Date: <strong><?= date("d M Y", strtotime($row['adjustment_date'])); ?></strong>
            </div>
        </div>

        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-bordered align-middle mb-0">
                    <tbody>
                        <tr>
                            <th width="30%" class="bg-light text-muted small fw-bold text-uppercase">Catalog Product</th>
                            <td>
                                <strong class="fs-6 text-dark d-block"><?= htmlspecialchars($row['final_product_name']); ?></strong>
                                <code class="text-primary font-monospace fw-bold"><?= htmlspecialchars($row['final_sku']); ?></code>
                                <span class="badge bg-light text-secondary border ms-1"><?= htmlspecialchars($row['product_category']); ?></span>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted small fw-bold text-uppercase">Storage Location</th>
                            <td>
                                <div><strong>Facility:</strong> <?= htmlspecialchars($row['final_warehouse']); ?></div>
                                <div class="mt-1"><strong>Assigned Bin:</strong> <span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace fs-6 px-2 py-1"><?= htmlspecialchars($row['final_bin']); ?></span></div>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted small fw-bold text-uppercase">Adjusted Units</th>
                            <td>
                                <span class="badge <?= $isIncrease ? 'bg-success' : 'bg-danger'; ?> fs-5 px-3 py-2 rounded-pill font-monospace">
                                    <?= $isIncrease ? '+' : '-'; ?><?= (int)$row['quantity']; ?> Units
                                </span>
                                <?php if (isset($row['current_stock_balance'])): ?>
                                    <small class="text-muted ms-2">(Current Live Available: <strong><?= (int)$row['current_stock_balance']; ?> Units</strong>)</small>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted small fw-bold text-uppercase">Adjustment Reason / Notes</th>
                            <td>
                                <div class="p-3 bg-light rounded-3 text-dark">
                                    <?= !empty($row['reason']) ? nl2br(htmlspecialchars($row['reason'])) : "<em class='text-muted'>No reason specified for this transaction.</em>"; ?>
                                </div>
                            </td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted small fw-bold text-uppercase">Authorized / Logged By</th>
                            <td>
                                <div class="fw-semibold text-dark">
                                    <i class="fa-solid fa-user-check text-success me-1"></i>
                                    <?= htmlspecialchars($row['employee_name'] ?? 'System Administrator'); ?>
                                </div>
                                <small class="text-muted font-monospace">Auth ID: <?= htmlspecialchars($row['user_identifier']); ?></small>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <!-- Bottom Action Footer -->
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top flex-wrap gap-2">
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Adjustments
                </a>
                <div class="d-flex gap-2">
                    <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-outline-danger rounded-pill px-3" onclick="return confirm('Revert stock changes and delete this adjustment voucher?');">
                        <i class="fa-solid fa-trash me-1"></i> Revert & Delete
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>