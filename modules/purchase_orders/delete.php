<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Purchase Order ID is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

/* ==========================================================================
   1. FETCH PO MASTER & VERIFY STATUS INTEGRITY
   ========================================================================== */

$poQuery = mysqli_query($conn, "SELECT id, po_number, status FROM purchase_orders WHERE id = '$id' LIMIT 1");

if (!$poQuery || mysqli_num_rows($poQuery) === 0) {
    $_SESSION['error'] = "Purchase Order #{$id} not found.";
    header("Location: index.php");
    exit();
}

$po = mysqli_fetch_assoc($poQuery);
$po_number = htmlspecialchars($po['po_number']);
$currentStatus = strtolower(trim($po['status'] ?? 'pending'));

// Business Rule: Received / Partially Received POs cannot be deleted directly without voiding GRN
if (in_array($currentStatus, ['received', 'completed', 'partially received'])) {
    $_SESSION['error'] = "⚠️ Cannot delete Purchase Order <strong>{$po_number}</strong> because inventory has already been received against this PO. Cancel or void inbound shipments first.";
    header("Location: index.php");
    exit();
}

/* ==========================================================================
   2. ATOMIC TRANSACTION & CASCADE CLEANUP
   ========================================================================== */

mysqli_begin_transaction($conn);

try {

    // 1. Delete associated line items (if child table exists)
    $chkItemsTable = @mysqli_query($conn, "SHOW TABLES LIKE 'purchase_order_items'");
    if ($chkItemsTable && mysqli_num_rows($chkItemsTable) > 0) {
        if (!mysqli_query($conn, "DELETE FROM purchase_order_items WHERE po_id = '$id'")) {
            throw new Exception("Failed to delete PO items: " . mysqli_error($conn));
        }
    }

    // 2. Delete Goods Receiving Notes (GRN) draft logs if linked
    $chkGrn = @mysqli_query($conn, "SHOW TABLES LIKE 'grn'");
    if ($chkGrn && mysqli_num_rows($chkGrn) > 0) {
        @mysqli_query($conn, "DELETE FROM grn WHERE po_id = '$id' AND (status = 'Draft' OR status = 'Pending')");
    }

    // 3. Delete PO Master Record
    if (!mysqli_query($conn, "DELETE FROM purchase_orders WHERE id = '$id'")) {
        throw new Exception("Failed to delete PO Master: " . mysqli_error($conn));
    }

    // Commit All Changes
    mysqli_commit($conn);
    $_SESSION['success'] = "Purchase Order <strong>{$po_number}</strong> and all associated line items deleted successfully.";

} catch (\Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Failed to delete Purchase Order: " . $e->getMessage();
}

header("Location: index.php");
exit();