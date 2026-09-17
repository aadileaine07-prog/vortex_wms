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

/* ==========================================================================
   1. AUTO-GENERATE UNIQUE ASN NUMBER
   ========================================================================== */
$asnTable = "asn_headers";
$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'asn_headers'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    $chk2 = @mysqli_query($conn, "SHOW TABLES LIKE 'asn'");
    if ($chk2 && mysqli_num_rows($chk2) > 0) {
        $asnTable = "asn";
    }
}

$autoQuery = mysqli_query($conn, "SELECT id FROM `{$asnTable}` ORDER BY id DESC LIMIT 1");
$nextId = ($autoQuery && mysqli_num_rows($autoQuery) > 0) ? (mysqli_fetch_assoc($autoQuery)['id'] + 1) : 1;
$asnNumber = "ASN-" . date("Ymd") . "-" . str_pad($nextId, 3, "0", STR_PAD_LEFT);

/* ==========================================================================
   2. FETCH ACTIVE SUPPLIERS & PRODUCTS
   ========================================================================== */
$suppliers = @mysqli_query($conn, "SELECT id, supplier_code, supplier_name FROM suppliers WHERE status = 'Active' OR status = '1' OR status IS NULL ORDER BY supplier_name ASC");

$productList = [];
$pRes = @mysqli_query($conn, "SELECT id, product_name, COALESCE(sku, product_code, 'PRD-00') AS product_code FROM products ORDER BY product_name ASC");
if ($pRes) {
    while ($p = mysqli_fetch_assoc($pRes)) {
        $productList[] = $p;
    }
}

/* ==========================================================================
   3. HANDLE FORM SUBMISSION
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_asn'])) {
    $asn_num       = mysqli_real_escape_string($conn, trim($_POST['asn_number']));
    $supplier_name = mysqli_real_escape_string($conn, trim($_POST['supplier_name']));
    $expected_date = !empty($_POST['expected_date']) ? $_POST['expected_date'] : date('Y-m-d');
    $item_code     = mysqli_real_escape_string($conn, trim($_POST['item_code']));
    $expected_qty  = max(1, intval($_POST['expected_qty'] ?? 1));

    if (empty($supplier_name) || empty($item_code)) {
        $_SESSION['error'] = "Please provide both supplier name and item code.";
    } else {
        $sql = "INSERT INTO `{$asnTable}` (asn_number, supplier_name, expected_date, item_code, expected_qty, status) 
                VALUES ('$asn_num', '$supplier_name', '$expected_date', '$item_code', '$expected_qty', 'Pending')";
        
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Advance Shipping Notice <strong>{$asn_num}</strong> logged successfully!";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to create ASN: " . mysqli_error($conn);
        }
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-file-invoice text-primary me-2"></i>Create Advance Shipping Notice (ASN)
            </h2>
            <p class="text-muted mb-0">Record incoming shipment details and expected manifest quantities</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to ASNs
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-9 col-lg-11 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-truck-ramp-box text-primary me-2"></i>Shipment Manifest Form
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill font-monospace">
                STATUS: PENDING
            </span>
        </div>

        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-3">
                    
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">ASN Number *</label>
                        <input type="text" name="asn_number" class="form-control border-2 font-monospace bg-light fw-bold text-primary" value="<?= $asnNumber; ?>" readonly required>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted">Supplier Name *</label>
                        <input type="text" name="supplier_name" class="form-control border-2 fw-semibold" placeholder="e.g. Acme Corporation" list="supplierOptions" required>
                        <datalist id="supplierOptions">
                            <?php if ($suppliers && mysqli_num_rows($suppliers) > 0): ?>
                                <?php while ($s = mysqli_fetch_assoc($suppliers)): ?>
                                    <option value="<?= htmlspecialchars($s['supplier_name']); ?>">
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </datalist>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Expected Arrival Date *</label>
                        <input type="date" name="expected_date" class="form-control border-2 fw-semibold" value="<?= date('Y-m-d'); ?>" required>
                    </div>

                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">Item / SKU Code *</label>
                        <input type="text" name="item_code" class="form-control border-2 font-monospace" placeholder="e.g. SKU-1001 or Product Name" list="productOptions" required>
                        <datalist id="productOptions">
                            <?php foreach ($productList as $prod): ?>
                                <option value="<?= htmlspecialchars($prod['product_code']); ?>">
                                    <?= htmlspecialchars($prod['product_name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </datalist>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Expected Quantity *</label>
                        <div class="input-group">
                            <input type="number" name="expected_qty" class="form-control border-2 fw-bold text-center" min="1" value="100" required>
                            <span class="input-group-text bg-light border-2">Units</span>
                        </div>
                    </div>

                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="save_asn" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save & Log ASN
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>