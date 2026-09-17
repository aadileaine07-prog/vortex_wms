<?php
require_once "../../../config/database.php";
include_once "../../../includes/header.php";

$grns = [];
if (isset($conn)) {
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'grn'");
    if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
        $res = @mysqli_query($conn, "SELECT * FROM grn ORDER BY id DESC");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $grns[] = $row;
            }
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-truck-ramp-box me-2 text-success"></i>Goods Received Notes (GRN)</h3>
            <p class="text-muted small mb-0">Manage incoming shipments, verify quantities, and log receipts.</p>
        </div>
        <div>
            <a href="create.php" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New GRN
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h5 class="fw-bold mb-3">Received Shipments Directory</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#ID</th>
                        <th>Reference / PO</th>
                        <th>Supplier</th>
                        <th>Received Date</th>
                        <th>Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($grns)): ?>
                        <?php foreach ($grns as $g): ?>
                            <tr>
                                <td><?= sprintf("#%03d", $g['id']); ?></td>
                                <td class="fw-bold font-mono"><?= htmlspecialchars($g['po_reference'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($g['supplier_name'] ?? 'General Supplier'); ?></td>
                                <td class="font-mono small"><?= htmlspecialchars($g['received_date'] ?? date('Y-m-d')); ?></td>
                                <td><span class="badge bg-success-subtle text-success px-2 py-1"><?= htmlspecialchars($g['status'] ?? 'Completed'); ?></span></td>
                                <td class="text-end">
                                    <a href="view.php?id=<?= $g['id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                        <i class="fa-solid fa-eye me-1"></i> View
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">No GRN records found in the database.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php 
include_once "../../../includes/footer.php"; 
?>