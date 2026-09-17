<?php
require_once "../../../config/database.php";
include_once "../../../includes/header.php"; //[cite: 2]

$putaways = [];
if (isset($conn)) {
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'putaway'");
    if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
        $res = @mysqli_query($conn, "SELECT * FROM putaway ORDER BY id DESC");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $putaways[] = $row;
            }
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-people-carry-box me-2 text-info"></i>Inbound Putaway Tasks</h3>
            <p class="text-muted small mb-0">Assign and track stock movement from receiving docks to permanent storage bins.</p>
        </div>
        <div>
            <a href="assign.php" class="btn btn-info text-white rounded-pill px-4 fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New Putaway Task
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h5 class="fw-bold mb-3">Putaway Tasks Directory</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#ID</th>
                        <th>Item / SKU</th>
                        <th>Quantity</th>
                        <th>Target Bin / Location</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($putaways)): ?>
                        <?php foreach ($putaways as $p): ?>
                            <tr>
                                <td><?= sprintf("#%03d", $p['id']); ?></td>
                                <td class="fw-bold font-mono"><?= htmlspecialchars($p['item_name'] ?? 'General Stock'); ?></td>
                                <td class="font-mono"><?= htmlspecialchars($p['quantity'] ?? 0); ?> Units</td>
                                <td><span class="badge bg-secondary-subtle text-dark border px-2 py-1 font-mono"><?= htmlspecialchars($p['target_location'] ?? 'Unassigned'); ?></span></td>
                                <td>
                                    <?php 
                                    $status = $p['status'] ?? 'Pending';
                                    $badgeBg = ($status === 'Completed') ? 'bg-success-subtle text-success' : 'bg-warning-subtle text-warning';
                                    ?>
                                    <span class="badge <?= $badgeBg; ?> px-2 py-1"><?= htmlspecialchars($status); ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="view.php?id=<?= $p['id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                        <i class="fa-solid fa-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">No active putaway tasks found in the database.</td>
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