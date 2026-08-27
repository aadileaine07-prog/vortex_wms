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

if ($_SERVER['REQUEST_METHOD'] !== "POST") {
    header("Location: index.php");
    exit();
}

$inventory_id    = intval($_POST['inventory_id'] ?? 0);
$adjustment_date = !empty($_POST['adjustment_date']) ? $_POST['adjustment_date'] : date('Y-m-d');
$adjustment_type = trim($_POST['adjustment_type'] ?? 'Increase');
$quantity        = intval($_POST['quantity'] ?? 0);
$reason          = trim($_POST['reason'] ?? 'Manual Physical Audit Adjustment');
$created_by      = $_SESSION['employee_id'];

if ($inventory_id <= 0 || $quantity <= 0) {
    $_SESSION['error'] = "Invalid Product or Quantity selected.";
    header("Location: create.php");
    exit();
}

// 1. Fetch Current Inventory Record
$inventory_query = mysqli_query($conn, "SELECT * FROM inventory WHERE id = '$inventory_id' LIMIT 1");
if (!$inventory_query || mysqli_num_rows($inventory_query) == 0) {
    $_SESSION['error'] = "Inventory Item Not Found in Ledger.";
    header("Location: create.php");
    exit();
}

$item         = mysqli_fetch_assoc($inventory_query);
$product_id   = intval($item['product_id'] ?? 0);
$product_code = $item['product_code'] ?? ($item['sku'] ?? 'SKU-00');
$product_name = $item['product_name'] ?? 'Stock Item';
$warehouse    = $item['warehouse'] ?? '';
$bin_location = $item['bin_location'] ?? 'L0-A1';
$current_qty  = intval($item['available_qty']);

// 2. Quantity & Status Calculation
if (strcasecmp($adjustment_type, "Increase") === 0) {
    $new_qty = $current_qty + $quantity;
    
    // Optional: Bin Capacity Validation on Increase
    $binChk = @mysqli_query($conn, "SELECT COALESCE(max_units, max_capacity, 100) AS max_limit FROM bin_locations WHERE bin_code = '$bin_location' LIMIT 1");
    if ($binChk && $bRow = mysqli_fetch_assoc($binChk)) {
        if ($new_qty > $bRow['max_limit']) {
            $_SESSION['error'] = "⚠️ Bin Overcapacity: {$bin_location} has a maximum limit of {$bRow['max_limit']} units (Adjusted total would be {$new_qty}).";
            header("Location: create.php");
            exit();
        }
    }
} else {
    if ($quantity > $current_qty) {
        $_SESSION['error'] = "Adjustment deduction quantity ($quantity) cannot exceed available balance ($current_qty units).";
        header("Location: create.php");
        exit();
    }
    $new_qty = $current_qty - $quantity;
}

// Status Calculation
if ($new_qty <= 0) {
    $status = "Out of Stock";
} elseif ($new_qty <= 10) {
    $status = "Low Stock";
} else {
    $status = "In Stock";
}

// 3. Dynamic Adjustment Table Detection
$adjTable = "stock_adjustments";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_adjustments'");
if (!$chkTable || mysqli_num_rows($chkTable) == 0) {
    $chkTable2 = @mysqli_query($conn, "SHOW TABLES LIKE 'stock_adjustment'");
    if ($chkTable2 && mysqli_num_rows($chkTable2) > 0) {
        $adjTable = "stock_adjustment";
    } else {
        $adjTable = "inventory_adjustments";
    }
}

// Detect columns of target table
$adjCols = [];
$cRes = @mysqli_query($conn, "SHOW COLUMNS FROM `{$adjTable}`");
if ($cRes) {
    while ($c = mysqli_fetch_assoc($cRes)) { 
        $adjCols[] = strtolower($c['Field']); 
    }
}

// 4. Safe Database Transaction
mysqli_begin_transaction($conn);

try {
    // 1. Update Main Inventory
    $update_sql = "UPDATE inventory SET available_qty = '$new_qty', status = '$status' WHERE id = '$inventory_id'";
    if (!mysqli_query($conn, $update_sql)) {
        throw new Exception("Inventory update failed: " . mysqli_error($conn));
    }

    // 2. Build Dynamic Insert Query for Audit Ledger
    $fields = [];
    $values = [];

    if (in_array('inventory_id', $adjCols)) { $fields[] = 'inventory_id'; $values[] = "'$inventory_id'"; }
    if (in_array('product_id', $adjCols))   { $fields[] = 'product_id'; $values[] = "'$product_id'"; }
    if (in_array('product_code', $adjCols)) { $fields[] = 'product_code'; $values[] = "'" . mysqli_real_escape_string($conn, $product_code) . "'"; }
    if (in_array('product_name', $adjCols)) { $fields[] = 'product_name'; $values[] = "'" . mysqli_real_escape_string($conn, $product_name) . "'"; }
    if (in_array('warehouse', $adjCols))    { $fields[] = 'warehouse'; $values[] = "'" . mysqli_real_escape_string($conn, $warehouse) . "'"; }
    if (in_array('bin_location', $adjCols)) { $fields[] = 'bin_location'; $values[] = "'" . mysqli_real_escape_string($conn, $bin_location) . "'"; }
    if (in_array('adjustment_type', $adjCols)) { $fields[] = 'adjustment_type'; $values[] = "'$adjustment_type'"; }
    elseif (in_array('type', $adjCols))     { $fields[] = 'type'; $values[] = "'$adjustment_type'"; }
    if (in_array('quantity', $adjCols))     { $fields[] = 'quantity'; $values[] = "'$quantity'"; }
    if (in_array('previous_qty', $adjCols)) { $fields[] = 'previous_qty'; $values[] = "'$current_qty'"; }
    if (in_array('new_qty', $adjCols))      { $fields[] = 'new_qty'; $values[] = "'$new_qty'"; }
    if (in_array('reason', $adjCols))       { $fields[] = 'reason'; $values[] = "'" . mysqli_real_escape_string($conn, $reason) . "'"; }
    if (in_array('adjustment_date', $adjCols)) { $fields[] = 'adjustment_date'; $values[] = "'$adjustment_date'"; }
    if (in_array('created_by', $adjCols))   { $fields[] = 'created_by'; $values[] = "'$created_by'"; }
    elseif (in_array('adjusted_by', $adjCols)) { $fields[] = 'adjusted_by'; $values[] = "'$created_by'"; }

    if (!empty($fields)) {
        $log_sql = "INSERT INTO `{$adjTable}` (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
        if (!mysqli_query($conn, $log_sql)) {
            throw new Exception("Ledger logging failed: " . mysqli_error($conn));
        }
    }

    // Commit Transaction
    mysqli_commit($conn);
    
    $sign = (strcasecmp($adjustment_type, 'Increase') === 0) ? '+' : '-';
    $_SESSION['success'] = "Stock Adjustment of <strong>{$sign}{$quantity} Units</strong> recorded successfully! (New Balance: <strong>{$new_qty}</strong>)";
    header("Location: index.php");
    exit();

} catch (\Throwable $e) {
    mysqli_rollback($conn);
    $_SESSION['error'] = "Failed to apply stock adjustment: " . $e->getMessage();
    header("Location: create.php");
    exit();
}