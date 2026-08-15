<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "PO ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// Fetch Master PO with Supplier
$poQuery = mysqli_query($conn, "
    SELECT po.*, s.supplier_name, s.supplier_code, s.contact_person, s.contact, s.email, s.gst_number, s.address 
    FROM purchase_orders po 
    LEFT JOIN suppliers s ON po.supplier_id = s.id 
    WHERE po.id='$id'
");

if (!$poQuery || mysqli_num_rows($poQuery) == 0) {
    $_SESSION['error'] = "Purchase Order Not Found.";
    header("Location: index.php");
    exit();
}

$po = mysqli_fetch_assoc($poQuery);

// Fetch PO Line Items
$itemsQuery = mysqli_query($conn, "SELECT * FROM purchase_order_items WHERE po_id='$id'");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 d-print-none">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-file-invoice text-primary me-2"></i>Purchase Order Slip</h2>
                <p class="text-muted mb-0">PO Number: <strong><?= htmlspecialchars($po['po_number']); ?></strong></p>
            </div>
            <div>
                <button onclick="window.print()" class="btn btn-outline-dark me-2"><i class="fa-solid fa-print me-1"></i> Print PO</button>
                <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4 p-4">
            <div class="card-body">

                <!-- Header Info -->
                <div class="row pb-4 mb-4 border-bottom">
                    <div class="col-6">
                        <h3 class="fw-bold text-primary mb-1">PURCHASE ORDER</h3>
                        <p class="text-muted mb-0">Order Ref: <strong>#<?= htmlspecialchars($po['po_number']); ?></strong></p>
                        <p class="text-muted mb-0">Date: <?= date("d M Y", strtotime($po['order_date'])); ?></p>
                    </div>
                    <div class="col-6 text-end">
                        <h5 class="fw-bold text-dark mb-1">Vendor Details:</h5>
                        <strong class="fs-5"><?= htmlspecialchars($po['supplier_name']); ?></strong><br>
                        <span class="font-monospace text-uppercase text-muted"><?= htmlspecialchars($po['supplier_code']); ?></span><br>
                        <span>GSTIN: <?= htmlspecialchars($po['gst_number'] ?? 'N/A'); ?></span><br>
                        <small class="text-muted"><?= htmlspecialchars($po['contact'] ?? ''); ?> | <?= htmlspecialchars($po['email'] ?? ''); ?></small>
                    </div>
                </div>

                <!-- Items Table -->
                <div class="table-responsive mb-4">
                    <table class="table table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">#</th>
                                <th>Product Details</th>
                                <th>SKU Code</th>
                                <th width="120" class="text-end">Qty</th>
                                <th width="150" class="text-end">Unit Price</th>
                                <th width="180" class="text-end">Subtotal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($itemsQuery && mysqli_num_rows($itemsQuery) > 0): $i=1; ?>
                                <?php while ($item = mysqli_fetch_assoc($itemsQuery)): ?>
                                    <tr>
                                        <td><?= $i++; ?></td>
                                        <td><strong><?= htmlspecialchars($item['product_name']); ?></strong></td>
                                        <td><code class="fs-6"><?= htmlspecialchars($item['product_code']); ?></code></td>
                                        <td class="text-end fw-bold"><?= $item['ordered_qty']; ?></td>
                                        <td class="text-end">₹<?= number_format($item['unit_price'], 2); ?></td>
                                        <td class="text-end fw-bold text-success">₹<?= number_format($item['subtotal'], 2); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr><td colspan="6" class="text-center py-4 text-muted">No items recorded in this PO</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Total Row -->
                <div class="row justify-content-end">
                    <div class="col-md-5">
                        <div class="card bg-light border-0 p-3 rounded-3 text-end">
                            <span class="text-muted fw-bold">TOTAL PO AMOUNT</span>
                            <h2 class="fw-bold text-success mb-0 mt-1">₹ <?= number_format($po['total_amount'], 2); ?></h2>
                        </div>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>