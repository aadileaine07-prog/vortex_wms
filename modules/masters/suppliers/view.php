<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Supplier identifier is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$supplierQuery = mysqli_query($conn, "SELECT * FROM suppliers WHERE id = '$id' LIMIT 1");
if (!$supplierQuery || mysqli_num_rows($supplierQuery) === 0) {
    $_SESSION['error'] = "Supplier record #{$id} not found.";
    header("Location: index.php");
    exit();
}

$supplier = mysqli_fetch_assoc($supplierQuery);
$contactPhone = $supplier['contact'] ?? ($supplier['phone'] ?? '-');
$gstNumber    = $supplier['gst_number'] ?? ($supplier['tax_id'] ?? '-');
$isActive     = (strcasecmp($supplier['status'] ?? 'Active', 'Active') === 0 || ($supplier['status'] ?? '') === '1');

// Fetch Linked POs
$poList = [];
$totalSpend = 0.00;
$completedCount = 0;
$pendingCount = 0;

$chkPO = @mysqli_query($conn, "SHOW TABLES LIKE 'purchase_orders'");
if ($chkPO && mysqli_num_rows($chkPO) > 0) {
    $po_query = mysqli_query($conn, "
        SELECT id, po_number, order_date, expected_date, status, total_amount 
        FROM purchase_orders 
        WHERE supplier_id = '$id' 
        ORDER BY id DESC
    ");

    if ($po_query && mysqli_num_rows($po_query) > 0) {
        while ($po = mysqli_fetch_assoc($po_query)) {
            $poList[] = $po;
            $amt = (float)($po['total_amount'] ?? 0);
            $totalSpend += $amt;
            
            $st = strtolower(trim($po['status'] ?? 'pending'));
            if (in_array($st, ['received', 'completed'])) {
                $completedCount++;
            } elseif ($st !== 'cancelled') {
                $pendingCount++;
            }
        }
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Action Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-industry text-primary me-2"></i>Supplier Profile & Performance
            </h2>
            <p class="text-muted mb-0">Vendor Code: <code class="fw-bold text-primary font-monospace"><?= htmlspecialchars($supplier['supplier_code']); ?></code> &bull; <strong><?= htmlspecialchars($supplier['supplier_name']); ?></strong></p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <button onclick="window.print()" class="btn btn-outline-dark fw-bold rounded-pill px-3 shadow-sm d-print-none">
                <i class="fa-solid fa-print me-1"></i> Print Profile
            </button>
            <!-- Fixed PO Creation Link (Goes 2 directories up to /modules/purchase_orders/) -->
            <a href="../../purchase_orders/create.php?supplier_id=<?= $supplier['id']; ?>" class="btn btn-success fw-bold rounded-pill px-3 shadow-sm d-print-none">
                <i class="fa-solid fa-cart-plus me-1"></i> Create PO
            </a>
            <a href="edit.php?id=<?= $supplier['id']; ?>" class="btn btn-warning fw-bold text-dark rounded-pill px-3 shadow-sm d-print-none">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Profile
            </a>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3 d-print-none">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to List
            </a>
        </div>
    </div>

    <!-- Executive KPI Row -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary h-100">
                <small class="text-muted fw-bold text-uppercase">Lifetime Procurement</small>
                <div class="fs-3 fw-bold text-dark my-1">₹<?= number_format($totalSpend, 2); ?></div>
                <small class="text-primary fw-semibold"><i class="fa-solid fa-receipt me-1"></i><?= count($poList); ?> Total Orders</small>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning h-100">
                <small class="text-muted fw-bold text-uppercase">Awaiting Inbound / GRN</small>
                <div class="fs-3 fw-bold text-warning my-1"><?= number_format($pendingCount); ?> <span class="fs-6 text-muted fw-normal">Orders</span></div>
                <small class="text-muted">In Transit or Open</small>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success h-100">
                <small class="text-muted fw-bold text-uppercase">Fulfilled Deliveries</small>
                <div class="fs-3 fw-bold text-success my-1"><?= number_format($completedCount); ?> <span class="fs-6 text-muted fw-normal">Orders</span></div>
                <small class="text-success fw-semibold"><i class="fa-solid fa-circle-check me-1"></i>Inventory Ingested</small>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 <?= $isActive ? 'border-success' : 'border-danger'; ?> h-100">
                <small class="text-muted fw-bold text-uppercase">Account Standing</small>
                <div class="fs-4 fw-bold <?= $isActive ? 'text-success' : 'text-danger'; ?> my-1">
                    <?= $isActive ? '🟢 Active Partner' : '🔴 Suspended / Inactive'; ?>
                </div>
                <small class="text-muted font-monospace"><?= htmlspecialchars($supplier['payment_terms'] ?? 'Net 30'); ?></small>
            </div>
        </div>
    </div>

    <!-- Main Content Row -->
    <div class="row g-4">
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-id-card text-primary me-2"></i>Vendor Credentials
                    </h5>
                    <span class="badge bg-light text-secondary border font-monospace">#<?= $supplier['id']; ?></span>
                </div>

                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle mb-0">
                            <tbody>
                                <tr>
                                    <th width="38%" class="bg-light text-muted small fw-bold text-uppercase">Vendor Code</th>
                                    <td><code class="fs-6 fw-bold text-primary font-monospace"><?= htmlspecialchars($supplier['supplier_code']); ?></code></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Company Name</th>
                                    <td><strong class="fs-6 text-dark"><?= htmlspecialchars($supplier['supplier_name']); ?></strong></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Contact Person</th>
                                    <td><?= htmlspecialchars($supplier['contact_person'] ?: 'Unassigned'); ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Phone / Mobile</th>
                                    <td><span class="font-monospace"><?= htmlspecialchars($contactPhone); ?></span></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Email Address</th>
                                    <td><?= !empty($supplier['email']) ? '<a href="mailto:' . htmlspecialchars($supplier['email']) . '" class="text-decoration-none font-monospace">' . htmlspecialchars($supplier['email']) . '</a>' : '<span class="text-muted">-</span>'; ?></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Tax ID / GSTIN</th>
                                    <td><code class="font-monospace text-uppercase fw-bold text-dark fs-6"><?= htmlspecialchars($gstNumber); ?></code></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Payment Terms</th>
                                    <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 font-monospace"><?= htmlspecialchars($supplier['payment_terms'] ?? 'Net 30'); ?></span></td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Operating Status</th>
                                    <td>
                                        <?= $isActive 
                                            ? '<span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill"><i class="fa-solid fa-circle-check me-1"></i>Active</span>' 
                                            : '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill"><i class="fa-solid fa-ban me-1"></i>Inactive</span>'; 
                                        ?>
                                    </td>
                                </tr>
                                <tr>
                                    <th class="bg-light text-muted small fw-bold text-uppercase">Registered Address</th>
                                    <td><small class="text-muted d-block"><?= nl2br(htmlspecialchars($supplier['address'] ?: 'No address specified')); ?></small></td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light p-3 rounded-bottom-4 border-0 d-flex justify-content-between align-items-center d-print-none">
                    <a href="delete.php?id=<?= $supplier['id']; ?>" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('⚠️ Delete this supplier profile completely?');">
                        <i class="fa-solid fa-trash me-1"></i> Delete
                    </a>
                    <a href="edit.php?id=<?= $supplier['id']; ?>" class="btn btn-warning btn-sm fw-bold rounded-pill px-3 text-dark">
                        <i class="fa-solid fa-pen-to-square me-1"></i> Edit Details
                    </a>
                </div>
            </div>
        </div>

        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-file-invoice text-primary me-2"></i>Procurement Order History
                        </h5>
                        <small class="text-muted">Purchase orders issued to this vendor</small>
                    </div>
                    <!-- Fixed PO Creation Button Link -->
                    <a href="../../purchase_orders/create.php?supplier_id=<?= $supplier['id']; ?>" class="btn btn-outline-primary btn-sm fw-bold rounded-pill px-3 d-print-none">
                        <i class="fa-solid fa-plus me-1"></i> Issue New PO
                    </a>
                </div>

                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>PO Number</th>
                                    <th>Issue Date</th>
                                    <th class="text-end">Total Amount</th>
                                    <th class="text-center">Status</th>
                                    <th class="text-center d-print-none" width="80">View</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($poList)): ?>
                                    <?php foreach ($poList as $po): ?>
                                        <?php
                                            $stLower = strtolower(trim($po['status'] ?? 'pending'));
                                            if (in_array($stLower, ['received', 'completed'])) {
                                                $badge = '<span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 rounded-pill">Received</span>';
                                            } elseif ($stLower === 'cancelled') {
                                                $badge = '<span class="badge bg-danger-subtle text-danger border border-danger-subtle px-2 py-1 rounded-pill">Cancelled</span>';
                                            } else {
                                                $badge = '<span class="badge bg-warning-subtle text-warning border border-warning-subtle px-2 py-1 rounded-pill">Pending</span>';
                                            }
                                        ?>
                                        <tr>
                                            <td><span class="badge bg-primary-subtle text-primary border border-primary-subtle font-monospace"><?= htmlspecialchars($po['po_number']); ?></span></td>
                                            <td><small class="text-muted"><?= date("d M Y", strtotime($po['order_date'])); ?></small></td>
                                            <td class="text-end font-monospace fw-bold text-dark">₹<?= number_format((float)($po['total_amount'] ?? 0), 2); ?></td>
                                            <td class="text-center"><?= $badge; ?></td>
                                            <td class="text-center d-print-none">
                                                <a href="../../purchase_orders/view.php?id=<?= $po['id']; ?>" class="btn btn-outline-info btn-sm rounded-circle" title="View PO Slip">
                                                    <i class="fa-solid fa-eye"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-file-invoice-dollar fs-2 d-block mb-2 text-secondary opacity-50"></i>
                                            No Purchase Orders linked with this vendor yet.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <div class="card-footer bg-light p-3 rounded-bottom-4 border-0 text-end d-print-none">
                    <a href="../../purchase_orders/index.php" class="small text-decoration-none fw-bold">
                        View Complete PO Ledger <i class="fa-solid fa-arrow-right ms-1"></i>
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>