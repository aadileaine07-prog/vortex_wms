<?php
// Output buffering start karein taaki header redirection mein koi issue na aaye
ob_start();

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

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

$rawId = $_GET['id'] ?? '';
$row = null;

if (!isset($conn) || !($conn instanceof mysqli)) {
    $_SESSION['error'] = "Database connection unavailable.";
    header("Location: index.php");
    exit();
}

// Smart Flexible Fetching (Supports both primary integer ID and string Employee ID)
if (!empty($rawId)) {
    if (is_numeric($rawId)) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM employees WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $rawId);
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
        }
    }
    
    if (!$row) {
        $stmt2 = mysqli_prepare($conn, "SELECT * FROM employees WHERE employee_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt2, "s", $rawId);
        mysqli_stmt_execute($stmt2);
        $res2 = mysqli_stmt_get_result($stmt2);
        if ($res2 && mysqli_num_rows($res2) > 0) {
            $row = mysqli_fetch_assoc($res2);
        }
    }
}

// Agar record nahi mila tabhi redirect karo
if (!$row) {
    $_SESSION['error'] = "Employee record not found or invalid ID.";
    header("Location: index.php");
    exit();
}

/* Fetch Warehouses */
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
$whQuery = @mysqli_query($conn, "SELECT DISTINCT {$whNameCol} AS wh_name FROM `{$whTable}` ORDER BY {$whNameCol} ASC");
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
    
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Employee Profile</h2>
            <p class="text-muted mb-0">Modify staff credentials, roles, and warehouse allocation</p>
        </div>
        <a href="index.php" class="btn btn-secondary fw-semibold rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Directory
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['error']); ?>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-11 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-user-pen text-primary me-2"></i>Update Account Information
            </h5>
            <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-3 py-1 rounded-pill font-monospace">
                EDITING ID: <?= htmlspecialchars($row['employee_id'] ?? 'N/A'); ?>
            </span>
        </div>

        <div class="card-body p-4">
            <form action="update.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="id" value="<?= $row['id'] ?? ''; ?>">
                <input type="hidden" name="old_photo" value="<?= htmlspecialchars($row['photo'] ?? 'default.png'); ?>">

                <div class="row g-4">
                    
                    <div class="col-lg-3 col-md-4 text-center">
                        <div class="p-3 bg-light rounded-4 border text-center">
                            <label class="form-label small fw-bold text-muted d-block mb-3">Profile Photo</label>
                            
                            <?php 
                            $photoVal = !empty($row['photo']) ? $row['photo'] : 'default.png';
                            $photoPath = "/vortex_wms/assets/images/employees/" . htmlspecialchars($photoVal);
                            ?>
                            <div class="position-relative d-inline-block mb-3">
                                <img id="imagePreview" src="<?= $photoPath; ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['full_name'] ?? 'User'); ?>&background=3b82f6&color=fff&size=150'" class="rounded-circle shadow-sm border" style="width:130px; height:130px; object-fit:cover;">
                            </div>

                            <div class="mt-2">
                                <label for="photoUpload" class="btn btn-outline-primary btn-sm rounded-pill px-3 cursor-pointer">
                                    <i class="fa-solid fa-camera me-1"></i> Change Photo
                                </label>
                                <input type="file" name="photo" id="photoUpload" class="d-none" accept="image/*" onchange="previewFile(this)">
                                <small class="text-muted d-block mt-2" style="font-size:11px;">Leave blank to keep current</small>
                            </div>
                        </div>
                    </div>

                    <div class="col-lg-9 col-md-8">
                        <div class="row g-3">
                            
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Full Name</label>
                                <input type="text" name="full_name" class="form-control border-2 fw-semibold" value="<?= htmlspecialchars($row['full_name'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Mobile Number</label>
                                <input type="text" name="mobile" class="form-control border-2 font-monospace" value="<?= htmlspecialchars($row['mobile'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Official Email Address</label>
                                <input type="email" name="email" class="form-control border-2" value="<?= htmlspecialchars($row['email'] ?? ''); ?>">
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Gender</label>
                                <select name="gender" class="form-select border-2">
                                    <option value="Male" <?= (($row['gender'] ?? '') === 'Male') ? 'selected' : ''; ?>>Male</option>
                                    <option value="Female" <?= (($row['gender'] ?? '') === 'Female') ? 'selected' : ''; ?>>Female</option>
                                    <option value="Other" <?= (($row['gender'] ?? '') === 'Other') ? 'selected' : ''; ?>>Other</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Date Of Birth</label>
                                <input type="date" name="dob" class="form-control border-2" value="<?= htmlspecialchars($row['dob'] ?? ''); ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Department</label>
                                <select name="department" class="form-select border-2 fw-semibold">
                                    <?php 
                                    $depts = ['Operations', 'Inbound', 'Outbound', 'Inventory', 'Management', 'HR', 'QC', 'Warehouse'];
                                    $currDept = $row['department'] ?? '';
                                    foreach($depts as $d) {
                                        $sel = ($currDept === $d) ? 'selected' : '';
                                        echo "<option value=\"$d\" $sel>$d</option>";
                                    }
                                    ?>
                                </select>
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Designation</label>
                                <input type="text" name="designation" class="form-control border-2" value="<?= htmlspecialchars($row['designation'] ?? ''); ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Security Access Role</label>
                                <input type="text" name="role" class="form-control border-2 fw-semibold" value="<?= htmlspecialchars($row['role'] ?? ''); ?>">
                            </div>

                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted">Assigned Warehouse Hub</label>
                                <select name="warehouse" class="form-select border-2 fw-semibold">
                                    <option value="">-- Choose Warehouse --</option>
                                    <?php 
                                    $currWh = $row['warehouse'] ?? '';
                                    foreach ($warehouses as $wh): 
                                        $sel = ($currWh === $wh) ? 'selected' : '';
                                    ?>
                                        <option value="<?= htmlspecialchars($wh); ?>" <?= $sel; ?>><?= htmlspecialchars($wh); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Operational Shift</label>
                                <select name="shift" class="form-select border-2">
                                    <?php $currShift = $row['shift'] ?? ''; ?>
                                    <option value="General" <?= ($currShift === 'General') ? 'selected' : ''; ?>>General</option>
                                    <option value="Morning" <?= ($currShift === 'Morning') ? 'selected' : ''; ?>>Morning</option>
                                    <option value="Evening" <?= ($currShift === 'Evening') ? 'selected' : ''; ?>>Evening</option>
                                    <option value="Night" <?= ($currShift === 'Night') ? 'selected' : ''; ?>>Night</option>
                                </select>
                            </div>

                            <div class="col-md-3">
                                <label class="form-label small fw-bold text-muted">Joining Date</label>
                                <input type="date" name="joining_date" class="form-control border-2" value="<?= htmlspecialchars($row['joining_date'] ?? ''); ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Login Username</label>
                                <input type="text" name="username" class="form-control border-2 font-monospace fw-bold" value="<?= htmlspecialchars($row['username'] ?? ''); ?>">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">New Password <small class="text-muted fw-normal">(Leave blank to keep current)</small></label>
                                <input type="password" name="password" class="form-control border-2 font-monospace" placeholder="••••••••">
                            </div>

                            <div class="col-md-4">
                                <label class="form-label small fw-bold text-muted">Account Status</label>
                                <select name="status" class="form-select border-2 fw-semibold">
                                    <?php $currStatus = $row['status'] ?? 'Active'; ?>
                                    <option value="Active" <?= ($currStatus === 'Active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="Inactive" <?= ($currStatus === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                            </div>

                        </div>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Residential Address</label>
                        <textarea name="address" class="form-control border-2" rows="3"><?= htmlspecialchars($row['address'] ?? ''); ?></textarea>
                    </div>

                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">HR Notes / Emergency Contact</label>
                        <textarea name="remarks" class="form-control border-2" rows="3"><?= htmlspecialchars($row['remarks'] ?? ''); ?></textarea>
                    </div>

                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" class="btn btn-warning px-5 fw-bold shadow-sm rounded-pill text-dark">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Update Employee
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
</script>

<?php 
include $projectRoot . "/includes/footer.php"; 
ob_end_flush();
?>