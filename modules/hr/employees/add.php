<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

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
                <h2 class="fw-bold">➕ Add Employee</h2>
                <p class="text-muted">Create New Employee Account</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary">← Back</a>
            </div>
        </div>

        <form action="save.php" method="POST" enctype="multipart/form-data">
            <div class="card shadow">
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-3 text-center mb-3">
                            <label class="form-label d-block">Employee Photo</label>
                            <img id="imagePreview" src="../../../assets/images/employees/default.png" class="img-thumbnail mb-2" style="width:150px;height:150px;object-fit:cover;">
                            <input type="file" name="photo" class="form-control" accept="image/*" onchange="previewFile(this)">
                        </div>

                        <div class="col-md-9">
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Full Name <span class="text-danger">*</span></label>
                                    <input type="text" name="full_name" class="form-control" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Mobile</label>
                                    <input type="text" name="mobile" class="form-control">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" name="email" class="form-control">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Gender</label>
                                    <select name="gender" class="form-select">
                                        <option value="Male">Male</option>
                                        <option value="Female">Female</option>
                                        <option value="Other">Other</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Date Of Birth</label>
                                    <input type="date" name="dob" class="form-control">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Department</label>
                                    <select name="department" class="form-select">
                                        <option value="Management">Management</option>
                                        <option value="HR">HR</option>
                                        <option value="Inbound">Inbound</option>
                                        <option value="Outbound">Outbound</option>
                                        <option value="Inventory">Inventory</option>
                                        <option value="Operations">Operations</option>
                                        <option value="Warehouse">Warehouse</option>
                                        <option value="QC">QC</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Designation</label>
                                    <input type="text" name="designation" class="form-control" placeholder="e.g. Senior Associate">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Role</label>
                                    <select name="role" class="form-select">
                                        <optgroup label="Management">
                                            <option value="Super Admin">Super Admin</option>
                                            <option value="Admin">Admin</option>
                                            <option value="HR Manager">HR Manager</option>
                                            <option value="HR Executive">HR Executive</option>
                                            <option value="Area Manager">Area Manager</option>
                                            <option value="Warehouse Manager">Warehouse Manager</option>
                                            <option value="Inventory Manager">Inventory Manager</option>
                                            <option value="Operations Manager">Operations Manager</option>
                                        </optgroup>
                                        <optgroup label="Shop Floor">
                                            <option value="Team Leader">Team Leader</option>
                                            <option value="Picker">Picker</option>
                                            <option value="Packer">Packer</option>
                                            <option value="Loader">Loader</option>
                                            <option value="Sorter">Sorter</option>
                                            <option value="Putter">Putter</option>
                                            <option value="Inbound Operator">Inbound Operator</option>
                                            <option value="Outbound Operator">Outbound Operator</option>
                                        </optgroup>
                                    </select>
                                </div>

                                <!-- Dynamic Warehouse Dropdown (Fixed SQL Query) -->
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Warehouse <span class="text-danger">*</span></label>
                                    <select name="warehouse" class="form-select" required>
                                        <option value="">-- Select Warehouse --</option>
                                        <?php
                                        $db_connection = isset($conn) ? $conn : (isset($db) ? $db : null);
                                        
                                        if ($db_connection) {
                                            // Fixed table name to 'warehouse'
                                            $wh_query = "SELECT * FROM warehouse WHERE status='Active' ORDER BY warehouse_name ASC";
                                            $wh_result = mysqli_query($db_connection, $wh_query);

                                            if ($wh_result && mysqli_num_rows($wh_result) > 0) {
                                                while ($wh = mysqli_fetch_assoc($wh_result)) {
                                                    $wh_name = $wh['warehouse_name'] ?? $wh['name'] ?? $wh['wh_name'] ?? '';
                                                    if (!empty($wh_name)) {
                                                        echo '<option value="' . htmlspecialchars($wh_name) . '">' . htmlspecialchars($wh_name) . '</option>';
                                                    }
                                                }
                                            }
                                        }
                                        ?>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Shift</label>
                                    <select name="shift" class="form-select">
                                        <option value="Morning">Morning</option>
                                        <option value="General">General</option>
                                        <option value="Evening">Evening</option>
                                        <option value="Night">Night</option>
                                    </select>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Username <span class="text-danger">*</span></label>
                                    <input type="text" name="username" class="form-control" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Password <span class="text-danger">*</span></label>
                                    <input type="password" name="password" class="form-control" required>
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Joining Date</label>
                                    <input type="date" name="joining_date" class="form-control">
                                </div>

                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Status</label>
                                    <select name="status" class="form-select">
                                        <option value="Active">Active</option>
                                        <option value="Inactive">Inactive</option>
                                    </select>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Address</label>
                            <textarea name="address" class="form-control" rows="3"></textarea>
                        </div>

                        <div class="col-md-6 mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3"></textarea>
                        </div>
                    </div>

                    <hr>

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary">💾 Save Employee</button>
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