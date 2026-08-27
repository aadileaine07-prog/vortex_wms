<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    header("Location: index.php");
    exit();
}

/* ==========================================================================
   1. INPUT SANITIZATION & INITIAL VALIDATION
   ========================================================================== */

$inventory_id   = intval($_POST['inventory_id'] ?? 0);
$transfer_date  = !empty($_POST['transfer_date']) ? $_POST['transfer_date'] : date('Y-m-d');
$from_warehouse = trim($_POST['from_warehouse'] ?? '');
$from_bin       = strtoupper(trim($_POST['from_bin'] ?? ''));
$to_warehouse   = trim($_POST['to_warehouse'] ?? '');
$to_bin         = strtoupper(trim($_POST['to_bin'] ?? ''));
$quantity       = intval($_POST['quantity'] ?? 0);
$remarks        = trim($_POST['remarks'] ?? 'Direct Warehouse Movement');
$user_id        = $_SESSION['employee_id'];

if ($inventory_id <= 0 || $quantity <= 0) {
    $_SESSION['error'] = "Invalid item or quantity selected for transfer.";
    header("Location: create.php");
    exit();
}

if (empty($to_warehouse) || empty($to_bin)) {
    $_SESSION['error'] = "Destination warehouse and bin coordinate are required.";
    header("Location: create.php");
    exit();
}

if ($from_warehouse === $to_warehouse && $from_bin === $to_bin) {
    $_SESSION['error'] = "Origin coordinate and destination coordinate cannot be identical.";
    header("Location: create.php");
    exit();
}

/* ==========================================================================
   2. FETCH ORIGIN INVENTORY & VERIFY BALANCE
   ========================================================================== */

$invRes = mysqli_query($conn, "SELECT * FROM inventory WHERE id = '$inventory_id' LIMIT 1");
if (!$invRes || mysqli_num_rows($invRes) === 0) {
    $_SESSION['error'] = "Source inventory item record not found.";
    header("Location: create.php");
    exit();
}

$srcItem      = mysqli_fetch_assoc($invRes);
$currentAvail = (int)($srcItem['available_qty'] ?? 0);

if ($quantity > $currentAvail) {
    $_SESSION['error'] = "Insufficient stock! Requested {$quantity} units, but only {$currentAvail} units are available in bin {$from_bin}.";
    header("Location: create.php");
    exit();
}

$product_id   = intval($srcItem['product_id'] ?? 0);
$product_code = $srcItem['product_code'] ?? ($srcItem['sku'] ?? 'SKU-00');
$product_name = $srcItem['product_name'] ?? 'Stock Item';
$batch_no     = $srcItem['batch_no'] ?? ($srcItem['batch_number'] ?? null);
$expiry_date  = (!empty($srcItem['expiry_date']) && $srcItem['expiry_date'] !== '0000-00-00') ? $srcItem['expiry_date'] : null;

/* ==========================================================================
   3. DESTINATION BIN CAPACITY CHECK
   ========================================================================== */

$destBinChk = @mysqli_query($conn, "
    SELECT 
        COALESCE(max_units, max_capacity, 100) AS max_limit,
        warehouse_id,
        (SELECT IFNULL(SUM(available_qty + reserved_qty), 0) FROM inventory WHERE bin_location = '$to_bin') AS already_occupied
    FROM bin_locations 
    WHERE bin_code = '$to_bin'
    LIMIT 1
");

$destWarehouseId = 0;
if ($destBinChk && $bData = mysqli_fetch_assoc($destBinChk)) {
    $destWarehouseId = intval($bData['warehouse_id'] ?? 0);
    $projectedTotal = $bData['already_occupied'] + $quantity;
    if ($projectedTotal > $bData['max_limit']) {
        $_SESSION['error'] = "⚠️ Destination Overcapacity: Bin <strong>{$to_bin}</strong> allows max {$bData['max_limit']} units (Currently has {$bData['already_occupied']}, tried adding {$quantity}).";
        header("Location: create.php");
        exit();
    }
}

/* ==========================================================================
   4. DYNAMIC TRANSFER & INVENTORY TABLE RESOLUTION
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

// Columns detection for Transfer table
$tCols = [];
$tRes = @mysqli_query($conn, "SHOW COLUMNS FROM `{$transferTable}`");
if ($tRes) {
    while ($c = mysqli_fetch_assoc($tRes)) { $tCols[] = strtolower($c['Field']); }
}

// Columns detection for Inventory table
$invCols = [];
$iRes = @mysqli_query($conn, "SHOW COLUMNS FROM `inventory`");
if ($iRes) {
    while ($c = mysqli_fetch_assoc($iRes)) { $invCols[] = strtolower($c['Field']); }
}

/* ==========================================================================
   5. ATOMIC DATABASE TRANSACTION
   ========================================================================== */

mysqli_begin_transaction($conn);

try {

    // -------------------------------------------------------------
    // STEP A: Deduct stock from Origin Bin
    // -------------------------------------------------------------
    $newSrcQty = $currentAvail - $quantity;
    $srcStatus = ($newSrcQty <= 0) ? "Out of Stock" : (($newSrcQty <= 10) ? "Low Stock" : "In Stock");

    $updSrcSql = "UPDATE inventory SET available_qty = '$newSrcQty', status = '$srcStatus' WHERE id = '$inventory_id'";
    if (!mysqli_query($conn, $updSrcSql)) {
        throw new Exception("Source inventory deduction failed: " . mysqli_error($conn));
    }

    // -------------------------------------------------------------
    // STEP B: Add / Update stock at Destination Coordinate
    // -------------------------------------------------------------
    $destWhere = ["bin_location = '$to_bin'"];
    if ($product_id > 0) {
        $destWhere[] = "product_id = '$product_id'";
    } else {
        $destWhere[] = "(product_code = '" . mysqli_real_escape_string($conn, $product_code) . "' OR sku = '" . mysqli_real_escape_string($conn, $product_code) . "')";
    }

    if (!empty($batch_no) && in_array('batch_no', $invCols)) {
        $destWhere[] = "batch_no = '" . mysqli_real_escape_string($conn, $batch_no) . "'";
    }

    $destCheckSql = "SELECT * FROM inventory WHERE " . implode(" AND ", $destWhere) . " LIMIT 1";
    $destCheckRes = mysqli_query($conn, $destCheckSql);

    if ($destCheckRes && mysqli_num_rows($destCheckRes) > 0) {
        $destRow = mysqli_fetch_assoc($destCheckRes);
        $newDestQty = (int)$destRow['available_qty'] + $quantity;
        $destStatus = ($newDestQty <= 0) ? "Out of Stock" : (($newDestQty <= 10) ? "Low Stock" : "In Stock");

        $updDestSql = "UPDATE inventory SET available_qty = '$newDestQty', status = '$destStatus' WHERE id = '" . $destRow['id'] . "'";
        if (!mysqli_query($conn, $updDestSql)) {
            throw new Exception("Destination inventory addition failed: " . mysqli_error($conn));
        }
    } else {
        // Insert new inventory ledger record at target bin
        $insertFields = ["bin_location", "available_qty", "reserved_qty", "status"];
        $insertValues = ["'$to_bin'", "'$quantity'", "'0'", "'In Stock'"];

        if (in_array('product_id', $invCols) && $product_id > 0) {
            $insertFields[] = "product_id";
            $insertValues[] = "'$product_id'";
        }
        if (in_array('product_code', $invCols)) {
            $insertFields[] = "product_code";
            $insertValues[] = "'" . mysqli_real_escape_string($conn, $product_code) . "'";
        } elseif (in_array('sku', $invCols)) {
            $insertFields[] = "sku";
            $insertValues[] = "'" . mysqli_real_escape_string($conn, $product_code) . "'";
        }
        if (in_array('product_name', $invCols)) {
            $insertFields[] = "product_name";
            $insertValues[] = "'" . mysqli_real_escape_string($conn, $product_name) . "'";
        }
        if (in_array('warehouse_id', $invCols) && $destWarehouseId > 0) {
            $insertFields[] = "warehouse_id";
            $insertValues[] = "'$destWarehouseId'";
        }
        if (in_array('warehouse', $invCols)) {
            $insertFields[] = "warehouse";
            $insertValues[] = "'" . mysqli_real_escape_string($conn, $to_warehouse) . "'";
        }
        if (in_array('batch_no', $invCols) && !empty($batch_no)) {
            $insertFields[] = "batch_no";
            $insertValues[] = "'" . mysqli_real_escape_string($conn, $batch_no) . "'";
        } elseif (in_array('batch_number', $invCols) && !empty($batch_no)) {
            $insertFields[] = "batch_number";
            $insertValues[] = "'" . mysqli_real_escape_string($conn, $batch_no) . "'";
        }
        if (in_array('expiry_date', $invCols) && !empty($expiry_date)) {
            $insertFields[] = "expiry_date";
            $insertValues[] = "'$expiry_date'";
        }

        $insDestSql = "INSERT INTO inventory (" . implode(", ", $insertFields) . ") VALUES (" . implode(", ", $insertValues) . ")";
        if (!mysqli_query($conn, $insDestSql)) {
            throw new Exception("New destination inventory row creation failed: " . mysqli_error($conn));
        }
    }

    // -------------------------------------------------------------
    // STEP C: Insert Movement Record in Transfer Ledger
    // -------------------------------------------------------------
    $logFields = [];
    $logValues = [];

    if (in_array('inventory_id', $tCols)) { $logFields[] = 'inventory_id'; $logValues[] = "'$inventory_id'"; }
    if (in_array('product_id', $tCols))   { $logFields[] = 'product_id'; $logValues[] = "'$product_id'"; }
    if (in_array('product_code', $tCols)) { $logFields[] = 'product_code'; $logValues[] = "'" . mysqli_real_escape_string($conn, $product_code) . "'"; }
    if (in_array('product_name', $tCols)) { $logFields[] = 'product_name'; $logValues[] = "'" . mysqli_real_escape_string($conn, $product_name) . "'"; }
    if (in_array('from_warehouse', $tCols)) { $logFields[] = 'from_warehouse'; $logValues[] = "'" . mysqli_real_escape_string($conn, $from_warehouse) . "'"; }
    if (in_array('from_bin', $tCols))       { $logFields[] = 'from_bin'; $logValues[] = "'" . mysqli_real_escape_string($conn, $from_bin) . "'"; }
    if (in_array('to_warehouse', $tCols))   { $logFields[] = 'to_warehouse'; $logValues[] = "'" . mysqli_real_escape_string($conn, $to_warehouse) . "'"; }
    if (in_array('to_bin', $tCols))         { $logFields[] = 'to_bin'; $logValues[] = "'" . mysqli_real_escape_string($conn, $to_bin) . "'"; }
    if (in_array('quantity', $tCols))       { $logFields[] = 'quantity'; $logValues[] = "'$quantity'"; }
    if (in_array('remarks', $tCols))        { $logFields[] = 'remarks'; $logValues[] = "'" . mysqli_real_escape_string($conn, $remarks) . "'"; }
    elseif (in_array('reason', $tCols))     { $logFields[] = 'reason'; $logValues[] = "'" . mysqli_real_escape_string($conn, $remarks) . "'"; }
    if (in_array('transfer_date', $tCols))  { $logFields[] = 'transfer_date'; $logValues[] = "'$transfer_date'"; }
    if (in_array('created_by', $tCols))     { $logFields[] = 'created_by'; $logValues[] = "'$user_id'"; }

    if (!empty($logFields)) {
        $logSql = "INSERT INTO `{$transferTable}` (" . implode(", ", $logFields) . ") VALUES (" . implode(", ", $logValues) . ")";
        if (!mysqli_query($conn, $logSql)) {
            throw new Exception("Transfer audit logging failed: " . mysqli_error($conn));
        }
    }

    // Commit Transaction
    mysqli_commit($conn);
    $_SESSION['success'] = "Successfully relocated <strong>{$quantity} Units</strong> of <strong>" . htmlspecialchars($product_name) . "</strong> from <code>{$from_bin}</code> to <code>{$to_bin}</code>!";
    header("Location: index.php");
    exit();

} catch (\Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Stock transfer execution failed: " . $e->getMessage();
    header("Location: create.php");
    exit();
}