<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = "Invalid warehouse ID.";
    header("Location: index.php");
    exit();
}

$whTable = "warehouses";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $whTable = "warehouse";
}

$whRes = mysqli_query($conn, "SELECT * FROM `{$whTable}` WHERE id = '$id' LIMIT 1");
if (!$whRes || mysqli_num_rows($whRes) === 0) {
    $_SESSION['error'] = "Warehouse not found.";
    header("Location: index.php");
    exit();
}

$whData = mysqli_fetch_assoc($whRes);
$whName = $whData['warehouse_name'] ?? ($whData['name'] ?? '');

// Check if active stock exists
$invCheck = mysqli_query($conn, "SELECT COUNT(*) FROM inventory WHERE (warehouse_id = '$id' OR warehouse = '" . mysqli_real_escape_string($conn, $whName) . "') AND available_qty > 0");
$activeStock = ($invCheck && $c = mysqli_fetch_array($invCheck)) ? (int)$c[0] : 0;

if ($activeStock > 0) {
    $_SESSION['error'] = "Cannot delete <strong>{$whName}</strong>! Active stock exists inside this warehouse.";
    header("Location: index.php");
    exit();
}

// Delete warehouse and linked empty bins
mysqli_query($conn, "DELETE FROM `{$whTable}` WHERE id = '$id'");
mysqli_query($conn, "DELETE FROM bin_locations WHERE warehouse_id = '$id' OR warehouse = '" . mysqli_real_escape_string($conn, $whName) . "'");

$_SESSION['success'] = "Warehouse facility <strong>{$whName}</strong> deleted successfully.";
header("Location: index.php");
exit();
?>