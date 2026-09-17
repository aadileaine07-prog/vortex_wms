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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_warehouse'])) {
    $id          = intval($_POST['id'] ?? 0);
    $warehouse_code = mysqli_real_escape_string($conn, strtoupper(trim($_POST['warehouse_code'] ?? '')));
    $warehouse_name = mysqli_real_escape_string($conn, trim($_POST['warehouse_name'] ?? ''));
    $address        = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $status         = mysqli_real_escape_string($conn, trim($_POST['status'] ?? 'Active'));

    if ($id <= 0) {
        $_SESSION['error'] = "Invalid Warehouse ID for update.";
        header("Location: index.php");
        exit();
    }

    // Determine table and columns dynamically
    $whTable = "warehouses";
    $chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
    if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
        $whTable = "warehouse";
    }

    $whNameCol = "warehouse_name";
    $cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
    if (!$cChk || mysqli_num_rows($cChk) === 0) {
        $whNameCol = "name";
    }

    $whCodeCol = "warehouse_code";
    $cChkCode = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_code'");
    if (!$cChkCode || mysqli_num_rows($cChkCode) === 0) {
        $whCodeCol = "code";
    }

    $whLocCol = "address";
    $cChkLoc = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'address'");
    if (!$cChkLoc || mysqli_num_rows($cChkLoc) === 0) {
        $whLocCol = "location";
    }

    // Update query execution
    $updateSql = "UPDATE `{$whTable}` SET `{$whCodeCol}` = '$warehouse_code', `{$whNameCol}` = '$warehouse_name', `{$whLocCol}` = '$address', `status` = '$status' WHERE id = $id";

    if (mysqli_query($conn, $updateSql)) {
        $_SESSION['success'] = "Warehouse <strong>{$warehouse_name}</strong> updated successfully!";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Database update error: " . mysqli_error($conn);
        header("Location: edit.php?id=" . $id);
        exit();
    }
} else {
    header("Location: index.php");
    exit();
}
?>