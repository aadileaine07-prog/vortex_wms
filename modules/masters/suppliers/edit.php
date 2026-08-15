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

$supplier = mysqli_query($conn, "SELECT * FROM suppliers WHERE id='$id'");

if (!$supplier || mysqli_num_rows($supplier) == 0) {
    $_SESSION['error'] = "Supplier Not Found.";
    header("Location: index.php");
    exit();
}

$row = mysqli_fetch_assoc($supplier);

if (isset($_POST['update'])) {

    $supplier_code  = mysqli_real_escape_string($conn, trim($_POST['supplier_code']));
    $supplier_name  = mysqli_real_escape_string($conn, trim($_POST['supplier_name']));
    $contact_person = mysqli_real_escape_string($conn, trim($_POST['contact_person']));
    $contact        = mysqli_real_escape_string($conn, trim($_POST['contact']));
    $email          = mysqli_real_escape_string($conn, trim($_POST['email']));
    $gst_number     = mysqli_real_escape_string($conn, trim($_POST['gst_number']));
    $payment_terms  = mysqli_real_escape_string($conn, trim($_POST['payment_terms']));
    $address        = mysqli_real_escape_string($conn, trim($_POST['address']));
    $status         = mysqli_real_escape_string($conn, trim($_POST['status']));

    // Check Duplicate Supplier Code for Other Suppliers
    $dupCheck = mysqli_query($conn, "SELECT id FROM suppliers WHERE supplier_code='$supplier_code' AND id != '$id'");
    if ($dupCheck && mysqli_num_rows($dupCheck) > 0) {
        $_SESSION['error'] = "Supplier Code <strong>{$supplier_code}</strong> is already assigned to another vendor.";
    } else {
        $updateSql = "
            UPDATE suppliers SET
                supplier_code  = '$supplier_code',
                supplier_name  = '$supplier_name',
                contact_person = '$contact_person',
                contact        = '$contact',
                email          = '$email',
                gst_number     = '$gst_number',
                payment_terms  = '$payment_terms',
                address        = '$address',
                status         = '$status'
            WHERE id = '$id'
        ";

        if (mysqli_query($conn, $updateSql)) {
            $_SESSION['success'] = "Supplier details updated successfully.";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to update supplier: " . mysqli_error($conn);
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
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Supplier Details</h2>
                <p class="text-muted mb-0">Update record for <strong><?= htmlspecialchars($row['supplier_name']); ?></strong> (#<?= $row['id']; ?>)</p>
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
                            <input type="text" name="supplier_code" class="form-control font-monospace" value="<?= htmlspecialchars($row['supplier_code']); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Supplier Name <span class="text-danger">*</span></label>
                            <input type="text" name="supplier_name" class="form-control" value="<?= htmlspecialchars($row['supplier_name']); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Contact Person</label>
                            <input type="text" name="contact_person" class="form-control" value="<?= htmlspecialchars($row['contact_person'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Contact Number</label>
                            <input type="text" name="contact" class="form-control" value="<?= htmlspecialchars($row['contact']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Email Address</label>
                            <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($row['email']); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">GSTIN / Tax ID</label>
                            <input type="text" name="gst_number" class="form-control text-uppercase font-monospace" value="<?= htmlspecialchars($row['gst_number'] ?? ''); ?>">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Payment Terms</label>
                            <select name="payment_terms" class="form-select">
                                <option value="Advance" <?= (($row['payment_terms'] ?? '') == 'Advance') ? 'selected' : ''; ?>>Advance Payment</option>
                                <option value="Net 15" <?= (($row['payment_terms'] ?? '') == 'Net 15') ? 'selected' : ''; ?>>Net 15 Days</option>
                                <option value="Net 30" <?= (($row['payment_terms'] ?? '') == 'Net 30') ? 'selected' : ''; ?>>Net 30 Days</option>
                                <option value="Net 60" <?= (($row['payment_terms'] ?? '') == 'Net 60') ? 'selected' : ''; ?>>Net 60 Days</option>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Active" <?= (($row['status'] ?? 'Active') == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?= (($row['status'] ?? 'Active') == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Full Address</label>
                            <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($row['address']); ?></textarea>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between align-items-center">
                        <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" name="update" class="btn btn-warning px-4 fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Update Supplier</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>