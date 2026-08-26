<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "Invalid Bin Location ID.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// Check for existing inventory in this bin before deleting
$chkBin = mysqli_query($conn, "SELECT bin_code FROM bin_locations WHERE id='$id'");
if ($chkBin && mysqli_num_rows($chkBin) > 0) {
    $bRow = mysqli_fetch_assoc($chkBin);
    $bCode = $bRow['bin_code'];

    $invChk = mysqli_query($conn, "SELECT id FROM inventory WHERE bin_location='$bCode' AND available_qty > 0 LIMIT 1");
    if ($invChk && mysqli_num_rows($invChk) > 0) {
        $_SESSION['error'] = "Cannot delete <strong>$bCode</strong> because it currently holds active stock.";
        header("Location: index.php");
        exit();
    }
}

if (mysqli_query($conn, "DELETE FROM bin_locations WHERE id='$id'")) {
    $_SESSION['success'] = "Bin location deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete bin location: " . mysqli_error($conn);
}

header("Location: index.php");
exit();
?>