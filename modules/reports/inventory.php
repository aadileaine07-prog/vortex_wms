<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$whTable = "warehouse";
$chk = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouse'");
if (!$chk || mysqli_num_rows($chk) == 0) $whTable = "warehouses";

$invList = @mysqli_query($conn, "
    SELECT 
        i.*,
        p.product_name,
        p.sku,
        COALESCE(w.warehouse_name, w.name, 'Facility') AS warehouse_name
    FROM inventory i
    LEFT JOIN products p ON p.id = i.product_id
    LEFT JOIN {$whTable} w ON w.id = i.warehouse_id
    ORDER BY i.available_qty ASC
");

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-boxes-stacked text-success me-2"></i>Inventory Valuation & Stock Report</h2>
            <p class="text-muted mb-0">Comprehensive stock status across active warehouse coordinates</p>
        </div>
        <div class="d-flex gap-2">
            <button onclick="window.print()" class="btn btn-outline-success rounded-pill px-3 shadow-sm"><i class="fa-solid fa-print me-1"></i> Print Ledger</button>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 bg-white">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Warehouse</th>
                            <th>Bin Location</th>
                            <th>Available Qty</th>
                            <th>Health Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($invList && mysqli_num_rows($invList) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($invList)): ?>
                                <tr>
                                    <td><code class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['sku'] ?? 'SKU-00'); ?></code></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($row['product_name'] ?? 'Master Item'); ?></td>
                                    <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['warehouse_name']); ?></span></td>
                                    <td><span class="badge bg-primary-subtle text-primary font-monospace"><?= htmlspecialchars($row['bin_location'] ?? 'L0-A1-001-01-A'); ?></span></td>
                                    <td class="fw-bold fs-6 text-dark"><?= number_format($row['available_qty'] ?? 0); ?></td>
                                    <td>
                                        <?php if (($row['available_qty'] ?? 0) <= 0): ?>
                                            <span class="badge bg-danger-subtle text-danger px-3 py-1 rounded-pill">Out of Stock</span>
                                        <?php elseif (($row['available_qty'] ?? 0) <= 10): ?>
                                            <span class="badge bg-warning-subtle text-warning px-3 py-1 rounded-pill">Low Stock</span>
                                        <?php else: ?>
                                            <span class="badge bg-success-subtle text-success px-3 py-1 rounded-pill">Healthy</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center py-4 text-muted">No inventory records found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>