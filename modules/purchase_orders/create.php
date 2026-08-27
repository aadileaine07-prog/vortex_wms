<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Project Root Path
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Pre-selected Supplier ID (If coming from Supplier Profile/View)
$selected_supplier_id = intval($_GET['supplier_id'] ?? 0);

// 1. Auto-generate Unique PO Number
$autoQuery = mysqli_query($conn, "SELECT id FROM purchase_orders ORDER BY id DESC LIMIT 1");
$nextId = ($autoQuery && mysqli_num_rows($autoQuery) > 0) ? (mysqli_fetch_assoc($autoQuery)['id'] + 1) : 1;
$po_number = "PO-" . date("Ymd") . "-" . str_pad($nextId, 3, "0", STR_PAD_LEFT);

// 2. Fetch Active Suppliers
$suppliers = mysqli_query($conn, "SELECT id, supplier_code, supplier_name FROM suppliers WHERE (status = 'Active' OR status = '1' OR status IS NULL) ORDER BY supplier_name ASC");

// 3. Fetch Master Product Catalog (Checking `products` table first, fallback to `inventory`)
$productList = [];
$chkProd = @mysqli_query($conn, "SHOW TABLES LIKE 'products'");
if ($chkProd && mysqli_num_rows($chkProd) > 0) {
    $pQuery = mysqli_query($conn, "SELECT id, product_name, COALESCE(sku, product_code, 'SKU-00') AS product_code, COALESCE(unit_price, 0.00) AS unit_price FROM products ORDER BY product_name ASC");
    if ($pQuery) {
        while ($p = mysqli_fetch_assoc($pQuery)) {
            $productList[] = $p;
        }
    }
} else {
    $pQuery = mysqli_query($conn, "SELECT MAX(id) AS id, product_name, product_code, 0.00 AS unit_price FROM inventory GROUP BY product_name, product_code ORDER BY product_name ASC");
    if ($pQuery) {
        while ($p = mysqli_fetch_assoc($pQuery)) {
            $productList[] = $p;
        }
    }
}

// 4. Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_po'])) {

    $po_num      = mysqli_real_escape_string($conn, trim($_POST['po_number']));
    $supplier_id = intval($_POST['supplier_id'] ?? 0);
    $order_date  = !empty($_POST['order_date']) ? $_POST['order_date'] : date('Y-m-d');
    $exp_date    = !empty($_POST['expected_date']) ? $_POST['expected_date'] : date('Y-m-d', strtotime('+7 days'));
    $total_amt   = floatval($_POST['total_amount'] ?? 0);
    $user_id     = $_SESSION['employee_id'];

    if ($supplier_id <= 0) {
        $_SESSION['error'] = "Please select a valid supplier.";
    } else {
        mysqli_begin_transaction($conn);

        try {
            // Check Columns of purchase_orders table
            $poCols = [];
            $cRes = @mysqli_query($conn, "SHOW COLUMNS FROM purchase_orders");
            if ($cRes) {
                while ($c = mysqli_fetch_assoc($cRes)) { $poCols[] = strtolower($c['Field']); }
            }

            $fields = ["`po_number`", "`supplier_id`", "`order_date`", "`expected_date`", "`total_amount`", "`status`"];
            $values = ["'$po_num'", "'$supplier_id'", "'$order_date'", "'$exp_date'", "'$total_amt'", "'Pending'"];

            if (in_array('created_by', $poCols)) {
                $fields[] = "`created_by`";
                $values[] = "'$user_id'";
            }

            $sqlPO = "INSERT INTO purchase_orders (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";
            if (!mysqli_query($conn, $sqlPO)) {
                throw new Exception("Error creating PO Master: " . mysqli_error($conn));
            }

            $po_id = mysqli_insert_id($conn);

            // Insert PO Line Items
            if (isset($_POST['product_name']) && is_array($_POST['product_name'])) {
                
                $p_names  = array_values($_POST['product_name']);
                $p_codes  = isset($_POST['product_code']) ? array_values($_POST['product_code']) : [];
                $qtys     = isset($_POST['quantity']) ? array_values($_POST['quantity']) : [];
                $prices   = isset($_POST['unit_price']) ? array_values($_POST['unit_price']) : [];
                $subtots  = isset($_POST['subtotal']) ? array_values($_POST['subtotal']) : [];

                // Detect or Create Table for PO Items
                $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'purchase_order_items'");
                if (!$checkTable || mysqli_num_rows($checkTable) === 0) {
                    mysqli_query($conn, "CREATE TABLE purchase_order_items (
                        id INT AUTO_INCREMENT PRIMARY KEY,
                        po_id INT NOT NULL,
                        product_code VARCHAR(100),
                        product_name VARCHAR(255),
                        ordered_qty INT NOT NULL DEFAULT 1,
                        received_qty INT DEFAULT 0,
                        unit_price DECIMAL(10,2) DEFAULT 0.00,
                        subtotal DECIMAL(10,2) DEFAULT 0.00,
                        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                    )");
                }

                for ($i = 0; $i < count($p_names); $i++) {
                    $p_name = mysqli_real_escape_string($conn, trim($p_names[$i]));
                    $p_code = mysqli_real_escape_string($conn, trim($p_codes[$i] ?? 'SKU-00'));
                    $qty    = max(1, intval($qtys[$i] ?? 1));
                    $price  = max(0.00, floatval($prices[$i] ?? 0.00));
                    $subtot = floatval($subtots[$i] ?? ($qty * $price));

                    if (!empty($p_name)) {
                        $sqlItem = "INSERT INTO purchase_order_items 
                            (po_id, product_code, product_name, ordered_qty, unit_price, subtotal) 
                            VALUES ('$po_id', '$p_code', '$p_name', '$qty', '$price', '$subtot')";
                        
                        if (!mysqli_query($conn, $sqlItem)) {
                            throw new Exception("Error inserting item at row " . ($i + 1) . ": " . mysqli_error($conn));
                        }
                    }
                }
            }

            mysqli_commit($conn);
            $_SESSION['success'] = "Purchase Order <strong>{$po_num}</strong> generated successfully!";
            header("Location: index.php");
            exit();

        } catch (\Throwable $e) {
            mysqli_rollback($conn);
            $_SESSION['error'] = "Failed to create PO: " . $e->getMessage();
        }
    }
}

// Single Unified Header Include
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-cart-plus text-primary me-2"></i>Create Purchase Order (PO)
            </h2>
            <p class="text-muted mb-0">Issue an inbound procurement request and allocate expected line items</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Orders
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Datalist for Product Auto-Suggestions -->
    <datalist id="productListOptions">
        <?php foreach ($productList as $prod): ?>
            <option value="<?= htmlspecialchars($prod['product_name']); ?>" 
                    data-code="<?= htmlspecialchars($prod['product_code']); ?>"
                    data-price="<?= htmlspecialchars($prod['unit_price']); ?>">
                SKU: <?= htmlspecialchars($prod['product_code']); ?> | Price: ₹<?= number_format((float)$prod['unit_price'], 2); ?>
            </option>
        <?php endforeach; ?>
    </datalist>

    <form method="POST" id="poCreateForm">
        
        <!-- PO Header Information Card -->
        <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                <h5 class="fw-bold mb-0 text-dark">
                    <i class="fa-solid fa-file-invoice text-primary me-2"></i>Procurement Specifications
                </h5>
                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill font-monospace">STATUS: PENDING</span>
            </div>
            
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Purchase Order # *</label>
                        <input type="text" name="po_number" class="form-control border-2 font-monospace bg-light fw-bold text-primary" value="<?= $po_number; ?>" readonly>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Authorized Supplier *</label>
                        <select name="supplier_id" class="form-select border-2 fw-semibold" required>
                            <option value="">-- Select Active Vendor --</option>
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
                        <label class="form-label small fw-bold text-muted">Order Date *</label>
                        <input type="date" name="order_date" class="form-control border-2 fw-semibold" value="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Expected Arrival Date</label>
                        <input type="date" name="expected_date" class="form-control border-2 fw-semibold" value="<?= date('Y-m-d', strtotime('+7 days')); ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Order Items Multi-Row Card -->
        <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Line Item Breakdown
                    </h5>
                    <small class="text-muted">Type product name to auto-fill SKU & unit price</small>
                </div>
                <button type="button" class="btn btn-outline-success btn-sm fw-bold rounded-pill px-3" onclick="addRow()">
                    <i class="fa-solid fa-plus me-1"></i> Add Line Row
                </button>
            </div>

            <div class="card-body p-4 pt-2">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="poItemsTable">
                        <thead class="table-light">
                            <tr>
                                <th>Item / Catalog Description</th>
                                <th width="180">Product SKU</th>
                                <th width="130" class="text-center">Order Qty</th>
                                <th width="160">Unit Price (₹)</th>
                                <th width="180" class="text-end">Subtotal (₹)</th>
                                <th width="60" class="text-center"></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <input type="text" name="product_name[]" class="form-control border-2 prod-name fw-semibold" list="productListOptions" placeholder="Type or Select Product" oninput="autoFillDetails(this)" required>
                                </td>
                                <td>
                                    <input type="text" name="product_code[]" class="form-control border-2 font-monospace prod-code text-primary" placeholder="SKU Code" required>
                                </td>
                                <td>
                                    <input type="number" name="quantity[]" class="form-control border-2 qty fw-bold text-center" min="1" value="1" oninput="calculateTotals()" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="unit_price[]" class="form-control border-2 price text-end fw-semibold" min="0" value="0.00" oninput="calculateTotals()" required>
                                </td>
                                <td>
                                    <input type="number" step="0.01" name="subtotal[]" class="form-control border-2 subtotal bg-light text-end font-monospace fw-bold text-dark" value="0.00" readonly>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-outline-danger btn-sm rounded-circle" onclick="removeRow(this)" title="Remove item"><i class="fa-solid fa-trash"></i></button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <!-- Grand Total & Summary Box -->
                <div class="row justify-content-end mt-4">
                    <div class="col-lg-4 col-md-6">
                        <div class="p-3 bg-light rounded-4 border text-end">
                            <small class="text-muted fw-bold d-block text-uppercase" style="font-size:11px;">Grand Inbound PO Valuation</small>
                            <h2 class="fw-bold text-success font-monospace mb-0 mt-1">₹ <span id="displayGrandTotal">0.00</span></h2>
                            <input type="hidden" name="total_amount" id="total_amount" value="0.00">
                        </div>
                    </div>
                </div>

                <!-- Action Footers -->
                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="save_po" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-paper-plane me-1"></i> Submit Purchase Order
                    </button>
                </div>
            </div>
        </div>

    </form>

</div>

<script>
function autoFillDetails(input) {
    const row = input.closest("tr");
    const val = input.value.trim();
    const datalist = document.getElementById("productListOptions");
    const options = datalist.querySelectorAll("option");

    options.forEach(opt => {
        if (opt.value === val) {
            row.querySelector(".prod-code").value = opt.getAttribute("data-code") || "";
            const price = parseFloat(opt.getAttribute("data-price")) || 0;
            if (price > 0) {
                row.querySelector(".price").value = price.toFixed(2);
            }
            calculateTotals();
        }
    });
}

function calculateTotals() {
    let grandTotal = 0;
    const rows = document.querySelectorAll("#poItemsTable tbody tr");

    rows.forEach(row => {
        const qty = parseFloat(row.querySelector(".qty").value) || 0;
        const price = parseFloat(row.querySelector(".price").value) || 0;
        const subtotal = qty * price;

        row.querySelector(".subtotal").value = subtotal.toFixed(2);
        grandTotal += subtotal;
    });

    document.getElementById("displayGrandTotal").innerText = grandTotal.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    document.getElementById("total_amount").value = grandTotal.toFixed(2);
}

function addRow() {
    const tbody = document.querySelector("#poItemsTable tbody");
    const tr = document.createElement("tr");

    tr.innerHTML = `
        <td><input type="text" name="product_name[]" class="form-control border-2 prod-name fw-semibold" list="productListOptions" placeholder="Type or Select Product" oninput="autoFillDetails(this)" required></td>
        <td><input type="text" name="product_code[]" class="form-control border-2 font-monospace prod-code text-primary" placeholder="SKU Code" required></td>
        <td><input type="number" name="quantity[]" class="form-control border-2 qty fw-bold text-center" min="1" value="1" oninput="calculateTotals()" required></td>
        <td><input type="number" step="0.01" name="unit_price[]" class="form-control border-2 price text-end fw-semibold" min="0" value="0.00" oninput="calculateTotals()" required></td>
        <td><input type="number" step="0.01" name="subtotal[]" class="form-control border-2 subtotal bg-light text-end font-monospace fw-bold text-dark" value="0.00" readonly></td>
        <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm rounded-circle" onclick="removeRow(this)" title="Remove item"><i class="fa-solid fa-trash"></i></button></td>
    `;

    tbody.appendChild(tr);
}

function removeRow(btn) {
    const rows = document.querySelectorAll("#poItemsTable tbody tr");
    if (rows.length > 1) {
        btn.closest("tr").remove();
        calculateTotals();
    } else {
        alert("⚠️ At least one order line item is mandatory!");
    }
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>