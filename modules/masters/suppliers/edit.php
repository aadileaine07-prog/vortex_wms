<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Dynamic Multi-Level Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

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

/* ==========================================================================
   1. DYNAMIC COLUMN DETECTION & FETCH EXISTING SUPPLIER RECORD
   ========================================================================== */

$cols = [];
$cRes = @mysqli_query($conn, "SHOW COLUMNS FROM suppliers");
if ($cRes) {
    while ($c = mysqli_fetch_assoc($cRes)) { 
        $cols[] = strtolower($c['Field']); 
    }
}

$supplierQuery = mysqli_query($conn, "SELECT * FROM suppliers WHERE id = '$id' LIMIT 1");

if (!$supplierQuery || mysqli_num_rows($supplierQuery) === 0) {
    $_SESSION['error'] = "Supplier record #{$id} not found.";
    header("Location: index.php");
    exit();
}

$row = mysqli_fetch_assoc($supplierQuery);

/* ==========================================================================
   2. HANDLE FORM SUBMISSION & RECORD UPDATE
   ========================================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {

    $supplier_code  = mysqli_real_escape_string($conn, strtoupper(trim($_POST['supplier_code'] ?? '')));
    $supplier_name  = mysqli_real_escape_string($conn, trim($_POST['supplier_name'] ?? ''));
    $contact_person = mysqli_real_escape_string($conn, trim($_POST['contact_person'] ?? ''));
    $contact        = mysqli_real_escape_string($conn, trim($_POST['contact'] ?? ''));
    $email          = mysqli_real_escape_string($conn, strtolower(trim($_POST['email'] ?? '')));
    $gst_number     = mysqli_real_escape_string($conn, strtoupper(trim($_POST['gst_number'] ?? '')));
    $payment_terms  = mysqli_real_escape_string($conn, trim($_POST['payment_terms'] ?? 'Net 30'));
    $address        = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $status         = mysqli_real_escape_string($conn, trim($_POST['status'] ?? 'Active'));

    if (empty($supplier_code) || empty($supplier_name)) {
        $_SESSION['error'] = "Supplier Code and Business Name cannot be empty.";
    } else {
        // Unique Supplier Code Collision Check
        $dupCheck = mysqli_query($conn, "SELECT id FROM suppliers WHERE supplier_code = '$supplier_code' AND id != '$id' LIMIT 1");
        
        if ($dupCheck && mysqli_num_rows($dupCheck) > 0) {
            $_SESSION['error'] = "Supplier Code <strong>{$supplier_code}</strong> is already assigned to another vendor.";
        } else {
            $updates = [
                "`supplier_code` = '$supplier_code'",
                "`supplier_name` = '$supplier_name'",
                "`status` = '$status'"
            ];

            if (in_array('contact_person', $cols)) { $updates[] = "`contact_person` = '$contact_person'"; }
            if (in_array('contact', $cols))        { $updates[] = "`contact` = '$contact'"; }
            elseif (in_array('phone', $cols))      { $updates[] = "`phone` = '$contact'"; }
            if (in_array('email', $cols))          { $updates[] = "`email` = '$email'"; }
            if (in_array('gst_number', $cols))     { $updates[] = "`gst_number` = '$gst_number'"; }
            elseif (in_array('tax_id', $cols))     { $updates[] = "`tax_id` = '$gst_number'"; }
            if (in_array('payment_terms', $cols))  { $updates[] = "`payment_terms` = '$payment_terms'"; }
            if (in_array('address', $cols))        { $updates[] = "`address` = '$address'"; }

            $updateSql = "UPDATE suppliers SET " . implode(", ", $updates) . " WHERE id = '$id'";

            if (mysqli_query($conn, $updateSql)) {
                $_SESSION['success'] = "Supplier <strong>{$supplier_name}</strong> details updated successfully.";
                header("Location: index.php");
                exit();
            } else {
                $_SESSION['error'] = "Failed to update supplier record: " . mysqli_error($conn);
            }
        }
    }
}

// Single Unified Layout Header
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Supplier Profile
            </h2>
            <p class="text-muted mb-0">Modifying vendor account: <strong><?= htmlspecialchars($row['supplier_name']); ?></strong> (<code class="fw-bold text-primary font-monospace">#<?= $row['id']; ?></code>)</p>
        </div>
        <div class="d-flex gap-2">
            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-outline-info fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-eye me-1"></i> View Profile
            </a>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Suppliers
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Main Edit Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-id-card-clip text-primary me-2"></i>Modify Vendor Specifications
            </h5>
            <span class="badge bg-light text-secondary border font-monospace px-3 py-1">ID: #<?= $row['id']; ?></span>
        </div>

        <div class="card-body p-4">
            <form method="POST" id="editSupplierForm">
                <div class="row g-4">

                    <!-- Supplier Code -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Vendor Identifier / Code *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-hashtag text-muted"></i></span>
                            <input type="text" name="supplier_code" class="form-control border-2 font-monospace fw-bold text-primary" value="<?= htmlspecialchars($row['supplier_code']); ?>" required>
                        </div>
                    </div>

                    <!-- Supplier Name -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Business / Company Name *</label>
                        <input type="text" name="supplier_name" class="form-control border-2 fw-semibold" value="<?= htmlspecialchars($row['supplier_name']); ?>" required>
                    </div>

                    <!-- Contact Person -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Key Contact Representative</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-user-tie text-muted"></i></span>
                            <input type="text" name="contact_person" class="form-control border-2" value="<?= htmlspecialchars($row['contact_person'] ?? ''); ?>" placeholder="Account Manager">
                        </div>
                    </div>

                    <!-- Contact Phone -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Primary Phone / Mobile</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-phone text-muted"></i></span>
                            <input type="tel" name="contact" class="form-control border-2 font-monospace" value="<?= htmlspecialchars($row['contact'] ?? ($row['phone'] ?? '')); ?>" placeholder="+91 98765 43210">
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Official Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control border-2" value="<?= htmlspecialchars($row['email'] ?? ''); ?>" placeholder="procurement@vendor.com">
                        </div>
                    </div>

                    <!-- Tax ID / GSTIN -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Tax Registration ID (GSTIN / TIN)</label>
                        <input type="text" name="gst_number" class="form-control border-2 text-uppercase font-monospace" value="<?= htmlspecialchars($row['gst_number'] ?? ($row['tax_id'] ?? '')); ?>" placeholder="24AAAAA0000A1Z5">
                    </div>

                    <!-- Payment Terms -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Commercial Payment Terms</label>
                        <select name="payment_terms" class="form-select border-2 fw-semibold">
                            <option value="Advance" <?= (($row['payment_terms'] ?? '') === 'Advance') ? 'selected' : ''; ?>>Advance Payment (Immediate)</option>
                            <option value="Net 15" <?= (($row['payment_terms'] ?? '') === 'Net 15') ? 'selected' : ''; ?>>Net 15 Days Credit</option>
                            <option value="Net 30" <?= (($row['payment_terms'] ?? '') === 'Net 30') ? 'selected' : ''; ?>>Net 30 Days Credit</option>
                            <option value="Net 60" <?= (($row['payment_terms'] ?? '') === 'Net 60') ? 'selected' : ''; ?>>Net 60 Days Credit</option>
                            <option value="Net 90" <?= (($row['payment_terms'] ?? '') === 'Net 90') ? 'selected' : ''; ?>>Net 90 Days Credit</option>
                        </select>
                    </div>

                    <!-- Status -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Vendor Account Status *</label>
                        <select name="status" class="form-select border-2 fw-semibold" required>
                            <option value="Active" <?= (strcasecmp($row['status'] ?? 'Active', 'Active') === 0 || ($row['status'] ?? '') === '1') ? 'selected' : ''; ?>>🟢 Active (Authorized for POs)</option>
                            <option value="Inactive" <?= (strcasecmp($row['status'] ?? '', 'Inactive') === 0 || ($row['status'] ?? '') === '0') ? 'selected' : ''; ?>>🔴 Inactive / On Hold</option>
                        </select>
                    </div>

                    <!-- Address -->
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">Office / Warehouse Address</label>
                        <textarea name="address" class="form-control border-2" rows="3" placeholder="Full postal address, state, and pincode..."><?= htmlspecialchars($row['address'] ?? ''); ?></textarea>
                    </div>

                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="update" class="btn btn-warning px-5 fw-bold shadow-sm rounded-pill text-dark">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Update Supplier Profile
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>