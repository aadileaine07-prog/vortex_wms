<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$products = mysqli_query($conn, "
    SELECT id, product_code, product_name, warehouse, bin_location, available_qty
    FROM inventory
    WHERE available_qty >= 0
    ORDER BY product_name ASC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h3 class="card-title m-0">Create Stock Adjustment</h3>
            </div>
            <div class="card-body">
                <form action="save.php" method="POST">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="inventory_id" class="form-label">Product</label>
                            <select name="inventory_id" id="inventory_id" class="form-control" required>
                                <option value="">Select Product</option>
                                <?php while ($row = mysqli_fetch_assoc($products)): ?>
                                    <option 
                                        value="<?= htmlspecialchars($row['id']); ?>" 
                                        data-wh="<?= htmlspecialchars($row['warehouse'] ?? ''); ?>" 
                                        data-bin="<?= htmlspecialchars($row['bin_location'] ?? ''); ?>" 
                                        data-qty="<?= htmlspecialchars($row['available_qty']); ?>">
                                        <?= htmlspecialchars($row['product_code']); ?> - <?= htmlspecialchars($row['product_name']); ?> (Stock: <?= htmlspecialchars($row['available_qty']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="adjustment_date" class="form-label">Adjustment Date</label>
                            <input type="date" name="adjustment_date" id="adjustment_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="warehouse" class="form-label">Warehouse</label>
                            <input type="text" name="warehouse" id="warehouse" class="form-control" readonly>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="bin_location" class="form-label">Bin Location</label>
                            <input type="text" name="bin_location" id="bin_location" class="form-control" readonly>
                        </div>

                        <div class="col-md-4 mb-3">
                            <label for="available_qty" class="form-label">Available Qty</label>
                            <input type="number" id="available_qty" class="form-control" readonly>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="adjustment_type" class="form-label">Adjustment Type</label>
                            <select name="adjustment_type" id="adjustment_type" class="form-control" required>
                                <option value="Increase">Increase</option>
                                <option value="Decrease">Decrease</option>
                            </select>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label for="quantity" class="form-label">Adjustment Quantity</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" min="1" required>
                        </div>

                        <div class="col-12 mb-3">
                            <label for="reason" class="form-label">Reason</label>
                            <textarea name="reason" id="reason" class="form-control" rows="3" placeholder="Enter adjustment reason"></textarea>
                        </div>

                        <div class="col-12">
                            <hr>
                            <div class="d-flex justify-content-between">
                                <a href="index.php" class="btn btn-secondary">← Back</a>
                                <button type="submit" class="btn btn-success">Save Adjustment</button>
                            </div>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include "../../../includes/footer.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const inventorySelect = document.getElementById("inventory_id");
    const typeSelect = document.getElementById("adjustment_type");
    const quantityInput = document.getElementById("quantity");
    const warehouseInput = document.getElementById("warehouse");
    document.getElementById("bin_location");
    const availableQtyInput = document.getElementById("available_qty");

    function validateQuantity() {
        const type = typeSelect.value;
        const available = parseInt(availableQtyInput.value) || 0;
        const qty = parseInt(quantityInput.value) || 0;

        if (type === "Decrease" && qty > available) {
            alert("Adjustment quantity cannot be greater than available stock.");
            quantityInput.value = "";
        }
    }

    inventorySelect.addEventListener("change", function () {
        const option = this.options[this.selectedIndex];
        warehouseInput.value = option.getAttribute("data-wh") || "";
        document.getElementById("bin_location").value = option.getAttribute("data-bin") || "";
        availableQtyInput.value = option.getAttribute("data-qty") || "";
        validateQuantity();
    });

    typeSelect.addEventListener("change", validateQuantity);
    quantityInput.addEventListener("input", validateQuantity);
});
</script>