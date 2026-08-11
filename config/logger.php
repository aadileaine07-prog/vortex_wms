<?php
require_once __DIR__ . "/database.php";

function logActivity($conn, $module, $action, $description) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

    $emp_id   = $_SESSION['employee_id'] ?? 'SYSTEM';
    $emp_name = $_SESSION['full_name'] ?? 'System User';
    $ip       = $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN';

    $stmt = $conn->prepare("INSERT INTO audit_logs (employee_id, employee_name, module_name, action_type, description, ip_address) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param("ssssss", $emp_id, $emp_name, $module, $action, $description, $ip);
    $stmt->execute();
    $stmt->close();
}
?>