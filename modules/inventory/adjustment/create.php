<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

// Clean Query: Directly uses inventory table columns without missing ID references
$products = mysqli_query($conn, "
    SELECT 
        id, 
        product_code, 
        product_name, 
        warehouse AS warehouse_name,
        bin_location AS bin_code,
        available_qty
    FROM inventory
    WHERE available_qty >= 0
    ORDER BY product_name ASC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-sliders text-primary me-2"></i>New Stock Adjustment</h2>
                <p class="text-muted mb-0">Manually increase or decrease item quantity with reason</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <div class="card shadow-sm border-0 rounded-4 col-lg-10 mx-auto">
            <div class="card-body p-4">
                <form action="save.php" method="POST" id="adjustmentForm">
                    <div class="row g-3">

                        <!-- Item Select -->
                        <div class="col-md-6">
                            <label for="inventory_id" class="form-label fw-semibold">Select Inventory Product *</label>
                            <select name="inventory_id" id="inventory_id" class="form-select" required>
                                <option value="">-- Choose Product --</option>
                                <?php if ($products && mysqli_num_rows($products) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($products)): ?>
                                        <option 
                                            value="<?= htmlspecialchars($row['id']); ?>" 
                                            data-wh="<?= htmlspecialchars($row['warehouse_name'] ?? ''); ?>" 
                                            data-bin="<?= htmlspecialchars($row['bin_code'] ?? ''); ?>" 
                                            data-qty="<?= htmlspecialchars($row['available_qty']); ?>">
                                            <?= htmlspecialchars($row['product_code']); ?> - <?= htmlspecialchars($row['product_name']); ?> (Available: <?= htmlspecialchars($row['available_qty']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Date -->
                        <div class="col-md-6">
                            <label for="adjustment_date" class="form-label fw-semibold">Adjustment Date *</label>
                            <input type="date" name="adjustment_date" id="adjustment_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Warehouse (Read Only) -->
                        <div class="col-md-4">
                            <label for="warehouse" class="form-label fw-semibold">Warehouse</label>
                            <input type="text" name="warehouse" id="warehouse" class="form-control bg-light" readonly placeholder="-">
                        </div>

                        <!-- Bin Location (Read Only) -->
                        <div class="col-md-4">
                            <label for="bin_location" class="form-label fw-semibold">Bin Location</label>
                            <input type="text" name="bin_location" id="bin_location" class="form-control bg-light font-monospace" readonly placeholder="-">
                        </div>

                        <!-- Available Stock (Read Only) -->
                        <div class="col-md-4">
                            <label for="available_qty" class="form-label fw-semibold">Current Available Qty</label>
                            <input type="number" id="available_qty" class="form-control bg-light text-primary fw-bold" readonly placeholder="0">
                        </div>

                        <hr class="my-2">

                        <!-- Adjustment Type -->
                        <div class="col-md-6">
                            <label for="adjustment_type" class="form-label fw-semibold">Adjustment Action *</label>
                            <select name="adjustment_type" id="adjustment_type" class="form-select" required>
                                <option value="Increase">➕ Increase Stock (+)</option>
                                <option value="Decrease">➖ Decrease Stock (-)</option>
                            </select>
                        </div>

                        <!-- Adjustment Qty -->
                        <div class="col-md-6">
                            <label for="quantity" class="form-label fw-semibold">Quantity to Adjust *</label>
                            <input type="number" name="quantity" id="quantity" class="form-control" min="1" placeholder="Enter Qty" required>
                            <div class="invalid-feedback" id="qtyError">Adjustment quantity exceeds available stock!</div>
                        </div>

                        <!-- Reason -->
                        <div class="col-12">
                            <label for="reason" class="form-label fw-semibold">Reason for Adjustment *</label>
                            <textarea name="reason" id="reason" class="form-control" rows="3" placeholder="e.g. Physical audit recount, Damaged goods removal, Expiry disposal..." required></textarea>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary px-3">Cancel</a>
                        <button type="submit" id="saveBtn" class="btn btn-success px-4">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Confirm & Save
                        </button>
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
    const binInput = document.getElementById("bin_location");
    const availableQtyInput = document.getElementById("available_qty");
    const saveBtn = document.getElementById("saveBtn");
    const qtyError = document.getElementById("qtyError");

    function validateQuantity() {
        const type = typeSelect.value;
        const available = parseInt(availableQtyInput.value) || 0;
        const qty = parseInt(quantityInput.value) || 0;

        if (type === "Decrease" && qty > available) {
            quantityInput.classList.add("is-invalid");
            qtyError.style.display = "block";
            saveBtn.disabled = true;
        } else {
            quantityInput.classList.remove("is-invalid");
            qtyError.style.display = "none";
            saveBtn.disabled = false;
        }
    }

    inventorySelect.addEventListener("change", function () {
        const option = this.options[this.selectedIndex];
        warehouseInput.value = option.getAttribute("data-wh") || "";
        binInput.value = option.getAttribute("data-bin") || "";
        availableQtyInput.value = option.getAttribute("data-qty") || "0";
        validateQuantity();
    });

    typeSelect.addEventListener("change", validateQuantity);
    quantityInput.addEventListener("input", validateQuantity);
});
</script>