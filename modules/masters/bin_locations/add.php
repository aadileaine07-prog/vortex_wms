<?php
session_start();

$projectRoot = dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Corrected Table: Querying 'warehouse' (singular) for Active status
$warehouses = mysqli_query($conn, "
    SELECT id, warehouse_code, warehouse_name 
    FROM warehouse 
    WHERE status = 'Active' 
    ORDER BY id ASC
");

if (isset($_POST['save'])) {
    $warehouse_id = intval($_POST['warehouse_id']);
    $bin_code     = mysqli_real_escape_string($conn, strtoupper(trim($_POST['bin_code'])));
    $zone_name    = mysqli_real_escape_string($conn, $_POST['zone_name'] ?? 'Zone-A');
    $max_capacity = intval($_POST['max_capacity'] ?? 100);
    $description  = mysqli_real_escape_string($conn, $_POST['description']);
    $status       = mysqli_real_escape_string($conn, $_POST['status']);

    $check = mysqli_query($conn, "SELECT id FROM bin_locations WHERE warehouse_id='$warehouse_id' AND bin_code='$bin_code'");
    if ($check && mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = "Bin Code (<b>$bin_code</b>) already exists in selected warehouse!";
    } else {
        $sql = "INSERT INTO bin_locations (warehouse_id, bin_code, zone_name, max_capacity, description, status)
                VALUES ('$warehouse_id', '$bin_code', '$zone_name', '$max_capacity', '$description', '$status')";
        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Bin Location <b>$bin_code</b> added successfully.";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to add bin location: " . mysqli_error($conn);
        }
    }
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content"><div class="container-fluid p-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="fw-bold mb-0"><i class="fa-solid fa-plus-circle text-primary me-2"></i>Add Single Bin Location</h2>
        <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-3"><div class="card-body p-4">
        <form method="POST">
            <div class="row g-3">
                
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Target Warehouse *</label>
                    <select name="warehouse_id" class="form-select" required>
                        <option value="">-- Select Active Warehouse --</option>
                        <?php if ($warehouses && mysqli_num_rows($warehouses) > 0): ?>
                            <?php while ($w = mysqli_fetch_assoc($warehouses)): ?>
                                <option value="<?= $w['id']; ?>">
                                    <?= htmlspecialchars($w['warehouse_name']); ?> (<?= htmlspecialchars($w['warehouse_code']); ?>)
                                </option>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <option value="" disabled>No Active Warehouses Found</option>
                        <?php endif; ?>
                    </select>
                </div>

                <div class="col-md-6">
                    <label class="form-label fw-semibold">Bin Code *</label>
                    <input type="text" name="bin_code" class="form-control font-monospace" placeholder="e.g. L0-A1-001-01-A" required>
                    <small class="text-muted">Standard Format: <code>[Floor]-[Aisle]-[Rack]-[Shelf]-[Bin]</code></small>
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Zone / Area Name</label>
                    <input type="text" name="zone_name" class="form-control" placeholder="e.g. Zone-A" value="Zone-A">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Max Capacity (Units)</label>
                    <input type="number" name="max_capacity" class="form-control" value="100" min="1">
                </div>

                <div class="col-md-4">
                    <label class="form-label fw-semibold">Status *</label>
                    <select name="status" class="form-select" required>
                        <option value="Active">Active</option>
                        <option value="Inactive">Inactive</option>
                    </select>
                </div>

                <div class="col-md-12">
                    <label class="form-label fw-semibold">Description / Remarks</label>
                    <input type="text" name="description" class="form-control" placeholder="e.g. Ground level rack for heavy items">
                </div>
            </div>

            <hr class="my-4">

            <div class="d-flex justify-content-between">
                <a href="index.php" class="btn btn-outline-secondary px-3">Cancel</a>
                <button type="submit" name="save" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Save Bin Location</button>
            </div>
        </form>
    </div></div>
</div></div>

<?php include $projectRoot . "/includes/footer.php"; ?>