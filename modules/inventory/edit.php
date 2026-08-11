<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$query = "SELECT * FROM inventory WHERE id = '$id'";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Inventory item not found.";
    header("Location: index.php");
    exit();
}

$item = mysqli_fetch_assoc($result);

include "../../includes/header.php";
include "../../includes/navbar.php";
include "../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Inventory Item
                </h2>
                <p class="text-muted mb-0">Update item details for #<?= $item['id']; ?></p>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <!-- Edit Form -->
        <form action="update.php" method="POST">
            <input type="hidden" name="id" value="<?= $item['id']; ?>">

            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product Code</label>
                            <input type="text" name="product_code" class="form-control bg-light" value="<?= htmlspecialchars($item['product_code']); ?>" readonly>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product Name <span class="text-danger">*</span></label>
                            <input type="text" name="product_name" class="form-control" value="<?= htmlspecialchars($item['product_name']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Warehouse <span class="text-danger">*</span></label>
                            <input type="text" name="warehouse" class="form-control" value="<?= htmlspecialchars($item['warehouse']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bin Location <span class="text-danger">*</span></label>
                            <input type="text" name="bin_location" class="form-control" value="<?= htmlspecialchars($item['bin_location']); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Available Qty <span class="text-danger">*</span></label>
                            <input type="number" name="available_qty" class="form-control" value="<?= $item['available_qty']; ?>" min="0" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Reserved Qty <span class="text-danger">*</span></label>
                            <input type="number" name="reserved_qty" class="form-control" value="<?= $item['reserved_qty']; ?>" min="0" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Stock Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="In Stock" <?= ($item['status'] == 'In Stock') ? 'selected' : ''; ?>>In Stock</option>
                                <option value="Low Stock" <?= ($item['status'] == 'Low Stock') ? 'selected' : ''; ?>>Low Stock</option>
                                <option value="Out Of Stock" <?= ($item['status'] == 'Out Of Stock') ? 'selected' : ''; ?>>Out Of Stock</option>
                            </select>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="text-end">
                        <button type="submit" class="btn btn-warning px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Update Item</button>
                        <a href="index.php" class="btn btn-outline-secondary px-3">Cancel</a>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

<?php include "../../includes/footer.php"; ?>