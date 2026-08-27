<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Multi-Level Project Root Detection (3 levels up: /modules/masters/products/ -> /vortex_wms/)
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Product identifier is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

/* ==========================================================================
   1. VERIFY PRODUCT EXISTENCE & SCHEMA
   ========================================================================== */

$pCols = [];
$cRes = @mysqli_query($conn, "SHOW COLUMNS FROM products");
if ($cRes) {
    while ($c = mysqli_fetch_assoc($cRes)) { 
        $pCols[] = strtolower($c['Field']); 
    }
}

$skuCol = in_array('sku', $pCols) ? 'sku' : (in_array('product_code', $pCols) ? 'product_code' : "''");

$productQuery = mysqli_query($conn, "SELECT id, product_name, {$skuCol} AS product_sku FROM products WHERE id = '$id' LIMIT 1");

if (!$productQuery || mysqli_num_rows($productQuery) === 0) {
    $_SESSION['error'] = "Product record #{$id} not found.";
    header("Location: index.php");
    exit();
}

$prod = mysqli_fetch_assoc($productQuery);
$prodName = htmlspecialchars($prod['product_name']);
$prodSku  = htmlspecialchars($prod['product_sku'] ?? 'SKU-00');

/* ==========================================================================
   2. STRICT FOREIGN KEY INTEGRITY GUARDS
   ========================================================================== */

// Guard 1: Active Warehouse Inventory Balance
$chkInv = @mysqli_query($conn, "SHOW TABLES LIKE 'inventory'");
if ($chkInv && mysqli_num_rows($chkInv) > 0) {
    $invCheck = mysqli_query($conn, "SELECT SUM(available_qty) AS total_qty FROM inventory WHERE (product_id = '$id' OR product_code = '$prodSku')");
    if ($invCheck && mysqli_num_rows($invCheck) > 0) {
        $invData = mysqli_fetch_assoc($invCheck);
        $totalQty = (int)($invData['total_qty'] ?? 0);
        if ($totalQty > 0) {
            $_SESSION['error'] = "⚠️ Cannot delete product <strong>{$prodName}</strong> ({$prodSku}) because <strong>{$totalQty} Units</strong> exist in warehouse inventory. Clear/relocate physical stock first or set status to <strong>'Inactive'</strong>.";
            header("Location: index.php");
            exit();
        }
    }
}

// Guard 2: Linked Inbound Purchase Orders
$chkPO = @mysqli_query($conn, "SHOW TABLES LIKE 'purchase_order_items'");
if ($chkPO && mysqli_num_rows($chkPO) > 0) {
    $poCheck = mysqli_query($conn, "SELECT id, po_id FROM purchase_order_items WHERE (product_code = '$prodSku' OR product_name = '" . mysqli_real_escape_string($conn, $prod['product_name']) . "') LIMIT 1");
    if ($poCheck && mysqli_num_rows($poCheck) > 0) {
        $_SESSION['error'] = "⚠️ Cannot delete product <strong>{$prodName}</strong> because it is referenced in Purchase Orders. Archive/Inactivate the SKU instead.";
        header("Location: index.php");
        exit();
    }
}

// Guard 3: Linked Outbound Orders / Shipments
$chkOut = @mysqli_query($conn, "SHOW TABLES LIKE 'outbound_order_items'");
if ($chkOut && mysqli_num_rows($chkOut) > 0) {
    $outCheck = mysqli_query($conn, "SELECT id FROM outbound_order_items WHERE (product_code = '$prodSku' OR product_name = '" . mysqli_real_escape_string($conn, $prod['product_name']) . "') LIMIT 1");
    if ($outCheck && mysqli_num_rows($outCheck) > 0) {
        $_SESSION['error'] = "⚠️ Cannot delete product <strong>{$prodName}</strong> because sales dispatch orders are associated with this SKU.";
        header("Location: index.php");
        exit();
    }
}

/* ==========================================================================
   3. SAFE DELETE TRANSACTION
   ========================================================================== */

mysqli_begin_transaction($conn);

try {
    // 1. Remove empty 0-quantity inventory rows if any
    if ($chkInv && mysqli_num_rows($chkInv) > 0) {
        @mysqli_query($conn, "DELETE FROM inventory WHERE (product_id = '$id' OR product_code = '$prodSku') AND available_qty <= 0");
    }

    // 2. Delete Master Product
    if (!mysqli_query($conn, "DELETE FROM products WHERE id = '$id'")) {
        throw new Exception("SQL execution failed: " . mysqli_error($conn));
    }

    mysqli_commit($conn);
    $_SESSION['success'] = "Product <strong>{$prodName}</strong> (<code>{$prodSku}</code>) deleted successfully from master catalog.";
} catch (\Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Failed to delete product: " . $e->getMessage();
}

header("Location: index.php");
exit();