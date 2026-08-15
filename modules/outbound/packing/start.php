<?php
session_start();

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Picking ID Missing.";
    header("Location: index.php");
    exit();
}

$picking_id = intval($_GET['id']);

$pickingRes = mysqli_query($conn, "
    SELECT p.*, s.order_number, s.customer_name
    FROM picking p
    INNER JOIN sales_orders s ON p.sales_order_id = s.id
    WHERE p.id = '$picking_id'
");

if (!$pickingRes || mysqli_num_rows($pickingRes) == 0) {
    $_SESSION['error'] = "Picking Record Not Found.";
    header("Location: index.php");
    exit();
}

$picking = mysqli_fetch_assoc($pickingRes);

$items = mysqli_query($conn, "
    SELECT * FROM picking_items 
    WHERE picking_id = '$picking_id' 
    ORDER BY id ASC
");

$packing_no = "PACK-" . date("YmdHis");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-box-open text-warning me-2"></i>Pack Order Items</h2>
                <p class="text-muted mb-0">Order: <strong><?= htmlspecialchars($picking['order_number']); ?></strong> | Customer: <strong><?= htmlspecialchars($picking['customer_name']); ?></strong></p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <form action="save.php" method="POST">
            <input type="hidden" name="picking_id" value="<?= $picking_id; ?>">
            <input type="hidden" name="sales_order_id" value="<?= $picking['sales_order_id']; ?>">
            <input type="hidden" name="packing_number" value="<?= $packing_no; ?>">

            <div class="card shadow-sm border-0 rounded-4">
                <div class="card-body p-4">

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Packing Slip Number</label>
                            <input type="text" class="form-control bg-light font-monospace" value="<?= $packing_no; ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Packing Date *</label>
                            <input type="date" name="packing_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Packer Name *</label>
                            <input type="text" name="packer_name" class="form-control" value="<?= $_SESSION['employee_name'] ?? 'Staff'; ?>" required>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-list-check text-primary me-2"></i>Item Verification Checklist</h5>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Product Details</th>
                                    <th>Warehouse</th>
                                    <th>Bin</th>
                                    <th class="text-center" width="140">Picked Qty</th>
                                    <th width="180">Packed Qty *</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($items && mysqli_num_rows($items) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($items)): ?>
                                        <tr>
                                            <td>
                                                <strong class="fs-6"><?= htmlspecialchars($row['product_name']); ?></strong><br>
                                                <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['product_code']); ?></span>
                                                <input type="hidden" name="picking_item_id[]" value="<?= $row['id']; ?>">
                                            </td>
                                            <td><?= htmlspecialchars($row['warehouse']); ?></td>
                                            <td><code class="fw-bold"><?= htmlspecialchars($row['bin_location']); ?></code></td>
                                            <td class="text-center"><span class="badge bg-primary fs-6 px-3 py-2"><?= $row['picked_qty']; ?> Units</span></td>
                                            <td>
                                                <input type="number" 
                                                       name="packed_qty[]" 
                                                       class="form-control packed-input fw-bold" 
                                                       min="0" 
                                                       max="<?= $row['picked_qty']; ?>" 
                                                       value="<?= $row['picked_qty']; ?>" 
                                                       required>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="fa-solid fa-box-archive me-1"></i> Complete Packing (Ready for Dispatch)
                        </button>
                    </div>

                </div>
            </div>
        </form>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>