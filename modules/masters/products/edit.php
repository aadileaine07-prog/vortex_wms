<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Product ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$product = mysqli_query($conn, "SELECT * FROM products WHERE id='$id'");

if (!$product || mysqli_num_rows($product) == 0) {
    $_SESSION['error'] = "Product Not Found.";
    header("Location: index.php");
    exit();
}

$row = mysqli_fetch_assoc($product);

if (isset($_POST['update'])) {

    $product_code  = mysqli_real_escape_string($conn, $_POST['product_code']);
    $product_name  = mysqli_real_escape_string($conn, $_POST['product_name']);
    $category      = mysqli_real_escape_string($conn, $_POST['category']);
    $brand         = mysqli_real_escape_string($conn, $_POST['brand']);
    $uom           = mysqli_real_escape_string($conn, $_POST['uom']);
    $mrp           = floatval($_POST['mrp']);
    $selling_price = floatval($_POST['selling_price']);
    $status        = mysqli_real_escape_string($conn, $_POST['status']);

    $updateSql = "
        UPDATE products SET
            product_code  = '$product_code',
            product_name  = '$product_name',
            category      = '$category',
            brand         = '$brand',
            uom           = '$uom',
            mrp           = '$mrp',
            selling_price = '$selling_price',
            status        = '$status'
        WHERE id = '$id'
    ";

    if (mysqli_query($conn, $updateSql)) {
        $_SESSION['success'] = "Product updated successfully.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to update product: " . mysqli_error($conn);
    }
}

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Product</h2>
                <p class="text-muted mb-0">Update item details (#<?= $row['id']; ?>)</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product Code <span class="text-danger">*</span></label>
                            <input type="text" name="product_code" class="form-control" value="<?= htmlspecialchars($row['product_code']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="product_name" class="form-control" value="<?= htmlspecialchars($row['product_name']); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Category</label>
                            <input type="text" name="category" class="form-control" value="<?= htmlspecialchars($row['category'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Brand</label>
                            <input type="text" name="brand" class="form-control" value="<?= htmlspecialchars($row['brand'] ?? ''); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">UOM</label>
                            <input type="text" name="uom" class="form-control" value="<?= htmlspecialchars($row['uom'] ?? 'PCS'); ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">MRP (₹)</label>
                            <input type="number" step="0.01" name="mrp" class="form-control" value="<?= $row['mrp'] ?? 0; ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Selling Price (₹)</label>
                            <input type="number" step="0.01" name="selling_price" class="form-control" value="<?= $row['selling_price'] ?? 0; ?>">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Active" <?= (($row['status'] ?? 'Active') == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?= (($row['status'] ?? 'Active') == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" name="update" class="btn btn-warning px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Update Product</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>