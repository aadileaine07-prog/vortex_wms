<?php
session_start();
if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}
require_once "../../config/database.php";

include "../../includes/header.php";
include "../../includes/navbar.php";
include "../../includes/sidebar.php";
?>

<div class="content main-content">
    <div class="container-fluid">
        <div class="card shadow border-0">
            <div class="card-header bg-primary text-white" style="background-color: #00b4d8 !important;">
                <h3 class="mb-0" style="font-size: 20px; font-weight: 600;">📦 Add New Inventory Stock</h3>
            </div>
            <div class="card-body p-4">
                <form action="save.php" method="POST">
                    <input type="hidden" name="action" value="add">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Product Code</label>
                            <input type="text" name="product_code" class="form-control" placeholder="E.g. PROD101" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Product Name</label>
                            <input type="text" name="product_name" class="form-control" placeholder="Product Description" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Warehouse</label>
                            <input type="text" name="warehouse" class="form-control" placeholder="E.g. Main Warehouse" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Bin Location</label>
                            <input type="text" name="bin_location" class="form-control" placeholder="E.g. A-12" required>
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Available Quantity</label>
                            <input type="number" name="available_qty" class="form-control" min="0" value="0" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label fw-bold">Reserved Quantity</label>
                            <input type="number" name="reserved_qty" class="form-control" min="0" value="0" required>
                        </div>
                    </div>

                    <hr class="my-4">
                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-secondary px-4">← Back</a>
                        <button type="submit" class="btn btn-success px-4">Save Stock</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include "../../includes/footer.php"; ?>