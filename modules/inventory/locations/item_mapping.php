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
   1. HANDLE ITEM MAPPING FORM SUBMISSION
   ========================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_mapping'])) {
    $product_id   = intval($_POST['product_id'] ?? 0);
    $warehouse    = mysqli_real_escape_string($conn, trim($_POST['warehouse'] ?? ''));
    $bin_location = mysqli_real_escape_string($conn, strtoupper(trim($_POST['bin_location'] ?? '')));
    $quantity     = max(0, intval($_POST['quantity'] ?? 0));
    $batch_no     = mysqli_real_escape_string($conn, trim($_POST['batch_no'] ?? 'BAT-' . date('Ymd')));

    if ($product_id <= 0 || empty($warehouse) || empty($bin_location)) {
        $_SESSION['error'] = "Please select a valid product, warehouse, and bin location.";
    } else {
        // Fetch Product Details
        $pRes = mysqli_query($conn, "SELECT product_code, product_name FROM products WHERE id = '$product_id' LIMIT 1");
        $pData = ($pRes && mysqli_num_rows($pRes) > 0) ? mysqli_fetch_assoc($pRes) : [];
        $pCode = mysqli_real_escape_string($conn, $pData['product_code'] ?? 'PRD-' . $product_id);
        $pName = mysqli_real_escape_string($conn, $pData['product_name'] ?? 'Mapped Stock Item');

        // Check if mapping already exists for this product in this bin
        $chkMap = mysqli_query($conn, "SELECT id, available_qty FROM inventory WHERE product_id = '$product_id' AND warehouse = '$warehouse' AND bin_location = '$bin_location' LIMIT 1");

        if ($chkMap && mysqli_num_rows($chkMap) > 0) {
            $existing = mysqli_fetch_assoc($chkMap);
            $newQty = (int)$existing['available_qty'] + $quantity;
            mysqli_query($conn, "UPDATE inventory SET available_qty = '$newQty', status = 'In Stock' WHERE id = '{$existing['id']}'");
            $_SESSION['success'] = "Updated existing inventory mapping for <strong>{$pName}</strong> at [{$bin_location}].";
        } else {
            mysqli_query($conn, "
                INSERT INTO inventory (product_id, product_code, product_name, warehouse, bin_location, batch_no, available_qty, status)
                VALUES ('$product_id', '$pCode', '$pName', '$warehouse', '$bin_location', '$batch_no', '$quantity', 'In Stock')
            ");
            $_SESSION['success'] = "Successfully mapped <strong>{$pName}</strong> to Bin <strong>[{$bin_location}]</strong> ({$warehouse}).";
        }
        header("Location: item_mapping.php");
        exit();
    }
}

/* ==========================================================================
   2. FETCH MASTER DATA FOR DROPDOWNS FROM DATABASE
   ========================================================================== */
$products = [];
$prodRes = mysqli_query($conn, "SELECT id, product_code, product_name FROM products ORDER BY product_name ASC");
if ($prodRes) {
    while ($p = mysqli_fetch_assoc($prodRes)) {
        $products[] = $p;
    }
}

// Fetch Active Warehouses directly from 'warehouse' table
$warehouses = [];
$whQuery = @mysqli_query($conn, "SELECT warehouse_name FROM warehouse WHERE status = 'Active' ORDER BY id ASC");
if ($whQuery && mysqli_num_rows($whQuery) > 0) {
    while ($w = mysqli_fetch_assoc($whQuery)) {
        if (!empty($w['warehouse_name'])) {
            $warehouses[] = $w['warehouse_name'];
        }
    }
}

$bins = [];
$binRes = mysqli_query($conn, "SELECT bin_code FROM bin_locations ORDER BY bin_code ASC");
if ($binRes && mysqli_num_rows($binRes) > 0) {
    while ($b = mysqli_fetch_assoc($binRes)) {
        $bins[] = $b['bin_code'];
    }
}

// Fetch Existing Mappings
$mappings = [];
$mapRes = mysqli_query($conn, "SELECT * FROM inventory WHERE available_qty > 0 ORDER BY id DESC LIMIT 50");
if ($mapRes) {
    while ($m = mysqli_fetch_assoc($mapRes)) {
        $mappings[] = $m;
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-map-location-dot text-primary me-2"></i>Item & Bin Location Mapping
            </h2>
            <p class="text-muted mb-0">Assign catalog products directly to warehouse storage coordinates</p>
        </div>
        <a href="../index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Mapping Form Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-link text-primary me-2"></i>New Product-Bin Assignment</h5>
        </div>
        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-3">
                    
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Select Product / SKU *</label>
                        <select name="product_id" class="form-select border-2 fw-semibold" required>
                            <option value="">-- Choose Catalog Product --</option>
                            <?php foreach ($products as $prod): ?>
                                <option value="<?= $prod['id']; ?>">
                                    <?= htmlspecialchars($prod['product_name']); ?> (<?= htmlspecialchars($prod['product_code']); ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Warehouse Hub *</label>
                        <select name="warehouse" class="form-select border-2 fw-semibold" required>
                            <?php if (!empty($warehouses)): ?>
                                <option value="">-- Choose Active Warehouse --</option>
                                <?php foreach ($warehouses as $wh): ?>
                                    <option value="<?= htmlspecialchars($wh); ?>"><?= htmlspecialchars($wh); ?></option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled selected>NO ACTIVE WAREHOUSES</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted">Bin Location Coordinate *</label>
                        <select name="bin_location" class="form-select border-2 font-monospace fw-bold text-primary" required>
                            <option value="">-- Choose Storage Bin Coordinate --</option>
                            <?php foreach ($bins as $binCode): ?>
                                <option value="<?= htmlspecialchars($binCode); ?>"><?= htmlspecialchars($binCode); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Initial Quantity *</label>
                        <input type="number" name="quantity" class="form-control border-2 font-monospace fw-bold text-center" min="1" value="100" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Batch Number</label>
                        <input type="text" name="batch_no" class="form-control border-2 font-monospace text-uppercase" value="BAT-2026-<?= rand(100, 999); ?>" required>
                    </div>

                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                    <a href="../index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="save_mapping" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Item Mapping
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Active Mappings Table -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-check text-primary me-2"></i>Recently Mapped Inventory Locations</h5>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Product Name</th>
                            <th>SKU Code</th>
                            <th>Warehouse</th>
                            <th class="text-center">Bin Location</th>
                            <th class="text-center">Available Qty</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($mappings)): ?>
                            <?php foreach ($mappings as $m): ?>
                                <tr>
                                    <td><strong class="text-dark"><?= htmlspecialchars($m['product_name']); ?></strong></td>
                                    <td><code class="text-primary font-monospace"><?= htmlspecialchars($m['product_code']); ?></code></td>
                                    <td><small class="text-muted"><?= htmlspecialchars($m['warehouse']); ?></small></td>
                                    <td class="text-center"><span class="badge bg-primary-subtle text-primary border font-monospace"><?= htmlspecialchars($m['bin_location']); ?></span></td>
                                    <td class="text-center font-monospace fw-bold"><?= number_format((int)$m['available_qty']); ?> Units</td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No active mappings found. Use the form above to assign items to bins.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>