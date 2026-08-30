<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

require_once $projectRoot . "/config/database.php";

header('Content-Type: application/json');

// Error suppression for clean JSON response
error_reporting(0);
ini_set('display_errors', 0);

$warehouse_id = intval($_GET['warehouse_id'] ?? 0);
$zone_filter  = trim($_GET['zone_type'] ?? ($_GET['zone_category'] ?? ($_GET['zone'] ?? '')));

if ($warehouse_id <= 0 || !$conn) {
    echo json_encode([]);
    exit();
}

/* ==========================================================================
   1. DETECT DYNAMIC SCHEMAS IN bin_locations & warehouse TABLES
   ========================================================================== */

// Detect Warehouse Table & Column Name
$whTable = "warehouses";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $whTable = "warehouse";
}

$whNameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) === 0) {
    $whNameCol = "name";
}

// Fetch Warehouse Name
$currentWhName = '';
$whRes = @mysqli_query($conn, "SELECT `{$whNameCol}` AS wh_name FROM `{$whTable}` WHERE id = '$warehouse_id' LIMIT 1");
if ($whRes && $whRow = mysqli_fetch_assoc($whRes)) {
    $currentWhName = $whRow['wh_name'];
}
$whNameEscaped = mysqli_real_escape_string($conn, $currentWhName);

// Detect Columns in bin_locations
$binCols = [];
$colRes = @mysqli_query($conn, "SHOW COLUMNS FROM bin_locations");
if ($colRes) {
    while ($col = mysqli_fetch_assoc($colRes)) {
        $binCols[] = strtolower($col['Field']);
    }
}

function hasBinCol($name, $cols) { return in_array(strtolower($name), $cols); }

$hasZoneName  = hasBinCol('zone_name', $binCols);
$hasZone      = hasBinCol('zone', $binCols);
$hasZoneType  = hasBinCol('zone_type', $binCols);
$hasMaxUnits  = hasBinCol('max_units', $binCols);
$hasMaxWeight = hasBinCol('max_weight_kg', $binCols);
$hasMaxCap    = hasBinCol('max_capacity', $binCols) || hasBinCol('capacity', $binCols);
$capColumn    = hasBinCol('capacity', $binCols) ? 'capacity' : 'max_capacity';

/* ==========================================================================
   2. BUILD DYNAMIC ZONE FILTER & CAPACITY COLUMNS
   ========================================================================== */

$whereZone = "";
if (!empty($zone_filter)) {
    $zoneEsc = mysqli_real_escape_string($conn, $zone_filter);
    $zoneConditions = [];
    if ($hasZoneName) $zoneConditions[] = "b.zone_name = '$zoneEsc'";
    if ($hasZone)     $zoneConditions[] = "b.zone = '$zoneEsc'";
    if ($hasZoneType) $zoneConditions[] = "b.zone_type = '$zoneEsc'";
    
    if (!empty($zoneConditions)) {
        $whereZone = "AND (" . implode(" OR ", $zoneConditions) . ")";
    }
}

// Capacity Select Expressions
$capacitySelect = "150 AS max_units_limit, 500 AS max_weight_limit";
if ($hasMaxUnits && $hasMaxWeight) {
    $capacitySelect = "COALESCE(b.max_units, 150) AS max_units_limit, COALESCE(b.max_weight_kg, 500) AS max_weight_limit";
} elseif ($hasMaxCap) {
    $capacitySelect = "COALESCE(b.{$capColumn}, 150) AS max_units_limit, 500 AS max_weight_limit";
}

/* ==========================================================================
   3. OCCUPIED UNITS & LIVE BINS QUERY
   ========================================================================== */

$query = "
    SELECT 
        b.id, 
        b.bin_code, 
        COALESCE(" . ($hasZoneName ? "b.zone_name" : ($hasZone ? "b.zone" : "'General Zone'")) . ", 'General Zone') AS zone_display,
        $capacitySelect,
        COALESCE(SUM(i.available_qty + i.reserved_qty), 0) AS total_occupied_units
    FROM bin_locations b
    LEFT JOIN inventory i ON (
        i.bin_location = b.bin_code 
        AND (i.warehouse_id = '$warehouse_id' OR i.warehouse = '{$whNameEscaped}')
    )
    WHERE (b.warehouse_id = '$warehouse_id' OR b.warehouse = '{$whNameEscaped}' OR b.warehouse_id IS NULL OR b.warehouse_id = 0 OR b.warehouse = '')
      AND (LOWER(COALESCE(b.status, 'Active')) = 'active' OR b.status = '1')
      $whereZone
    GROUP BY b.id, b.bin_code
    HAVING total_occupied_units < max_units_limit
    ORDER BY total_occupied_units ASC, b.bin_code ASC
";

$result = @mysqli_query($conn, $query);
$bins = [];

if ($result && mysqli_num_rows($result) > 0) {
    while ($row = mysqli_fetch_assoc($result)) {
        $maxUnits    = (int)$row['max_units_limit'];
        $maxWeight   = (float)$row['max_weight_limit'];
        $occupied    = (int)$row['total_occupied_units'];
        $spaceLeft   = max(0, $maxUnits - $occupied);
        
        $percentUsed = ($maxUnits > 0) ? round(($occupied / $maxUnits) * 100) : 0;

        // Custom Visual Status Labels
        if ($occupied === 0) {
            $statusLabel = "🟢 EMPTY (0/{$maxUnits} Units - Free)";
        } else {
            $statusLabel = "🟡 PARTIAL ({$spaceLeft} Units Left | {$percentUsed}% Full)";
        }

        $bins[] = [
            'id'              => (int)$row['id'],
            'bin_code'        => $row['bin_code'],
            'zone'            => $row['zone_display'],
            'occupied_units'  => $occupied,
            'max_units'       => $maxUnits,
            'max_weight_kg'   => $maxWeight,
            'available_space' => $spaceLeft,
            'percent_used'    => $percentUsed,
            'label'           => $row['bin_code'] . " - " . $statusLabel
        ];
    }
}

/* ==========================================================================
   4. DYNAMIC FALLBACK IF NO BINS EXIST IN DB
   ========================================================================== */
if (empty($bins)) {
    // Check occupied bins directly in inventory for this warehouse
    $occMap = [];
    $occQ = @mysqli_query($conn, "SELECT bin_location, SUM(available_qty + reserved_qty) AS stock FROM inventory WHERE (warehouse_id = '$warehouse_id' OR warehouse = '{$whNameEscaped}') GROUP BY bin_location");
    if ($occQ) {
        while ($o = mysqli_fetch_assoc($occQ)) {
            $occMap[strtoupper(trim($o['bin_location']))] = (int)$o['stock'];
        }
    }

    $floors = ['L0', 'L1'];
    $aisles = ['A1', 'A2', 'B1', 'B2', 'C1'];
    $dummyId = 1;

    foreach ($floors as $fl) {
        foreach ($aisles as $ais) {
            for ($rack = 1; $rack <= 3; $rack++) {
                for ($shelf = 1; $shelf <= 2; $shelf++) {
                    $code = sprintf("%s-%s-%03d-%02d-A", $fl, $ais, $rack, $shelf);
                    $occupied = $occMap[$code] ?? 0;
                    $maxUnits = 150;
                    $spaceLeft = max(0, $maxUnits - $occupied);

                    if ($spaceLeft > 0) {
                        $percentUsed = round(($occupied / $maxUnits) * 100);
                        $statusLabel = ($occupied === 0) 
                            ? "🟢 EMPTY (0/{$maxUnits} Units - Free)" 
                            : "🟡 PARTIAL ({$spaceLeft} Units Left | {$percentUsed}% Full)";

                        $bins[] = [
                            'id'              => $dummyId++,
                            'bin_code'        => $code,
                            'zone'            => 'Zone ' . $ais,
                            'occupied_units'  => $occupied,
                            'max_units'       => $maxUnits,
                            'max_weight_kg'   => 500,
                            'available_space' => $spaceLeft,
                            'percent_used'    => $percentUsed,
                            'label'           => $code . " - " . $statusLabel
                        ];
                    }
                }
            }
        }
    }
}

echo json_encode($bins, JSON_UNESCAPED_UNICODE);
exit();
?>