<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    die("Invalid Request");
}

$id = intval($_GET['id']);

// Execute deletion using a transaction to guarantee data integrity
$conn->begin_transaction();

try {
    // Delete items
    $stmt1 = $conn->prepare("DELETE FROM asn_items WHERE asn_id = ?");
    $stmt1->bind_param("i", $id);
    $stmt1->execute();
    $stmt1->close();

    // Delete ASN master record
    $stmt2 = $conn->prepare("DELETE FROM asn WHERE id = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $stmt2->close();

    $conn->commit();
} catch (Exception $e) {
    $conn->rollback();
    die("Failed to delete record: " . $e->getMessage());
}

header("Location: index.php");
exit();