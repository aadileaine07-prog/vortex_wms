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
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?= htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold">✏ Edit Employee</h2>
                <p class="text-muted">Update Employee Information</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary">← Back</a>
            </div>
        </div>

        <form action="update.php" method="POST" enctype="multipart/form-data">
            <input type="hidden" name="id" value="<?= $row['id']; ?>">
            <input type="hidden" name="old_photo" value="<?= htmlspecialchars($row['photo']); ?>">

            <div class="card shadow">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center mb-3">
                            <img id="imagePreview" src="../../../assets/images/employees/<?= (empty($row['photo']) || $row['photo'] == 'default.png') ? 'default.png' : htmlspecialchars($row['photo']); ?>" class="img-thumbnail mb-2" style="width:170px;height:170px;object-fit:cover;">
                            <input type="file" name="photo" class="form-control" accept="image/*" onchange="previewFile(this)">
                        </div>

                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Employee ID</label>
                                    <input type="text" class="form-control" value="<?= htmlspecialchars($row['employee_id']); ?>" readonly>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control" value="<?= htmlspecialchars($row['full_name']); ?>" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Mobile</label>
                                    <input type="text" name="mobile" class="form-control" value="<?= htmlspecialchars($row['mobile'] ?? ''); ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control" value="<?= htmlspecialchars($row['email'] ?? ''); ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="Male" <?= ($row['gender'] == "Male") ? "selected" : ""; ?>>Male</option>
                                        <option value="Female" <?= ($row['gender'] == "Female") ? "selected" : ""; ?>>Female</option>
                                        <option value="Other" <?= ($row['gender'] == "Other") ? "selected" : ""; ?>>Other</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Date Of Birth</label>
                                    <input type="date" name="dob" class="form-control" value="<?= htmlspecialchars($row['dob'] ?? ''); ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Department</label>
                                    <select name="department" class="form-select">
                                        <?php 
                                        $depts = ['Management', 'HR', 'Inbound', 'Outbound', 'Inventory', 'Operations', 'Warehouse', 'QC'];
                                        foreach($depts as $d):
                                        ?>
                                            <option value="<?= $d; ?>" <?= ($row['department'] == $d) ? 'selected' : ''; ?>><?= $d; ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Designation</label>
                                    <input type="text" name="designation" class="form-control" value="<?= htmlspecialchars($row['designation'] ?? ''); ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Role</label>
                                    <input type="text" name="role" class="form-control" value="<?= htmlspecialchars($row['role']); ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Warehouse</label>
                                    <input type="text" name="warehouse" class="form-control" value="<?= htmlspecialchars($row['warehouse']); ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Shift</label>
                                    <select name="shift" class="form-select">
                                        <option value="Morning" <?= ($row['shift'] == "Morning") ? "selected" : ""; ?>>Morning</option>
                                        <option value="General" <?= ($row['shift'] == "General") ? "selected" : ""; ?>>General</option>
                                        <option value="Evening" <?= ($row['shift'] == "Evening") ? "selected" : ""; ?>>Evening</option>
                                        <option value="Night" <?= ($row['shift'] == "Night") ? "selected" : ""; ?>>Night</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" class="form-control" value="<?= htmlspecialchars($row['username']); ?>" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">New Password</label>
                                    <input type="password" name="password" class="form-control" placeholder="Leave blank to keep current">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Joining Date</label>
                                    <input type="date" name="joining_date" class="form-control" value="<?= htmlspecialchars($row['joining_date'] ?? ''); ?>">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="Active" <?= ($row['status'] == "Active") ? "selected" : ""; ?>>Active</option>
                                        <option value="Inactive" <?= ($row['status'] == "Inactive") ? "selected" : ""; ?>>Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3"><?= htmlspecialchars($row['address'] ?? ''); ?></textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3"><?= htmlspecialchars($row['remarks'] ?? ''); ?></textarea>
                        </div>
                    </div>

                    <hr>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">💾 Update Employee</button>
                        <a href="index.php" class="btn btn-secondary">Cancel</a>
                    </div>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
function previewFile(input) {
    const file = input.files[0];
    if (file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('imagePreview').src = e.target.result;
        }
        reader.readAsDataURL(file);
    }
}
</script>

<?php include "../../../includes/footer.php"; ?>