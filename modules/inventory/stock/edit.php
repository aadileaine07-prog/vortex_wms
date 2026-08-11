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

include "../../includes/header.php";
include "../../includes/navbar.php";
include "../../includes/sidebar.php";
?>

<div class="content main-content">
    <div class="container-fluid">
        <div class="card shadow border-0">
            <div class="card-header bg-warning text-dark">
                <h3 class="mb-0" style="font-size: 20px; font-weight: 600;">📝 Edit Inventory Stock (ID: <?= $row['id']; ?>)</h3>
            </div>
            <div class="card-body p-4">
                <form action="save.php" method="POST">
                    <input type="hidden" name="action" value="edit">
                    <input type="hidden" name="id" value="<?= $row['id']; ?>">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Product Code</label>
                            <input type="text" name="product_code" class="form-control" value="<?= $row['product_code']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Product Name</label>
                            <input type="text" name="product_name" class="form-control" value="<?= $row['product_name']; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Warehouse</label>
                            <input type="text" name="warehouse" class="form-control" value="<?= $row['warehouse']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Bin Location</label>
                            <input type="text" name="bin_location" class="form-control" value="<?= $row['bin_location']; ?>" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Available Quantity</label>
                            <input type="number" name="available_qty" class="form-control" min="0" value="<?= $row['available_qty']; ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Reserved Quantity</label>
                            <input type="number" name="reserved_qty" class="form-control" min="0" value="<?= $row['reserved_qty']; ?>" required>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary px-4">← Back</a>
                        <button type="submit" class="btn btn-warning px-4">Update Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include "../../includes/footer.php"; ?>