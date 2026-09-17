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
    $_SESSION['error'] = "Attendance record ID is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);
$attQuery = mysqli_query($conn, "SELECT id FROM attendance WHERE id = '$id' LIMIT 1");

if (!$attQuery || mysqli_num_rows($attQuery) === 0) {
    $_SESSION['error'] = "Attendance record not found.";
    header("Location: index.php");
    exit();
}

$deleteRes = mysqli_query($conn, "DELETE FROM attendance WHERE id = '$id'");

if ($deleteRes) {
    $_SESSION['success'] = "Attendance record deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete attendance record: " . mysqli_error($conn);
}

header("Location: index.php");
exit();
?>