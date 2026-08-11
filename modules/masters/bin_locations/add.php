<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$warehouses = mysqli_query($conn, "
    SELECT id, warehouse_name 
    FROM warehouse 
    WHERE status='Active' 
    ORDER BY warehouse_name ASC
");

if (isset($_POST['save'])) {

    $warehouse_id = intval($_POST['warehouse_id']);
    $bin_code     = mysqli_real_escape_string($conn, $_POST['bin_code']);
    $zone_name    = mysqli_real_escape_string($conn, $_POST['zone_name'] ?? 'General');
    $max_capacity = intval($_POST['max_capacity'] ?? 100);
    $description  = mysqli_real_escape_string($conn, $_POST['description']);
    $status       = mysqli_real_escape_string($conn, $_POST['status']);

    // Check Duplicate Bin Code in Same Warehouse
    $check = mysqli_query($conn, "SELECT id FROM bin_locations WHERE warehouse_id='$warehouse_id' AND bin_code='$bin_code'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = "Bin Code already exists in the selected warehouse!";
    } else {
        $sql = "
            INSERT INTO bin_locations (warehouse_id, bin_code, zone_name, max_capacity, description, status)
            VALUES ('$warehouse_id', '$bin_code', '$zone_name', '$max_capacity', '$description', '$status')
        ";

        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Bin Location added successfully.";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to add bin location: " . mysqli_error($conn);
        }
    }
}

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-plus-circle text-primary me-2"></i>Add Bin Location</h2>
                <p class="text-muted mb-0">Define storage bin, rack code, and capacity</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Warehouse <span class="text-danger">*</span></label>
                            <select name="warehouse_id" class="form-select" required>
                                <option value="">-- Select Warehouse --</option>
                                <?php while ($w = mysqli_fetch_assoc($warehouses)): ?>
                                    <option value="<?= $w['id']; ?>"><?= htmlspecialchars($w['warehouse_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Bin Code <span class="text-danger">*</span></label>
                            <input type="text" name="bin_code" class="form-control font-monospace" placeholder="e.g. A-01-R01-S1" required>
                            <small class="text-muted">Recommended Format: [Zone]-[Aisle]-[Rack]-[Shelf]</small>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Zone / Area Name</label>
                            <input type="text" name="zone_name" class="form-control" placeholder="e.g. Cold Storage / Zone A">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Capacity (Units)</label>
                            <input type="number" name="max_capacity" class="form-control" value="100" min="1">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Description / Remarks</label>
                            <input type="text" name="description" class="form-control" placeholder="e.g. Top rack for heavy pallets">
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" name="save" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Save Bin Location</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>