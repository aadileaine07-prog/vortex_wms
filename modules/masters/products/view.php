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

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-box text-primary me-2"></i>Inventory Item Details
                </h2>
                <p class="text-muted mb-0">Item ID: #<?= $item['id']; ?></p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print();" class="btn btn-dark"><i class="fa-solid fa-print me-1"></i> Print</button>
                <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <div class="row g-4">
            
            <!-- Summary Card -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-3 text-center p-4">
                    <div class="display-1 text-primary mb-2">📦</div>
                    <h4 class="fw-bold mb-1"><?= htmlspecialchars($item['product_name']); ?></h4>
                    <p class="text-muted mb-2">Code: <span class="badge bg-light text-dark border"><?= htmlspecialchars($item['product_code']); ?></span></p>
                    
                    <div class="mt-3">
                        <?php
                        $st = $item['status'];
                        if ($st == "In Stock") echo "<span class='badge bg-success fs-6 px-3 py-2'><i class='fa-solid fa-check-circle me-1'></i> In Stock</span>";
                        elseif ($st == "Low Stock") echo "<span class='badge bg-warning text-dark fs-6 px-3 py-2'><i class='fa-solid fa-triangle-exclamation me-1'></i> Low Stock</span>";
                        else echo "<span class='badge bg-danger fs-6 px-3 py-2'><i class='fa-solid fa-times-circle me-1'></i> Out Of Stock</span>";
                        ?>
                    </div>

                    <hr class="my-4">

                    <div class="d-grid gap-2">
                        <a href="stock_adjustment/create.php" class="btn btn-warning fw-semibold">
                            <i class="fa-solid fa-sliders me-1"></i> Adjust Stock
                        </a>
                    </div>
                </div>
            </div>

            <!-- Detailed Information -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-body p-4">
                        <h5 class="fw-bold text-dark mb-3">Warehouse & Stock Information</h5>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block text-uppercase fw-semibold">Warehouse</small>
                                    <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($item['warehouse']); ?></span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block text-uppercase fw-semibold">Bin Location</small>
                                    <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($item['bin_location']); ?></span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block text-uppercase fw-semibold">Available Quantity</small>
                                    <span class="fw-bold text-primary fs-5"><?= $item['available_qty']; ?> Units</span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block text-uppercase fw-semibold">Reserved Quantity</small>
                                    <span class="fw-bold text-warning fs-5"><?= $item['reserved_qty']; ?> Units</span>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="edit.php?id=<?= $item['id']; ?>" class="btn btn-warning px-4"><i class="fa-solid fa-pen-to-square me-1"></i> Edit Item</a>
                            <a href="index.php" class="btn btn-outline-secondary px-3">Back to List</a>
                        </div>

                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<?php include "../../includes/footer.php"; ?>