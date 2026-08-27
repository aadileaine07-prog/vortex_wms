<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Purchase Order ID is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

/* ==========================================================================
   1. FETCH PO MASTER & SUPPLIER INFORMATION
   ========================================================================== */

$poQuery = mysqli_query($conn, "
    SELECT 
        po.*, 
        COALESCE(s.supplier_name, 'Unassigned Vendor') AS supplier_name, 
        COALESCE(s.supplier_code, 'VEND-00') AS supplier_code, 
        s.contact_person, 
        s.contact, 
        s.email, 
        s.gst_number, 
        s.address,
        e.full_name AS employee_name
    FROM purchase_orders po 
    LEFT JOIN suppliers s ON po.supplier_id = s.id 
    LEFT JOIN employees e ON (e.id = po.created_by OR e.employee_id = po.created_by)
    WHERE po.id = '$id'
    LIMIT 1
");

if (!$poQuery || mysqli_num_rows($poQuery) === 0) {
    $_SESSION['error'] = "Purchase Order #{$id} not found.";
    header("Location: index.php");
    exit();
}

$po = mysqli_fetch_assoc($poQuery);

/* ==========================================================================
   2. FETCH LINE ITEMS
   ========================================================================== */

$itemsQuery = mysqli_query($conn, "SELECT * FROM purchase_order_items WHERE po_id = '$id' ORDER BY id ASC");
$items = [];
$totalOrderedUnits = 0;
$totalReceivedUnits = 0;

if ($itemsQuery && mysqli_num_rows($itemsQuery) > 0) {
    while ($it = mysqli_fetch_assoc($itemsQuery)) {
        $items[] = $it;
        $totalOrderedUnits += (int)($it['ordered_qty'] ?? 0);
        $totalReceivedUnits += (int)($it['received_qty'] ?? 0);
    }
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
            <p class="text-muted mb-0">Reference Number: <code class="fw-bold text-primary font-monospace fs-6"><?= htmlspecialchars($po['po_number']); ?></code></p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button onclick="window.print()" class="btn btn-outline-dark fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print PO Slip
            </button>
            <?php if (!in_array($statusLower, ['received', 'completed', 'cancelled'])): ?>
                <a href="../grn/create.php?po_id=<?= $po['id']; ?>" class="btn btn-success fw-bold rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-dolly me-1"></i> Inbound Receiving / GRN
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
                    <p class="text-muted small mb-0">Enterprise Logistics & Fulfillment Center</p>
                    <div class="mt-3">
                        <span class="text-muted small d-block">PURCHASE ORDER NUMBER:</span>
                        <code class="fs-5 fw-bold text-primary font-monospace"><?= htmlspecialchars($po['po_number']); ?></code>
                    </div>
                    <div class="mt-2 text-muted small">
                        <div>Issue Date: <strong><?= date("d M Y", strtotime($po['order_date'])); ?></strong></div>
                        <div>Expected Arrival: <strong><?= (!empty($po['expected_date']) && $po['expected_date'] !== '0000-00-00') ? date("d M Y", strtotime($po['expected_date'])) : 'Not Specified'; ?></strong></div>
                    </div>
                </div>

                <div class="col-sm-6 text-sm-end">
                    <div class="mb-2">
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
                        <strong class="fs-6 text-dark d-block mt-1"><?= htmlspecialchars($po['supplier_name']); ?></strong>
                        <span class="badge bg-light text-secondary border font-monospace mt-1"><?= htmlspecialchars($po['supplier_code']); ?></span>
                        
                        <?php if (!empty($po['contact_person'])): ?>
                            <div class="small text-muted mt-2"><i class="fa-solid fa-user text-secondary me-1"></i> <?= htmlspecialchars($po['contact_person']); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($po['gst_number'])): ?>
                            <div class="small text-muted font-monospace"><i class="fa-solid fa-file-invoice text-secondary me-1"></i> GSTIN: <?= htmlspecialchars($po['gst_number']); ?></div>
                        <?php endif; ?>
                        
                        <?php if (!empty($po['contact']) || !empty($po['email'])): ?>
                            <div class="small text-muted"><i class="fa-solid fa-phone text-secondary me-1"></i> <?= htmlspecialchars($po['contact'] ?? ''); ?> <?= (!empty($po['email'])) ? '| ' . htmlspecialchars($po['email']) : ''; ?></div>
                        <?php endif; ?>

                        <?php if (!empty($po['address'])): ?>
                            <div class="small text-muted mt-1"><i class="fa-solid fa-location-dot text-secondary me-1"></i> <?= htmlspecialchars($po['address']); ?></div>
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
                        <?php if (!empty($items)): $i = 1; ?>
                            <?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="text-center text-muted"><?= $i++; ?></td>
                                    <td>
                                        <strong class="text-dark"><?= htmlspecialchars($item['product_name']); ?></strong>
                                    </td>
                                    <td>
                                        <code class="text-primary font-monospace fw-bold"><?= htmlspecialchars($item['product_code']); ?></code>
                                    </td>
                                    <td class="text-center fw-bold font-monospace">
                                        <?= (int)$item['ordered_qty']; ?>
                                    </td>
                                    <td class="text-center font-monospace">
                                        <?php $recv = (int)($item['received_qty'] ?? 0); ?>
                                        <span class="badge <?= ($recv >= (int)$item['ordered_qty']) ? 'bg-success' : (($recv > 0) ? 'bg-warning text-dark' : 'bg-light text-muted border'); ?> px-2 py-1">
                                            <?= $recv; ?> Units
                                        </span>
                                    </td>
                                    <td class="text-end font-monospace">
                                        ₹<?= number_format((float)$item['unit_price'], 2); ?>
                                    </td>
                                    <td class="text-end font-monospace fw-bold text-dark">
                                        ₹<?= number_format((float)$item['subtotal'], 2); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center py-4 text-muted">No individual line items registered for this Purchase Order.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Summary & Grand Valuation -->
            <div class="row g-4 align-items-center">
                <div class="col-md-6">
                    <div class="p-3 bg-light rounded-4 border">
                        <small class="text-muted fw-bold d-block text-uppercase" style="font-size: 11px;">Authorizations & Logistics Terms</small>
                        <div class="small text-dark mt-1">Authorized By: <strong><?= htmlspecialchars($po['employee_name'] ?? 'Operations Manager'); ?></strong></div>
                        <div class="small text-muted mt-1">Delivery Destination: <strong>Primary Vortex Distribution Warehouse</strong></div>
                        <div class="small text-muted mt-1">Inspection Mandate: Mandatory 100% Quality & QC verification at Inbound Bay.</div>
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
                            <h2 class="fw-bold text-success font-monospace mb-0">₹<?= number_format((float)$po['total_amount'], 2); ?></h2>
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

            <!-- Footer Toolbar (Hidden during Print) -->
            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top d-print-none flex-wrap gap-2">
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4">
                    <i class="fa-solid fa-arrow-left me-1"></i> Back to Orders
                </a>
                <div class="d-flex gap-2">
                    <?php if (!in_array($statusLower, ['received', 'completed'])): ?>
                        <a href="delete.php?id=<?= $po['id']; ?>" class="btn btn-outline-danger rounded-pill px-3" onclick="return confirm('⚠️ Are you sure you want to cancel and delete this Purchase Order?');">
                            <i class="fa-solid fa-trash me-1"></i> Cancel & Delete PO
                        </a>
                    <?php endif; ?>
                </div>
            </div>

        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>