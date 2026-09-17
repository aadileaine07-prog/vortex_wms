<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../../../config/database.php") ? dirname(__DIR__, 4) : dirname(__DIR__, 2));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-user-plus text-primary me-2"></i>Add Employee</h2>
            <p class="text-muted mb-0">Create new staff account credentials and profile details</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary fw-bold rounded-pill px-3 shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to List</a>
        </div>
    </div>

    <form action="save.php" method="POST" enctype="multipart/form-data">
        <div class="card shadow-sm border-0 rounded-4 bg-white">
            <div class="card-body p-4">
                <div class="row g-4">
                    <!-- Photo Column -->
                    <div class="col-md-3 text-center border-end">
                        <label class="form-label fw-bold small text-muted d-block mb-3">Employee Photo</label>
                        <img id="imagePreview" src="../../../assets/images/employees/default.png" class="rounded-circle shadow-sm mb-3" style="width:130px;height:130px;object-fit:cover; border: 3px solid #f8f9fa;">
                        <input type="file" name="photo" class="form-control form-control-sm border-2" accept="image/*" onchange="previewFile(this)">
                    </div>

                    <!-- Input Fields Column -->
                    <div class="col-md-9">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control border-2 fw-semibold" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Mobile Number</label>
                                <input type="text" name="mobile" class="form-control border-2 fw-semibold">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Email Address <span class="text-danger">*</span></label>
                                <input type="email" name="email" class="form-control border-2 fw-semibold" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Gender</label>
                                <select name="gender" class="form-select border-2 fw-semibold">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Date Of Birth</label>
                                <input type="date" name="dob" class="form-control border-2 fw-semibold">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Department</label>
                                <select name="department" class="form-select border-2 fw-semibold">
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

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Designation</label>
                                <input type="text" name="designation" class="form-control border-2 fw-semibold" placeholder="e.g. Senior Associate">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">System Role</label>
                                <select name="role" class="form-select border-2 fw-semibold">
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

                            <!-- Warehouse Dropdown -->
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Assigned Warehouse <span class="text-danger">*</span></label>
                                <select name="warehouse" class="form-select border-2 fw-semibold" required>
                                    <option value="">-- Select Warehouse --</option>
                                    <?php
                                    $wh_query = "SELECT * FROM warehouse WHERE status='Active' ORDER BY warehouse_name ASC";
                                    $db_connection = $GLOBALS['conn'] ?? null;
                                    $wh_result = $db_connection instanceof mysqli
                                        ? mysqli_query($db_connection, $wh_query)
                                        : false;

                                    if ($wh_result && mysqli_num_rows($wh_result) > 0) {
                                        while ($wh = mysqli_fetch_assoc($wh_result)) {
                                            $wh_name = $wh['warehouse_name'] ?? $wh['name'] ?? $wh['wh_name'] ?? '';
                                            if (!empty($wh_name)) {
                                                echo '<option value="' . htmlspecialchars($wh_name) . '">' . htmlspecialchars($wh_name) . '</option>';
                                            }
                                        }
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Shift Timing</label>
                                <select name="shift" class="form-select border-2 fw-semibold">
                                    <option value="Morning">Morning</option>
                                    <option value="General">General</option>
                                    <option value="Evening">Evening</option>
                                    <option value="Night">Night</option>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Login Username <span class="text-danger">*</span></label>
                                <input type="text" name="username" class="form-control border-2 fw-semibold" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Account Password <span class="text-danger">*</span></label>
                                <input type="password" name="password" class="form-control border-2 fw-semibold" required>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Joining Date</label>
                                <input type="date" name="joining_date" class="form-control border-2 fw-semibold" value="<?= date('Y-m-d'); ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Account Status</label>
                                <select name="status" class="form-select border-2 fw-semibold">
                                    <option value="Active">Active</option>
                                    <option value="Inactive">Inactive</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <!-- Textareas -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Residential Address</label>
                        <textarea name="address" class="form-control border-2" rows="3" placeholder="Enter full address..."></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Remarks / Notes</label>
                        <textarea name="remarks" class="form-control border-2" rows="3" placeholder="Optional comments..."></textarea>
                    </div>
                </div>

                <hr class="my-4 text-muted">

                <div class="text-end">
                    <button type="submit" class="btn btn-primary px-4 fw-bold rounded-pill"><i class="fa-solid fa-floppy-disk me-1"></i> Save Employee</button>
                    <a href="index.php" class="btn btn-outline-secondary px-3 fw-bold rounded-pill">Cancel</a>
                </div>
            </div>
        </div>
    </form>
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

<?php include $projectRoot . "/includes/footer.php"; ?>