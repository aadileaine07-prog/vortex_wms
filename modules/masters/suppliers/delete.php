<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Multi-Level Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Supplier identifier is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

/* ==========================================================================
   1. VERIFY SUPPLIER EXISTENCE
   ========================================================================== */

$check = mysqli_query($conn, "SELECT supplier_name, supplier_code FROM suppliers WHERE id = '$id' LIMIT 1");

if (!$check || mysqli_num_rows($check) === 0) {
    $_SESSION['error'] = "Supplier record #{$id} not found.";
    header("Location: index.php");
    exit();
}

$supRow  = mysqli_fetch_assoc($check);
$supName = htmlspecialchars($supRow['supplier_name']);
$supCode = htmlspecialchars($supRow['supplier_code'] ?? 'N/A');

/* ==========================================================================
   2. STRICT REFERENTIAL INTEGRITY CHECKS (FOREIGN KEYS)
   ========================================================================== */

// Check 1: Linked Purchase Orders
$chkPO = @mysqli_query($conn, "SHOW TABLES LIKE 'purchase_orders'");
if ($chkPO && mysqli_num_rows($chkPO) > 0) {
    $po_check = mysqli_query($conn, "SELECT id, po_number FROM purchase_orders WHERE supplier_id = '$id' LIMIT 1");
    if ($po_check && mysqli_num_rows($po_check) > 0) {
        $poNum = mysqli_fetch_assoc($po_check)['po_number'];
        $_SESSION['error'] = "⚠️ Cannot delete supplier <strong>{$supName}</strong> ({$supCode}) because active Purchase Orders (e.g. <code>{$poNum}</code>) are linked. Please mark the supplier as <strong>'Inactive'</strong> instead.";
        header("Location: index.php");
        exit();
    }
}

// Check 2: Linked Goods Receipt Notes (GRN)
$chkGRN = @mysqli_query($conn, "SHOW TABLES LIKE 'grn'");
if ($chkGRN && mysqli_num_rows($chkGRN) > 0) {
    $grn_check = mysqli_query($conn, "SELECT id FROM grn WHERE supplier_id = '$id' LIMIT 1");
    if ($grn_check && mysqli_num_rows($grn_check) > 0) {
        $_SESSION['error'] = "⚠️ Cannot delete supplier <strong>{$supName}</strong> because inbound Goods Receipt Notes (GRN) exist for this vendor.";
        header("Location: index.php");
        exit();
    }
}

// Check 3: Linked Supplier Bills / Invoices
$chkBills = @mysqli_query($conn, "SHOW TABLES LIKE 'supplier_bills'");
if ($chkBills && mysqli_num_rows($chkBills) > 0) {
    $bill_check = mysqli_query($conn, "SELECT id FROM supplier_bills WHERE supplier_id = '$id' LIMIT 1");
    if ($bill_check && mysqli_num_rows($bill_check) > 0) {
        $_SESSION['error'] = "⚠️ Cannot delete supplier <strong>{$supName}</strong> because financial audit invoices are registered against this vendor.";
        header("Location: index.php");
        exit();
    }
}

/* ==========================================================================
   3. SAFE DATABASE TRANSACTION & DELETION
   ========================================================================== */

mysqli_begin_transaction($conn);

try {
    $delSql = "DELETE FROM suppliers WHERE id = '$id'";
    if (!mysqli_query($conn, $delSql)) {
        throw new Exception("SQL execution failed: " . mysqli_error($conn));
    }

    mysqli_commit($conn);
    $_SESSION['success'] = "Supplier <strong>{$supName}</strong> (<code>{$supCode}</code>) has been successfully removed from the system.";
} catch (\Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Failed to remove supplier: " . $e->getMessage();
}

header("Location: index.php");
exit();