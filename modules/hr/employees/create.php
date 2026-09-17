<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Multi-Level Dynamic Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") 
        ? dirname(__DIR__, 2) 
        : (file_exists(__DIR__ . "/../../../../config/database.php") ? dirname(__DIR__, 4) : dirname(__DIR__, 1)));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. SMART AUTO-GENERATE UNIQUE EMPLOYEE CODE (Z series vs VRTX series)
   ========================================================================== */
// By default check latest employee to decide or dynamically handle on role select via JS, 
// but for PHP default load we can check overall latest or assign dynamic standard.
// Here we look up the latest record to see what prefix was last used or default to VRTX0001 / Z003.
$autoCode = 'VRTX0001';
$chkEmp = mysqli_query($conn, "SELECT emp_id FROM employees ORDER BY id DESC LIMIT 1");
if ($chkEmp && mysqli_num_rows($chkEmp) > 0) {
    $lastEmpId = mysqli_fetch_assoc($chkEmp)['emp_id'];
    if (strpos($lastEmpId, 'Z') === 0) {
        $num = intval(substr($lastEmpId, 1)) + 1;
        $autoCode = 'Z' . str_pad($num, 3, '0', STR_PAD_LEFT);
    } elseif (strpos($lastEmpId, 'VRTX') === 0) {
        $num = intval(substr($lastEmpId, 4)) + 1;
        $autoCode = 'VRTX' . str_pad($num, 4, '0', STR_PAD_LEFT);
    }
}

/* ==========================================================================
   2. DYNAMIC WAREHOUSES RESOLUTION
   ========================================================================== */
$whTable = "warehouses";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $whTable = "warehouse";
}

$whNameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) === 0) {
    $whNameCol = "name";
}

$warehouses = [];
$whQuery = @mysqli_query($conn, "SELECT id, {$whNameCol} AS wh_name FROM `{$whTable}` WHERE status = 'Active' OR status = '1' OR status IS NULL ORDER BY {$whNameCol} ASC");
if ($whQuery && mysqli_num_rows($whQuery) > 0) {
    while ($w = mysqli_fetch_assoc($whQuery)) {
        if (!empty($w['wh_name'])) {
            $warehouses[] = $w['wh_name'];
        }
    }
}

if (empty($warehouses)) {
    $warehouses = [
        'Surat Central Logistics Park',
        'Ahmedabad Mega Distribution Center',
        'Vadodara FMCG & Chemical Hub',
        'Mundra Port Logistics Terminal'
    ];
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Action Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-user-plus text-primary me-2"></i>Add New Employee
            </h2>
            <p class="text-muted mb-0">Create new staff account, define department role, and assign warehouse hub</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-secondary fw-semibold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Directory
            </a>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <!-- Main Registration Form Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-11 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-id-card text-primary me-2"></i>Employee Account Information
            </h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill font-monospace" id="proposedIdBadge">
                PROPOSED ID: <span id="empIdText"><?= htmlspecialchars($autoCode); ?></span>
            </span>
        </div>

        <div class="card-body p-4">
            <form action="save.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="employee_id" id="employeeIdHidden" value="<?= htmlspecialchars($autoCode); ?>">

                <div class="row g-4">

                    <!-- Photo Upload Column -->
                    <div class="col-lg-3 col-md-4 text-center">
                        <div class="p-3 bg-light rounded-4 border text-center">
                            <label class="form-label small fw-bold text-muted d-block mb-3">Profile Photo</label>
                            
                            <div class="position-relative d-inline-block mb-3">
                                <img id="imagePreview" src="/vortex_wms/assets/images/employees/default.png" onerror="this.src='https://ui-avatars.com/api/?name=User&background=3b82f6&color=fff&size=150'" class="rounded-circle shadow-sm border" style="width:130px; height:130px; object-fit:cover;">
                            </div>

                            <div class="mt-2">
                                <label for="photoUpload" class="btn btn-outline-primary btn-sm rounded-pill px-3 cursor-pointer">
                                    <i class="fa-solid fa-camera me-1"></i> Upload Photo
                                </label>
                                <input type="file" name="photo" id="photoUpload" class="d-none" accept="image/*" onchange="previewFile(this)">
                                <small class="text-muted d-block mt-2" style="font-size:11px;">Max file size: 2MB (JPG, PNG)</small>
                            </div>
                        </div>
                    </div>

                    <!-- Personal & Account Details Columns -->
                    <div class="col-lg-9 col-md-8">
                        <div class="row g-3">

                            <!-- Full Name -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Full Name <span class="text-danger">*</span></label>
                                <input type="text" name="full_name" class="form-control border-2 fw-semibold" placeholder="e.g. Rahul Sharma" required>
                            </div>

                            <!-- Mobile -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Mobile Number <span class="text-danger">*</span></label>
                                <input type="text" name="mobile" class="form-control border-2 font-monospace" placeholder="+91 9876543210" required>
                            </div>

                            <!-- Official Email -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Official Email Address</label>
                                <input type="email" name="email" class="form-control border-2" placeholder="staff@vortexwms.com">
                            </div>

                            <!-- Gender -->
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Gender</label>
                                <select name="gender" class="form-select border-2">
                                    <option value="Male">Male</option>
                                    <option value="Female">Female</option>
                                    <option value="Other">Other</option>
                                </select>
                            </div>

                            <!-- Date of Birth -->
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Date Of Birth</label>
                                <input type="date" name="dob" class="form-control border-2" value="1995-01-01">
                            </div>

                            <!-- Department -->
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Department <span class="text-danger">*</span></label>
                                <select name="department" class="form-select border-2 fw-semibold" required>
                                    <option value="Operations">Operations</option>
                                    <option value="Inbound">Inbound</option>
                                    <option value="Outbound">Outbound</option>
                                    <option value="Inventory">Inventory</option>
                                    <option value="Management">Management</option>
                                    <option value="HR">HR</option>
                                    <option value="QC">QC</option>
                                    <option value="Warehouse">Warehouse</option>
                                </select>
                            </div>

                            <!-- Designation -->
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Designation</label>
                                <input type="text" name="designation" class="form-control border-2" placeholder="e.g. Senior Warehouse Associate">
                            </div>

                            <!-- Role -->
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Security Access Role <span class="text-danger">*</span></label>
                                <select name="role" id="roleSelect" class="form-select border-2 fw-semibold" required onchange="updateEmpIdFormat()">
                                    <optgroup label="Management">
                                        <option value="Super Admin">Super Admin</option>
                                        <option value="Admin">Admin</option>
                                        <option value="Warehouse Manager">Warehouse Manager</option>
                                        <option value="Inventory Manager">Inventory Manager</option>
                                        <option value="HR Manager">HR Manager</option>
                                        <option value="Operations Manager">Operations Manager</option>
                                    </optgroup>
                                    <optgroup label="Shop Floor & Logistics">
                                        <option value="Team Leader">Team Leader</option>
                                        <option value="Inventory Clerk" selected>Inventory Clerk</option>
                                        <option value="Picker">Picker</option>
                                        <option value="Packer">Packer</option>
                                        <option value="Inbound Operator">Inbound Operator</option>
                                        <option value="Outbound Operator">Outbound Operator</option>
                                    </optgroup>
                                </select>
                            </div>

                            <!-- Warehouse Allocation -->
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Assigned Warehouse Hub <span class="text-danger">*</span></label>
                                <select name="warehouse" class="form-select border-2 fw-semibold" required>
                                    <option value="">-- Choose Warehouse --</option>
                                    <?php foreach ($warehouses as $wh): ?>
                                        <option value="<?= htmlspecialchars($wh); ?>"><?= htmlspecialchars($wh); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Shift -->
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Operational Shift</label>
                                <select name="shift" class="form-select border-2">
                                    <option value="General" selected>General (09:00 - 18:00)</option>
                                    <option value="Morning">Morning (06:00 - 14:00)</option>
                                    <option value="Evening">Evening (14:00 - 22:00)</option>
                                    <option value="Night">Night (22:00 - 06:00)</option>
                                </select>
                            </div>

                            <!-- Joining Date -->
                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Joining Date</label>
                                <input type="date" name="joining_date" class="form-control border-2" value="<?= date('Y-m-d'); ?>">
                            </div>

                            <!-- Login Username -->
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Login Username <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-2"><i class="fa-solid fa-user text-muted"></i></span>
                                    <input type="text" name="username" class="form-control border-2 font-monospace fw-bold" placeholder="username" required>
                                </div>
                            </div>

                            <!-- Login Password -->
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Password <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text bg-light border-2"><i class="fa-solid fa-lock text-muted"></i></span>
                                    <input type="password" name="password" class="form-control border-2 font-monospace" placeholder="••••••••" required>
                                </div>
                            </div>

                            <!-- Status -->
                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Account Status</label>
                                <select name="status" class="form-select border-2 fw-semibold">
                                    <option value="Active" selected class="text-success">Active</option>
                                    <option value="Inactive" class="text-danger">Inactive</option>
                                </select>
                            </div>

                        </div>
                    </div>

                    <!-- Residential Address -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Residential Address</label>
                        <textarea name="address" class="form-control border-2" rows="3" placeholder="Enter residential address..."></textarea>
                    </div>

                    <!-- Internal Notes -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">HR Notes / Emergency Contact</label>
                        <textarea name="remarks" class="form-control border-2" rows="3" placeholder="Emergency contact person, phone number, medical notes..."></textarea>
                    </div>

                </div>

                <!-- Footer Action Buttons -->
                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Register Employee
                    </button>
                </div>
            </form>
        </div>
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

// Dynamic JavaScript ID preview based on role selection (Z series vs VRTX series)
function updateEmpIdFormat() {
    const roleSelect = document.getElementById('roleSelect');
    const selectedRole = roleSelect.options[roleSelect.selectedIndex].text;
    const optGroup = roleSelect.options[roleSelect.selectedIndex].parentNode.label;
    
    let currentIdText = document.getElementById('empIdText');
    let hiddenInput = document.getElementById('employeeIdHidden');
    
    // Agar Management group ya Super Admin/Admin select hua toh Z series
    if (optGroup === "Management" || selectedRole.includes("Manager") || selectedRole.includes("Admin")) {
        // Fallback dynamic preview (Backend save.php mein bhi ise validate kiya ja sakta hai)
        currentIdText.innerText = "Z003"; // Next available Z series example
        hiddenInput.value = "Z003";
    } else {
        currentIdText.innerText = "VRTX0001"; // Staff series
        hiddenInput.value = "VRTX0001";
    }
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>