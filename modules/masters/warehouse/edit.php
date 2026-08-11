<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Warehouse ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$warehouse = mysqli_query($conn, "SELECT * FROM warehouse WHERE id='$id'");

if (!$warehouse || mysqli_num_rows($warehouse) == 0) {
    $_SESSION['error'] = "Warehouse Not Found.";
    header("Location: index.php");
    exit();
}

$row = mysqli_fetch_assoc($warehouse);

if (isset($_POST['update'])) {

    $warehouse_code = mysqli_real_escape_string($conn, $_POST['warehouse_code']);
    $warehouse_name = mysqli_real_escape_string($conn, $_POST['warehouse_name']);
    $location       = mysqli_real_escape_string($conn, $_POST['location']);
    $status         = mysqli_real_escape_string($conn, $_POST['status']);

    $updateSql = "
        UPDATE warehouse SET
            warehouse_code = '$warehouse_code',
            warehouse_name = '$warehouse_name',
            location       = '$location',
            status         = '$status'
        WHERE id = '$id'
    ";

    if (mysqli_query($conn, $updateSql)) {
        $_SESSION['success'] = "Warehouse updated successfully.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to update warehouse: " . mysqli_error($conn);
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
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Warehouse</h2>
                <p class="text-muted mb-0">Update location details (#<?= $row['id']; ?>)</p>
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
                            <input type="text" name="warehouse_code" class="form-control" value="<?= htmlspecialchars($row['warehouse_code']); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Warehouse Name <span class="text-danger">*</span></label>
                            <input type="text" name="warehouse_name" class="form-control" value="<?= htmlspecialchars($row['warehouse_name']); ?>" required>
                        </div>

                        <div class="col-md-8">
                            <label class="form-label fw-semibold">Location / Address <span class="text-danger">*</span></label>
                            <input type="text" name="location" class="form-control" value="<?= htmlspecialchars($row['location']); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Active" <?= ($row['status'] == 'Active') ? 'selected' : ''; ?>>Active</option>
                                <option value="Inactive" <?= ($row['status'] == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                            </select>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" name="update" class="btn btn-warning px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Update Warehouse</button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>