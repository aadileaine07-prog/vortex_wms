<?php
session_start();
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

require_once $projectRoot . "/config/database.php";

header('Content-Type: application/json');

$po_id = intval($_GET['po_id'] ?? 0);

if ($po_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid PO ID']);
    exit();
}

// Fetch PO Master Details
$poQuery = mysqli_query($conn, "
    SELECT po.*, s.supplier_name, s.supplier_code 
    FROM purchase_orders po 
    LEFT JOIN suppliers s ON po.supplier_id = s.id 
    WHERE po.id = '$po_id'
");

if (!$poQuery || mysqli_num_rows($poQuery) == 0) {
    echo json_encode(['success' => false, 'message' => 'PO Not Found']);
    exit();
}

$po = mysqli_fetch_assoc($poQuery);

// Fetch PO Items
$itemsQuery = mysqli_query($conn, "SELECT * FROM purchase_order_items WHERE po_id = '$po_id'");
$items = [];

if ($itemsQuery && mysqli_num_rows($itemsQuery) > 0) {
    while ($row = mysqli_fetch_assoc($itemsQuery)) {
        $items[] = [
            'product_code' => $row['product_code'],
            'product_name' => $row['product_name'],
            'ordered_qty'  => $row['ordered_qty'],
            'unit_price'   => $row['unit_price']
        ];
    }
}

echo json_encode([
    'success' => true,
    'po' => [
        'id' => $po['id'],
        'po_number' => $po['po_number'],
        'supplier_id' => $po['supplier_id'],
        'supplier_name' => $po['supplier_name'],
        'supplier_code' => $po['supplier_code'],
        'expected_date' => $po['expected_date']
    ],
    'items' => $items
]);
exit();
?>