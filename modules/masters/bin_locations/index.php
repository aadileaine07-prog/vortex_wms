<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$result = mysqli_query($conn, "
    SELECT 
        bin_locations.*,
        warehouse.warehouse_name
    FROM bin_locations
    LEFT JOIN warehouse ON warehouse.id = bin_locations.warehouse_id
    ORDER BY bin_locations.id DESC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-location-dot text-primary me-2"></i>Bin Location Master
                </h2>
                <p class="text-muted mb-0">Manage warehouse storage bins, racks, and capacity</p>
            </div>
            <div>
                <a href="add.php" class="btn btn-primary px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Add Bin Location
                </a>
            </div>
        </div>

        <!-- Session Messages -->
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
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">

                <!-- Search Input -->
                <div class="row mb-3">
                    <div class="col-md-4">
                        <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search Bin Code, Warehouse or Description...">
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="binTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">ID</th>
                                <th>Warehouse</th>
                                <th>Bin Code</th>
                                <th>Zone / Rack</th>
                                <th>Description</th>
                                <th>Status</th>
                                <th width="180" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td><strong><?= htmlspecialchars($row['warehouse_name'] ?? 'N/A'); ?></strong></td>
                                        <td><span class="badge bg-light text-dark border font-monospace fs-6"><?= htmlspecialchars($row['bin_code']); ?></span></td>
                                        <td><?= htmlspecialchars($row['zone_name'] ?? 'General'); ?></td>
                                        <td><?= htmlspecialchars($row['description'] ?? '-'); ?></td>
                                        <td>
                                            <?php if (($row['status'] ?? 'Active') == 'Active'): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm text-white"><i class="fa-solid fa-eye"></i></a>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this Bin Location?');"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted py-4">No Bin Locations Found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
            <div class="card-footer bg-light p-3 d-flex justify-content-between align-items-center">
                <a href="../../../dashboard.php" class="btn btn-outline-secondary">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
                <a href="add.php" class="btn btn-primary">
                    <i class="fa-solid fa-plus me-1"></i> Add Bin Location
                </a>
            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>

<script>
document.getElementById("searchInput").addEventListener("keyup", function() {
    let value = this.value.toLowerCase();
    let rows = document.querySelectorAll("#binTable tbody tr");

    rows.forEach(function(row) {
        row.style.display = row.innerText.toLowerCase().includes(value) ? "" : "none";
    });
});
</script>