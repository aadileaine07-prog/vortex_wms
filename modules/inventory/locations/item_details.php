<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../../../config/database.php") ? dirname(__DIR__, 4) : dirname(__DIR__, 2));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$itemData = null;

if ($itemId > 0) {
    $res = mysqli_query($conn, "SELECT * FROM inventory WHERE id = $itemId LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $itemData = mysqli_fetch_assoc($res);
    }
}

include_once $projectRoot . "/includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-box-open me-2 text-primary"></i>Item & Location Details</h3>
            <p class="text-muted mb-0">Detailed view of inventory stock unit and bin coordinates</p>
        </div>
        <a href="inventory_view.php" class="btn btn-outline-secondary rounded-pill px-3 fw-bold shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory View</a>
    </div>

    <?php if ($itemData): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 bg-white mb-4">
            <h5 class="fw-bold text-dark mb-4 border-bottom pb-2">Item Specifications & Location</h5>
            <div class="row g-4">
                <div class="col-md-4">
                    <span class="text-muted small d-block text-uppercase fw-semibold">Inventory Record ID</span>
                    <strong class="font-monospace fs-5 text-dark">#<?= htmlspecialchars($itemData['id']); ?></strong>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block text-uppercase fw-semibold">Product Name / Title</span>
                    <strong class="text-dark fs-6"><?= htmlspecialchars($itemData['product_name'] ?? $itemData['item_name'] ?? 'Catalog Item'); ?></strong>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block text-uppercase fw-semibold">Product SKU Code</span>
                    <strong class="font-monospace text-primary fs-6"><?= htmlspecialchars($itemData['product_code'] ?? $itemData['sku'] ?? 'SKU-N/A'); ?></strong>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block text-uppercase fw-semibold">Warehouse Hub</span>
                    <span class="fw-bold text-secondary"><?= htmlspecialchars($itemData['warehouse'] ?? 'Surat S1'); ?></span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block text-uppercase fw-semibold">Bin Location Coordinate</span>
                    <code class="text-danger fw-bold fs-6">[📍 <?= htmlspecialchars($itemData['bin_location'] ?? $itemData['bin_code'] ?? 'Unassigned'); ?>]</code>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block text-uppercase fw-semibold">Available Stock Quantity</span>
                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-2 fs-6 fw-bold">
                        <?= (int)($itemData['available_qty'] ?? $itemData['quantity'] ?? 0); ?> Units
                    </span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block text-uppercase fw-semibold">Batch Number</span>
                    <span class="font-monospace text-muted"><?= htmlspecialchars($itemData['batch_no'] ?? 'BAT-GEN'); ?></span>
                </div>
                <div class="col-md-4">
                    <span class="text-muted small d-block text-uppercase fw-semibold">Stock Status</span>
                    <span class="badge bg-primary px-3 py-1"><?= htmlspecialchars($itemData['status'] ?? 'In Stock'); ?></span>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger rounded-4 p-4 shadow-sm border-0">
            <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i> Item details not found or invalid ID (<strong><?= $itemId; ?></strong>) specified. Please select a valid item from the inventory list.
        </div>
    <?php endif; ?>
</div>

<?php 
include_once $projectRoot . "/includes/footer.php"; 
?>