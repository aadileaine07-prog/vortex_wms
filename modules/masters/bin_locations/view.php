<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Bin Location ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$binQuery = mysqli_query($conn, "
    SELECT 
        bin_locations.*,
        warehouse.warehouse_name
    FROM bin_locations
    LEFT JOIN warehouse ON warehouse.id = bin_locations.warehouse_id
    WHERE bin_locations.id='$id'
");

if (!$binQuery || mysqli_num_rows($binQuery) == 0) {
    $_SESSION['error'] = "Bin Location Not Found.";
    header("Location: index.php");
    exit();
}

$bin = mysqli_fetch_assoc($binQuery);

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-circle-info text-primary me-2"></i>Bin Location Details</h2>
                <p class="text-muted mb-0">Bin Code: <?= htmlspecialchars($bin['bin_code']); ?></p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">

                <table class="table table-bordered align-middle">
                    <tr>
                        <th width="220" class="bg-light">Warehouse</th>
                        <td><strong><?= htmlspecialchars($bin['warehouse_name'] ?? 'N/A'); ?></strong></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Bin Code</th>
                        <td><span class="badge bg-light text-dark border font-monospace fs-6"><?= htmlspecialchars($bin['bin_code']); ?></span></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Zone Name</th>
                        <td><?= htmlspecialchars($bin['zone_name'] ?? 'General Area'); ?></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Max Capacity</th>
                        <td><span class="badge bg-info text-dark fs-6"><?= $bin['max_capacity'] ?? '100'; ?> Units</span></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Description</th>
                        <td><?= htmlspecialchars($bin['description'] ?: '-'); ?></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Status</th>
                        <td>
                            <?php if (($bin['status'] ?? 'Active') == 'Active'): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Inactive</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                </table>

                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
                    <div>
                        <a href="edit.php?id=<?= $bin['id']; ?>" class="btn btn-warning px-3 me-2"><i class="fa-solid fa-pen-to-square me-1"></i> Edit</a>
                        <a href="delete.php?id=<?= $bin['id']; ?>" class="btn btn-danger px-3" onclick="return confirm('Delete this Bin Location?');"><i class="fa-solid fa-trash me-1"></i> Delete</a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>