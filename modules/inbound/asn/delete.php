<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    $_SESSION['error'] = "Invalid ASN ID Request.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// 1. Fetch ASN Details for Logging/Notification
$asnQuery = mysqli_query($conn, "SELECT asn_number, po_id FROM asn WHERE id='$id'");
if (!$asnQuery || mysqli_num_rows($asnQuery) == 0) {
    $_SESSION['error'] = "ASN Record Not Found.";
    header("Location: index.php");
    exit();
}
$asn = mysqli_fetch_assoc($asnQuery);
$asn_no = $asn['asn_number'];
$po_id  = $asn['po_id'];

// 2. SAFETY CHECK: Prevent Deletion if GRN exists for this ASN
$checkGRN = mysqli_query($conn, "SELECT id FROM grn WHERE asn_id='$id' LIMIT 1");
if ($checkGRN && mysqli_num_rows($checkGRN) > 0) {
    $_SESSION['error'] = "Cannot delete ASN <strong>{$asn_no}</strong> because Goods Receipt Note (GRN) has already been processed.";
    header("Location: index.php");
    exit();
}

// 3. Execute Deletion using Atomic DB Transaction
$conn->begin_transaction();

try {
    // Delete ASN Items
    $stmt1 = $conn->prepare("DELETE FROM asn_items WHERE asn_id = ?");
    $stmt1->bind_param("i", $id);
    $stmt1->execute();
    $stmt1->close();

    // Delete Master ASN
    $stmt2 = $conn->prepare("DELETE FROM asn WHERE id = ?");
    $stmt2->bind_param("i", $id);
    $stmt2->execute();
    $stmt2->close();

    // Reset Linked PO status back to Approved/Pending if PO exists
    if (!empty($po_id) && $po_id > 0) {
        $stmt3 = $conn->prepare("UPDATE purchase_orders SET status = 'Approved' WHERE id = ? AND status = 'ASN Created'");
        $stmt3->bind_param("i", $po_id);
        $stmt3->execute();
        $stmt3->close();
    }

    $conn->commit();
    $_SESSION['success'] = "ASN <strong>{$asn_no}</strong> and linked items deleted successfully.";

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Failed to delete ASN record: " . $e->getMessage();
}

header("Location: index.php");
exit();
?>