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

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Stock Transfer ID is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

/* ==========================================================================
   1. DYNAMIC TABLE DETECTION (stock_transfers / stock_transfer)
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

// Fetch transfer record
$result = mysqli_query($conn, "SELECT * FROM `{$transferTable}` WHERE id = '$id' LIMIT 1");

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Transfer record #{$id} not found.";
    header("Location: index.php");
    exit();
}

$row = mysqli_fetch_assoc($result);

$product_id     = intval($row['product_id'] ?? 0);
$product_code   = trim($row['product_code'] ?? ($row['sku'] ?? ''));
$product_name   = trim($row['product_name'] ?? '');
$inventory_id   = intval($row['inventory_id'] ?? 0);
$qty            = intval($row['quantity'] ?? 0);
$from_warehouse = trim($row['from_warehouse'] ?? 'Surat Central Logistics Park');
$from_bin       = trim($row['from_bin'] ?? '');
$to_warehouse   = trim($row['to_warehouse'] ?? '');
$to_bin         = trim($row['to_bin'] ?? '');

/* ==========================================================================
   2. ATOMIC TWO-WAY TRANSACTION ROLLBACK
   ========================================================================== */

mysqli_begin_transaction($conn);

try {

    // -------------------------------------------------------------
    // STEP A: Return Stock to Source Coordinate (Re-add / Recreate)
    // -------------------------------------------------------------
    $srcWhere = [];
    if ($inventory_id > 0) {
        $srcWhere[] = "id = '$inventory_id'";
    }
    if ($product_id > 0) {
        $srcWhere[] = "(product_id = '$product_id' AND warehouse = '" . mysqli_real_escape_string($conn, $from_warehouse) . "' AND bin_location = '" . mysqli_real_escape_string($conn, $from_bin) . "')";
    }
    if (!empty($product_code)) {
        $srcWhere[] = "(product_code = '" . mysqli_real_escape_string($conn, $product_code) . "' AND warehouse = '" . mysqli_real_escape_string($conn, $from_warehouse) . "' AND bin_location = '" . mysqli_real_escape_string($conn, $from_bin) . "')";
    }

    $srcQuery = "SELECT * FROM inventory WHERE " . implode(" OR ", $srcWhere) . " LIMIT 1";
    $sourceRes = mysqli_query($conn, $srcQuery);

    if ($sourceRes && mysqli_num_rows($sourceRes) > 0) {
        $src = mysqli_fetch_assoc($sourceRes);
        $newSrcQty = (int)$src['available_qty'] + $qty;
        $srcStatus = ($newSrcQty <= 10) ? "Low Stock" : "In Stock";

        $updSrc = "UPDATE inventory SET available_qty = '$newSrcQty', status = '$srcStatus' WHERE id = '" . $src['id'] . "'";
        if (!mysqli_query($conn, $updSrc)) {
            throw new Exception("Failed to restore source inventory balance: " . mysqli_error($conn));
        }
    } else {
        // Agar pehle origin bin zero hone par purge ho gaya tha, toh slot restore karein
        $pInfoRes = mysqli_query($conn, "SELECT id, product_code, product_name FROM products WHERE id = '$product_id' OR product_code = '" . mysqli_real_escape_string($conn, $product_code) . "' LIMIT 1");
        $pInfo = ($pInfoRes && mysqli_num_rows($pInfoRes) > 0) ? mysqli_fetch_assoc($pInfoRes) : [];
        $pCode = mysqli_real_escape_string($conn, $pInfo['product_code'] ?? $product_code);
        $pName = mysqli_real_escape_string($conn, $pInfo['product_name'] ?? $product_name);

        $srcStatus = ($qty <= 10) ? "Low Stock" : "In Stock";
        $insSrc = "
            INSERT INTO inventory (product_id, product_code, product_name, warehouse, bin_location, batch_no, available_qty, reserved_qty, status)
            VALUES ('$product_id', '$pCode', '$pName', '" . mysqli_real_escape_string($conn, $from_warehouse) . "', '" . mysqli_real_escape_string($conn, $from_bin) . "', 'BAT-" . date('Ymd') . "', '$qty', 0, '$srcStatus')
        ";
        if (!mysqli_query($conn, $insSrc)) {
            throw new Exception("Failed to recreate source inventory slot: " . mysqli_error($conn));
        }
    }

    // -------------------------------------------------------------
    // STEP B: Deduct Stock from Destination Coordinate
    // -------------------------------------------------------------
    $destWhere = [];
    if ($product_id > 0) {
        $destWhere[] = "(product_id = '$product_id' AND warehouse = '" . mysqli_real_escape_string($conn, $to_warehouse) . "' AND bin_location = '" . mysqli_real_escape_string($conn, $to_bin) . "')";
    }
    if (!empty($product_code)) {
        $destWhere[] = "(product_code = '" . mysqli_real_escape_string($conn, $product_code) . "' AND warehouse = '" . mysqli_real_escape_string($conn, $to_warehouse) . "' AND bin_location = '" . mysqli_real_escape_string($conn, $to_bin) . "')";
    }

    if (!empty($destWhere)) {
        $destQuery = "SELECT * FROM inventory WHERE " . implode(" OR ", $destWhere) . " LIMIT 1";
        $destRes = mysqli_query($conn, $destQuery);

        if ($destRes && mysqli_num_rows($destRes) > 0) {
            $dest = mysqli_fetch_assoc($destRes);
            $newDestQty = (int)$dest['available_qty'] - $qty;

            if ($newDestQty <= 0) {
                mysqli_query($conn, "DELETE FROM inventory WHERE id = '" . $dest['id'] . "'");
            } else {
                $destStatus = ($newDestQty <= 10) ? "Low Stock" : "In Stock";
                $updDest = "UPDATE inventory SET available_qty = '$newDestQty', status = '$destStatus' WHERE id = '" . $dest['id'] . "'";
                if (!mysqli_query($conn, $updDest)) {
                    throw new Exception("Failed to deduct destination inventory balance: " . mysqli_error($conn));
                }
            }
        }
    }

    // -------------------------------------------------------------
    // STEP C: Delete Transfer Voucher Record
    // -------------------------------------------------------------
    $delSql = "DELETE FROM `{$transferTable}` WHERE id = '$id'";
    if (!mysqli_query($conn, $delSql)) {
        throw new Exception("Failed to remove transfer ledger record: " . mysqli_error($conn));
    }

    // Commit Transaction
    mysqli_commit($conn);
    $_SESSION['success'] = "Transfer voucher <strong>#{$id}</strong> deleted. <strong>{$qty} Units</strong> successfully reverted back to origin coordinate <code>{$from_bin}</code>!";

} catch (\Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Rollback error: " . $e->getMessage();
}

header("Location: index.php");
exit();