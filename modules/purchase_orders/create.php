<?php
session_start();

// Dynamic Project Root Path
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Pre-selected Supplier ID (If coming from Supplier View page)
$selected_supplier_id = intval($_GET['supplier_id'] ?? 0);

// Auto-generate Unique PO Number
$autoQuery = mysqli_query($conn, "SELECT id FROM purchase_orders ORDER BY id DESC LIMIT 1");
$nextId = ($autoQuery && mysqli_num_rows($autoQuery) > 0) ? (mysqli_fetch_assoc($autoQuery)['id'] + 1) : 1;
$po_number = "PO-" . date("Ymd") . "-" . str_pad($nextId, 3, "0", STR_PAD_LEFT);

// Fetch Active Suppliers
$suppliers = mysqli_query($conn, "SELECT id, supplier_code, supplier_name FROM suppliers ORDER BY supplier_name ASC");

// Fetch Unique Products / Inventory Items for Auto-suggestion
$products = mysqli_query($conn, "
    SELECT MAX(id) AS id, product_code, product_name 
    FROM inventory 
    GROUP BY product_code, product_name 
    ORDER BY product_name ASC
");
$productList = [];
if ($products && mysqli_num_rows($products) > 0) {
    while ($p = mysqli_fetch_assoc($products)) {
        $productList[] = $p;
    }
}

// Handle Form Submission
if (isset($_POST['save_po'])) {

    $po_num      = mysqli_real_escape_string($conn, trim($_POST['po_number']));
    $supplier_id = intval($_POST['supplier_id']);
    $order_date  = mysqli_real_escape_string($conn, $_POST['order_date']);
    $exp_date    = mysqli_real_escape_string($conn, $_POST['expected_date']);
    $total_amt   = floatval($_POST['total_amount']);

    if ($supplier_id <= 0) {
        $_SESSION['error'] = "Please select a valid supplier.";
    } else {
        $conn->begin_transaction();

        try {
            // 1. Insert Purchase Order Master
            $sqlPO = "INSERT INTO purchase_orders (po_number, supplier_id, order_date, expected_date, total_amount, status) 
                      VALUES ('$po_num', '$supplier_id', '$order_date', '$exp_date', '$total_amt', 'Pending')";
            
            if (!mysqli_query($conn, $sqlPO)) {
                throw new Exception("Error inserting PO Master: " . mysqli_error($conn));
            }

            $po_id = mysqli_insert_id($conn);

            // 2. Insert PO Items with Clean Re-indexing (Fixes First Entry Skipping Issue)
            if (isset($_POST['product_name']) && is_array($_POST['product_name'])) {
                
                // Re-index all array keys starting strictly from Index 0
                $p_names  = array_values($_POST['product_name']);
                $p_codes  = isset($_POST['product_code']) ? array_values($_POST['product_code']) : [];
                $qtys     = isset($_POST['quantity']) ? array_values($_POST['quantity']) : [];
                $prices   = isset($_POST['unit_price']) ? array_values($_POST['unit_price']) : [];
                $subtots  = isset($_POST['subtotal']) ? array_values($_POST['subtotal']) : [];

                $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'purchase_order_items'");
                if ($checkTable && mysqli_num_rows($checkTable) > 0) {
                    
                    for ($i = 0; $i < count($p_names); $i++) {
                        $p_name = mysqli_real_escape_string($conn, trim($p_names[$i]));
                        $p_code = mysqli_real_escape_string($conn, trim($p_codes[$i] ?? ''));
                        $qty    = intval($qtys[$i] ?? 0);
                        $price  = floatval($prices[$i] ?? 0);
                        $subtot = floatval($subtots[$i] ?? 0);

                        if (!empty($p_name) && $qty > 0) {
                            $sqlItem = "INSERT INTO purchase_order_items 
                                (po_id, product_code, product_name, ordered_qty, unit_price, subtotal) 
                                VALUES ('$po_id', '$p_code', '$p_name', '$qty', '$price', '$subtot')";
                            
                            if (!mysqli_query($conn, $sqlItem)) {
                                throw new Exception("Error inserting PO item at row " . ($i + 1) . ": " . mysqli_error($conn));
                            }
                        }
                    }
                }
            }

            $conn->commit();
            $_SESSION['success'] = "Purchase Order <strong>{$po_num}</strong> created successfully!";
            header("Location: index.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Failed to create PO: " . $e->getMessage();
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
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-cart-plus text-primary me-2"></i>Create Purchase Order (PO)</h2>
                <p class="text-muted mb-0">Generate new procurement order for supplier</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Datalist for Product Auto-Suggestions -->
        <datalist id="productListOptions">
            <?php foreach ($productList as $prod): ?>
                <option value="<?= htmlspecialchars($prod['product_name']); ?>" data-code="<?= htmlspecialchars($prod['product_code']); ?>">
                    SKU: <?= htmlspecialchars($prod['product_code']); ?>
                </option>
            <?php endforeach; ?>
        </datalist>

        <form method="POST">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-primary text-white p-3 rounded-top-4">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-file-invoice me-2"></i>PO Header Details</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">PO Number *</label>
                            <input type="text" name="po_number" class="form-control font-monospace bg-light" value="<?= $po_number; ?>" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Supplier *</label>
                            <select name="supplier_id" class="form-select" required>
                                <option value="">-- Choose Active Supplier --</option>
                                <?php if ($suppliers && mysqli_num_rows($suppliers) > 0): ?>
                                    <?php while ($s = mysqli_fetch_assoc($suppliers)): ?>
                                        <option value="<?= $s['id']; ?>" <?= ($s['id'] == $selected_supplier_id) ? 'selected' : ''; ?>>
                                            <?= htmlspecialchars($s['supplier_name']); ?> (<?= htmlspecialchars($s['supplier_code']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Order Date *</label>
                            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Expected Delivery Date</label>
                            <input type="date" name="expected_date" class="form-control" value="<?= date('Y-m-d', strtotime('+7 days')); ?>">
                        </div>

                    </div>
                </div>
            </div>

            <!-- Items Table Card -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-dark text-white p-3 rounded-top-4 d-flex justify-content-between align-items-center">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-boxes-stacked me-2"></i>Order Items</h5>
                    <button type="button" class="btn btn-success btn-sm fw-bold" onclick="addRow()"><i class="fa-solid fa-plus me-1"></i> Add Item Row</button>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="poItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Product / Item Details</th>
                                    <th width="180">Product Code</th>
                                    <th width="140">Quantity</th>
                                    <th width="160">Unit Price (₹)</th>
                                    <th width="180">Subtotal (₹)</th>
                                    <th width="80" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <input type="text" name="product_name[]" class="form-control prod-name" list="productListOptions" placeholder="Type or Select Product" onchange="autoFillCode(this)" required>
                                    </td>
                                    <td>
                                        <input type="text" name="product_code[]" class="form-control font-monospace prod-code" placeholder="SKU Code" required>
                                    </td>
                                    <td>
                                        <input type="number" name="quantity[]" class="form-control qty" min="1" value="1" onchange="calculateTotals()" onkeyup="calculateTotals()" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="unit_price[]" class="form-control price" min="0" value="0.00" onchange="calculateTotals()" onkeyup="calculateTotals()" required>
                                    </td>
                                    <td>
                                        <input type="number" step="0.01" name="subtotal[]" class="form-control subtotal bg-light" value="0.00" readonly>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <!-- Grand Total Display -->
                    <div class="row justify-content-end mt-3">
                        <div class="col-md-4">
                            <div class="card bg-light border-0 p-3 rounded-3 text-end">
                                <span class="text-muted fw-bold">GRAND TOTAL AMOUNT</span>
                                <h2 class="fw-bold text-success mb-0 mt-1">₹ <span id="displayGrandTotal">0.00</span></h2>
                                <input type="hidden" name="total_amount" id="total_amount" value="0.00">
                            </div>
                        </div>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-3">
                        <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" name="save_po" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-paper-plane me-1"></i> Submit Purchase Order</button>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<script>
function autoFillCode(input) {
    let row = input.closest("tr");
    let val = input.value;
    let datalist = document.getElementById("productListOptions");
    let options = datalist.querySelectorAll("option");

    options.forEach(opt => {
        if (opt.value === val) {
            row.querySelector(".prod-code").value = opt.getAttribute("data-code");
        }
    });
}

function calculateTotals() {
    let grandTotal = 0;
    let rows = document.querySelectorAll("#poItemsTable tbody tr");

    rows.forEach(row => {
        let qty = parseFloat(row.querySelector(".qty").value) || 0;
        let price = parseFloat(row.querySelector(".price").value) || 0;
        let subtotal = qty * price;

        row.querySelector(".subtotal").value = subtotal.toFixed(2);
        grandTotal += subtotal;
    });

    document.getElementById("displayGrandTotal").innerText = grandTotal.toFixed(2);
    document.getElementById("total_amount").value = grandTotal.toFixed(2);
}

function addRow() {
    let tbody = document.querySelector("#poItemsTable tbody");
    let tr = document.createElement("tr");

    tr.innerHTML = `
        <td><input type="text" name="product_name[]" class="form-control prod-name" list="productListOptions" placeholder="Type or Select Product" onchange="autoFillCode(this)" required></td>
        <td><input type="text" name="product_code[]" class="form-control font-monospace prod-code" placeholder="SKU Code" required></td>
        <td><input type="number" name="quantity[]" class="form-control qty" min="1" value="1" onchange="calculateTotals()" onkeyup="calculateTotals()" required></td>
        <td><input type="number" step="0.01" name="unit_price[]" class="form-control price" min="0" value="0.00" onchange="calculateTotals()" onkeyup="calculateTotals()" required></td>
        <td><input type="number" step="0.01" name="subtotal[]" class="form-control subtotal bg-light" value="0.00" readonly></td>
        <td class="text-center"><button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)"><i class="fa-solid fa-trash"></i></button></td>
    `;

    tbody.appendChild(tr);
}

function removeRow(btn) {
    let rows = document.querySelectorAll("#poItemsTable tbody tr");
    if (rows.length > 1) {
        btn.closest("tr").remove();
        calculateTotals();
    } else {
        alert("At least one item row is required!");
    }
}
</script>