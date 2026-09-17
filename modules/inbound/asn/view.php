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

$id = isset($_GET['id']) ? intval($_GET['id']) : 0;
if ($id <= 0) {
    $_SESSION['error'] = "Invalid ASN ID specified.";
    header("Location: index.php");
    exit();
}

$asnTable = "asn_headers";
$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'asn_headers'");
if (!$chk || mysqli_num_rows($chk) === 0) {
    $asnTable = "asn";
}

$res = mysqli_query($conn, "SELECT * FROM `{$asnTable}` WHERE id = $id LIMIT 1");
if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['error'] = "ASN record not found.";
    header("Location: index.php");
    exit();
}

$asn = mysqli_fetch_assoc($res);
$statusLower = strtolower(trim($asn['status'] ?? 'pending'));

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2 d-print-none">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-file-invoice text-primary me-2"></i>ASN Shipment Voucher
            </h2>
            <p class="text-muted mb-0">Reference Number: <code class="fw-bold text-primary font-monospace fs-6"><?= htmlspecialchars($asn['asn_number']); ?></code></p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-dark fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print Slip
            </button>
            <a href="edit.php?id=<?= $asn['id']; ?>" class="btn btn-warning fw-bold text-dark rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit
            </a>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-9 mx-auto mb-4">
        <div class="card-body p-4 p-md-5">
            <div class="row pb-4 mb-4 border-bottom g-4">
                <div class="col-sm-6">
                    <h4 class="fw-bold text-dark mb-0">VORTEX WMS</h4>
                    <p class="text-muted small">Inbound Logistics & ASN Verification</p>
                    <div class="mt-3">
                        <span class="text-muted small d-block text-uppercase fw-semibold">ASN REFERENCE:</span>
                        <code class="fs-5 fw-bold text-primary font-monospace"><?= htmlspecialchars($asn['asn_number']); ?></code>
                    </div>
                </div>
                <div class="col-sm-6 text-sm-end">
                    <span class="badge <?= ($statusLower === 'completed' || $statusLower === 'received') ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-warning-subtle text-warning border border-warning-subtle'; ?> fs-6 px-3 py-1.5 rounded-pill fw-bold">
                        <?= strtoupper($asn['status'] ?? 'Pending'); ?>
                    </span>
                    <div class="mt-3 text-muted small">
                        <div>Expected Arrival: <strong><?= date("d M Y", strtotime($asn['expected_date'])); ?></strong></div>
                    </div>
                </div>
            </div>

            <div class="table-responsive mb-4">
                <table class="table table-bordered align-middle mb-0">
                    <tbody>
                        <tr>
                            <th width="35%" class="bg-light text-muted small fw-bold text-uppercase">Supplier Name</th>
                            <td><strong class="text-dark"><?= htmlspecialchars($asn['supplier_name']); ?></strong></td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted small fw-bold text-uppercase">Item / SKU Code</th>
                            <td><code class="text-primary font-monospace fw-bold"><?= htmlspecialchars($asn['item_code'] ?? 'SKU-00'); ?></code></td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted small fw-bold text-uppercase">Expected Quantity</th>
                            <td><span class="badge bg-primary fs-6 px-3 py-1 font-monospace"><?= number_format((int)($asn['expected_qty'] ?? 0)); ?> Units</span></td>
                        </tr>
                        <tr>
                            <th class="bg-light text-muted small fw-bold text-uppercase">Log Timestamp</th>
                            <td><small class="text-muted"><?= htmlspecialchars($asn['created_at'] ?? date('Y-m-d H:i:s')); ?></small></td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="d-flex justify-content-between align-items-center mt-4 pt-3 border-top d-print-none">
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4"><i class="fa-solid fa-arrow-left me-1"></i> Back to List</a>
            </div>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>