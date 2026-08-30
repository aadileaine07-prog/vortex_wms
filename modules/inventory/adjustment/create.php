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

$preselected_id = intval($_GET['inventory_id'] ?? 0);

// Dynamic Warehouse Table Check
$whTable = "warehouses";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $whTable = "warehouse";
}

$nameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) === 0) {
    $nameCol = "name";
}

// Fetch Inventory Products with Detailed Join
$query = "
    SELECT 
        i.id,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS product_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS sku_code,
        COALESCE(w.{$nameCol}, i.warehouse, 'Surat Central Logistics Park') AS warehouse_name,
        COALESCE(i.bin_location, 'DOCK-INWARD') AS bin_code,
        COALESCE(i.available_qty, 0) AS available_qty
    FROM inventory i
    LEFT JOIN products p ON (p.id = i.product_id OR p.product_code = i.product_code)
    LEFT JOIN `{$whTable}` w ON (w.id = i.warehouse_id OR w.{$nameCol} = i.warehouse)
    ORDER BY product_name ASC
";
$products = @mysqli_query($conn, $query);

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-sliders text-warning me-2"></i>New Stock Adjustment
            </h2>
            <p class="text-muted mb-0">Record physical audit discrepancies, damaged goods, or inventory corrections</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Adjustments
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-exclamation me-2"></i> <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-9 col-lg-11 mx-auto">
        <div class="card-body p-4">
            
            <form action="save.php" method="POST" id="adjustmentForm">
                <input type="hidden" name="action" value="create">

                <div class="row g-4">

                    <!-- Select Product -->
                    <div class="col-md-7">
                        <label class="form-label small fw-bold text-muted">Select Inventory Item *</label>
                        <select name="inventory_id" id="inventory_id" class="form-select border-2 fw-semibold" required>
                            <option value="">-- Choose Stock Record --</option>
                            <?php if ($products && mysqli_num_rows($products) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($products)): ?>
                                    <option 
                                        value="<?= $row['id']; ?>" 
                                        data-wh="<?= htmlspecialchars($row['warehouse_name']); ?>" 
                                        data-bin="<?= htmlspecialchars($row['bin_code']); ?>" 
                                        data-qty="<?= (int)$row['available_qty']; ?>"
                                        <?= ($row['id'] == $preselected_id) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($row['product_name']); ?> (<?= htmlspecialchars($row['sku_code']); ?>) | Bin: <?= htmlspecialchars($row['bin_code']); ?> [Available: <?= $row['available_qty']; ?>]
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <!-- Adjustment Date -->
                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted">Adjustment Date *</label>
                        <input type="date" name="adjustment_date" id="adjustment_date" class="form-control border-2 fw-semibold" value="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <!-- Metadata Read-only Blocks -->
                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Warehouse Facility</small>
                            <input type="text" id="warehouse" class="form-control-plaintext fw-bold text-dark py-0" readonly value="-">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Current Bin Coordinate</small>
                            <input type="text" id="bin_location" class="form-control-plaintext font-monospace fw-bold text-primary py-0" readonly value="-">
                        </div>
                    </div>

                    <div class="col-md-4">
                        <div class="p-3 bg-light rounded-4 border border-start border-4 border-primary">
                            <small class="text-muted d-block text-uppercase fw-semibold" style="font-size:11px;">Current Available Qty</small>
                            <input type="text" id="available_qty" class="form-control-plaintext fs-5 fw-bold text-primary py-0" readonly value="0 Units">
                        </div>
                    </div>

                    <!-- Action Details -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Adjustment Type *</label>
                        <select name="adjustment_type" id="adjustment_type" class="form-select border-2 fw-bold text-dark" required>
                            <option value="Increase" class="text-success">➕ Increase Stock (Found / Audit Surplus)</option>
                            <option value="Decrease" class="text-danger">➖ Decrease Stock (Damaged / Missing / Write-off)</option>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Quantity to Adjust *</label>
                        <div class="input-group">
                            <input type="number" name="quantity" id="quantity" class="form-control border-2 fw-bold text-center fs-5" min="1" placeholder="Qty" required>
                            <span class="input-group-text bg-light border-2">Units</span>
                        </div>
                        <div class="text-danger small mt-1 fw-bold" id="qtyError" style="display: none;">
                            <i class="fa-solid fa-triangle-exclamation me-1"></i> Deduction quantity cannot exceed current available stock!
                        </div>
                    </div>

                    <!-- Live Calculated Balance Preview -->
                    <div class="col-12">
                        <div class="p-3 bg-primary bg-opacity-10 rounded-4 border border-primary border-opacity-25 d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div>
                                <strong class="text-primary d-block">Projected New Available Balance:</strong>
                                <small class="text-muted">Updated value after adjustment confirms</small>
                            </div>
                            <div class="fs-4 fw-bold font-monospace text-primary" id="newBalancePreview">0 Units</div>
                        </div>
                    </div>

                    <!-- Reason -->
                    <div class="col-12">
                        <label class="form-label small fw-bold text-muted">Adjustment Reason / Audit Notes *</label>
                        <textarea name="reason" id="reason" class="form-control border-2" rows="3" placeholder="e.g. Physical inventory cycle count correction, Damaged stock disposal, System discrepancy reconciliation..." required></textarea>
                    </div>

                </div>

                <div class="mt-4 pt-3 border-top text-end d-flex justify-content-end gap-2">
                    <a href="index.php" class="btn btn-light px-4 rounded-pill">Cancel</a>
                    <button type="submit" id="saveBtn" class="btn btn-success px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Confirm & Save Adjustment
                    </button>
                </div>
            </form>

        </div>
    </div>

</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const inventorySelect   = document.getElementById("inventory_id");
    const typeSelect        = document.getElementById("adjustment_type");
    const quantityInput     = document.getElementById("quantity");
    const warehouseInput    = document.getElementById("warehouse");
    const binInput          = document.getElementById("bin_location");
    const availableQtyInput = document.getElementById("available_qty");
    const newBalancePreview = document.getElementById("newBalancePreview");
    const saveBtn           = document.getElementById("saveBtn");
    const qtyError          = document.getElementById("qtyError");

    let currentStock = 0;

    function recalculate() {
        const type = typeSelect.value;
        const qty = parseInt(quantityInput.value) || 0;

        if (type === "Decrease" && qty > currentStock) {
            quantityInput.classList.add("is-invalid");
            qtyError.style.display = "block";
            saveBtn.disabled = true;
            newBalancePreview.innerText = "Invalid (Below 0)";
            newBalancePreview.className = "fs-4 fw-bold font-monospace text-danger";
        } else {
            quantityInput.classList.remove("is-invalid");
            qtyError.style.display = "none";
            saveBtn.disabled = false;

            const finalBalance = (type === "Increase") ? (currentStock + qty) : (currentStock - qty);
            newBalancePreview.innerText = finalBalance + " Units";
            newBalancePreview.className = "fs-4 fw-bold font-monospace text-primary";
        }
    }

    function syncSelectedOption() {
        if (inventorySelect.selectedIndex > 0) {
            const option = inventorySelect.options[inventorySelect.selectedIndex];
            warehouseInput.value = option.getAttribute("data-wh") || "-";
            binInput.value = option.getAttribute("data-bin") || "-";
            currentStock = parseInt(option.getAttribute("data-qty")) || 0;
            availableQtyInput.value = currentStock + " Units";
            recalculate();
        } else {
            warehouseInput.value = "-";
            binInput.value = "-";
            currentStock = 0;
            availableQtyInput.value = "0 Units";
            newBalancePreview.innerText = "0 Units";
        }
    }

    inventorySelect.addEventListener("change", syncSelectedOption);
    typeSelect.addEventListener("change", recalculate);
    quantityInput.addEventListener("input", recalculate);

    // Initial Trigger for preselected IDs
    syncSelectedOption();
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>