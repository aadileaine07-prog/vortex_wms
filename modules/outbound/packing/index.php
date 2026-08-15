<?php
session_start();

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch Picked Orders awaiting packing
$result = mysqli_query($conn, "
    SELECT 
        p.id AS picking_id,
        p.sales_order_id,
        p.picking_number,
        p.picker_name,
        p.picking_date,
        s.order_number,
        s.customer_name,
        s.status AS order_status,
        COUNT(pi.id) AS total_items,
        SUM(pi.picked_qty) AS total_units
    FROM picking p
    INNER JOIN sales_orders s ON p.sales_order_id = s.id
    LEFT JOIN picking_items pi ON p.id = pi.picking_id
    WHERE p.status = 'Completed' 
      AND s.status IN ('Picked', 'Picking', 'Partially Picked')
    GROUP BY p.id
    ORDER BY p.id DESC
");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-boxes-packing text-warning me-2"></i>Packing Station
                </h2>
                <p class="text-muted mb-0">Verify picked items, box packaging, and prepare for shipment dispatch</p>
            </div>
            <a href="../picking/index.php" class="btn btn-outline-secondary px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Picking Orders
            </a>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3">
                <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th>Picking #</th>
                                <th>Sales Order</th>
                                <th>Customer Name</th>
                                <th>Picked Items</th>
                                <th>Picked Date</th>
                                <th class="text-center" width="180">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><span class="badge bg-secondary font-monospace fs-6"><?= htmlspecialchars($row['picking_number']); ?></span></td>
                                        <td><strong><?= htmlspecialchars($row['order_number']); ?></strong></td>
                                        <td><?= htmlspecialchars($row['customer_name']); ?></td>
                                        <td>
                                            <span class="badge bg-light text-dark border">
                                                <?= $row['total_items']; ?> SKUs (<?= $row['total_units']; ?> Units)
                                            </span>
                                        </td>
                                        <td><?= date("d M Y", strtotime($row['picking_date'])); ?></td>
                                        <td class="text-center">
                                            <a href="start.php?id=<?= $row['picking_id']; ?>" class="btn btn-warning btn-sm px-3 fw-bold shadow-sm">
                                                <i class="fa-solid fa-box-open me-1"></i> Start Packing
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-box-check fs-2 text-success d-block mb-2"></i>
                                        No pending picked orders for packing.
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