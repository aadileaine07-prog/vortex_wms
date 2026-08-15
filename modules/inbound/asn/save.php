<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if ($_SERVER['REQUEST_METHOD'] != "POST" || !isset($_POST['save_asn'])) {
    header("Location: index.php");
    exit();
}

$po_id          = intval($_POST['po_id'] ?? 0);
$asn_number     = mysqli_real_escape_string($conn, trim($_POST['asn_number']));
$supplier_id    = intval($_POST['supplier_id'] ?? 0);
$expected_date = mysqli_real_escape_string($conn, $_POST['expected_date']);
$invoice_number = mysqli_real_escape_string($conn, trim($_POST['invoice_number'] ?? ''));

// Fetch Supplier Name if Supplier ID is provided
$supplier_name = "N/A";
if ($supplier_id > 0) {
    $supQuery = mysqli_query($conn, "SELECT supplier_name FROM suppliers WHERE id='$supplier_id'");
    if ($supQuery && mysqli_num_rows($supQuery) > 0) {
        $supplier_name = mysqli_fetch_assoc($supQuery)['supplier_name'];
    }
}
$supplier_name = mysqli_real_escape_string($conn, $supplier_name);

// Check Duplicate ASN Number
$dupCheck = mysqli_query($conn, "SELECT id FROM asn WHERE asn_number='$asn_number'");
if ($dupCheck && mysqli_num_rows($dupCheck) > 0) {
    $_SESSION['error'] = "ASN Number <strong>{$asn_number}</strong> already exists!";
    header("Location: create.php");
    exit();
}

// Start Transaction for Atomic DB Insert
$conn->begin_transaction();

try {
    // 1. Insert Master ASN Record
    $sqlASN = "
        INSERT INTO asn (po_id, asn_number, supplier_name, invoice_number, expected_date, status)
        VALUES ('$po_id', '$asn_number', '$supplier_name', '$invoice_number', '$expected_date', 'Pending')
    ";

    if (!mysqli_query($conn, $sqlASN)) {
        throw new Exception("Error inserting ASN Master: " . mysqli_error($conn));
    }

    $asn_id = mysqli_insert_id($conn);

    // 2. Insert ASN Line Items
    if (isset($_POST['product_name']) && is_array($_POST['product_name'])) {
        foreach ($_POST['product_name'] as $key => $p_name) {
            $p_name = mysqli_real_escape_string($conn, trim($p_name));
            $p_code = mysqli_real_escape_string($conn, trim($_POST['product_code'][$key] ?? ''));
            $exp_qty = intval($_POST['expected_qty'][$key] ?? 0);

            if ($exp_qty > 0) {
                $sqlItem = "
                    INSERT INTO asn_items (asn_id, product_code, product_name, expected_qty)
                    VALUES ('$asn_id', '$p_code', '$p_name', '$exp_qty')
                ";
                
                if (!mysqli_query($conn, $sqlItem)) {
                    throw new Exception("Error inserting ASN Item: " . mysqli_error($conn));
                }
            }
        }
    }

    // 3. Update Purchase Order Status if Linked
    if ($po_id > 0) {
        mysqli_query($conn, "UPDATE purchase_orders SET status='ASN Created' WHERE id='$po_id'");
    }

    // Commit Transaction
    $conn->commit();

    $_SESSION['success'] = "Advance Shipping Notice (ASN) <strong>{$asn_number}</strong> saved successfully!";
    header("Location: index.php");
    exit();

} catch (Exception $e) {
    $conn->rollback();
    $_SESSION['error'] = "Failed to save ASN: " . $e->getMessage();
    header("Location: create.php");
    exit();
}
?>