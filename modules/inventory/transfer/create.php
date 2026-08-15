<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch In-Stock Inventory Products
$products = mysqli_query($conn, "
    SELECT *
    FROM inventory
    WHERE available_qty > 0
    ORDER BY product_name ASC
");

// Fetch Active Warehouses for Destination Selection
$warehouses = mysqli_query($conn, "
    SELECT id, warehouse_name, warehouse_code 
    FROM warehouse 
    WHERE status = 'Active' 
    ORDER BY warehouse_name ASC
");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-right-left text-primary me-2"></i>Create Stock Transfer</h2>
                <p class="text-muted mb-0">Move stock between warehouses, zones, and bin locations</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <div class="card shadow-sm border-0 rounded-4 col-lg-10 mx-auto">
            <div class="card-header bg-primary text-white p-3 rounded-top-4">
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-boxes-stacked me-2"></i>Stock Transfer Entry Form</h5>
            </div>

            <div class="card-body p-4">
                <form action="save.php" method="POST">
                    <div class="row g-3">

                        <!-- Product Selection -->
                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Select Product *</label>
                            <select name="inventory_id" id="inventory_id" class="form-select" required>
                                <option value="">-- Choose Product to Transfer --</option>
                                <?php if ($products && mysqli_num_rows($products) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($products)): ?>
                                        <option 
                                            value="<?= $row['id']; ?>"
                                            data-code="<?= htmlspecialchars($row['product_code']); ?>"
                                            data-name="<?= htmlspecialchars($row['product_name']); ?>"
                                            data-wh="<?= htmlspecialchars($row['warehouse']); ?>"
                                            data-bin="<?= htmlspecialchars($row['bin_location']); ?>"
                                            data-qty="<?= $row['available_qty']; ?>"
                                        >
                                            [<?= htmlspecialchars($row['product_code']); ?>] <?= htmlspecialchars($row['product_name']); ?> — (Loc: <?= htmlspecialchars($row['warehouse']); ?> / <?= htmlspecialchars($row['bin_location']); ?>) — Stock: <?= $row['available_qty']; ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Transfer Date -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Transfer Date *</label>
                            <input type="date" name="transfer_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <!-- Source Details (Auto-filled Readonly) -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted">From Warehouse</label>
                            <input type="text" name="from_warehouse" id="from_warehouse" class="form-control bg-light" readonly placeholder="Auto-selected">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted">From Bin Location</label>
                            <input type="text" name="from_bin" id="from_bin" class="form-control bg-light font-monospace" readonly placeholder="Auto-selected">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold text-muted">Available Stock</label>
                            <input type="number" id="available_qty" class="form-control bg-light fw-bold text-primary" readonly placeholder="0">
                        </div>

                        <hr class="my-3">

                        <!-- Destination Details -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">To Warehouse *</label>
                            <select name="to_warehouse" id="to_warehouse" class="form-select" required>
                                <option value="">-- Choose Destination Warehouse --</option>
                                <?php if ($warehouses && mysqli_num_rows($warehouses) > 0): ?>
                                    <?php while ($wh = mysqli_fetch_assoc($warehouses)): ?>
                                        <option value="<?= htmlspecialchars($wh['warehouse_name']); ?>" data-wh-id="<?= $wh['id']; ?>">
                                            <?= htmlspecialchars($wh['warehouse_name']); ?> (<?= htmlspecialchars($wh['warehouse_code']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Target Zone Filter -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Target Zone Filter</label>
                            <select id="target_zone_type" class="form-select">
                                <option value="">-- All Zones --</option>
                                <option value="Regular">📦 Regular Zone</option>
                                <option value="Toxic">⚠️ Toxic Zone</option>
                                <option value="Toys">🧸 Toys Zone</option>
                                <option value="Apparel">👕 Apparel Zone</option>
                                <option value="Festive">🎉 Festive Zone</option>
                                <option value="SPR">⚙️ SPR Zone</option>
                            </select>
                        </div>

                        <!-- Destination Bin Dropdown (Auto Loaded) -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">To Bin Location (Suggested Empty Bins) *</label>
                            <select name="to_bin" id="to_bin" class="form-select font-monospace" required>
                                <option value="">-- Select Destination Warehouse First --</option>
                            </select>
                        </div>

                        <!-- Transfer Quantity -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Transfer Quantity *</label>
                            <input type="number" name="quantity" id="quantity" class="form-control fw-bold" min="1" placeholder="Enter Qty to Move" required>
                        </div>

                        <!-- Remarks -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Remarks / Reason</label>
                            <input type="text" name="remarks" class="form-control" placeholder="Optional notes for stock movement">
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" class="btn btn-success px-4 fw-bold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Save & Transfer Stock
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function () {
    const inventorySelect = document.getElementById("inventory_id");
    const toWarehouseSelect = document.getElementById("to_warehouse");
    const targetZoneSelect = document.getElementById("target_zone_type");
    const toBinSelect = document.getElementById("to_bin");
    const quantityInput = document.getElementById("quantity");

    // 1. Auto Fill Source Details on Product Selection
    inventorySelect.addEventListener("change", function () {
        let option = this.options[this.selectedIndex];
        document.getElementById("from_warehouse").value = option.getAttribute("data-wh") || "";
        document.getElementById("from_bin").value = option.getAttribute("data-bin") || "";
        document.getElementById("available_qty").value = option.getAttribute("data-qty") || "";
    });

    // 2. Fetch Empty Bins via AJAX when Target Warehouse or Zone changes
    function fetchEmptyBins() {
        const selectedOption = toWarehouseSelect.options[toWarehouseSelect.selectedIndex];
        const warehouseId = selectedOption ? selectedOption.getAttribute("data-wh-id") : null;
        const zoneType = targetZoneSelect.value;

        if (!warehouseId) {
            toBinSelect.innerHTML = '<option value="">-- Select Destination Warehouse First --</option>';
            return;
        }

        toBinSelect.innerHTML = '<option value="">🔍 Searching Empty Bins...</option>';

        let url = `../../inventory/get_empty_bins.php?warehouse_id=${warehouseId}`;
        if (zoneType) {
            url += `&zone_type=${encodeURIComponent(zoneType)}`;
        }

        fetch(url)
            .then(response => response.json())
            .then(data => {
                toBinSelect.innerHTML = '';
                if (!data || data.length === 0) {
                    toBinSelect.innerHTML = '<option value="">❌ No Empty Bins Available in this Zone</option>';
                } else {
                    toBinSelect.innerHTML = '<option value="">-- Select Suggested Bin --</option>';
                    data.forEach(bin => {
                        let option = document.createElement("option");
                        option.value = bin.bin_code;
                        option.textContent = `${bin.bin_code} (${bin.zone_name} - ${bin.available_space} Free)`;
                        toBinSelect.appendChild(option);
                    });
                }
            })
            .catch(err => {
                console.error("Error fetching bins:", err);
                toBinSelect.innerHTML = '<option value="">Error loading bins</option>';
            });
    }

    toWarehouseSelect.addEventListener("change", fetchEmptyBins);
    targetZoneSelect.addEventListener("change", fetchEmptyBins);

    // 3. Stock Quantity Validation
    quantityInput.addEventListener("input", function () {
        let available = parseInt(document.getElementById("available_qty").value) || 0;
        let qty = parseInt(this.value) || 0;

        if (qty > available) {
            alert("⚠️ Transfer quantity cannot be greater than available stock (" + available + ").");
            this.value = "";
        }
    });
});
</script>