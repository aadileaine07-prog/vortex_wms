<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "PO ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$conn->begin_transaction();

try {
    mysqli_query($conn, "DELETE FROM purchase_order_items WHERE po_id='$id'");
    mysqli_query($conn, "DELETE FROM purchase_orders WHERE id='$id'");
    
    $conn->commit();
    $_SESSION['success'] = "Purchase Order deleted successfully.";
} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Failed to delete PO: " . $e->getMessage();
}

header("Location: index.php");
exit();
?>