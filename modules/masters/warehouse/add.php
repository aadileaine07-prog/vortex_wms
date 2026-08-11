<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (isset($_POST['save'])) {

    $warehouse_code = mysqli_real_escape_string($conn, $_POST['warehouse_code']);
    $warehouse_name = mysqli_real_escape_string($conn, $_POST['warehouse_name']);
    $location       = mysqli_real_escape_string($conn, $_POST['location']);
    $status         = mysqli_real_escape_string($conn, $_POST['status']);

    // Duplicate Check
    $check = mysqli_query($conn, "SELECT id FROM warehouse WHERE warehouse_code='$warehouse_code'");
    if (mysqli_num_rows($check) > 0) {
        $_SESSION['error'] = "Warehouse Code already exists!";
    } else {
        $sql = "
            INSERT INTO warehouse (warehouse_code, warehouse_name, location, status)
            VALUES ('$warehouse_code', '$warehouse_name', '$location', '$status')
        ";

        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = "Warehouse added successfully.";
            header("Location: index.php");
            exit();
        } else {
            $_SESSION['error'] = "Failed to add warehouse: " . mysqli_error($conn);
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
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-plus-circle text-primary me-2"></i>Add Warehouse</h2>
                <p class="text-muted mb-0">Create new warehouse storage location</p>
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
                            <label class="form-label fw-semibold">Warehouse Code <span class="text-danger">*</span></label>
                            <input type="text" name="warehouse_code" class="form-control" placeholder="e.g. WH-001" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Warehouse Name <span class="text-danger">*</span></label>
                            <input type="text" name="warehouse_name" class="form-control" placeholder="Enter warehouse name" required>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Location / Address <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control" placeholder="City or Full Address" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Active">Active</option>
                                <option value="Inactive">Inactive</option>
                            </select>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" name="save" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Save Warehouse</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>