<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Multi-Level Project Root Detection (3 levels up: /modules/masters/products/ -> /vortex_wms/)
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. AUTO-GENERATE UNIQUE PRODUCT / SKU CODE
   ========================================================================== */

$autoQuery = @mysqli_query($conn, "SELECT id FROM products ORDER BY id DESC LIMIT 1");
$nextId = ($autoQuery && mysqli_num_rows($autoQuery) > 0) ? (mysqli_fetch_assoc($autoQuery)['id'] + 1) : 1;
$default_code = "PRD-" . str_pad($nextId, 4, "0", STR_PAD_LEFT);

/* ==========================================================================
   2. DYNAMIC COLUMN DETECTION (products table)
   ========================================================================== */

$pCols = [];
$cRes = @mysqli_query($conn, "SHOW COLUMNS FROM products");
if ($cRes) {
    while ($c = mysqli_fetch_assoc($cRes)) {
        $pCols[] = strtolower($c['Field']);
    }
}

/* ==========================================================================
   3. HANDLE PRODUCT REGISTRATION FORM SUBMISSION
   ========================================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

    $product_code  = mysqli_real_escape_string($conn, strtoupper(trim($_POST['product_code'] ?? '')));
    $product_name  = mysqli_real_escape_string($conn, trim($_POST['product_name'] ?? ''));
    $category      = mysqli_real_escape_string($conn, trim($_POST['category'] ?? 'General'));
    $brand         = mysqli_real_escape_string($conn, trim($_POST['brand'] ?? ''));
    $uom           = mysqli_real_escape_string($conn, strtoupper(trim($_POST['uom'] ?? 'PCS')));
    $mrp           = max(0.00, floatval($_POST['mrp'] ?? 0.00));
    $selling_price = max(0.00, floatval($_POST['selling_price'] ?? 0.00));
    $status        = mysqli_real_escape_string($conn, trim($_POST['status'] ?? 'Active'));

    if (empty($product_code) || empty($product_name)) {
        $_SESSION['error'] = "Product SKU Code and Product Title are mandatory.";
    } else {
        // Unique SKU Validation
        $codeCol = in_array('sku', $pCols) ? 'sku' : 'product_code';
        $check = mysqli_query($conn, "SELECT id FROM products WHERE {$codeCol} = '$product_code' LIMIT 1");

        if ($check && mysqli_num_rows($check) > 0) {
            $_SESSION['error'] = "Product SKU Code <strong>{$product_code}</strong> already exists in the catalog! Please use a unique SKU.";
        } else {
            $fields = ["`product_name`", "`status`"];
            $values = ["'$product_name'", "'$status'"];

            // Handle SKU / Product Code
            if (in_array('sku', $pCols)) {
                $fields[] = "`sku`";
                $values[] = "'$product_code'";
            }
            if (in_array('product_code', $pCols)) {
                $fields[] = "`product_code`";
                $values[] = "'$product_code'";
            }

            // Category & Brand
            if (in_array('category', $pCols)) {
                $fields[] = "`category`";
                $values[] = "'$category'";
            }
            if (in_array('brand', $pCols)) {
                $fields[] = "`brand`";
                $values[] = "'$brand'";
            }
            if (in_array('uom', $pCols)) {
                $fields[] = "`uom`";
                $values[] = "'$uom'";
            } elseif (in_array('unit', $pCols)) {
                $fields[] = "`unit`";
                $values[] = "'$uom'";
            }

            // Prices
            if (in_array('mrp', $pCols)) {
                $fields[] = "`mrp`";
                $values[] = "'$mrp'";
            }
            if (in_array('selling_price', $pCols)) {
                $fields[] = "`selling_price`";
                $values[] = "'$selling_price'";
            }
            if (in_array('unit_price', $pCols)) {
                $fields[] = "`unit_price`";
                $values[] = "'$selling_price'";
            }
            if (in_array('price', $pCols) && !in_array('unit_price', $pCols) && !in_array('selling_price', $pCols)) {
                $fields[] = "`price`";
                $values[] = "'$selling_price'";
            }

            $sql = "INSERT INTO products (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";

            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Product <strong>{$product_name}</strong> (<code>{$product_code}</code>) registered successfully.";
                header("Location: index.php");
                exit();
            } else {
                $_SESSION['error'] = "Failed to add product: " . mysqli_error($conn);
            }
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
                <i class="fa-solid fa-box-open text-primary me-2"></i>Add New Product (SKU)
            </h2>
            <p class="text-muted mb-0">Master Catalog: Register a new trade SKU, define pricing tiers, and assign classification</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Products
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Product Registration Form Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-barcode text-primary me-2"></i>Item Specifications & Pricing
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill font-monospace">PRODUCT MASTER</span>
        </div>

        <div class="card-body p-4">
            <form method="POST" id="productForm">
                <div class="row g-4">

                    <!-- SKU / Product Code -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">SKU / Product Code *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-barcode text-muted"></i></span>
                            <input type="text" name="product_code" class="form-control border-2 font-monospace fw-bold text-primary" value="<?= htmlspecialchars($_POST['product_code'] ?? $default_code); ?>" required>
                        </div>
                        <small class="text-muted">Unique tracking SKU identifier</small>
                    </div>

                    <!-- Product Name -->
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">Product Title / Name *</label>
                        <input type="text" name="product_name" class="form-control border-2 fw-semibold" placeholder="e.g. Premium Basmati Rice (5kg Bag)" value="<?= htmlspecialchars($_POST['product_name'] ?? ''); ?>" required>
                        <small class="text-muted">Full descriptive item title for invoices and packing lists</small>
                    </div>

                    <!-- Category -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Product Category</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-tags text-muted"></i></span>
                            <input type="text" name="category" class="form-control border-2" placeholder="e.g. Grocery, Electronics" value="<?= htmlspecialchars($_POST['category'] ?? ''); ?>" list="categoryList">
                        </div>
                        <datalist id="categoryList">
                            <option value="Grocery">
                            <option value="FMCG">
                            <option value="Electronics">
                            <option value="Beverages">
                            <option value="Packaged Foods">
                            <option value="Apparel">
                        </datalist>
                    </div>

                    <!-- Brand -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Brand / Manufacturer</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-copyright text-muted"></i></span>
                            <input type="text" name="brand" class="form-control border-2" placeholder="e.g. Fortune, Nestle" value="<?= htmlspecialchars($_POST['brand'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- UOM -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Unit of Measure (UOM) *</label>
                        <select name="uom" class="form-select border-2 fw-semibold" required>
                            <option value="PCS" selected>PCS (Pieces)</option>
                            <option value="BOX">BOX (Carton / Box)</option>
                            <option value="KG">KG (Kilograms)</option>
                            <option value="LTR">LTR (Liters)</option>
                            <option value="BAG">BAG (Bags / Sacks)</option>
                            <option value="SET">SET (Pack / Set)</option>
                        </select>
                    </div>

                    <!-- MRP -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Maximum Retail Price (MRP ₹)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2 fw-bold text-muted">₹</span>
                            <input type="number" step="0.01" name="mrp" class="form-control border-2 font-monospace text-end fw-semibold" value="<?= htmlspecialchars($_POST['mrp'] ?? '0.00'); ?>">
                        </div>
                        <small class="text-muted">Standard printed retail price</small>
                    </div>

                    <!-- Selling Price -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Default Selling Price (₹)</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2 fw-bold text-success">₹</span>
                            <input type="number" step="0.01" name="selling_price" class="form-control border-2 font-monospace text-end fw-bold text-success fs-5" value="<?= htmlspecialchars($_POST['selling_price'] ?? '0.00'); ?>" required>
                        </div>
                        <small class="text-muted">Commercial trading rate</small>
                    </div>

                    <!-- Status -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Catalog Status *</label>
                        <select name="status" class="form-select border-2 fw-semibold" required>
                            <option value="Active" selected>🟢 Active (Available for PO / Sales)</option>
                            <option value="Inactive">🔴 Inactive / Discontinued</option>
                        </select>
                    </div>

                </div>

                <!-- Footer Buttons -->
                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="save" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Product SKU
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>