<?php
require_once "../../../config/database.php";
include_once "../../../includes/header.php";

$qc_records = [];
if (isset($conn)) {
    $tableCheck = @mysqli_query($conn, "SHOW TABLES LIKE 'quality_control'");
    if ($tableCheck && mysqli_num_rows($tableCheck) > 0) {
        $res = @mysqli_query($conn, "SELECT * FROM quality_control ORDER BY id DESC");
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $qc_records[] = $row;
            }
        }
    }
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-clipboard-check me-2 text-primary"></i>Inbound Quality Control (QC)</h3>
            <p class="text-muted small mb-0">Inspect received items, log defects, and approve inbound stock batches.</p>
        </div>
        <div>
            <a href="inspect.php" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm">
                <i class="fa-solid fa-plus me-1"></i> New QC Inspection
            </a>
        </div>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4">
        <h5 class="fw-bold mb-3">Quality Inspection Logs</h5>
        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th>#ID</th>
                        <th>Reference / PO</th>
                        <th>Inspector</th>
                        <th>Inspection Date</th>
                        <th>Result Status</th>
                        <th class="text-end">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (!empty($qc_records)): ?>
                        <?php foreach ($qc_records as $q): ?>
                            <tr>
                                <td><?= sprintf("#%03d", $q['id']); ?></td>
                                <td class="fw-bold font-mono"><?= htmlspecialchars($q['reference_no'] ?? 'N/A'); ?></td>
                                <td><?= htmlspecialchars($q['inspector_name'] ?? 'Admin Inspector'); ?></td>
                                <td class="font-mono small"><?= htmlspecialchars($q['inspection_date'] ?? date('Y-m-d')); ?></td>
                                <td>
                                    <?php 
                                    $status = $q['status'] ?? 'Passed';
                                    $badgeBg = ($status === 'Passed') ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger';
                                    ?>
                                    <span class="badge <?= $badgeBg; ?> px-2 py-1"><?= htmlspecialchars($status); ?></span>
                                </td>
                                <td class="text-end">
                                    <a href="view.php?id=<?= $q['id']; ?>" class="btn btn-outline-primary btn-sm rounded-pill px-3">
                                        <i class="fa-solid fa-eye me-1"></i> Inspect
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" class="text-center text-muted p-4">No quality control records found in the database.</td>
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