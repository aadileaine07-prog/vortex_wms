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

$warehouseQuery = mysqli_query($conn, "SELECT * FROM warehouse WHERE id='$id'");

if (!$warehouseQuery || mysqli_num_rows($warehouseQuery) == 0) {
    $_SESSION['error'] = "Warehouse Not Found.";
    header("Location: index.php");
    exit();
}

$warehouse = mysqli_fetch_assoc($warehouseQuery);

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-circle-info text-primary me-2"></i>Warehouse Details</h2>
                <p class="text-muted mb-0">Warehouse Code: <?= htmlspecialchars($warehouse['warehouse_code']); ?></p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">

                <table class="table table-bordered align-middle">
                    <tr>
                        <th width="220" class="bg-light">Warehouse Code</th>
                        <td><span class="badge bg-light text-dark border fs-6"><?= htmlspecialchars($warehouse['warehouse_code']); ?></span></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Warehouse Name</th>
                        <td><strong><?= htmlspecialchars($warehouse['warehouse_name']); ?></strong></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Location / Address</th>
                        <td><?= htmlspecialchars($warehouse['location']); ?></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Status</th>
                        <td>
                            <?php if ($warehouse['status'] == 'Active'): ?>
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
                        <a href="edit.php?id=<?= $warehouse['id']; ?>" class="btn btn-warning px-3 me-2"><i class="fa-solid fa-pen-to-square me-1"></i> Edit</a>
                        <a href="delete.php?id=<?= $warehouse['id']; ?>" class="btn btn-danger px-3" onclick="return confirm('Delete this Warehouse?');"><i class="fa-solid fa-trash me-1"></i> Delete</a>
                    </div>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>