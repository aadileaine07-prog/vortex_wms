<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$orderQuery = mysqli_query($conn, "SELECT * FROM sales_orders WHERE id='$id'");
if (!$orderQuery || mysqli_num_rows($orderQuery) == 0) {
    $_SESSION['error'] = "Sales Order not found.";
    header("Location: index.php");
    exit();
}

$order = mysqli_fetch_assoc($orderQuery);

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Sales Order</h2>
                <p class="text-muted mb-0">Update Order Details (#<?= htmlspecialchars($order['order_number']); ?>)</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <form action="update.php" method="POST">
            <input type="hidden" name="id" value="<?= $order['id']; ?>">

            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4">
                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Order Number</label>
                            <input type="text" class="form-control bg-light" value="<?= htmlspecialchars($order['order_number']); ?>" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Order Date <span class="text-danger">*</span></label>
                            <input type="date" name="order_date" class="form-control" value="<?= $order['order_date']; ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select fw-semibold" required>
                                <option value="Pending" <?= ($order['status'] == "Pending") ? "selected" : ""; ?>>Pending</option>
                                <option value="Picking" <?= ($order['status'] == "Picking") ? "selected" : ""; ?>>Picking</option>
                                <option value="Packed" <?= ($order['status'] == "Packed") ? "selected" : ""; ?>>Packed</option>
                                <option value="Dispatched" <?= ($order['status'] == "Dispatched") ? "selected" : ""; ?>>Dispatched</option>
                                <option value="Delivered" <?= ($order['status'] == "Delivered") ? "selected" : ""; ?>>Delivered</option>
                                <option value="Cancelled" <?= ($order['status'] == "Cancelled") ? "selected" : ""; ?>>Cancelled</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" class="form-control" value="<?= htmlspecialchars($order['customer_name']); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Customer Phone</label>
                            <input type="text" name="customer_phone" class="form-control" value="<?= htmlspecialchars($order['customer_phone']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Customer Email</label>
                            <input type="email" name="customer_email" class="form-control" value="<?= htmlspecialchars($order['customer_email']); ?>">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Shipping Address</label>
                            <textarea name="shipping_address" class="form-control" rows="3"><?= htmlspecialchars($order['shipping_address']); ?></textarea>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="text-end">
                        <button type="submit" class="btn btn-warning px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Update Order</button>
                        <a href="index.php" class="btn btn-outline-secondary px-3">Cancel</a>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>