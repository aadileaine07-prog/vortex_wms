<?php
session_start();
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once "../../config/database.php";

if (!isset($_GET['id'])) {
    die("Inventory ID Missing");
}

$id = intval($_GET['id']);
$query = mysqli_query($conn, "SELECT * FROM inventory WHERE id='$id'");
if (mysqli_num_rows($query) == 0) {
    die("Inventory Not Found");
}
$row = mysqli_fetch_assoc($query);

// Active Warehouses fetch karein
$warehouses = mysqli_query($conn, "SELECT id, warehouse_name FROM warehouse WHERE status='Active' ORDER BY warehouse_name ASC");

// Active Bins fetch karein
$bins = mysqli_query($conn, "SELECT id, bin_code FROM bin_locations WHERE status='Active' ORDER BY bin_code ASC");

include "../../includes/header.php";
include "../../includes/navbar.php";
include "../../includes/sidebar.php";
?>

<div class="content main-content">
    <div class="container-fluid p-4">
        <div class="card shadow-sm border-0 rounded-4 col-lg-9 mx-auto">
            <div class="card-header bg-warning text-dark p-3 rounded-top-4">
                <h3 class="mb-0 fw-bold fs-5"><i class="fa-solid fa-pen-to-square me-2"></i>Edit Inventory Stock (ID: #<?= $row['id']; ?>)</h3>
            </div>
            <div class="card-body p-4">
                <form action="save.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $row['id']; ?>">

                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product Code *</label>
                            <input type="text" name="product_code" class="form-control font-monospace" value="<?= htmlspecialchars($row['product_code']); ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Product Name *</label>
                            <input type="text" name="product_name" class="form-control" value="<?= htmlspecialchars($row['product_name']); ?>" required>
                        </div>

                        <!-- Target Warehouse Dropdown -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Warehouse *</label>
                            <select name="warehouse" class="form-select" required>
                                <option value="">-- Select Warehouse --</option>
                                <?php while ($w = mysqli_fetch_assoc($warehouses)): ?>
                                    <option value="<?= htmlspecialchars($w['warehouse_name']); ?>" <?= ($row['warehouse'] == $w['warehouse_name']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($w['warehouse_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <!-- Target Bin Location Dropdown -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bin Location *</label>
                            <select name="bin_location" class="form-select font-monospace" required>
                                <option value="">-- Select Bin --</option>
                                <?php while ($b = mysqli_fetch_assoc($bins)): ?>
                                    <option value="<?= htmlspecialchars($b['bin_code']); ?>" <?= ($row['bin_location'] == $b['bin_code']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($b['bin_code']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Available Quantity *</label>
                            <input type="number" name="available_qty" class="form-control fw-bold text-primary" min="0" value="<?= $row['available_qty']; ?>" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Reserved Quantity *</label>
                            <input type="number" name="reserved_qty" class="form-control fw-bold text-warning" min="0" value="<?= $row['reserved_qty']; ?>" required>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
                        <button type="submit" class="btn btn-warning px-4 fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include "../../includes/footer.php"; ?>