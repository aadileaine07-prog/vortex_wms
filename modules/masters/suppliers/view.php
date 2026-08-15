<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Supplier ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$supplierQuery = mysqli_query($conn, "SELECT * FROM suppliers WHERE id='$id'");

if (!$supplierQuery || mysqli_num_rows($supplierQuery) == 0) {
    $_SESSION['error'] = "Supplier Not Found.";
    header("Location: index.php");
    exit();
}

$supplier = mysqli_fetch_assoc($supplierQuery);

// Fetch Linked Purchase Orders for this Supplier
$po_query = mysqli_query($conn, "
    SELECT id, po_number, order_date, status, total_amount 
    FROM purchase_orders 
    WHERE supplier_id = '$id' 
    ORDER BY id DESC LIMIT 10
");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-circle-info text-primary me-2"></i>Supplier Profile & History</h2>
                <p class="text-muted mb-0">Code: <strong><?= htmlspecialchars($supplier['supplier_code']); ?></strong> | Vendor Name: <strong><?= htmlspecialchars($supplier['supplier_name']); ?></strong></p>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary px-3 me-2"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
                <a href="../../purchase_orders/create.php?supplier_id=<?= $supplier['id']; ?>" class="btn btn-success px-3 fw-bold"><i class="fa-solid fa-cart-plus me-1"></i> Create PO</a>
            </div>
        </div>

        <div class="row g-4">
            <!-- Details Card -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-dark text-white p-3 rounded-top-4">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-building me-2"></i>Vendor Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <table class="table table-bordered align-middle mb-0">
                            <tr>
                                <th width="35%" class="bg-light fw-semibold">Supplier Code</th>
                                <td><span class="badge bg-secondary font-monospace fs-6"><?= htmlspecialchars($supplier['supplier_code']); ?></span></td>
                            </tr>
                            <tr>
                                <th class="bg-light fw-semibold">Company Name</th>
                                <td><strong class="fs-5 text-dark"><?= htmlspecialchars($supplier['supplier_name']); ?></strong></td>
                            </tr>
                            <tr>
                                <th class="bg-light fw-semibold">Contact Person</th>
                                <td><?= htmlspecialchars($supplier['contact_person'] ?? '-'); ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light fw-semibold">Contact Number</th>
                                <td><?= htmlspecialchars($supplier['contact'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light fw-semibold">Email Address</th>
                                <td><?= htmlspecialchars($supplier['email'] ?: '-'); ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light fw-semibold">GSTIN / Tax ID</th>
                                <td><span class="font-monospace text-uppercase fw-bold text-primary"><?= htmlspecialchars($supplier['gst_number'] ?? '-'); ?></span></td>
                            </tr>
                            <tr>
                                <th class="bg-light fw-semibold">Payment Terms</th>
                                <td><span class="badge bg-info text-dark px-3 py-2"><?= htmlspecialchars($supplier['payment_terms'] ?? 'Net 30'); ?></span></td>
                            </tr>
                            <tr>
                                <th class="bg-light fw-semibold">Full Address</th>
                                <td><?= nl2br(htmlspecialchars($supplier['address'] ?: '-')); ?></td>
                            </tr>
                            <tr>
                                <th class="bg-light fw-semibold">Account Status</th>
                                <td>
                                    <?php if (($supplier['status'] ?? 'Active') == 'Active'): ?>
                                        <span class="badge bg-success px-3 py-2"><i class="fa-solid fa-circle-check me-1"></i> Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger px-3 py-2"><i class="fa-solid fa-ban me-1"></i> Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        </table>

                        <div class="d-flex justify-content-end mt-4">
                            <a href="edit.php?id=<?= $supplier['id']; ?>" class="btn btn-warning px-3 me-2 text-dark"><i class="fa-solid fa-pen-to-square me-1"></i> Edit</a>
                            <a href="delete.php?id=<?= $supplier['id']; ?>" class="btn btn-danger px-3" onclick="return confirm('Delete this Supplier?');"><i class="fa-solid fa-trash me-1"></i> Delete</a>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Purchase Orders Linked Card -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 rounded-4">
                    <div class="card-header bg-primary text-white p-3 rounded-top-4 d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-file-invoice me-2"></i>Purchase Order History</h5>
                        <a href="../../purchase_orders/create.php?supplier_id=<?= $supplier['id']; ?>" class="btn btn-light btn-sm fw-bold"><i class="fa-solid fa-plus me-1"></i> New PO</a>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-hover mb-0 align-middle">
                                <thead class="table-light">
                                    <tr>
                                        <th>PO Number</th>
                                        <th>Order Date</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if ($po_query && mysqli_num_rows($po_query) > 0): ?>
                                        <?php while ($po = mysqli_fetch_assoc($po_query)): ?>
                                            <tr>
                                                <td><span class="badge bg-secondary font-monospace"><?= htmlspecialchars($po['po_number']); ?></span></td>
                                                <td><?= date("d M Y", strtotime($po['order_date'])); ?></td>
                                                <td><strong>₹<?= number_format($po['total_amount'] ?? 0, 2); ?></strong></td>
                                                <td><span class="badge bg-info text-dark"><?= htmlspecialchars($po['status']); ?></span></td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="4" class="text-center py-4 text-muted">
                                                <i class="fa-solid fa-receipt fs-3 d-block mb-2"></i>
                                                No Purchase Orders linked with this supplier yet.
                                            </td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>