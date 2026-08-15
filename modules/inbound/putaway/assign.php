<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id'])) {
    die("GRN ID Missing");
}

$grn_id = intval($_GET['id']);

// Fetch GRN Details
$grn = mysqli_query($conn, "SELECT * FROM grn WHERE id='$grn_id'");

if (mysqli_num_rows($grn) == 0) {
    die("GRN Not Found");
}

$grn = mysqli_fetch_assoc($grn);

// Fetch GRN Items
$items = mysqli_query($conn, "
    SELECT 
        g.*,
        a.product_name,
        a.product_code
    FROM grn_items g
    LEFT JOIN asn_items a ON g.asn_item_id = a.id
    WHERE g.grn_id='$grn_id'
    ORDER BY g.id ASC
");

// Fetch Active Warehouses for Dropdown
$warehouses = mysqli_query($conn, "SELECT id, warehouse_name, warehouse_code FROM warehouse WHERE status='Active' ORDER BY warehouse_name ASC");
$wh_list = [];
if ($warehouses && mysqli_num_rows($warehouses) > 0) {
    while ($w = mysqli_fetch_assoc($warehouses)) {
        $wh_list[] = $w;
    }
}

// Complete Putaway Form Submission
if (isset($_POST['save'])) {

    foreach ($_POST['warehouse'] as $item_id => $warehouse) {

        $warehouse = mysqli_real_escape_string($conn, $warehouse);
        $bin       = mysqli_real_escape_string($conn, $_POST['bin'][$item_id]);

        // Update GRN Items table
        mysqli_query($conn, "
            UPDATE grn_items
            SET warehouse='$warehouse', bin_location='$bin'
            WHERE id='$item_id'
        ");

        /* Get Product Details */
        $item = mysqli_fetch_assoc(mysqli_query($conn, "
            SELECT 
                g.accepted_qty,
                a.product_id,
                a.product_code,
                a.product_name
            FROM grn_items g
            LEFT JOIN asn_items a ON a.id = g.asn_item_id
            WHERE g.id='$item_id'
        "));

        $product_id   = $item['product_id'];
        $product_code = $item['product_code'];
        $product_name = $item['product_name'];
        $qty          = $item['accepted_qty'];

        /* Check Existing Inventory */
        $check = mysqli_query($conn, "
            SELECT * FROM inventory
            WHERE product_id='$product_id'
              AND warehouse='$warehouse'
              AND bin_location='$bin'
        ");

        if (mysqli_num_rows($check) > 0) {

            $inv = mysqli_fetch_assoc($check);
            $newQty = $inv['available_qty'] + $qty;
            $status = "In Stock";

            if ($newQty <= 0) {
                $status = "Out of Stock";
            } elseif ($newQty <= 10) {
                $status = "Low Stock";
            }

            mysqli_query($conn, "
                UPDATE inventory
                SET available_qty='$newQty', status='$status'
                WHERE id='" . $inv['id'] . "'
            ");

        } else {

            $status = "In Stock";

            if ($qty <= 0) {
                $status = "Out of Stock";
            } elseif ($qty <= 10) {
                $status = "Low Stock";
            }

            mysqli_query($conn, "
                INSERT INTO inventory (product_id, product_code, product_name, warehouse, bin_location, available_qty, reserved_qty, status)
                VALUES ('$product_id', '$product_code', '$product_name', '$warehouse', '$bin', '$qty', '0', '$status')
            ");

        }
    }

    // Update GRN status
    mysqli_query($conn, "UPDATE grn SET status='Putaway Completed' WHERE id='$grn_id'");

    header("Location: index.php");
    exit();
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-boxes-packing text-primary me-2"></i>Assign Warehouse & Bin (Putaway)</h2>
                <p class="text-muted mb-0">Select target warehouse location & empty bins for received goods</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <!-- Putaway Form -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-success text-white p-3 rounded-top-4">
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-location-dot me-2"></i>Location Allocation Form</h5>
            </div>

            <div class="card-body p-4">
                <form method="POST">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-4">
                            <thead class="table-dark">
                                <tr>
                                    <th>Product Details</th>
                                    <th width="140">Accepted Qty</th>
                                    <th width="300">Select Warehouse</th>
                                    <th width="320">Select Bin Location</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($items && mysqli_num_rows($items) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($items)): ?>
                                        <tr>
                                            <td>
                                                <strong><?= htmlspecialchars($row['product_name']); ?></strong><br>
                                                <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['product_code']); ?></span>
                                            </td>
                                            <td>
                                                <span class="badge bg-primary fs-6 px-3 py-2"><?= $row['accepted_qty']; ?> Units</span>
                                            </td>
                                            <td>
                                                <!-- Warehouse Selection Dropdown -->
                                                <select 
                                                    name="warehouse[<?= $row['id']; ?>]" 
                                                    class="form-select warehouse-select" 
                                                    data-item-id="<?= $row['id']; ?>" 
                                                    required>
                                                    <option value="">-- Choose Warehouse --</option>
                                                    <?php foreach ($wh_list as $wh): ?>
                                                        <option value="<?= htmlspecialchars($wh['warehouse_name']); ?>" data-wh-id="<?= $wh['id']; ?>">
                                                            <?= htmlspecialchars($wh['warehouse_name']); ?> (<?= htmlspecialchars($wh['warehouse_code']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <!-- Auto Suggested Empty Bin Location Dropdown -->
                                                <select 
                                                    name="bin[<?= $row['id']; ?>]" 
                                                    id="bin_select_<?= $row['id']; ?>" 
                                                    class="form-select font-monospace" 
                                                    required>
                                                    <option value="">-- Select Warehouse First --</option>
                                                </select>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="4" class="text-center py-4 text-muted">No items found in this GRN</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-3">
                        <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Back to GRNs</a>
                        <button type="submit" name="save" class="btn btn-success px-4 fw-bold">
                            <i class="fa-solid fa-check-double me-1"></i> Complete Putaway Process
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <!-- Putaway Summary Footer Card -->
        <div class="card shadow-sm border-0 rounded-4 col-lg-8 mx-auto">
            <div class="card-header bg-primary text-white p-3 rounded-top-4">
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-file-invoice me-2"></i>GRN Reference Summary</h5>
            </div>
            <div class="card-body p-4">
                <table class="table table-bordered align-middle mb-0">
                    <tr>
                        <th width="30%" class="bg-light fw-semibold">GRN No</th>
                        <td><strong>#<?= htmlspecialchars($grn['grn_number'] ?? $grn['id']); ?></strong></td>
                    </tr>
                    <tr>
                        <th class="bg-light fw-semibold">Received Date</th>
                        <td><?= date("d M Y", strtotime($grn['received_date'])); ?></td>
                    </tr>
                    <tr>
                        <th class="bg-light fw-semibold">Current Status</th>
                        <td><span class="badge bg-warning text-dark px-3 py-2 fs-6"><?= htmlspecialchars($grn['status']); ?></span></td>
                    </tr>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<!-- AJAX Script to dynamically load Empty Bins on Warehouse Select -->
<script>
document.addEventListener("DOMContentLoaded", function () {
    const warehouseSelects = document.querySelectorAll(".warehouse-select");

    warehouseSelects.forEach(select => {
        select.addEventListener("change", function () {
            const itemId = this.getAttribute("data-item-id");
            const binSelect = document.getElementById("bin_select_" + itemId);
            const selectedOption = this.options[this.selectedIndex];
            const warehouseId = selectedOption.getAttribute("data-wh-id");

            if (!warehouseId) {
                binSelect.innerHTML = '<option value="">-- Select Warehouse First --</option>';
                return;
            }

            binSelect.innerHTML = '<option value="">🔍 Searching Empty Bins...</option>';

            // Fetch empty bins via AJAX
            fetch(`../../inventory/get_empty_bins.php?warehouse_id=${warehouseId}`)
                .then(response => response.json())
                .then(data => {
                    binSelect.innerHTML = '';
                    if (!data || data.length === 0) {
                        binSelect.innerHTML = '<option value="">❌ No Empty Bins Available</option>';
                    } else {
                        binSelect.innerHTML = '<option value="">-- Select Suggested Bin --</option>';
                        data.forEach(bin => {
                            let option = document.createElement("option");
                            option.value = bin.bin_code;
                            option.textContent = `${bin.bin_code} (${bin.zone_name} - ${bin.available_space} Free)`;
                            binSelect.appendChild(option);
                        });
                    }
                })
                .catch(err => {
                    console.error("Error fetching bins:", err);
                    binSelect.innerHTML = '<option value="">Error loading bins</option>';
                });
        });
    });
});
</script>