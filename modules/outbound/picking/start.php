<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Sales Order ID Missing.";
    header("Location: index.php");
    exit();
}

$sales_order_id = intval($_GET['id']);

$orderQuery = mysqli_query($conn, "SELECT * FROM sales_orders WHERE id='$sales_order_id'");

if (!$orderQuery || mysqli_num_rows($orderQuery) == 0) {
    $_SESSION['error'] = "Sales Order Not Found.";
    header("Location: index.php");
    exit();
}

$order = mysqli_fetch_assoc($orderQuery);

$items = mysqli_query($conn, "
    SELECT *
    FROM sales_order_items
    WHERE sales_order_id='$sales_order_id'
    ORDER BY id ASC
");

$picking_no = "PICK-" . date("YmdHis");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-dolly text-success me-2"></i>Start Picking Process</h2>
                <p class="text-muted mb-0">Order No: <?= htmlspecialchars($order['order_number']); ?></p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <form action="save.php" method="POST">
            <input type="hidden" name="sales_order_id" value="<?= $sales_order_id; ?>">
            <input type="hidden" name="picking_number" value="<?= $picking_no; ?>">

            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4">

                    <div class="row g-3 mb-4">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Picking Number</label>
                            <input type="text" class="form-control bg-light" value="<?= $picking_no; ?>" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Picking Date <span class="text-danger">*</span></label>
                            <input type="date" name="picking_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Picker Name <span class="text-danger">*</span></label>
                            <input type="text" name="picker_name" class="form-control" placeholder="Enter picker employee name" required>
                        </div>
                    </div>

                    <h5 class="fw-bold text-dark mb-3">Items To Pick</h5>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th>Product</th>
                                    <th>Warehouse</th>
                                    <th>Bin Location</th>
                                    <th>Ordered Qty</th>
                                    <th width="180">Picked Qty <span class="text-danger">*</span></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php while ($row = mysqli_fetch_assoc($items)) { ?>
                                    <tr>
                                        <td>
                                            <strong><?= htmlspecialchars($row['product_name']); ?></strong><br>
                                            <small class="text-muted"><?= htmlspecialchars($row['product_code']); ?></small>
                                            <input type="hidden" name="sales_order_item_id[]" value="<?= $row['id']; ?>">
                                        </td>
                                        <td><?= htmlspecialchars($row['warehouse']); ?></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['bin_location']); ?></span></td>
                                        <td><span class="badge bg-primary fs-6"><?= $row['ordered_qty']; ?></span></td>
                                        <td>
                                            <input type="number" 
                                                   name="picked_qty[]" 
                                                   class="form-control picked-qty" 
                                                   min="0" 
                                                   max="<?= $row['ordered_qty']; ?>" 
                                                   data-max="<?= $row['ordered_qty']; ?>"
                                                   value="<?= $row['ordered_qty']; ?>" 
                                                   required>
                                        </td>
                                    </tr>
                                <?php } ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
                        <button type="submit" class="btn btn-success px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Complete Picking</button>
                    </div>

                </div>
            </div>
        </form>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>

<script>
document.querySelectorAll(".picked-qty").forEach(input => {
    input.addEventListener("input", function() {
        let max = parseInt(this.getAttribute("data-max")) || 0;
        let val = parseInt(this.value) || 0;

        if (val > max) {
            alert("Picked quantity cannot exceed ordered quantity (" + max + ").");
            this.value = max;
        }
    });
});
</script>