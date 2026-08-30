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

// 2. Fetch the adjustment record
$adj_query = mysqli_query($conn, "SELECT * FROM `{$adjTable}` WHERE id = '$id' LIMIT 1");
if (!$adj_query || mysqli_num_rows($adj_query) === 0) {
    $_SESSION['error'] = "Stock adjustment record not found.";
    header("Location: index.php");
    exit();
}

$adj = mysqli_fetch_assoc($adj_query);
$inventory_id    = intval($adj['inventory_id'] ?? 0);
$product_id      = intval($adj['product_id'] ?? 0);
$product_code    = trim($adj['product_code'] ?? ($adj['sku'] ?? ''));
$adjustment_type = trim($adj['adjustment_type'] ?? ($adj['type'] ?? 'Increase'));
$quantity        = intval($adj['quantity'] ?? 0);

// 3. Database Transaction for safe stock reversion & log cleanup
mysqli_begin_transaction($conn);

try {
    // Locate target inventory item
    $whereConds = [];
    if ($inventory_id > 0) {
        $whereConds[] = "id = '$inventory_id'";
    }
    if ($product_id > 0) {
        $whereConds[] = "product_id = '$product_id'";
    }
    if (!empty($product_code)) {
        $pCodeEscaped = mysqli_real_escape_string($conn, $product_code);
        $whereConds[] = "product_code = '{$pCodeEscaped}'";
    }

    $whereSql = !empty($whereConds) ? implode(" OR ", $whereConds) : "id = 0";
    $inv_res = mysqli_query($conn, "SELECT id, available_qty FROM inventory WHERE {$whereSql} LIMIT 1");

    if ($inv_res && mysqli_num_rows($inv_res) > 0) {
        $inv = mysqli_fetch_assoc($inv_res);
        $target_id   = (int)$inv['id'];
        $current_qty = (int)$inv['available_qty'];

        // Reverse stock quantity (Increase reverted -> deduction, Decrease reverted -> addition)
        if (strcasecmp($adjustment_type, "Increase") === 0) {
            $reverted_qty = max(0, $current_qty - $quantity);
        } else {
            $reverted_qty = $current_qty + $quantity;
        }

        // Auto-status recalculation
        if ($reverted_qty <= 0) {
            $status = "Out of Stock";
        } elseif ($reverted_qty <= 10) {
            $status = "Low Stock";
        } else {
            $status = "In Stock";
        }

        mysqli_query($conn, "UPDATE inventory SET available_qty = '$reverted_qty', status = '$status' WHERE id = '$target_id'");
    }

    // Delete adjustment record
    mysqli_query($conn, "DELETE FROM `{$adjTable}` WHERE id = '$id'");

    mysqli_commit($conn);
    $_SESSION['success'] = "Adjustment record <strong>#{$id}</strong> deleted and stock successfully reverted!";

} catch (\Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Failed to revert adjustment: " . $e->getMessage();
}

header("Location: index.php");
exit();