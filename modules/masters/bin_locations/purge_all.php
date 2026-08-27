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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_purge_all'])) {
    // Disable FK checks and truncate
    @mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0");
    $del = @mysqli_query($conn, "TRUNCATE TABLE bin_locations");
    @mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1");

    if ($del) {
        $_SESSION['success'] = "All bin locations have been permanently deleted and IDs reset!";
    } else {
        @mysqli_query($conn, "DELETE FROM bin_locations");
        $_SESSION['success'] = "All bin locations cleared successfully!";
    }
}

header("Location: index.php");
exit();