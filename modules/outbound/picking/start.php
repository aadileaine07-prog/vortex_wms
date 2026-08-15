<?php
session_start();

// Dynamic Project Root
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

$sales_order_id = intval($_GET['id']);

// Fetch Order Details
$orderQuery = mysqli_query($conn, "SELECT * FROM sales_orders WHERE id='$sales_order_id'");

if (!$orderQuery || mysqli_num_rows($orderQuery) == 0) {
    $_SESSION['error'] = "Sales Order Not Found.";
    header("Location: index.php");
    exit();
}

$order = mysqli_fetch_assoc($orderQuery);

// Fetch Items ordered by Warehouse & Bin Location for OPTIMIZED PICK PATH
$items = mysqli_query($conn, "
    SELECT 
        soi.*,
        COALESCE(inv.available_qty, 0) AS stock_available
    FROM sales_order_items soi
    LEFT JOIN inventory inv ON (
        (inv.product_id = soi.product_id OR inv.product_code = soi.product_code) 
        AND inv.warehouse = soi.warehouse 
        AND inv.bin_location = soi.bin_location
    )
    WHERE soi.sales_order_id='$sales_order_id'
    ORDER BY soi.warehouse ASC, soi.bin_location ASC
");

$picking_no = "PICK-" . date("YmdHis");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-dolly text-success me-2"></i>Start Picking Process</h2>
                <p class="text-muted mb-0">Order No: <strong><?= htmlspecialchars($order['order_number']); ?></strong> | Customer: <strong><?= htmlspecialchars($order['customer_name']); ?></strong></p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <form action="save.php" method="POST">
            <input type="hidden" name="sales_order_id" value="<?= $sales_order_id; ?>">
            <input type="hidden" name="picking_number" value="<?= $picking_no; ?>">

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">

                    <!-- Header Inputs -->
                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Picking Slip Number</label>
                            <input type="text" class="form-control bg-light font-monospace" value="<?= $picking_no; ?>" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Picking Date <span class="text-danger">*</span></label>
                            <input type="date" name="picking_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Picker Employee Name <span class="text-danger">*</span></label>
                            <input type="text" name="picker_name" class="form-control" placeholder="Enter picker name" value="<?= $_SESSION['employee_name'] ?? ''; ?>" required>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-route text-primary me-2"></i>Optimized Pick List Route</h5>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Product Details</th>
                                    <th>Target Warehouse</th>
                                    <th>Bin Location</th>
                                    <th>Bin Stock Available</th>
                                    <th>Ordered Qty</th>
                                    <th width="180">Picked Qty <span class="text-danger">*</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($items && mysqli_num_rows($items) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($items)): ?>
                                        <?php 
                                            $ordered = intval($row['ordered_qty']);
                                            $avail = intval($row['stock_available']);
                                            $defaultPick = min($ordered, $avail);
                                        ?>
                                        <tr>
                                            <td>
                                                <strong class="fs-6"><?= htmlspecialchars($row['product_name']); ?></strong><br>
                                                <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['product_code']); ?></span>
                                                <input type="hidden" name="sales_order_item_id[]" value="<?= $row['id']; ?>">
                                            </td>
                                            <td>
                                                <i class="fa-solid fa-warehouse me-1 text-primary"></i>
                                                <strong><?= htmlspecialchars($row['warehouse']); ?></strong>
                                            </td>
                                            <td>
                                                <i class="fa-solid fa-location-dot text-danger me-1"></i>
                                                <code class="fs-6 fw-bold text-dark"><?= htmlspecialchars($row['bin_location']); ?></code>
                                            </td>
                                            <td>
                                                <?php if ($avail >= $ordered): ?>
                                                    <span class="badge bg-success px-3 py-2"><i class="fa-solid fa-circle-check me-1"></i> <?= $avail; ?> Units</span>
                                                <?php elseif ($avail > 0): ?>
                                                    <span class="badge bg-warning text-dark px-3 py-2"><i class="fa-solid fa-triangle-exclamation me-1"></i> <?= $avail; ?> Units (Shortage)</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger px-3 py-2"><i class="fa-solid fa-circle-xmark me-1"></i> 0 Units Out of Stock</span>
                                                <?php endif; ?>
                                            </td>
                                            <td><span class="badge bg-primary fs-6 px-3 py-2"><?= $ordered; ?> Units</span></td>
                                            <td>
                                                <input type="number" 
                                                       name="picked_qty[]" 
                                                       class="form-control picked-qty fw-bold" 
                                                       min="0" 
                                                       max="<?= $ordered; ?>" 
                                                       data-max="<?= $ordered; ?>"
                                                       value="<?= $defaultPick; ?>" 
                                                       required>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center py-4 text-muted">No items found for this order</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Complete Picking & Update Inventory
                        </button>
                    </div>

                </div>
            </div>
        </form>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<script>
document.querySelectorAll(".picked-qty").forEach(input => {
    input.addEventListener("input", function() {
        let max = parseInt(this.getAttribute("data-max")) || 0;
        let val = parseInt(this.value) || 0;

        if (val > max) {
            alert("⚠️ Picked quantity cannot exceed ordered quantity (" + max + ").");
            this.value = max;
        }
    });
});
</script>