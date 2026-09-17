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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id > 0) {
    $asnTable = "asn_headers";
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE 'asn_headers'");
    if (!$chk || mysqli_num_rows($chk) === 0) {
        $asnTable = "asn";
    }

    if (mysqli_query($conn, "DELETE FROM `{$asnTable}` WHERE id = $id LIMIT 1")) {
        $_SESSION['success'] = "ASN record deleted successfully.";
    } else {
        $_SESSION['error'] = "Failed to delete ASN: " . mysqli_error($conn);
    }
}

header("Location: index.php");
exit();