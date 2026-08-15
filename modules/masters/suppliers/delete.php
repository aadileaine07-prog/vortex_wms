<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Supplier ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// Check Supplier Existence
$check = mysqli_query($conn, "SELECT supplier_name FROM suppliers WHERE id='$id'");

if (!$check || mysqli_num_rows($check) == 0) {
    $_SESSION['error'] = "Supplier Not Found.";
    header("Location: index.php");
    exit();
}

$supName = mysqli_fetch_assoc($check)['supplier_name'];

// 1. FOREIGN KEY PROTECTION CHECK: Check linked Purchase Orders
$po_check = mysqli_query($conn, "SELECT id FROM purchase_orders WHERE supplier_id='$id' LIMIT 1");
if ($po_check && mysqli_num_rows($po_check) > 0) {
    $_SESSION['error'] = "Cannot delete supplier <strong>{$supName}</strong> because Purchase Orders are linked to this vendor. Mark status as 'Inactive' instead.";
    header("Location: index.php");
    exit();
}

// 2. FOREIGN KEY PROTECTION CHECK: Check linked GRNs
$grn_check = mysqli_query($conn, "SELECT id FROM grn WHERE supplier_id='$id' LIMIT 1");
if ($grn_check && mysqli_num_rows($grn_check) > 0) {
    $_SESSION['error'] = "Cannot delete supplier <strong>{$supName}</strong> because Goods Receipt Notes (GRN) exist for this vendor.";
    header("Location: index.php");
    exit();
}

// Safe Delete Operation
if (mysqli_query($conn, "DELETE FROM suppliers WHERE id='$id'")) {
    $_SESSION['success'] = "Supplier <strong>{$supName}</strong> deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete supplier: " . mysqli_error($conn);
}

header("Location: index.php");
exit();
?>