<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    die("Employee Not Found");
}

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="fw-bold">👤 Employee Profile</h2>
            <a href="index.php" class="btn btn-secondary">← Back</a>
        </div>

        <div class="card shadow">
            <div class="card-body">
                <div class="row">
                    <div class="col-md-3 text-center mb-3">
                        <img src="../../../assets/images/employees/<?= (empty($row['photo']) || $row['photo'] == 'default.png') ? 'default.png' : htmlspecialchars($row['photo']); ?>" class="img-thumbnail mb-3" style="width:180px;height:180px;object-fit:cover;" alt="Avatar">
                        <div>
                            <?php if (str_starts_with($row['employee_id'], "Z")): ?>
                                <span class="badge bg-primary fs-6"><?= htmlspecialchars($row['employee_id']); ?></span>
                            <?php else: ?>
                                <span class="badge bg-success fs-6"><?= htmlspecialchars($row['employee_id']); ?></span>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="col-md-9">
                        <table class="table table-bordered">
                            <tr><th width="220">Full Name</th><td><?= htmlspecialchars($row['full_name']); ?></td></tr>
                            <tr><th>Mobile</th><td><?= htmlspecialchars($row['mobile'] ?? '-'); ?></td></tr>
                            <tr><th>Email</th><td><?= htmlspecialchars($row['email'] ?? '-'); ?></td></tr>
                            <tr><th>Gender</th><td><?= htmlspecialchars($row['gender']); ?></td></tr>
                            <tr><th>Date Of Birth</th><td><?= htmlspecialchars($row['dob'] ?? '-'); ?></td></tr>
                            <tr><th>Department</th><td><?= htmlspecialchars($row['department']); ?></td></tr>
                            <tr><th>Designation</th><td><?= htmlspecialchars($row['designation'] ?? '-'); ?></td></tr>
                            <tr><th>Role</th><td><?= htmlspecialchars($row['role']); ?></td></tr>
                            <tr><th>Warehouse</th><td><?= htmlspecialchars($row['warehouse']); ?></td></tr>
                            <tr><th>Shift</th><td><?= htmlspecialchars($row['shift']); ?></td></tr>
                            <tr><th>Joining Date</th><td><?= !empty($row['joining_date']) ? htmlspecialchars($row['joining_date']) : '-'; ?></td></tr>
                            <tr>
                                <th>Status</th>
                                <td>
                                    <?php if ($row['status'] == "Active"): ?>
                                        <span class="badge bg-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger">Inactive</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <tr><th>Address</th><td><?= nl2br(htmlspecialchars($row['address'] ?? '-')); ?></td></tr>
                            <tr><th>Remarks</th><td><?= nl2br(htmlspecialchars($row['remarks'] ?? '-')); ?></td></tr>
                        </table>

                        <div class="mt-3">
                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning">✏ Edit Employee</a>
                            <a href="index.php" class="btn btn-secondary">Back</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include "../../../includes/footer.php"; ?>