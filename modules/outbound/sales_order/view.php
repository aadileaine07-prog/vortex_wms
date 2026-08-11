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

$items = mysqli_query($conn, "
    SELECT * FROM sales_order_items 
    WHERE sales_order_id='$id' 
    ORDER BY id ASC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-file-invoice text-primary me-2"></i>Sales Order Details
                </h2>
                <p class="text-muted mb-0">Order #: <?= htmlspecialchars($order['order_number']); ?></p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print();" class="btn btn-dark"><i class="fa-solid fa-print me-1"></i> Print</button>
                <a href="edit.php?id=<?= $order['id']; ?>" class="btn btn-warning"><i class="fa-solid fa-pen-to-square me-1"></i> Edit</a>
                <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <small class="text-muted d-block text-uppercase fw-semibold">Customer Name</small>
                        <span class="fw-bold fs-6 text-dark"><?= htmlspecialchars($order['customer_name']); ?></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block text-uppercase fw-semibold">Contact Phone</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($order['customer_phone'] ?: '-'); ?></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block text-uppercase fw-semibold">Customer Email</small>
                        <span class="fw-semibold text-dark"><?= htmlspecialchars($order['customer_email'] ?: '-'); ?></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block text-uppercase fw-semibold">Order Date</small>
                        <span class="fw-semibold text-dark"><?= date("d M, Y", strtotime($order['order_date'])); ?></span>
                    </div>
                    <div class="col-md-4">
                        <small class="text-muted d-block text-uppercase fw-semibold">Status</small>
                        <?php
                        $st = $order['status'];
                        if ($st == "Pending") echo '<span class="badge bg-warning text-dark">Pending</span>';
                        elseif ($st == "Picking") echo '<span class="badge bg-info">Picking</span>';
                        elseif ($st == "Packed") echo '<span class="badge bg-primary">Packed</span>';
                        elseif ($st == "Dispatched") echo '<span class="badge bg-success">Dispatched</span>';
                        elseif ($st == "Delivered") echo '<span class="badge bg-dark">Delivered</span>';
                        else echo '<span class="badge bg-danger">Cancelled</span>';
                        ?>
                    </div>
                    <div class="col-md-12">
                        <small class="text-muted d-block text-uppercase fw-semibold">Shipping Address</small>
                        <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($order['shipping_address'] ?: 'N/A')); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">
                <h5 class="fw-bold text-dark mb-3">Ordered Products</h5>

                <div class="table-responsive">
                    <table class="table table-hover align-middle border">
                        <thead class="table-dark">
                            <tr>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Warehouse</th>
                                <th>Bin Location</th>
                                <th class="text-center">Ordered Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($items && mysqli_num_rows($items) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($items)): ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['product_code']); ?></span></td>
                                        <td><strong><?= htmlspecialchars($row['product_name']); ?></strong></td>
                                        <td><?= htmlspecialchars($row['warehouse']); ?></td>
                                        <td><?= htmlspecialchars($row['bin_location']); ?></td>
                                        <td class="text-center"><span class="badge bg-primary fs-6"><?= $row['ordered_qty']; ?></span></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="5" class="text-center text-muted">No items found for this order.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>