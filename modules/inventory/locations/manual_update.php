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

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $itemId   = intval($_POST['item_id'] ?? 0);
    $location = mysqli_real_escape_string($conn, $_POST['location'] ?? '');
    $qty      = intval($_POST['quantity'] ?? 0);

    if ($itemId > 0) {
        // Universal update supporting both bin_location and location columns
        $updateQ = mysqli_query($conn, "UPDATE inventory SET bin_location = '$location', location = '$location', quantity = $qty, available_qty = $qty WHERE id = $itemId");
        if ($updateQ) {
            $_SESSION['success'] = "Inventory location & quantity updated successfully!";
        } else {
            $_SESSION['error'] = "Update Failed: " . mysqli_error($conn);
        }
        header("Location: manual_update.php?id=$itemId");
        exit();
    }
}

$itemId = isset($_GET['id']) ? intval($_GET['id']) : 0;
$itemData = null;

if ($itemId > 0) {
    $res = mysqli_query($conn, "SELECT * FROM inventory WHERE id = $itemId LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $itemData = mysqli_fetch_assoc($res);
    }
}

// Fallback: If no ID is provided in URL, automatically fetch the latest inventory item
if (!$itemData) {
    $res = mysqli_query($conn, "SELECT * FROM inventory ORDER BY id DESC LIMIT 1");
    if ($res && mysqli_num_rows($res) > 0) {
        $itemData = mysqli_fetch_assoc($res);
        $itemId = $itemData['id'];
    }
}

include_once $projectRoot . "/includes/header.php";
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-map-location-dot me-2 text-warning"></i>Manual Location & Stock Update</h3>
            <p class="text-muted small mb-0">Modify physical warehouse bin assignments and inventory counts.</p>
        </div>
        <a href="inventory_view.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3 fw-bold shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory View</a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success rounded-4 border-0 shadow-sm p-3 mb-4"><i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm p-3 mb-4"><i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if ($itemData): ?>
        <div class="card border-0 shadow-sm rounded-4 p-4 col-lg-8 mx-auto bg-white">
            <form method="POST">
                <input type="hidden" name="item_id" value="<?= $itemData['id']; ?>">
                
                <div class="mb-3">
                    <label class="form-label small fw-bold font-monospace">Item SKU / Name</label>
                    <input type="text" class="form-control font-monospace bg-light fw-bold" value="<?= htmlspecialchars($itemData['product_name'] ?? $itemData['item_name'] ?? 'SKU-' . $itemData['id']); ?>" readonly>
                </div>

                <div class="mb-3">
                    <label class="form-label small fw-bold font-monospace">Warehouse Location / Bin Code *</label>
                    <input type="text" name="location" class="form-control font-monospace fw-semibold" value="<?= htmlspecialchars($itemData['bin_location'] ?? $itemData['location'] ?? ''); ?>" placeholder="e.g. Aisle-03, Rack-B, Bin-12" required>
                </div>

                <div class="mb-4">
                    <label class="form-label small fw-bold font-monospace">Stock Quantity *</label>
                    <input type="number" name="quantity" class="form-control font-monospace fw-bold text-success fs-5" value="<?= htmlspecialchars($itemData['available_qty'] ?? $itemData['quantity'] ?? 0); ?>" required>
                </div>

                <div class="text-end">
                    <button type="submit" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-floppy-disk me-1"></i> Update Location & Stock</button>
                </div>
            </form>
        </div>
    <?php else: ?>
        <div class="alert alert-warning rounded-4 p-4 text-center border-0 shadow-sm">
            <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i> Please specify a valid item ID to perform a manual update (e.g., <code>?id=37</code>).
        </div>
    <?php endif; ?>
</div>

<?php 
include_once $projectRoot . "/includes/footer.php"; 
?>