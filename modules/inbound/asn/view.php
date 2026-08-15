<?php
session_start();

// Dynamic Project Root Path
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "ASN ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// Fetch Master ASN Details
$asnQuery = mysqli_query($conn, "
    SELECT a.*, s.supplier_code 
    FROM asn a 
    LEFT JOIN suppliers s ON a.supplier_name = s.supplier_name 
    WHERE a.id = '$id'
");

if (!$asnQuery || mysqli_num_rows($asnQuery) == 0) {
    $_SESSION['error'] = "ASN Record Not Found.";
    header("Location: index.php");
    exit();
}

$asn = mysqli_fetch_assoc($asnQuery);

// Fetch Line Items
$itemsQuery = mysqli_query($conn, "SELECT * FROM asn_items WHERE asn_id = '$id'");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-truck-ramp-box text-primary me-2"></i>ASN Details</h2>
                <p class="text-muted mb-0">Advance Shipping Notice Reference</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <!-- Master Details Card -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body p-4">
                <table class="table table-bordered mb-0 align-middle">
                    <tbody>
                        <tr>
                            <th width="200" class="bg-light fw-bold">ASN Number</th>
                            <td><strong class="font-monospace text-primary fs-6"><?= htmlspecialchars($asn['asn_number'] ?? ''); ?></strong></td>
                        </tr>
                        <tr>
                            <th class="bg-light fw-bold">Supplier</th>
                            <td><strong><?= htmlspecialchars($asn['supplier_name'] ?? ''); ?></strong></td>
                        </tr>
                        <tr>
                            <th class="bg-light fw-bold">Invoice Number</th>
                            <td><?= htmlspecialchars($asn['invoice_number'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light fw-bold">Invoice Date</th>
                            <td><?= !empty($asn['invoice_date']) ? date("Y-m-d", strtotime($asn['invoice_date'])) : ''; ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light fw-bold">Expected Date</th>
                            <td><?= !empty($asn['expected_date']) ? date("Y-m-d", strtotime($asn['expected_date'])) : ''; ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light fw-bold">Vehicle Number</th>
                            <td><?= htmlspecialchars($asn['vehicle_number'] ?? ''); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light fw-bold">Status</th>
                            <td>
                                <?php 
                                    $st = $asn['status'] ?? 'Pending';
                                    if ($st == 'Completed' || $st == 'Received') echo '<span class="badge bg-success px-3 py-1">Received</span>';
                                    else echo '<span class="badge bg-warning text-dark px-3 py-1">Pending</span>';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Products Table Card -->
        <h4 class="fw-bold text-dark mb-3">Products</h4>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th width="50">#</th>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th width="100">Quantity</th>
                                <th width="100">UOM</th>
                                <th>Batch</th>
                                <th>Expiry</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($itemsQuery && mysqli_num_rows($itemsQuery) > 0): $i = 1; ?>
                                <?php while ($item = mysqli_fetch_assoc($itemsQuery)): ?>
                                    <tr>
                                        <td><?= $i++; ?></td>
                                        <td><code class="fs-6"><?= htmlspecialchars($item['product_code'] ?? ''); ?></code></td>
                                        <td><strong><?= htmlspecialchars($item['product_name'] ?? ''); ?></strong></td>
                                        <td><?= intval($item['expected_qty'] ?? $item['quantity'] ?? 0); ?></td>
                                        <td><?= htmlspecialchars($item['uom'] ?? 'PCS'); ?></td>
                                        <td><?= htmlspecialchars($item['batch'] ?? $item['batch_number'] ?? ''); ?></td>
                                        <td><?= !empty($item['expiry_date']) ? date("Y-m-d", strtotime($item['expiry_date'])) : ''; ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No items recorded in this ASN.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>