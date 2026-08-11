<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

/* ===============================
   Dashboard Statistics
================================ */

$totalProducts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM inventory"))['total'] ?? 0;
$totalStock    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(available_qty),0) total FROM inventory"))['total'] ?? 0;
$lowStock      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM inventory WHERE available_qty > 0 AND available_qty <= 10"))['total'] ?? 0;
$outStock      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM inventory WHERE available_qty = 0"))['total'] ?? 0;

/* Distinct Warehouses for Dropdown Filter */
$warehouses = mysqli_query($conn, "SELECT DISTINCT warehouse FROM inventory WHERE warehouse IS NOT NULL AND warehouse != '' ORDER BY warehouse ASC");

/* Inventory List Query */
$result = mysqli_query($conn, "SELECT * FROM inventory ORDER BY id DESC");

include "../../includes/header.php";
include "../../includes/navbar.php";
include "../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header & Action Buttons -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Inventory Dashboard
                </h2>
                <p class="text-muted mb-0">Real Time Warehouse Inventory Overview</p>
            </div>
            <div class="d-flex gap-2">
                <a href="add.php" class="btn btn-success px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Add Stock
                </a>
                <a href="stock_adjustment/create.php" class="btn btn-warning px-3 shadow-sm">
                    <i class="fa-solid fa-sliders me-1"></i> Stock Adjustment
                </a>
                <a href="pdf/export_inventory.php" class="btn btn-danger px-3 shadow-sm" target="_blank">
                    <i class="fa-solid fa-file-pdf me-1"></i> Export PDF
                </a>
            </div>
        </div>

        <!-- Success/Error Alert Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Stat Cards Row -->
        <div class="row g-3 mb-4">
            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-0 bg-primary text-white rounded-3">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-bold">Total Products</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= number_format($totalProducts); ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-0 bg-success text-white rounded-3">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-bold">Total Available Stock</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= number_format($totalStock); ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-0 bg-warning text-dark rounded-3">
                    <div class="card-body p-3">
                        <small class="text-dark-50 text-uppercase fw-bold">Low Stock Items</small>
                        <h3 class="fw-bold mb-0 mt-1 text-dark"><?= number_format($lowStock); ?></h3>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6">
                <div class="card shadow-sm border-0 bg-danger text-white rounded-3">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-bold">Out Of Stock</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= number_format($outStock); ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Table Card -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">

                <!-- Live Filters -->
                <div class="row g-2 mb-3">
                    <div class="col-md-6">
                        <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search Product Code or Name...">
                    </div>

                    <div class="col-md-3">
                        <select id="warehouseSelect" class="form-select">
                            <option value="">All Warehouses</option>
                            <?php while ($wh = mysqli_fetch_assoc($warehouses)): ?>
                                <option value="<?= htmlspecialchars($wh['warehouse']); ?>">
                                    <?= htmlspecialchars($wh['warehouse']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-3">
                        <select id="statusSelect" class="form-select">
                            <option value="">All Status</option>
                            <option value="In Stock">In Stock</option>
                            <option value="Low Stock">Low Stock</option>
                            <option value="Out Of Stock">Out Of Stock</option>
                        </select>
                    </div>
                </div>

                <!-- Inventory Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="inventoryTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">ID</th>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Warehouse</th>
                                <th>Bin</th>
                                <th width="120">Available</th>
                                <th width="120">Reserved</th>
                                <th width="120">Status</th>
                                <th width="150" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['product_code']); ?></span></td>
                                        <td><strong><?= htmlspecialchars($row['product_name']); ?></strong></td>
                                        <td class="wh-cell"><?= htmlspecialchars($row['warehouse']); ?></td>
                                        <td><?= htmlspecialchars($row['bin_location']); ?></td>
                                        <td>
                                            <span class="badge bg-primary fs-6 px-2 py-1"><?= $row['available_qty']; ?></span>
                                        </td>
                                        <td>
                                            <span class="badge bg-warning text-dark fs-6 px-2 py-1"><?= $row['reserved_qty']; ?></span>
                                        </td>
                                        <td class="status-cell">
                                            <?php
                                            $st = $row['status'];
                                            if ($st == "In Stock") {
                                                echo "<span class='badge bg-success'>In Stock</span>";
                                            } elseif ($st == "Low Stock") {
                                                echo "<span class='badge bg-warning text-dark'>Low Stock</span>";
                                            } else {
                                                echo "<span class='badge bg-danger'>Out Of Stock</span>";
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm text-white"><i class="fa-solid fa-eye"></i> View</a>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='9' class='text-center text-muted py-4'>No Inventory Items Found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

            </div>

            <!-- Footer Bar -->
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="../../index.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
                <a href="export.php" class="btn btn-success">
                    <i class="fa-solid fa-file-excel me-1"></i> Export Excel
                </a>
            </div>
        </div>

    </div>
</div>

<?php include "../../includes/footer.php"; ?>

<script>
// Combined JavaScript Filter (Search + Warehouse Dropdown + Status Dropdown)
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("searchInput");
    const warehouseSelect = document.getElementById("warehouseSelect");
    const statusSelect = document.getElementById("statusSelect");

    function filterTable() {
        const searchVal = searchInput.value.toLowerCase();
        const warehouseVal = warehouseSelect.value.toLowerCase();
        const statusVal = statusSelect.value.toLowerCase();
        const rows = document.querySelectorAll("#inventoryTable tbody tr");

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const whText = row.querySelector(".wh-cell") ? row.querySelector(".wh-cell").innerText.toLowerCase() : "";
            const statusText = row.querySelector(".status-cell") ? row.querySelector(".status-cell").innerText.toLowerCase() : "";

            const matchesSearch = text.includes(searchVal);
            const matchesWarehouse = warehouseVal === "" || whText.includes(warehouseVal);
            const matchesStatus = statusVal === "" || statusText.includes(statusVal);

            row.style.display = (matchesSearch && matchesWarehouse && matchesStatus) ? "" : "none";
        });
    }

    if (searchInput) searchInput.addEventListener("keyup", filterTable);
    if (warehouseSelect) warehouseSelect.addEventListener("change", filterTable);
    if (statusSelect) statusSelect.addEventListener("change", filterTable);
});
</script>