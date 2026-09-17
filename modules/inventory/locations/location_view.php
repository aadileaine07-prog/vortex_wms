<?php
require_once "../../../config/database.php";
include_once "../../../includes/header.php"; //[cite: 2]

$locationCode = isset($_GET['location']) ? mysqli_real_escape_string($conn, $_GET['location']) : '';
$items = [];

if (!empty($locationCode) && isset($conn)) {
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'inventory'");
    if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
        $res = @mysqli_query($conn, "SELECT * FROM inventory WHERE location = '$locationCode'");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $items[] = $row;
            }
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-warehouse me-2 text-primary"></i>Location View: <?= htmlspecialchars($locationCode ?: 'Unspecified'); ?></h3>
            <p class="text-muted small mb-0">Inspect items and stock units currently assigned to this storage bin or rack.</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-3">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Inventory
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h5 class="fw-bold mb-3">Stored Inventory Items</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#ID</th>
                        <th>Item Name / SKU</th>
                        <th>Quantity</th>
                        <th>Category</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($items)): ?>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?= sprintf("#%03d", $item['id']); ?></td>
                                <td class="fw-bold font-mono"><?= htmlspecialchars($item['item_name'] ?? 'SKU-' . $item['id']); ?></td>
                                <td class="font-mono"><?= htmlspecialchars($item['quantity'] ?? 0); ?> Units</td>
                                <td><span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($item['category'] ?? 'General'); ?></span></td>
                                <td class="text-end">
                                    <a href="item_details.php?id=<?= $item['id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                        <i class="fa-solid fa-eye me-1"></i> Details
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="5" class="text-center text-muted p-4">No items found assigned to this specific location.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
include_once "../../../includes/footer.php"; //[cite: 1]
?>