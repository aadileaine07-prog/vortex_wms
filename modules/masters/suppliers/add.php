<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Auto Generate Supplier Code
$autoQuery = mysqli_query($conn, "SELECT id FROM suppliers ORDER BY id DESC LIMIT 1");
$nextId = ($autoQuery && mysqli_num_rows($autoQuery) > 0) ? (mysqli_fetch_assoc($autoQuery)['id'] + 1) : 1;
$default_code = "SUP-" . str_pad($nextId, 4, "0", STR_PAD_LEFT);

if (isset($_POST['save'])) {

    $supplier_code  = mysqli_real_escape_string($conn, trim($_POST['supplier_code']));
    $supplier_name  = mysqli_real_escape_string($conn, trim($_POST['supplier_name']));
    $contact_person = mysqli_real_escape_string($conn, trim($_POST['contact_person']));
    $contact        = mysqli_real_escape_string($conn, trim($_POST['contact']));
    $email          = mysqli_real_escape_string($conn, trim($_POST['email']));
    $gst_number     = mysqli_real_escape_string($conn, trim($_POST['gst_number']));
    $payment_terms  = mysqli_real_escape_string($conn, trim($_POST['payment_terms']));
    $address        = mysqli_real_escape_string($conn, trim($_POST['address']));
    $status         = mysqli_real_escape_string($conn, trim($_POST['status']));

    // Duplicate Check
    $check = mysqli_query($conn, "SELECT id FROM suppliers WHERE supplier_code='$supplier_code'");
    if ($check && mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = "Supplier Code <strong>{$supplier_code}</strong> already exists! Please use a unique code.";
    } else {
        $sql = "
            INSERT INTO suppliers (supplier_code, supplier_name, contact_person, contact, email, gst_number, payment_terms, address, status)
            VALUES ('$supplier_code', '$supplier_name', '$contact_person', '$contact', '$email', '$gst_number', '$payment_terms', '$address', '$status')
        ";

        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Supplier <strong>{$supplier_name}</strong> registered successfully.";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to add supplier: " . mysqli_error($conn);
        }
    }
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-plus-circle text-primary me-2"></i>Add New Supplier</h2>
                <p class="text-muted mb-0">Register a new vendor in the system database</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-4 col-lg-10 mx-auto">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row g-3">

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Supplier Code <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_code" class="form-control font-monospace" value="<?= $default_code; ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Supplier / Business Name <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_name" class="form-control" placeholder="e.g. Acme Logistics Pvt Ltd" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" placeholder="Key contact person">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Contact Number</label>
                            <input type="text" name="contact" class="form-control" placeholder="+91 98765 43210">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" placeholder="vendor@domain.com">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">GSTIN / Tax ID</label>
                            <input type="text" name="gst_number" class="form-control text-uppercase font-monospace" placeholder="24AAAAA0000A1Z5">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Terms</label>
                            <select name="payment_terms" class="form-select">
                                <option value="Advance">Advance Payment</option>
                                <option value="Net 15">Net 15 Days</option>
                                <option value="Net 30" selected>Net 30 Days</option>
                                <option value="Net 60">Net 60 Days</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Active" selected>Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Full Address</label>
                            <textarea name="address" class="form-control" rows="3" placeholder="Office / Factory address"></textarea>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" name="save" class="btn btn-primary px-4 fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Save Supplier</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>