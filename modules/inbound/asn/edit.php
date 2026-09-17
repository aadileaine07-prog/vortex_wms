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

// Ensure `item_code` column always exists in the table to prevent unknown column crash
@mysqli_query($conn, "ALTER TABLE `{$asnTable}` ADD COLUMN IF NOT EXISTS item_code VARCHAR(50) DEFAULT 'SKU-00'");

// Handle Form Submission Safely
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_asn'])) {
    $supplier_name = mysqli_real_escape_string($conn, trim($_POST['supplier_name']));
    $expected_date = !empty($_POST['expected_date']) ? $_POST['expected_date'] : date('Y-m-d');
    $item_code     = mysqli_real_escape_string($conn, trim($_POST['item_code']));
    $expected_qty  = max(1, intval($_POST['expected_qty'] ?? 1));
    $status        = mysqli_real_escape_string($conn, trim($_POST['status'] ?? 'Pending'));

    $sql = "UPDATE `{$asnTable}` SET 
            supplier_name = '$supplier_name', 
            expected_date = '$expected_date', 
            item_code = '$item_code', 
            expected_qty = '$expected_qty', 
            status = '$status' 
            WHERE id = $id";

    if (mysqli_query($conn, $sql)) {
        $_SESSION['success'] = "ASN record updated successfully!";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Update failed: " . mysqli_error($conn);
    }
}

$res = mysqli_query($conn, "SELECT * FROM `{$asnTable}` WHERE id = $id LIMIT 1");
if (!$res || mysqli_num_rows($res) === 0) {
    $_SESSION['error'] = "ASN record not found.";
    header("Location: index.php");
    exit();
}
$asn = mysqli_fetch_assoc($res);

$currentItemVal = $asn['item_code'] ?? $asn['product_code'] ?? $asn['sku'] ?? 'SKU-00';

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit ASN Record
            </h2>
            <p class="text-muted mb-0">Modify shipping manifest details and arrival status</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-9 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-0 text-dark">Updating ASN: <code class="text-primary font-monospace"><?= htmlspecialchars($asn['asn_number']); ?></code></h5>
        </div>

        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">ASN Number</label>
                        <input type="text" class="form-control border-2 font-monospace bg-light fw-bold" value="<?= htmlspecialchars($asn['asn_number']); ?>" readonly>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted">Supplier Name *</label>
                        <input type="text" name="supplier_name" class="form-control border-2 fw-semibold" value="<?= htmlspecialchars($asn['supplier_name']); ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Expected Date *</label>
                        <input type="date" name="expected_date" class="form-control border-2 fw-semibold" value="<?= htmlspecialchars($asn['expected_date']); ?>" required>
                    </div>

                    <div class="col-md-5">
                        <label class="form-label small fw-bold text-muted">Item / SKU Code *</label>
                        <input type="text" name="item_code" class="form-control border-2 font-monospace" value="<?= htmlspecialchars($currentItemVal); ?>" required>
                    </div>

                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Expected Quantity *</label>
                        <input type="number" name="expected_qty" class="form-control border-2 fw-bold text-center" min="1" value="<?= (int)$asn['expected_qty']; ?>" required>
                    </div>

                    <div class="col-md-3">
                        <label class="form-label small fw-bold text-muted">Status</label>
                        <select name="status" class="form-select border-2 fw-semibold">
                            <option value="Pending" <?= ($asn['status'] === 'Pending') ? 'selected' : ''; ?>>Pending</option>
                            <option value="Completed" <?= ($asn['status'] === 'Completed' || $asn['status'] === 'Received') ? 'selected' : ''; ?>>Completed</option>
                            <option value="Cancelled" <?= ($asn['status'] === 'Cancelled') ? 'selected' : ''; ?>>Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="update_asn" class="btn btn-success px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Update ASN Record
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>