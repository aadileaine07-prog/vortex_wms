<?php
session_start();
header('Content-Type: application/json');

require_once "../../../config/database.php";

if (!isset($_SESSION['employee_id']) || !isset($conn)) {
    echo json_encode(['force_logout' => true]);
    exit();
}

$current_user_id = mysqli_real_escape_string($conn, $_SESSION['employee_id']);
$res = mysqli_query($conn, "SELECT force_logout FROM employees WHERE id = '$current_user_id' LIMIT 1");

if ($res && $row = mysqli_fetch_assoc($res)) {
    if ((int)$row['force_logout'] === 1) {
        // Reset flag and signal frontend to logout
        mysqli_query($conn, "UPDATE employees SET force_logout = 0 WHERE id = '$current_user_id'");
        session_unset();
        session_destroy();
        echo json_encode(['force_logout' => true]);
        exit();
    }
}

echo json_encode(['force_logout' => false]);
?>