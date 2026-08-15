<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$result = mysqli_query($conn, "
    SELECT *
    FROM stock_adjustment
    ORDER BY id DESC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-boxes-packing text-primary me-2"></i>Stock Adjustments
                </h2>
                <p class="text-muted mb-0">Track and manage manual inventory stock adjustments</p>
            </div>
            <div>
                <a href="create.php" class="btn btn-success px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> New Adjustment
                </a>
            </div>
        </div>

        <!-- Session Alert Messages -->
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

        <!-- Table Card -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">

                <!-- Search Input -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search Product Code or Name...">
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="adjustmentTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">ID</th>
                                <th>Product</th>
                                <th>Warehouse</th>
                                <th>Bin Location</th>
                                <th>Type</th>
                                <th width="100">Qty</th>
                                <th width="130">Date</th>
                                <th width="160" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['product_name']); ?></strong><br>
                                            <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['product_code']); ?></span>
                                        </td>
                                        <td><?= htmlspecialchars($row['warehouse']); ?></td>
                                        <td><code><?= htmlspecialchars($row['bin_location']); ?></code></td>
                                        <td>
                                            <?php if ($row['adjustment_type'] == "Increase"): ?>
                                                <span class="badge bg-success px-2 py-1"><i class="fa-solid fa-arrow-up me-1"></i>Increase</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger px-2 py-1"><i class="fa-solid fa-arrow-down me-1"></i>Decrease</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <span class="badge bg-primary px-3 py-1 fs-6"><?= $row['quantity']; ?></span>
                                        </td>
                                        <td><?= date("d-m-Y", strtotime($row['adjustment_date'])); ?></td>
                                        <td class="text-center">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm text-white me-1"><i class="fa-solid fa-eye"></i> View</a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure? Deleting this adjustment will revert stock changes!');"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center text-muted py-4'>No Adjustment Records Found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

            </div>
            <div class="card-footer bg-light d-flex justify-content-between align-items-center p-3 rounded-bottom-4">
                <a href="../index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory</a>
                <a href="create.php" class="btn btn-success"><i class="fa-solid fa-plus me-1"></i> New Adjustment</a>
            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll("#adjustmentTable tbody tr");

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});
</script>