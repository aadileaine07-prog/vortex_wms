<?php
session_start();

$projectRoot = dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch bin locations with Warehouse Name, ordered by Newest First (id DESC)
$result = mysqli_query($conn, "
    SELECT 
        b.*,
        COALESCE(w.warehouse_name, 'Unassigned') AS warehouse_name,
        COALESCE(w.warehouse_code, 'N/A') AS warehouse_code
    FROM bin_locations b
    LEFT JOIN warehouse w ON w.id = b.warehouse_id
    ORDER BY b.id DESC
");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Bin Locations Master
                </h2>
                <p class="text-muted mb-0">Manage and view warehouse bin rack structures & zones</p>
            </div>
            <div>
                <a href="bulk_add.php" class="btn btn-primary px-3 me-2">
                    <i class="fa-solid fa-layer-group me-1"></i> Bulk Add Bins
                </a>
                <a href="create.php" class="btn btn-success px-3">
                    <i class="fa-solid fa-plus me-1"></i> Add Single Bin
                </a>
            </div>
        </div>

        <!-- Alert Session Messages -->
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

        <!-- Table Card -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-4">

                <!-- Live Filter Search Bar -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" id="binSearch" class="form-control" placeholder="🔍 Search Bin Code, Zone, or Warehouse...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="binTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">ID</th>
                                <th>Bin Code</th>
                                <th>Warehouse</th>
                                <th>Zone Name</th>
                                <th>Zone Type</th>
                                <th>Max Capacity</th>
                                <th>Status</th>
                                <th width="120" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td>
                                            <code class="fs-6 fw-bold text-primary"><?= htmlspecialchars($row['bin_code']); ?></code>
                                        </td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['warehouse_name']); ?></strong>
                                            <small class="text-muted d-block">(<?= htmlspecialchars($row['warehouse_code']); ?>)</small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['zone_name'] ?? 'Zone-A'); ?></span></td>
                                        <td>
                                            <?php 
                                                $zt = $row['zone_type'] ?? 'Regular';
                                                if ($zt == 'Toxic') echo '<span class="badge bg-danger"><i class="fa-solid fa-biohazard me-1"></i> Toxic</span>';
                                                elseif ($zt == 'Apparel') echo '<span class="badge bg-info text-dark"><i class="fa-solid fa-shirt me-1"></i> Apparel</span>';
                                                elseif ($zt == 'Toys') echo '<span class="badge bg-warning text-dark"><i class="fa-solid fa-gamepad me-1"></i> Toys</span>';
                                                elseif ($zt == 'Festive') echo '<span class="badge bg-purple text-white" style="background:#6f42c1"><i class="fa-solid fa-gifts me-1"></i> Festive</span>';
                                                elseif ($zt == 'SPR') echo '<span class="badge bg-secondary"><i class="fa-solid fa-wrench me-1"></i> SPR</span>';
                                                else echo '<span class="badge bg-success"><i class="fa-solid fa-box me-1"></i> Regular</span>';
                                            ?>
                                        </td>
                                        <td><span class="fw-bold"><?= $row['max_capacity'] ?? 100; ?></span> Units</td>
                                        <td>
                                            <?php if (($row['status'] ?? 'Active') == 'Active'): ?>
                                                <span class="badge bg-success px-2 py-1">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary px-2 py-1">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm text-dark"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this bin location?');"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-boxes-stacked fs-2 d-block mb-2"></i>
                                        No Bin Locations Found. Click "Bulk Add Bins" to create new bins.
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
document.getElementById("binSearch").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll("#binTable tbody tr");

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});
</script>