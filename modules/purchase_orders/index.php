<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch Purchase Orders with Supplier details
$result = mysqli_query($conn, "
    SELECT 
        po.*,
        s.supplier_name,
        s.supplier_code
    FROM purchase_orders po
    LEFT JOIN suppliers s ON po.supplier_id = s.id
    ORDER BY po.id DESC
");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Purchase Orders (PO)</h2>
                <p class="text-muted mb-0">Manage supplier procurement orders, delivery schedules, and inbound status</p>
            </div>
            <div>
                <a href="create.php" class="btn btn-primary px-3 shadow-sm fw-bold">
                    <i class="fa-solid fa-cart-plus me-1"></i> Create New PO
                </a>
            </div>
        </div>

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

        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">

                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search PO Number or Supplier...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="poTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">ID</th>
                                <th>PO Number</th>
                                <th>Supplier Name</th>
                                <th>Order Date</th>
                                <th>Expected Delivery</th>
                                <th>Total Amount</th>
                                <th>Status</th>
                                <th width="180" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td><span class="badge bg-secondary font-monospace fs-6"><?= htmlspecialchars($row['po_number']); ?></span></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['supplier_name'] ?? 'Unassigned'); ?></strong>
                                            <small class="d-block text-muted">(<?= htmlspecialchars($row['supplier_code'] ?? 'N/A'); ?>)</small>
                                        </td>
                                        <td><?= date("d M Y", strtotime($row['order_date'])); ?></td>
                                        <td><?= $row['expected_date'] ? date("d M Y", strtotime($row['expected_date'])) : '-'; ?></td>
                                        <td><strong class="text-success">₹<?= number_format($row['total_amount'], 2); ?></strong></td>
                                        <td>
                                            <?php 
                                                $st = $row['status'] ?? 'Pending';
                                                if ($st == 'Completed' || $st == 'Received') echo '<span class="badge bg-success px-3 py-1">Received</span>';
                                                elseif ($st == 'Cancelled') echo '<span class="badge bg-danger px-3 py-1">Cancelled</span>';
                                                else echo '<span class="badge bg-warning text-dark px-3 py-1">Pending Inbound</span>';
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm text-white me-1" title="View PO Details"><i class="fa-solid fa-eye"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this Purchase Order?');" title="Delete PO"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-5">
                                        <i class="fa-solid fa-file-invoice-dollar fs-2 d-block mb-2 text-secondary"></i>
                                        No Purchase Orders Found. Click "Create New PO" to get started.
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
    let rows = document.querySelectorAll("#poTable tbody tr");

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});
</script>