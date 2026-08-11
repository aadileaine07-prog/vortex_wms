<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$result = mysqli_query($conn, "
    SELECT *
    FROM sales_orders
    WHERE status='Pending'
    ORDER BY id DESC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-hand-holding-hand text-primary me-2"></i>Picking Orders
                </h2>
                <p class="text-muted mb-0">Select pending sales orders to begin item picking</p>
            </div>
            <div>
                <a href="../sales_order/index.php" class="btn btn-outline-secondary px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Sales Orders
                </a>
            </div>
        </div>

        <!-- Session Messages -->
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
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">

                <!-- Search Filter -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search Order No or Customer...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="pickingTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">ID</th>
                                <th>Order No</th>
                                <th>Customer</th>
                                <th>Order Date</th>
                                <th>Status</th>
                                <th width="180" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['order_number']); ?></span></td>
                                        <td><strong><?= htmlspecialchars($row['customer_name']); ?></strong></td>
                                        <td><?= date("d-m-Y", strtotime($row['order_date'])); ?></td>
                                        <td>
                                            <?php
                                            if ($row['status'] == "Pending") {
                                                echo '<span class="badge bg-warning text-dark">Pending</span>';
                                            } else {
                                                echo '<span class="badge bg-success">' . htmlspecialchars($row['status']) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="start.php?id=<?= $row['id']; ?>" class="btn btn-success btn-sm px-3 shadow-sm">
                                                <i class="fa-solid fa-play me-1"></i> Start Picking
                                            </a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center text-muted py-4'>No Pending Orders for Picking</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll("#pickingTable tbody tr");

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});
</script>