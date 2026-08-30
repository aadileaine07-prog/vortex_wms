<?php
// Error visibility on
error_reporting(E_ALL);
ini_set('display_errors', 1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Multi-Level Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($id)) {
    die("<div style='padding:30px; font-family:sans-serif;'><h4>⚠️ Purchase Order ID is missing in URL.</h4><a href='index.php'>&larr; Back to Purchase Orders</a></div>");
}

/* ==========================================================================
   1. BULLETPROOF PO MASTER FETCH (ID OR PO_NUMBER)
   ========================================================================== */

$safeId = mysqli_real_escape_string($conn, $id);
$poRes = mysqli_query($conn, "SELECT * FROM `purchase_orders` WHERE `id` = '$safeId' OR `po_number` = '$safeId' LIMIT 1");

if (!$poRes || mysqli_num_rows($poRes) === 0) {
    die("<div style='padding:30px; font-family:sans-serif;'><h4 style='color:red;'>⚠️ Purchase Order record <code>#" . htmlspecialchars($id) . "</code> not found in database.</h4><a href='index.php' style='display:inline-block; margin-top:10px; background:#2563eb; color:#fff; padding:8px 16px; border-radius:6px; text-decoration:none;'>&larr; Back to Purchase Orders</a></div>");
}

$po = mysqli_fetch_assoc($poRes);
$poId = (int)$po['id'];
$poNumber = $po['po_number'] ?? ('PO-2026-' . str_pad($poId, 4, '0', STR_PAD_LEFT));

/* ==========================================================================
   2. DYNAMIC SUPPLIER LOOKUP
   ========================================================================== */

$supplierName = $po['supplier_name'] ?? 'DailyNeeds Wholesale';
$supplierCode = 'SUP-0013';
$contactPerson = '-';
$contactPhone = '';
$contactEmail = '';
$gstNumber = '';
$supplierAddress = '';

if (!empty($po['supplier_id'])) {
    $sId = (int)$po['supplier_id'];
    $sRes = @mysqli_query($conn, "SELECT * FROM `suppliers` WHERE `id` = '$sId' LIMIT 1");
    if ($sRes && mysqli_num_rows($sRes) > 0) {
        $sData = mysqli_fetch_assoc($sRes);
        $supplierName    = $sData['supplier_name'] ?? $supplierName;
        $supplierCode    = $sData['supplier_code'] ?? $supplierCode;
        $contactPerson   = $sData['contact_person'] ?? '-';
        $contactPhone    = $sData['contact'] ?? ($sData['phone'] ?? '');
        $contactEmail    = $sData['email'] ?? '';
        $gstNumber       = $sData['gst_number'] ?? ($sData['gstin'] ?? '');
        $supplierAddress = $sData['address'] ?? '';
    }
}

/* ==========================================================================
   3. FETCH LINE ITEMS
   ========================================================================== */

$items = [];
$totalOrderedUnits = 0;
$totalReceivedUnits = 0;

$itemsRes = @mysqli_query($conn, "SELECT * FROM `purchase_order_items` WHERE `po_id` = '$poId' ORDER BY id ASC");
if ($itemsRes && mysqli_num_rows($itemsRes) > 0) {
    while ($it = mysqli_fetch_assoc($itemsRes)) {
        $items[] = $it;
        $totalOrderedUnits  += (int)($it['ordered_qty'] ?? ($it['quantity'] ?? 0));
        $totalReceivedUnits += (int)($it['received_qty'] ?? 0);
    }
}

// Fallback entry agar individual line items store na hue hon
if (empty($items)) {
    $pQty   = (int)($po['total_qty'] ?? 100);
    $pTotal = (float)($po['total_amount'] ?? 12300.00);
    $pPrice = ($pQty > 0) ? ($pTotal / $pQty) : 123.00;

    $items[] = [
        'product_name' => $po['product_name'] ?? 'Procurement Catalog Item',
        'product_code' => $po['product_code'] ?? 'PRD-1001',
        'ordered_qty'  => $pQty,
        'received_qty' => 0,
        'unit_price'   => $pPrice,
        'subtotal'     => $pTotal
    ];
    $totalOrderedUnits = $pQty;
}

$statusLower = strtolower(trim($po['status'] ?? 'pending'));

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Action Toolbar (Hidden during Print) -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 d-print-none">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-file-invoice text-primary me-2"></i>Purchase Order Voucher
            </h2>
            <p class="text-muted mb-0">Reference Number: <code class="fw-bold text-primary font-monospace fs-6"><?= htmlspecialchars($poNumber); ?></code></p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button onclick="window.print()" class="btn btn-outline-dark fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print PO Slip
            </button>
            <?php if (!in_array($statusLower, ['received', 'completed', 'cancelled'])): ?>
                <a href="../inbound/create.php?po_id=<?= $poId; ?>" class="btn btn-success fw-bold rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-truck-ramp-box me-1"></i> Inward Receiving (GRN)
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Orders
            </a>
        </div>
    </div>

    <!-- Main Printable PO Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-body p-4 p-md-5">

            <!-- Official Header Section -->
            <div class="row pb-4 mb-4 border-bottom g-4">
                <div class="col-sm-6">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <div class="p-2 bg-primary bg-opacity-10 text-primary rounded-3 d-inline-block">
                            <i class="fa-solid fa-warehouse fa-lg"></i>
                        </div>
                        <h4 class="fw-bold text-dark mb-0">VORTEX WMS</h4>
                    </div>
                    <p class="text-muted small mb-0">Enterprise Logistics & Fulfillment Hub</p>
                    <div class="mt-3">
                        <span class="text-muted small d-block text-uppercase fw-semibold">PURCHASE ORDER NUMBER:</span>
                        <code class="fs-5 fw-bold text-primary font-monospace"><?= htmlspecialchars($poNumber); ?></code>
                    </div>
                    <div class="mt-2 text-muted small">
                        <div>Issue Date: <strong><?= !empty($po['order_date']) ? date("d M Y", strtotime($po['order_date'])) : date("d M Y"); ?></strong></div>
                        <div>Expected Delivery: <strong><?= (!empty($po['expected_date']) && $po['expected_date'] !== '0000-00-00') ? date("d M Y", strtotime($po['expected_date'])) : '03 Sep 2026'; ?></strong></div>
                    </div>
                </div>

                <div class="col-sm-6 text-sm-end">
                    <div class="mb-3">
                        <?php if (in_array($statusLower, ['received', 'completed'])): ?>
                            <span class="badge bg-success-subtle text-success border border-success-subtle fs-6 px-3 py-1 rounded-pill fw-bold">
                                <i class="fa-solid fa-circle-check me-1"></i> RECEIVED & STOCKED
                            </span>
                        <?php elseif ($statusLower === 'cancelled'): ?>
                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-6 px-3 py-1 rounded-pill fw-bold">
                                <i class="fa-solid fa-circle-xmark me-1"></i> CANCELLED ORDER
                            </span>
                        <?php else: ?>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle fs-6 px-3 py-1 rounded-pill fw-bold">
                                <i class="fa-solid fa-clock me-1"></i> PENDING INBOUND SHIPMENT
                            </span>
                        <?php endif; ?>
                    </div>

                    <div class="p-3 bg-light rounded-4 border text-start d-inline-block w-100" style="max-width: 320px;">
                        <small class="text-muted fw-bold text-uppercase d-block" style="font-size: 11px;">Authorized Vendor / Supplier</small>
                        <strong class="fs-6 text-dark d-block mt-1"><?= htmlspecialchars($supplierName); ?></strong>
                        <span class="badge bg-light text-secondary border font-monospace mt-1"><?= htmlspecialchars($supplierCode); ?></span>
                        
                        <?php if (!empty($contactPerson) && $contactPerson !== '-'): ?>
                            <div class="small text-muted mt-2"><i class="fa-solid fa-user text-secondary me-1"></i> <?= htmlspecialchars($contactPerson); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($gstNumber)): ?>
                            <div class="small text-muted font-monospace"><i class="fa-solid fa-file-invoice text-secondary me-1"></i> GSTIN: <?= htmlspecialchars($gstNumber); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($contactPhone) || !empty($contactEmail)): ?>
                            <div class="small text-muted"><i class="fa-solid fa-phone text-secondary me-1"></i> <?= htmlspecialchars($contactPhone); ?> <?= (!empty($contactEmail)) ? '| ' . htmlspecialchars($contactEmail) : ''; ?></div>
                        <?php endif; ?>

                        <?php if (!empty($supplierAddress)): ?>
                            <div class="small text-muted mt-1"><i class="fa-solid fa-location-dot text-secondary me-1"></i> <?= htmlspecialchars($supplierAddress); ?></div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Line Items Table -->
            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th width="50" class="text-center">#</th>
                            <th>Product Description / Specification</th>
                            <th width="160">SKU Code</th>
                            <th width="120" class="text-center">Ordered Qty</th>
                            <th width="120" class="text-center">Received Qty</th>
                            <th width="150" class="text-end">Unit Price (₹)</th>
                            <th width="180" class="text-end">Subtotal (₹)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; foreach ($items as $item): ?>
                            <?php
                                $ordered = (int)($item['ordered_qty'] ?? ($item['quantity'] ?? 0));
                                $recv    = (int)($item['received_qty'] ?? 0);
                                $price   = (float)($item['unit_price'] ?? ($item['price'] ?? 0));
                                $sub     = (float)($item['subtotal'] ?? ($ordered * $price));
                            ?>
                            <tr>
                                <td class="text-center text-muted"><?= $i++; ?></td>
                                <td>
                                    <strong class="text-dark"><?= htmlspecialchars($item['product_name']); ?></strong>
                                </td>
                                <td>
                                    <code class="text-primary font-monospace fw-bold"><?= htmlspecialchars($item['product_code']); ?></code>
                                </td>
                                <td class="text-center fw-bold font-monospace">
                                    <?= number_format($ordered); ?>
                                </td>
                                <td class="text-center font-monospace">
                                    <span class="badge <?= ($recv >= $ordered && $ordered > 0) ? 'bg-success' : (($recv > 0) ? 'bg-warning text-dark' : 'bg-light text-muted border'); ?> px-2 py-1">
                                        <?= number_format($recv); ?> Units
                                    </span>
                                </td>
                                <td class="text-end font-monospace">
                                    ₹<?= number_format($price, 2); ?>
                                </td>
                                <td class="text-end font-monospace fw-bold text-dark">
                                    ₹<?= number_format($sub, 2); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary & Grand Total -->
            <div class="row g-4 align-items-center">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-4 border">
                        <small class="text-muted fw-bold d-block text-uppercase" style="font-size: 11px;">Terms & Inbound Guidelines</small>
                        <div class="small text-muted mt-1">Delivery Destination: <strong>Primary Vortex Distribution Warehouse</strong></div>
                        <div class="small text-muted mt-1">Mandate: Mandatory Quality Check (QC) upon dock receipt before putaway.</div>
                    </div>
                </div>

                <div class="col-md-6">
                    <div class="p-4 bg-light rounded-4 border text-end">
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fw-semibold">Total Line Items:</span>
                            <strong class="font-monospace"><?= count($items); ?> SKU(s)</strong>
                        </div>
                        <div class="d-flex justify-content-between mb-2">
                            <span class="text-muted fw-semibold">Total Units Ordered:</span>
                            <strong class="font-monospace"><?= number_format($totalOrderedUnits); ?> Units</strong>
                        </div>
                        <hr class="my-2">
                        <div class="d-flex justify-content-between align-items-center">
                            <span class="text-muted fw-bold text-uppercase">Grand Total Amount:</span>
                            <h2 class="fw-bold text-success font-monospace mb-0">₹<?= number_format((float)($po['total_amount'] ?? 12300), 2); ?></h2>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Signatures Section for Official Print -->
            <div class="row mt-5 pt-4 text-center d-none d-print-flex">
                <div class="col-6">
                    <div class="border-top pt-2 mx-4">
                        <small class="text-muted fw-bold">Supplier / Authorized Signatory</small>
                    </div>
                </div>
                <div class="col-6">
                    <div class="border-top pt-2 mx-4">
                        <small class="text-muted fw-bold">Warehouse Inbound Manager</small>
                    </div>
                </div>
            </div>

            <!-- Footer Toolbar -->
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top d-print-none flex-wrap gap-2">
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Orders
                </a>
            </div>

        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>