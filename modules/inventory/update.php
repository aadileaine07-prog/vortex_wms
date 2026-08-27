<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id            = intval($_POST['id'] ?? 0);
    $product_name  = trim($_POST['product_name'] ?? '');
    $warehouse_id  = intval($_POST['warehouse_id'] ?? 0);
    $warehouse_raw = trim($_POST['warehouse'] ?? '');
    $bin_location  = strtoupper(trim($_POST['bin_location'] ?? ''));
    $available_qty = max(0, intval($_POST['available_qty'] ?? 0));
    $reserved_qty  = max(0, intval($_POST['reserved_qty'] ?? 0));
    $batch_no      = trim($_POST['batch_no'] ?? ($_POST['batch_number'] ?? ''));
    $expiry_date   = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : null;

    if ($id <= 0) {
        $_SESSION['error'] = "Invalid Inventory Record ID.";
        header("Location: index.php");
        exit();
    }

    // 1. Automatic Dynamic Stock Status
    if ($available_qty <= 0) {
        $status = "Out of Stock";
    } elseif ($available_qty <= 10) {
        $status = "Low Stock";
    } else {
        $status = "In Stock";
    }

    // 2. Bin Capacity Validation Check (Prevent Overfilling Bins)
    if (!empty($bin_location)) {
        $binChk = @mysqli_query($conn, "
            SELECT 
                COALESCE(max_units, max_capacity, 100) AS max_limit,
                (SELECT IFNULL(SUM(available_qty + reserved_qty), 0) FROM inventory WHERE bin_location = '$bin_location' AND id != '$id') AS other_occupied
            FROM bin_locations 
            WHERE bin_code = '$bin_location'
            LIMIT 1
        ");

        if ($binChk && $bData = mysqli_fetch_assoc($binChk)) {
            $totalProjected = $bData['other_occupied'] + $available_qty + $reserved_qty;
            if ($totalProjected > $bData['max_limit']) {
                $_SESSION['error'] = "⚠️ Capacity Overload: Bin <strong>{$bin_location}</strong> allows max {$bData['max_limit']} units (Projected: {$totalProjected}).";
                header("Location: edit.php?id=" . $id);
                exit();
            }
        }
    }

    // 3. Detect Available Inventory Table Columns
    $tableCols = [];
    $colRes = @mysqli_query($conn, "SHOW COLUMNS FROM `inventory`");
    if ($colRes) {
        while ($c = mysqli_fetch_assoc($colRes)) {
            $tableCols[] = strtolower($c['Field']);
        }
    }

    // Dynamic field-value binding
    $updateParts = [
        "available_qty = $available_qty",
        "reserved_qty = $reserved_qty",
        "status = '$status'"
    ];

    if (in_array('product_name', $tableCols) && !empty($product_name)) {
        $updateParts[] = "product_name = '" . mysqli_real_escape_string($conn, $product_name) . "'";
    }
    if (in_array('bin_location', $tableCols)) {
        $updateParts[] = "bin_location = '" . mysqli_real_escape_string($conn, $bin_location) . "'";
    }
    if (in_array('warehouse_id', $tableCols) && $warehouse_id > 0) {
        $updateParts[] = "warehouse_id = $warehouse_id";
    }
    if (in_array('warehouse', $tableCols) && !empty($warehouse_raw)) {
        $updateParts[] = "warehouse = '" . mysqli_real_escape_string($conn, $warehouse_raw) . "'";
    }
    if (in_array('batch_no', $tableCols)) {
        $updateParts[] = "batch_no = '" . mysqli_real_escape_string($conn, $batch_no) . "'";
    } elseif (in_array('batch_number', $tableCols)) {
        $updateParts[] = "batch_number = '" . mysqli_real_escape_string($conn, $batch_no) . "'";
    }
    if (in_array('expiry_date', $tableCols)) {
        $updateParts[] = $expiry_date ? "expiry_date = '$expiry_date'" : "expiry_date = NULL";
    }
    if (in_array('updated_at', $tableCols)) {
        $updateParts[] = "updated_at = NOW()";
    }

    $sql = "UPDATE inventory SET " . implode(", ", $updateParts) . " WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Stock inventory record <strong>#{$id}</strong> updated successfully!";
    } else {
        $_SESSION['error'] = "Update failed: " . mysqli_error($conn);
    }

    header("Location: index.php");
    exit();

} else {
    header("Location: index.php");
    exit();
}