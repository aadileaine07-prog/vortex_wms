<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $id            = intval($_POST['id']);
    $product_name  = mysqli_real_escape_string($conn, $_POST['product_name']);
    $warehouse     = mysqli_real_escape_string($conn, $_POST['warehouse']);
    $bin_location  = mysqli_real_escape_string($conn, $_POST['bin_location']);
    $available_qty = intval($_POST['available_qty']);
    $reserved_qty  = intval($_POST['reserved_qty']);
    $status        = mysqli_real_escape_string($conn, $_POST['status']);

    // Automatic Status Calculation
    if ($available_qty <= 0) {
        $status = "Out Of Stock";
    } elseif ($available_qty <= 10) {
        $status = "Low Stock";
    }

    $sql = "
        UPDATE inventory SET
            product_name  = '$product_name',
            warehouse     = '$warehouse',
            bin_location  = '$bin_location',
            available_qty = '$available_qty',
            reserved_qty  = '$reserved_qty',
            status        = '$status'
        WHERE id = '$id'
    ";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "Inventory item updated successfully.";
    } else {
        $_SESSION['error'] = "Failed to update item: " . mysqli_error($conn);
    }

    header("Location: index.php");
    exit();

} else {
    header("Location: index.php");
    exit();
}
?>