<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id'])) {
    die("Adjustment ID Missing");
}

$id = intval($_GET['id']);

$result = mysqli_query($conn, "SELECT * FROM stock_adjustment WHERE id='$id'");

if (mysqli_num_rows($result) == 0) {
    die("Adjustment Record Not Found");
}

$row = mysqli_fetch_assoc($result);

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="card shadow-sm border-0 rounded-4 col-lg-8 mx-auto">
            <div class="card-header bg-primary text-white p-3 rounded-top-4 d-flex justify-content-between align-items-center">
                <h4 class="mb-0 fw-bold"><i class="fa-solid fa-file-lines me-2"></i>Stock Adjustment Summary</h4>
                <span class="badge bg-light text-primary fs-6">ID #<?= $row['id']; ?></span>
            </div>

            <div class="card-body p-4">
                <table class="table table-bordered align-middle">
                    <tr>
                        <th width="30%" class="bg-light">Product Info</th>
                        <td>
                            <strong><?= htmlspecialchars($row['product_name']); ?></strong><br>
                            <span class="badge bg-secondary font-monospace"><?= htmlspecialchars($row['product_code']); ?></span>
                        </td>
                    </tr>
                    <tr>
                        <th class="bg-light">Location</th>
                        <td>
                            <strong>Warehouse:</strong> <?= htmlspecialchars($row['warehouse']); ?> | 
                            <strong>Bin:</strong> <code><?= htmlspecialchars($row['bin_location']); ?></code>
                        </td>
                    </tr>
                    <tr>
                        <th class="bg-light">Adjustment Action</th>
                        <td>
                            <?php if ($row['adjustment_type'] == "Increase"): ?>
                                <span class="badge bg-success px-3 py-2"><i class="fa-solid fa-arrow-up me-1"></i> Increase (+<?= $row['quantity']; ?>)</span>
                            <?php else: ?>
                                <span class="badge bg-danger px-3 py-2"><i class="fa-solid fa-arrow-down me-1"></i> Decrease (-<?= $row['quantity']; ?>)</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th class="bg-light">Reason</th>
                        <td><?= !empty($row['reason']) ? nl2br(htmlspecialchars($row['reason'])) : "<em class='text-muted'>No reason provided</em>"; ?></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Adjustment Date</th>
                        <td><?= date("d M Y", strtotime($row['adjustment_date'])); ?></td>
                    </tr>
                    <tr>
                        <th class="bg-light">Logged By</th>
                        <td>Employee ID #<?= htmlspecialchars($row['created_by']); ?></td>
                    </tr>
                </table>

                <div class="d-flex justify-content-between align-items-center mt-4">
                    <a href="index.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back to List</a>
                    <div>
                        <button type="button" onclick="window.print();" class="btn btn-outline-primary me-2"><i class="fa-solid fa-print me-1"></i> Print Voucher</button>
                        <a href="create.php" class="btn btn-success"><i class="fa-solid fa-plus me-1"></i> New Adjustment</a>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>