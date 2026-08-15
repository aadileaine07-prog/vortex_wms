<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch Pending and Partially Picked Sales Orders
$result = mysqli_query($conn, "
    SELECT 
        so.*,
        COUNT(soi.id) AS total_items,
        SUM(soi.ordered_qty) AS total_ordered_qty,
        COALESCE(SUM(soi.picked_qty), 0) AS total_picked_qty
    FROM sales_orders so
    LEFT JOIN sales_order_items soi ON so.id = soi.sales_order_id
    WHERE so.status IN ('Pending', 'Approved', 'Partially Picked')
    GROUP BY so.id
    ORDER BY so.id DESC
");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-hand-holding-hand text-primary me-2"></i>Picking Orders List
                </h2>
                <p class="text-muted mb-0">Select orders to assign and begin item picking from warehouse bins</p>
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
        <div class="card shadow-sm border-0 rounded-4">
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
                                <th>Customer Name</th>
                                <th>Order Date</th>
                                <th>Total Items</th>
                                <th>Status</th>
                                <th width="180" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td><span class="badge bg-secondary font-monospace fs-6"><?= htmlspecialchars($row['order_number']); ?></span></td>
                                        <td><strong><?= htmlspecialchars($row['customer_name']); ?></strong></td>
                                        <td><?= date("d M Y", strtotime($row['order_date'])); ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= $row['total_items']; ?> SKUs (<?= $row['total_ordered_qty']; ?> Units)
                                            </span>
                                        </td>
                                        <td>
                                            <?php
                                            $st = $row['status'];
                                            if ($st == 'Pending' || $st == 'Approved') {
                                                echo '<span class="badge bg-warning text-dark px-3 py-2"><i class="fa-solid fa-clock me-1"></i> Ready to Pick</span>';
                                            } elseif ($st == 'Partially Picked') {
                                                echo '<span class="badge bg-info text-dark px-3 py-2"><i class="fa-solid fa-spinner me-1"></i> Partial Picked</span>';
                                            } else {
                                                echo '<span class="badge bg-success px-3 py-2">' . htmlspecialchars($st) . '</span>';
                                            }
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="start.php?id=<?= $row['id']; ?>" class="btn btn-success btn-sm px-3 shadow-sm fw-bold">
                                                <i class="fa-solid fa-play me-1"></i> Start Picking
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-circle-check fs-2 text-success d-block mb-2"></i>
                                        No Pending Orders for Picking
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll("#pickingTable tbody tr");

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});
</script>