<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$selected_po_id = isset($_GET['po_id']) ? trim($_GET['po_id']) : '';

/* ==========================================================================
   1. DIRECT PO FETCH (Zero Conditions, Zero-Fail)
   ========================================================================== */

$allPOs = [];
$poQuery = mysqli_query($conn, "SELECT * FROM `purchase_orders` ORDER BY id DESC");

if ($poQuery && mysqli_num_rows($poQuery) > 0) {
    while ($poRow = mysqli_fetch_assoc($poQuery)) {
        $pId = (int)$poRow['id'];
        
        // Supplier details fallback
        $supName = $poRow['supplier_name'] ?? '';
        if (empty($supName) && !empty($poRow['supplier_id'])) {
            $sRes = @mysqli_query($conn, "SELECT supplier_name FROM suppliers WHERE id = '{$poRow['supplier_id']}' LIMIT 1");
            if ($sRes && $sRow = mysqli_fetch_assoc($sRes)) {
                $supName = $sRow['supplier_name'];
            }
        }
        if (empty($supName)) $supName = 'DailyNeeds Wholesale';

        // Item details fallback
        $prodName = $poRow['product_name'] ?? '';
        $prodCode = $poRow['product_code'] ?? '';
        $prodId   = (int)($poRow['product_id'] ?? 0);
        $qty      = (int)($poRow['total_qty'] ?? ($poRow['quantity'] ?? 100));

        $itemRes = @mysqli_query($conn, "SELECT * FROM purchase_order_items WHERE po_id = '$pId' LIMIT 1");
        if ($itemRes && $it = mysqli_fetch_assoc($itemRes)) {
            $prodName = $it['product_name'] ?? $prodName;
            $prodCode = $it['product_code'] ?? $prodCode;
            $prodId   = (int)($it['product_id'] ?? $prodId);
            $qty      = (int)($it['ordered_qty'] ?? ($it['quantity'] ?? $qty));
        }

        $allPOs[] = [
            'id'            => $pId,
            'po_number'     => $poRow['po_number'] ?? ('PO-2026-' . $pId),
            'supplier_name' => $supName,
            'product_name'  => !empty($prodName) ? $prodName : 'Catalog Stock Item',
            'product_code'  => !empty($prodCode) ? $prodCode : 'PRD-1001',
            'product_id'    => $prodId,
            'quantity'      => $qty > 0 ? $qty : 100
        ];
    }
}

// Master Products List
$prodRes = mysqli_query($conn, "SELECT id, product_code, product_name, uom FROM products ORDER BY id ASC");

// Master Warehouses List
$whTable = "warehouses";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $whTable = "warehouse";
}
$whRes = mysqli_query($conn, "SELECT id, COALESCE(warehouse_code, CONCAT('WH-0', id)) AS wh_code, COALESCE(warehouse_name, name, 'Main Warehouse') AS wh_name, COALESCE(city, 'Surat') as city FROM `{$whTable}` WHERE status = 'Active' OR status = '1' ORDER BY id ASC");

/* ==========================================================================
   2. HANDLE GRN SUBMISSION
   ========================================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grn'])) {
    $grn_no        = "GRN-" . date('Ymd') . "-" . rand(1000, 9999);
    $po_number     = mysqli_real_escape_string($conn, trim($_POST['po_number'] ?? ''));
    $supplier_name = mysqli_real_escape_string($conn, trim($_POST['supplier_name'] ?? 'General Supplier'));
    $product_id    = intval($_POST['product_id'] ?? 0);
    $received_qty  = max(1, intval($_POST['received_qty'] ?? 1));
    $warehouse_id  = intval($_POST['warehouse_id'] ?? 1);
    $batch_no      = mysqli_real_escape_string($conn, trim($_POST['batch_no'] ?? 'BAT-' . date('Ymd')));
    $expiry_date   = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;
    $received_date = !empty($_POST['received_date']) ? $_POST['received_date'] : date('Y-m-d');

    // Fetch Warehouse Name
    $whName = 'Surat Central Logistics Park';
    $whQuery = mysqli_query($conn, "SELECT COALESCE(warehouse_name, name) as wh_name FROM `{$whTable}` WHERE id = '$warehouse_id' LIMIT 1");
    if ($whQuery && $wRow = mysqli_fetch_assoc($whQuery)) {
        $whName = $wRow['wh_name'];
    }
    $whNameEscaped = mysqli_real_escape_string($conn, $whName);

    // Fetch Product Info
    $pQuery = mysqli_query($conn, "SELECT product_code, product_name FROM products WHERE id = '$product_id' LIMIT 1");
    if ($pQuery && mysqli_num_rows($pQuery) > 0) {
        $pData = mysqli_fetch_assoc($pQuery);
        $pCode = mysqli_real_escape_string($conn, $pData['product_code']);
        $pName = mysqli_real_escape_string($conn, $pData['product_name']);
    } else {
        $pCode = 'PRD-' . $product_id;
        $pName = 'Inbound Stock Item';
    }

    $expVal = $expiry_date ? "'$expiry_date'" : "NULL";

    $insertSql = "
        INSERT INTO inbound_shipments 
        (grn_no, po_number, supplier_name, product_id, product_code, product_name, received_qty, warehouse_id, warehouse, bin_location, batch_no, expiry_date, qc_status, putaway_status, received_date)
        VALUES 
        ('$grn_no', '$po_number', '$supplier_name', '$product_id', '$pCode', '$pName', '$received_qty', '$warehouse_id', '$whNameEscaped', 'DOCK-INWARD', '$batch_no', $expVal, 'Pending', 'Pending', '$received_date')
    ";

    if (mysqli_query($conn, $insertSql)) {
        if (!empty($po_number)) {
            mysqli_query($conn, "UPDATE purchase_orders SET status = 'Received' WHERE po_number = '$po_number'");
        }
        $_SESSION['success'] = "GRN <strong>{$grn_no}</strong> created successfully! Ready for QC.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "GRN save failed: " . mysqli_error($conn);
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-truck-ramp-box text-primary me-2"></i>Create Inward Entry (GRN)
            </h2>
            <p class="text-muted mb-0">Record incoming cargo delivery & verify against Purchase Orders</p>
        </div>
        <a href="index.php" class="btn btn-secondary fw-semibold rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Inbound
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-file-invoice text-primary me-2"></i>Inbound Goods Receipt Intake
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill font-monospace">BAY DOCK RECEIVING</span>
        </div>

        <div class="card-body p-4">
            <form method="POST" id="inwardForm">
                
                <!-- Quick PO Selector Bar -->
                <div class="p-3 bg-light rounded-4 border mb-4">
                    <label class="form-label small fw-bold text-primary text-uppercase mb-1">
                        <i class="fa-solid fa-bolt me-1"></i> Select Purchase Order (Auto-Fill Form)
                    </label>
                    <select id="poQuickSelect" class="form-select border-2 fw-semibold fs-6" onchange="autoFillFromPO()">
                        <option value="">-- Choose Purchase Order (<?= count($allPOs); ?> Available in System) --</option>
                        <?php foreach ($allPOs as $po): ?>
                            <option 
                                value="<?= $po['id']; ?>"
                                data-po="<?= htmlspecialchars($po['po_number']); ?>"
                                data-supplier="<?= htmlspecialchars($po['supplier_name']); ?>"
                                data-prodid="<?= $po['product_id']; ?>"
                                data-prodcode="<?= htmlspecialchars($po['product_code']); ?>"
                                data-qty="<?= $po['quantity']; ?>"
                                <?= ($po['id'] == $selected_po_id || $po['po_number'] == $selected_po_id) ? 'selected' : ''; ?>
                            >
                                🎯 <?= htmlspecialchars($po['po_number']); ?> &bull; <?= htmlspecialchars($po['supplier_name']); ?> &bull; [<?= htmlspecialchars($po['product_name']); ?> &bull; Qty: <?= $po['quantity']; ?>]
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-4">

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Supplier / Vendor Name *</label>
                        <input type="text" name="supplier_name" id="supplierInput" class="form-control border-2 fw-semibold" placeholder="e.g. DailyNeeds Wholesale" required>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Purchase Order (PO) Number</label>
                        <input type="text" name="po_number" id="poInput" class="form-control border-2 font-monospace fw-bold text-primary" placeholder="e.g. PO-20260827-001">
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Receiving Warehouse Hub *</label>
                        <select name="warehouse_id" id="warehouseSelect" class="form-select border-2 fw-semibold" required>
                            <?php if ($whRes && mysqli_num_rows($whRes) > 0): ?>
                                <?php while ($w = mysqli_fetch_assoc($whRes)): ?>
                                    <option value="<?= $w['id']; ?>">
                                        <?= htmlspecialchars($w['wh_name']); ?> (<?= htmlspecialchars($w['wh_code']); ?> - <?= htmlspecialchars($w['city']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <option value="1">Surat Central Logistics Park (WH-01)</option>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Select Product SKU *</label>
                        <select name="product_id" id="productSelect" class="form-select border-2 fw-semibold" required>
                            <option value="">-- Choose Catalog SKU --</option>
                            <?php if ($prodRes && mysqli_num_rows($prodRes) > 0): ?>
                                <?php while ($p = mysqli_fetch_assoc($prodRes)): ?>
                                    <option value="<?= $p['id']; ?>" data-code="<?= htmlspecialchars($p['product_code']); ?>">
                                        <?= htmlspecialchars($p['product_name']); ?> (<?= htmlspecialchars($p['product_code']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            <?php endif; ?>
                        </select>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Received Units *</label>
                        <input type="number" name="received_qty" id="qtyInput" class="form-control border-2 font-monospace fw-bold text-center fs-5 text-dark" value="100" min="1" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Batch Tracking #</label>
                        <input type="text" name="batch_no" class="form-control border-2 font-monospace text-uppercase" value="BAT-2026-<?= rand(100, 999); ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control border-2" value="<?= date('Y-m-d', strtotime('+180 days')); ?>">
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Dock Receipt Date *</label>
                        <input type="date" name="received_date" class="form-control border-2 fw-semibold" value="<?= date('Y-m-d'); ?>" required>
                    </div>

                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="save_grn" class="btn btn-success px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Inward GRN
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<script>
function autoFillFromPO() {
    const sel = document.getElementById('poQuickSelect');
    if (!sel || sel.selectedIndex <= 0) return;

    const opt = sel.options[sel.selectedIndex];
    const poNum = opt.getAttribute('data-po') || '';
    const supplier = opt.getAttribute('data-supplier') || '';
    const qty = opt.getAttribute('data-qty') || '100';
    const prodId = opt.getAttribute('data-prodid');
    const prodCode = opt.getAttribute('data-prodcode') || '';

    document.getElementById('poInput').value = poNum;
    document.getElementById('supplierInput').value = supplier;
    document.getElementById('qtyInput').value = qty;

    const prodSel = document.getElementById('productSelect');
    if (prodSel) {
        let matched = false;
        if (prodId && parseInt(prodId) > 0) {
            for (let i = 0; i < prodSel.options.length; i++) {
                if (prodSel.options[i].value == prodId) {
                    prodSel.selectedIndex = i;
                    matched = true;
                    break;
                }
            }
        }
        if (!matched && prodCode) {
            for (let i = 0; i < prodSel.options.length; i++) {
                if (prodSel.options[i].getAttribute('data-code') === prodCode) {
                    prodSel.selectedIndex = i;
                    matched = true;
                    break;
                }
            }
        }
    }
}

document.addEventListener("DOMContentLoaded", function() {
    autoFillFromPO();
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>