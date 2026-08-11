<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

$query = "
    SELECT 
        p.product_code,
        p.product_name,
        p.category,
        p.uom,
        COALESCE(SUM(i.available_qty), 0) AS total_available,
        COALESCE(SUM(i.allocated_qty), 0) AS total_allocated
    FROM products p
    LEFT JOIN inventory i ON p.id = i.product_id
    GROUP BY p.id
    ORDER BY p.product_name ASC
";

$result = mysqli_query($conn, $query);

include "../../includes/header.php";
include "../../includes/navbar.php";
include "../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-chart-column text-primary me-2"></i>Inventory Stock Report
                </h2>
                <p class="text-muted mb-0">Overview of available stock levels across products</p>
            </div>
            <div>
                <button onclick="window.print()" class="btn btn-outline-dark px-3 me-2">
                    <i class="fa-solid fa-print me-1"></i> Print Report
                </button>
                <a href="../../dashboard.php" class="btn btn-secondary px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">

                <div class="table-responsive">
                    <table class="table table-hover align-middle border">
                        <thead class="table-dark">
                            <tr>
                                <th>Product Code</th>
                                <th>Product Name</th>
                                <th>Category</th>
                                <th>UOM</th>
                                <th>Available Stock</th>
                                <th>Allocated Stock</th>
                                <th>Total Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php $total = $row['total_available'] + $row['total_allocated']; ?>
                                    <tr>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['product_code']); ?></span></td>
                                        <td><strong><?= htmlspecialchars($row['product_name']); ?></strong></td>
                                        <td><?= htmlspecialchars($row['category'] ?: '-'); ?></td>
                                        <td><?= htmlspecialchars($row['uom'] ?: 'PCS'); ?></td>
                                        <td><span class="badge bg-success fs-6"><?= $row['total_available']; ?></span></td>
                                        <td><span class="badge bg-warning text-dark fs-6"><?= $row['total_allocated']; ?></span></td>
                                        <td><strong><?= $total; ?></strong></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No Stock Records Found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include "../../includes/footer.php"; ?>