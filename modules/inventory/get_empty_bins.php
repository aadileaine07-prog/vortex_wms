<?php
session_start();
require_once "../../config/database.php";

header('Content-Type: application/json');

$warehouse_id = intval($_GET['warehouse_id'] ?? 0);
$zone_type    = mysqli_real_escape_string($conn, $_GET['zone_type'] ?? '');

if ($warehouse_id <= 0) {
    echo json_encode([]);
    exit();
}

// Query to find Empty Bins OR Bins with available capacity in the selected Zone & Warehouse
$whereZone = !empty($zone_type) ? "AND b.zone_type = '$zone_type'" : "";

$query = "
    SELECT 
        b.id, 
        b.bin_code, 
        b.zone_name, 
        b.zone_type, 
        b.max_capacity,
        COALESCE(SUM(i.available_qty + i.reserved_qty), 0) AS total_occupied
    FROM bin_locations b
    LEFT JOIN inventory i ON (i.bin_location = b.bin_code AND i.warehouse = (SELECT warehouse_name FROM warehouse WHERE id = b.warehouse_id LIMIT 1))
    WHERE b.warehouse_id = '$warehouse_id'
      AND b.status = 'Active'
      $whereZone
    GROUP BY b.id
    HAVING total_occupied < b.max_capacity
    ORDER BY total_occupied ASC, b.bin_code ASC
";

$result = mysqli_query($conn, $query);
$bins = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $available_space = $row['max_capacity'] - $row['total_occupied'];
        $status_label = ($row['total_occupied'] == 0) ? "🟢 FULLY EMPTY" : "🟡 PARTIAL (" . $available_space . " Space Left)";
        
        $bins[] = [
            'bin_code' => $row['bin_code'],
            'zone_name' => $row['zone_name'],
            'zone_type' => $row['zone_type'],
            'occupied' => $row['total_occupied'],
            'max_capacity' => $row['max_capacity'],
            'available_space' => $available_space,
            'label' => $row['bin_code'] . " [" . $status_label . "]"
        ];
    }
}

echo json_encode($bins);
exit();
?>