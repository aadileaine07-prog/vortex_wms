<?php
session_start();

// Dynamic Project Root Path
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Sales Order ID Missing.";
    header("Location: index.php");
    exit();
}

$so_id = intval($_GET['id']);

// 1. Fetch Sales Order Master Details
$soQuery = mysqli_query($conn, "SELECT * FROM sales_orders WHERE id = '$so_id'");

if (!$soQuery || mysqli_num_rows($soQuery) == 0) {
    $_SESSION['error'] = "Sales Order Record Not Found.";
    header("Location: index.php");
    exit();
}

$so = mysqli_fetch_assoc($soQuery);

// 2. Fetch Sales Order Items (Checks both `sales_order_id` & `so_id` columns dynamically)
$itemList = [];
$checkCol = mysqli_query($conn, "SHOW COLUMNS FROM sales_order_items LIKE 'sales_order_id'");

if ($checkCol && mysqli_num_rows($checkCol) > 0) {
    $itemsQuery = mysqli_query($conn, "SELECT * FROM sales_order_items WHERE sales_order_id = '$so_id'");
} else {
    $itemsQuery = mysqli_query($conn, "SELECT * FROM sales_order_items WHERE so_id = '$so_id'");
}

if ($itemsQuery && mysqli_num_rows($itemsQuery) > 0) {
    while ($row = mysqli_fetch_assoc($itemsQuery)) {
        $itemList[] = [
            'product_code' => $row['product_code'] ?? $row['sku'] ?? 'N/A',
            'product_name' => $row['product_name'] ?? $row['item_name'] ?? 'N/A',
            'warehouse'    => $row['warehouse'] ?? $row['warehouse_name'] ?? 'Main Warehouse',
            'bin_location' => $row['bin_location'] ?? $row['bin'] ?? 'N/A',
            'ordered_qty'  => intval($row['ordered_qty'] ?? $row['quantity'] ?? $row['qty'] ?? 0)
        ];
    }
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Sales Order Details</h2>
                <p class="text-muted mb-0">Order #: <strong class="font-monospace text-primary"><?= htmlspecialchars($so['so_number'] ?? $so['order_number'] ?? 'SO-'.$so_id); ?></strong></p>
            </div>
            <div>
                <button onclick="window.print()" class="btn btn-dark px-3 me-2"><i class="fa-solid fa-print me-1"></i> Print</button>
                <a href="edit.php?id=<?= $so_id; ?>" class="btn btn-warning px-3 me-2 fw-bold"><i class="fa-solid fa-pen-to-square me-1"></i> Edit</a>
                <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <!-- Master Details Card -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body p-4">
                <div class="row g-4">
                    <div class="col-md-4">
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Customer Name</span>
                        <strong class="fs-6 text-dark"><?= htmlspecialchars($so['customer_name'] ?? 'N/A'); ?></strong>
                    </div>

                    <div class="col-md-4">
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Contact Phone</span>
                        <strong class="fs-6 text-dark"><?= htmlspecialchars($so['customer_phone'] ?? $so['phone'] ?? '-'); ?></strong>
                    </div>

                    <div class="col-md-4">
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Customer Email</span>
                        <strong class="fs-6 text-dark"><?= htmlspecialchars($so['customer_email'] ?? $so['email'] ?? '-'); ?></strong>
                    </div>

                    <div class="col-md-4">
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Order Date</span>
                        <strong class="fs-6 text-dark"><?= !empty($so['order_date']) ? date("d M, Y", strtotime($so['order_date'])) : '-'; ?></strong>
                    </div>

                    <div class="col-md-4">
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Status</span>
                        <?php 
                            $st = $so['status'] ?? 'Pending';
                            if ($st == 'Completed' || $st == 'Dispatched') echo '<span class="badge bg-success px-3 py-1 fs-6">Completed</span>';
                            elseif ($st == 'Picking' || $st == 'Processing') echo '<span class="badge bg-info px-3 py-1 fs-6">Processing</span>';
                            else echo '<span class="badge bg-warning text-dark px-3 py-1 fs-6">Pending</span>';
                        ?>
                    </div>

                    <div class="col-md-12">
                        <span class="text-muted small fw-bold text-uppercase d-block mb-1">Shipping Address</span>
                        <p class="mb-0 text-dark font-monospace"><?= nl2br(htmlspecialchars($so['shipping_address'] ?? $so['address'] ?? 'N/A')); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Ordered Products Table Card -->
        <h4 class="fw-bold text-dark mb-3"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Ordered Products</h4>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Warehouse</th>
                                <th>Bin Location</th>
                                <th width="140" class="text-center">Ordered Qty</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($itemList)): ?>
                                <?php foreach ($itemList as $item): ?>
                                    <tr>
                                        <td><code class="fs-6 fw-bold"><?= htmlspecialchars($item['product_code']); ?></code></td>
                                        <td><strong><?= htmlspecialchars($item['product_name']); ?></strong></td>
                                        <td><?= htmlspecialchars($item['warehouse']); ?></td>
                                        <td><span class="badge bg-light text-dark border font-monospace fs-6"><?= htmlspecialchars($item['bin_location']); ?></span></td>
                                        <td class="text-center fw-bold text-primary fs-6"><?= $item['ordered_qty']; ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="5" class="text-center text-muted py-4">No items found for this order.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>