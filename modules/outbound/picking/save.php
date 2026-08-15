<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if ($_SERVER['REQUEST_METHOD'] != "POST") {
    header("Location: index.php");
    exit();
}

$sales_order_id = intval($_POST['sales_order_id']);
$picking_number = mysqli_real_escape_string($conn, $_POST['picking_number']);
$picking_date   = mysqli_real_escape_string($conn, $_POST['picking_date']);
$picker_name    = mysqli_real_escape_string($conn, $_POST['picker_name']);

if ($sales_order_id <= 0) {
    $_SESSION['error'] = "Invalid Sales Order ID.";
    header("Location: index.php");
    exit();
}

// START TRANSACTION FOR ATOMIC CONSISTENCY
$conn->begin_transaction();

try {
    // 1. Insert Master Picking Record
    $insertPicking = mysqli_query($conn, "
        INSERT INTO picking (sales_order_id, picking_number, picker_name, picking_date, status)
        VALUES ('$sales_order_id', '$picking_number', '$picker_name', '$picking_date', 'Completed')
    ");

    if (!$insertPicking) {
        throw new Exception("Failed to record picking slip: " . mysqli_error($conn));
    }

    $picking_id = mysqli_insert_id($conn);
    $total_ordered_sum = 0;
    $total_picked_sum  = 0;

    // 2. Loop Picked Items
    if (isset($_POST['sales_order_item_id']) && is_array($_POST['sales_order_item_id'])) {
        foreach ($_POST['sales_order_item_id'] as $key => $sales_order_item_id) {

            $sales_order_item_id = intval($sales_order_item_id);
            $picked_qty          = intval($_POST['picked_qty'][$key]);

            $itemQuery = mysqli_query($conn, "SELECT * FROM sales_order_items WHERE id='$sales_order_item_id'");

            if (!$itemQuery || mysqli_num_rows($itemQuery) == 0) {
                continue;
            }

            $row = mysqli_fetch_assoc($itemQuery);

            $p_id   = $row['product_id'];
            $p_code = mysqli_real_escape_string($conn, $row['product_code']);
            $p_name = mysqli_real_escape_string($conn, $row['product_name']);
            $wh     = mysqli_real_escape_string($conn, $row['warehouse']);
            $bin    = mysqli_real_escape_string($conn, $row['bin_location']);
            $o_qty  = intval($row['ordered_qty']);

            $total_ordered_sum += $o_qty;
            $total_picked_sum  += $picked_qty;

            // A. Insert into picking_items
            mysqli_query($conn, "
                INSERT INTO picking_items (
                    picking_id, sales_order_item_id, product_id, product_code, product_name, warehouse, bin_location, ordered_qty, picked_qty
                ) VALUES (
                    '$picking_id', '$sales_order_item_id', '$p_id', '$p_code', '$p_name', '$wh', '$bin', '$o_qty', '$picked_qty'
                )
            ");

            // B. Update sales_order_items
            mysqli_query($conn, "
                UPDATE sales_order_items
                SET picked_qty = '$picked_qty'
                WHERE id = '$sales_order_item_id'
            ");

            // C. DEDUCT REAL INVENTORY STOCK FROM `inventory` TABLE
            if ($picked_qty > 0) {
                // Find inventory item at specific warehouse & bin
                $invCheck = mysqli_query($conn, "
                    SELECT id, available_qty FROM inventory 
                    WHERE (product_id = '$p_id' OR product_code = '$p_code')
                      AND warehouse = '$wh'
                      AND bin_location = '$bin'
                    LIMIT 1
                ");

                if ($invCheck && mysqli_num_rows($invCheck) > 0) {
                    $inv = mysqli_fetch_assoc($invCheck);
                    $newQty = max(0, $inv['available_qty'] - $picked_qty);

                    $invStatus = "In Stock";
                    if ($newQty <= 0) {
                        $invStatus = "Out of Stock";
                    } elseif ($newQty <= 10) {
                        $invStatus = "Low Stock";
                    }

                    mysqli_query($conn, "
                        UPDATE inventory 
                        SET available_qty = '$newQty', status = '$invStatus'
                        WHERE id = '" . $inv['id'] . "'
                    ");
                }
            }
        }
    }

    // 3. Update Sales Order Status based on Picked Total
    if ($total_picked_sum >= $total_ordered_sum && $total_ordered_sum > 0) {
        $finalStatus = 'Picked'; // Fully picked -> Next stage is Packing
    } elseif ($total_picked_sum > 0) {
        $finalStatus = 'Partially Picked';
    } else {
        $finalStatus = 'Pending';
    }

    mysqli_query($conn, "
        UPDATE sales_orders
        SET status = '$finalStatus'
        WHERE id = '$sales_order_id'
    ");

    // COMMIT ALL DB CHANGES
    $conn->commit();

    $_SESSION['success'] = "Picking Slip #{$picking_number} completed successfully! Inventory stock has been deducted.";
    header("Location: index.php");
    exit();

} catch (Exception $e) {
    // ROLLBACK ON ERROR
    $conn->rollback();
    $_SESSION['error'] = "Picking Failed: " . $e->getMessage();
    header("Location: index.php");
    exit();
}
?>