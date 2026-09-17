<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

include $projectRoot . "/includes/header.php";

// Simplified and robust query to fetch all inventory records reliably
$query = "
    SELECT 
        i.*, 
        COALESCE(i.product_name, 'Catalog Item') as item_name,
        COALESCE(i.product_code, 'SKU-N/A') as item_sku,
        COALESCE(i.bin_location, 'Unassigned') as resolved_bin,
        COALESCE(i.available_qty, i.quantity, 0) as stock_qty
    FROM inventory i
    ORDER BY i.id DESC
";
$result = mysqli_query($conn, $query);
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Inventory Stock View</h2>
            <p class="text-muted mb-0">Comprehensive ledger of all stored items across warehouse bins and locations synchronized with master catalog</p>
        </div>
        <div>
            <a href="/vortex_wms/modules/inventory/index.php" class="btn btn-secondary rounded-pill px-3 fw-bold shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>
    </div>

    <div class="card shadow-sm border-0 rounded-4 bg-white">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>#ID</th>
                            <th>Item Name / SKU</th>
                            <th>Location / Bin</th>
                            <th>Quantity</th>
                            <th>Warehouse</th>
                            <th class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td class="font-monospace text-muted">#<?= $row['id']; ?></td>
                                    <td>
                                        <div class="fw-bold text-dark"><?= htmlspecialchars($row['item_name']); ?></div>
                                        <small class="font-monospace text-primary"><?= htmlspecialchars($row['item_sku']); ?></small>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border font-monospace px-2 py-1">
                                            <i class="fa-solid fa-location-dot text-danger me-1"></i><?= htmlspecialchars($row['resolved_bin']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="fw-bold text-success font-monospace"><?= (int)$row['stock_qty']; ?> Units</span>
                                    </td>
                                    <td>
                                        <span class="badge bg-secondary-subtle text-dark border px-2 py-1"><?= htmlspecialchars($row['warehouse'] ?? 'Surat S1'); ?></span>
                                    </td>
                                    <td class="text-center">
                                        <a href="item_details.php?id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-primary rounded-pill px-3 fw-bold shadow-sm">
                                            <i class="fa-solid fa-eye me-1"></i> Details
                                        </a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" class="text-center py-5 text-muted">
                                    <i class="fa-solid fa-box-open fs-2 mb-2 d-block opacity-25"></i>
                                    No active inventory stock records found.
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>