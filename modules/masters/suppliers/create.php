<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// 3 levels up: /modules/masters/suppliers/ -> /vortex_wms/
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 4));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. AUTO-GENERATE UNIQUE SUPPLIER CODE
   ========================================================================== */

$autoQuery = @mysqli_query($conn, "SELECT id FROM suppliers ORDER BY id DESC LIMIT 1");
$nextId = ($autoQuery && mysqli_num_rows($autoQuery) > 0) ? (mysqli_fetch_assoc($autoQuery)['id'] + 1) : 1;
$default_code = "SUP-" . str_pad($nextId, 4, "0", STR_PAD_LEFT);

/* ==========================================================================
   2. HANDLE SUPPLIER REGISTRATION FORM SUBMISSION
   ========================================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {

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
        $_SESSION['error'] = "Supplier Code and Business Name are mandatory fields.";
    } else {
        // Unique Supplier Code Collision Check
        $check = mysqli_query($conn, "SELECT id FROM suppliers WHERE supplier_code = '$supplier_code' LIMIT 1");
        if ($check && mysqli_num_rows($check) > 0) {
            $_SESSION['error'] = "Supplier Code <strong>{$supplier_code}</strong> is already registered! Please assign a unique code.";
        } else {
            
            // Dynamic column inspection
            $cols = [];
            $cRes = @mysqli_query($conn, "SHOW COLUMNS FROM suppliers");
            if ($cRes) {
                while ($c = mysqli_fetch_assoc($cRes)) { 
                    $cols[] = strtolower($c['Field']); 
                }
            }

            $fields = ["`supplier_code`", "`supplier_name`", "`status`"];
            $values = ["'$supplier_code'", "'$supplier_name'", "'$status'"];

            if (in_array('contact_person', $cols)) { $fields[] = "`contact_person`"; $values[] = "'$contact_person'"; }
            if (in_array('contact', $cols))        { $fields[] = "`contact`"; $values[] = "'$contact'"; }
            elseif (in_array('phone', $cols))      { $fields[] = "`phone`"; $values[] = "'$contact'"; }
            if (in_array('email', $cols))          { $fields[] = "`email`"; $values[] = "'$email'"; }
            if (in_array('gst_number', $cols))     { $fields[] = "`gst_number`"; $values[] = "'$gst_number'"; }
            elseif (in_array('tax_id', $cols))     { $fields[] = "`tax_id`"; $values[] = "'$gst_number'"; }
            if (in_array('payment_terms', $cols))  { $fields[] = "`payment_terms`"; $values[] = "'$payment_terms'"; }
            if (in_array('address', $cols))        { $fields[] = "`address`"; $values[] = "'$address'"; }

            $sql = "INSERT INTO suppliers (" . implode(", ", $fields) . ") VALUES (" . implode(", ", $values) . ")";

            if (mysqli_query($conn, $sql)) {
                $_SESSION['success'] = "Supplier <strong>{$supplier_name}</strong> (<code>{$supplier_code}</code>) registered successfully.";
                header("Location: index.php");
                exit();
            } else {
                $_SESSION['error'] = "Failed to register supplier: " . mysqli_error($conn);
            }
        }
    }
}

// Single Unified Header Include
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-truck-field text-primary me-2"></i>Register New Supplier
            </h2>
            <p class="text-muted mb-0">Master Registry: Onboard verified procurement partners, distributors, and vendors</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
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

    <!-- Registration Form Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-10 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-id-card text-primary me-2"></i>Vendor Profile Entry
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill font-monospace">SUPPLIER MASTER</span>
        </div>

        <div class="card-body p-4">
            <form method="POST" id="supplierForm">
                <div class="row g-4">

                    <!-- Supplier Code -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Vendor Code / ID *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-hashtag text-muted"></i></span>
                            <input type="text" name="supplier_code" class="form-control border-2 font-monospace fw-bold text-primary" value="<?= htmlspecialchars($_POST['supplier_code'] ?? $default_code); ?>" required>
                        </div>
                        <small class="text-muted">Unique tracking identifier</small>
                    </div>

                    <!-- Supplier Name -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Company / Vendor Name *</label>
                        <input type="text" name="supplier_name" class="form-control border-2 fw-semibold" placeholder="e.g. Acme Industrial Solutions" value="<?= htmlspecialchars($_POST['supplier_name'] ?? ''); ?>" required>
                        <small class="text-muted">Registered business entity name</small>
                    </div>

                    <!-- Contact Person -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Key Contact Person</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-user-tie text-muted"></i></span>
                            <input type="text" name="contact_person" class="form-control border-2" placeholder="Sales / Account Rep" value="<?= htmlspecialchars($_POST['contact_person'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Phone Number -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Contact Phone / Mobile</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-phone text-muted"></i></span>
                            <input type="tel" name="contact" class="form-control border-2 font-monospace" placeholder="+91 98765 43210" value="<?= htmlspecialchars($_POST['contact'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- Email Address -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Official Email Address</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-envelope text-muted"></i></span>
                            <input type="email" name="email" class="form-control border-2" placeholder="orders@vendor.com" value="<?= htmlspecialchars($_POST['email'] ?? ''); ?>">
                        </div>
                    </div>

                    <!-- GSTIN / Tax ID -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">GSTIN / Tax ID</label>
                        <input type="text" name="gst_number" class="form-control border-2 text-uppercase font-monospace" placeholder="24AAAAA0000A1Z5" value="<?= htmlspecialchars($_POST['gst_number'] ?? ''); ?>">
                    </div>

                    <!-- Payment Terms -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Payment Credit Terms</label>
                        <select name="payment_terms" class="form-select border-2 fw-semibold">
                            <option value="Advance">Advance Payment (Immediate)</option>
                            <option value="Net 15">Net 15 Days</option>
                            <option value="Net 30" selected>Net 30 Days</option>
                            <option value="Net 60">Net 60 Days</option>
                            <option value="Net 90">Net 90 Days</option>
                        </select>
                    </div>

                    <!-- Operating Status -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Supplier Account Status *</label>
                        <select name="status" class="form-select border-2 fw-semibold" required>
                            <option value="Active" selected>🟢 Active (Ready for POs)</option>
                            <option value="Inactive">🔴 Inactive / On Hold</option>
                        </select>
                    </div>

                    <!-- Address -->
                    <div class="col-md-12">
                        <label class="form-label small fw-bold text-muted">Registered Dispatch / Office Address</label>
                        <textarea name="address" class="form-control border-2" rows="3" placeholder="Full postal address, city, state, pincode..."><?= htmlspecialchars($_POST['address'] ?? ''); ?></textarea>
                    </div>

                </div>

                <!-- Footer Buttons -->
                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="save" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Register Supplier
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>