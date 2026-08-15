<?php
session_start();

// Dynamic Project Root Path
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Auto-generate Unique SO Number
$autoQuery = mysqli_query($conn, "SELECT id FROM sales_orders ORDER BY id DESC LIMIT 1");
$nextId = ($autoQuery && mysqli_num_rows($autoQuery) > 0) ? (mysqli_fetch_assoc($autoQuery)['id'] + 1) : 1;
$so_number = "SO-" . date("Ymd") . "-" . str_pad($nextId, 3, "0", STR_PAD_LEFT);

// Fetch Available Inventory Products for Selection (ONLY_FULL_GROUP_BY compatible)
$inventoryQuery = mysqli_query($conn, "
    SELECT product_code, product_name, warehouse, bin_location, SUM(available_qty) as stock 
    FROM inventory 
    GROUP BY product_code, product_name, warehouse, bin_location 
    HAVING stock > 0
");
$inventoryList = [];
if ($inventoryQuery && mysqli_num_rows($inventoryQuery) > 0) {
    while ($inv = mysqli_fetch_assoc($inventoryQuery)) {
        $inventoryList[] = $inv;
    }
}

// Handle Sales Order Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_so'])) {

    $so_num     = mysqli_real_escape_string($conn, trim($_POST['so_number']));
    $cust_name  = mysqli_real_escape_string($conn, trim($_POST['customer_name']));
    $cust_phone = mysqli_real_escape_string($conn, trim($_POST['customer_phone'] ?? ''));
    $cust_email = mysqli_real_escape_string($conn, trim($_POST['customer_email'] ?? ''));
    $order_date = mysqli_real_escape_string($conn, $_POST['order_date']);
    $address    = mysqli_real_escape_string($conn, trim($_POST['shipping_address'] ?? ''));

    if (empty($cust_name)) {
        $_SESSION['error'] = "Customer Name is required.";
    } else {
        $conn->begin_transaction();

        try {
            // Check available columns in sales_orders to prevent schema mismatch
            $checkCols = mysqli_query($conn, "SHOW COLUMNS FROM sales_orders");
            $colNames = [];
            while ($c = mysqli_fetch_assoc($checkCols)) {
                $colNames[] = $c['Field'];
            }

            // Build Dynamic INSERT query for sales_orders
            $fields = ['customer_name', 'customer_phone', 'customer_email', 'order_date', 'shipping_address', 'status'];
            $values = ["'$cust_name'", "'$cust_phone'", "'$cust_email'", "'$order_date'", "'$address'", "'Pending'"];

            if (in_array('so_number', $colNames)) {
                $fields[] = 'so_number';
                $values[] = "'$so_num'";
            }
            if (in_array('order_number', $colNames)) {
                $fields[] = 'order_number';
                $values[] = "'$so_num'";
            }

            $sqlSO = "INSERT INTO sales_orders (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $values) . ")";
            
            if (!mysqli_query($conn, $sqlSO)) {
                throw new Exception("Error inserting Sales Order Master: " . mysqli_error($conn));
            }

            $so_id = mysqli_insert_id($conn);

            // 2. Insert Line Items with Array Re-indexing (Fixes First Item Missing Issue)
            if (isset($_POST['product_code']) && is_array($_POST['product_code'])) {

                $p_codes    = array_values($_POST['product_code']);
                $p_names    = isset($_POST['product_name']) ? array_values($_POST['product_name']) : [];
                $warehouses = isset($_POST['warehouse']) ? array_values($_POST['warehouse']) : [];
                $bins       = isset($_POST['bin_location']) ? array_values($_POST['bin_location']) : [];
                $qtys       = isset($_POST['quantity']) ? array_values($_POST['quantity']) : [];

                // Check FK Column Name in sales_order_items (`sales_order_id` or `so_id`)
                $checkItemCol = mysqli_query($conn, "SHOW COLUMNS FROM sales_order_items LIKE 'sales_order_id'");
                $fk_col = ($checkItemCol && mysqli_num_rows($checkItemCol) > 0) ? 'sales_order_id' : 'so_id';

                for ($i = 0; $i < count($p_codes); $i++) {
                    $p_code = mysqli_real_escape_string($conn, trim($p_codes[$i]));
                    $p_name = mysqli_real_escape_string($conn, trim($p_names[$i] ?? ''));
                    $wh     = mysqli_real_escape_string($conn, trim($warehouses[$i] ?? 'Main Warehouse'));
                    $bin    = mysqli_real_escape_string($conn, trim($bins[$i] ?? 'BIN-A1'));
                    $qty    = intval($qtys[$i] ?? 0);

                    if (!empty($p_code) && $qty > 0) {
                        $sqlItem = "INSERT INTO sales_order_items 
                                    ($fk_col, product_code, product_name, warehouse, bin_location, ordered_qty) 
                                    VALUES ('$so_id', '$p_code', '$p_name', '$wh', '$bin', '$qty')";

                        if (!mysqli_query($conn, $sqlItem)) {
                            throw new Exception("Error inserting item {$p_code}: " . mysqli_error($conn));
                        }
                    }
                }
            }

            $conn->commit();
            $_SESSION['success'] = "Sales Order <strong>{$so_num}</strong> created successfully!";
            header("Location: index.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Failed to create Sales Order: " . $e->getMessage();
        }
    }
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-cart-shopping text-primary me-2"></i>Create Sales Order</h2>
                <p class="text-muted mb-0">Generate new outbound order for dispatch</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= $_SESSION['error']; unset($_SESSION['error']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <form method="POST">
            <!-- Header Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-primary text-white p-3 rounded-top-4">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-user me-2"></i>Customer & Order Details</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Order Number *</label>
                            <input type="text" name="so_number" class="form-control font-monospace bg-light" value="<?= $so_number; ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Customer Name *</label>
                            <input type="text" name="customer_name" class="form-control" placeholder="Aadil Raine" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Contact Phone</label>
                            <input type="text" name="customer_phone" class="form-control" placeholder="+91 9876543210">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Customer Email</label>
                            <input type="email" name="customer_email" class="form-control" placeholder="aadileaine07@gmail.com">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Order Date *</label>
                            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-9">
                            <label class="form-label fw-semibold">Shipping Address</label>
                            <input type="text" name="shipping_address" class="form-control" placeholder="Karodhra Varli, Hakkimi Wire">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Items Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-dark text-white p-3 rounded-top-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-boxes-stacked me-2"></i>Ordered Products</h5>
                    <button type="button" class="btn btn-success btn-sm fw-bold" onclick="addRow()"><i class="fa-solid fa-plus me-1"></i> Add Product Row</button>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="soItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Select Product (From Live Stock)</th>
                                    <th width="160">Product Code</th>
                                    <th width="180">Warehouse</th>
                                    <th width="160">Bin Location</th>
                                    <th width="120">Order Qty</th>
                                    <th width="80" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <select class="form-select prod-select" onchange="fillProductDetails(this)" required>
                                            <option value="">-- Select Available Product --</option>
                                            <?php foreach ($inventoryList as $inv): ?>
                                                <option value="<?= htmlspecialchars($inv['product_code']); ?>" 
                                                        data-name="<?= htmlspecialchars($inv['product_name']); ?>" 
                                                        data-wh="<?= htmlspecialchars($inv['warehouse']); ?>" 
                                                        data-bin="<?= htmlspecialchars($inv['bin_location']); ?>">
                                                    <?= htmlspecialchars($inv['product_name']); ?> (Code: <?= htmlspecialchars($inv['product_code']); ?> | Stock: <?= $inv['stock']; ?>)
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                        <input type="hidden" name="product_name[]" class="p-name">
                                    </td>
                                    <td>
                                        <input type="text" name="product_code[]" class="form-control font-monospace p-code bg-light" readonly required>
                                    </td>
                                    <td>
                                        <input type="text" name="warehouse[]" class="form-control p-wh bg-light" readonly required>
                                    </td>
                                    <td>
                                        <input type="text" name="bin_location[]" class="form-control p-bin bg-light font-monospace" readonly required>
                                    </td>
                                    <td>
                                        <input type="number" name="quantity[]" class="form-control text-center fw-bold" min="1" value="1" required>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-3">
                        <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" name="save_so" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-paper-plane me-1"></i> Submit Sales Order</button>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<script>
function fillProductDetails(select) {
    let row = select.closest("tr");
    let opt = select.options[select.selectedIndex];
    
    if (select.value !== "") {
        row.querySelector(".p-code").value = select.value;
        row.querySelector(".p-name").value = opt.getAttribute("data-name");
        row.querySelector(".p-wh").value   = opt.getAttribute("data-wh");
        row.querySelector(".p-bin").value  = opt.getAttribute("data-bin");
    } else {
        row.querySelector(".p-code").value = "";
        row.querySelector(".p-name").value = "";
        row.querySelector(".p-wh").value   = "";
        row.querySelector(".p-bin").value  = "";
    }
}

function addRow() {
    let tbody = document.querySelector("#soItemsTable tbody");
    let tr = document.createElement("tr");

    tr.innerHTML = `
        <td>
            <select class="form-select prod-select" onchange="fillProductDetails(this)" required>
                ${document.querySelector(".prod-select").innerHTML}
            </select>
            <input type="hidden" name="product_name[]" class="p-name">
        </td>
        <td><input type="text" name="product_code[]" class="form-control font-monospace p-code bg-light" readonly required></td>
        <td><input type="text" name="warehouse[]" class="form-control p-wh bg-light" readonly required></td>
        <td><input type="text" name="bin_location[]" class="form-control p-bin bg-light font-monospace" readonly required></td>
        <td><input type="number" name="quantity[]" class="form-control text-center fw-bold" min="1" value="1" required></td>
        <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button></td>
    `;

    tbody.appendChild(tr);
}

function removeRow(btn) {
    let rows = document.querySelectorAll("#soItemsTable tbody tr");
    if (rows.length > 1) {
        btn.closest("tr").remove();
    } else {
        alert("At least one product row is required!");
    }
}
</script>