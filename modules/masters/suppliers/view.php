<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

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

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-circle-info text-primary me-2"></i>Supplier Details</h2>
                <p class="text-muted mb-0">Supplier Code: <?= htmlspecialchars($supplier['supplier_code']); ?></p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">

                <table class="table table-bordered align-middle">
                    <tr>
                        <th width="220" class="bg-light">Supplier Code</th>
                        <td><span class="badge bg-light text-dark border fs-6"><?= htmlspecialchars($supplier['supplier_code']); ?></span></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Supplier Name</th>
                        <td><strong><?= htmlspecialchars($supplier['supplier_name']); ?></strong></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Contact Person</th>
                        <td><?= htmlspecialchars($supplier['contact_person'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Contact Number</th>
                        <td><?= htmlspecialchars($supplier['contact'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Email Address</th>
                        <td><?= htmlspecialchars($supplier['email'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <th class="bg-light">GSTIN / Tax ID</th>
                        <td><span class="font-monospace text-uppercase fw-bold"><?= htmlspecialchars($supplier['gst_number'] ?? '-'); ?></span></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Payment Terms</th>
                        <td><span class="badge bg-info text-dark"><?= htmlspecialchars($supplier['payment_terms'] ?? 'Net 30'); ?></span></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Address</th>
                        <td><?= nl2br(htmlspecialchars($supplier['address'] ?: '-')); ?></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Status</th>
                        <td>
                            <?php if (($supplier['status'] ?? 'Active') == 'Active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
                    <div>
                        <a href="edit.php?id=<?= $supplier['id']; ?>" class="btn btn-warning px-3 me-2"><i class="fa-solid fa-pen-to-square me-1"></i> Edit</a>
                        <a href="delete.php?id=<?= $supplier['id']; ?>" class="btn btn-danger px-3" onclick="return confirm('Delete this Supplier?');"><i class="fa-solid fa-trash me-1"></i> Delete</a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>