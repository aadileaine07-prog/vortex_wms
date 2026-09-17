<?php
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

// 1. Check if ID is provided and try fetching via numeric ID or string Employee ID
if (!empty($rawId)) {
    if (is_numeric($rawId)) {
        $stmt = mysqli_prepare($conn, "SELECT * FROM employees WHERE id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "i", $rawId);
    } else {
        $stmt = mysqli_prepare($conn, "SELECT * FROM employees WHERE employee_id = ? LIMIT 1");
        mysqli_stmt_bind_param($stmt, "s", $rawId);
    }
    
    if ($stmt) {
        mysqli_stmt_execute($stmt);
        $res = mysqli_stmt_get_result($stmt);
        if ($res && mysqli_num_rows($res) > 0) {
            $row = mysqli_fetch_assoc($res);
        }
    }
}

// 2. Fallback: Agar specific ID se data na mile, toh sabse latest employee utha lo
if (!$row) {
    $fallbackRes = mysqli_query($conn, "SELECT * FROM employees ORDER BY id DESC LIMIT 1");
    if ($fallbackRes && mysqli_num_rows($fallbackRes) > 0) {
        $row = mysqli_fetch_assoc($fallbackRes);
    } else {
        $_SESSION['error'] = "No employee records found in database.";
        header("Location: index.php");
        exit();
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    
    <!-- Top Header Toolbar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-id-badge text-primary me-2"></i>Employee Profile View
            </h2>
            <p class="text-muted mb-0">Detailed staff profile, access credentials, and assignments</p>
        </div>
        <div class="d-flex gap-2">
            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning fw-bold rounded-pill px-3 shadow-sm text-dark">
                <i class="fa-solid fa-pen-to-square me-1"></i> Edit Profile
            </a>
            <a href="index.php" class="btn btn-secondary fw-semibold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Directory
            </a>
        </div>
    </div>

    <!-- Alert Messages -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="row g-4">
        
        <!-- Left Column: Profile Card & Photo -->
        <div class="col-xl-4 col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 bg-white text-center p-4 h-100">
                <div class="card-body">
                    <?php 
                    $photoVal = !empty($row['photo']) ? $row['photo'] : 'default.png';
                    $photoPath = "/vortex_wms/assets/images/employees/" . htmlspecialchars($photoVal);
                    ?>
                    <img src="<?= $photoPath; ?>" onerror="this.src='https://ui-avatars.com/api/?name=<?= urlencode($row['full_name'] ?? 'User'); ?>&background=3b82f6&color=fff&size=150'" class="rounded-circle shadow-sm border mb-3" style="width:140px; height:140px; object-fit:cover; border: 4px solid #f8f9fa;">
                    
                    <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($row['full_name'] ?? 'Unknown Employee'); ?></h4>
                    <p class="text-muted font-monospace small mb-3">@<?= htmlspecialchars($row['username'] ?? 'username'); ?></p>

                    <div class="mb-3">
                        <?php 
                        $empId = $row['employee_id'] ?? 'N/A';
                        $badgeColor = (strncmp($empId, "Z", 1) === 0) ? "bg-primary-subtle text-primary border-primary-subtle" : "bg-success-subtle text-success border-success-subtle";
                        ?>
                        <span class="badge <?= $badgeColor; ?> border px-3 py-1 rounded-pill font-monospace fs-6">
                            <?= htmlspecialchars($empId); ?>
                        </span>
                    </div>

                    <div class="d-flex justify-content-center gap-2 mb-4">
                        <?php if (($row['status'] ?? 'Active') === 'Active'): ?>
                            <span class="badge bg-success px-3 py-1 rounded-pill">Active Status</span>
                        <?php else: ?>
                            <span class="badge bg-danger px-3 py-1 rounded-pill">Inactive Status</span>
                        <?php endif; ?>
                        
                        <span class="badge bg-info-subtle text-info border border-info-subtle px-3 py-1 rounded-pill">
                            <?= htmlspecialchars($row['shift'] ?? 'General'); ?> Shift
                        </span>
                    </div>

                    <hr class="text-muted opacity-25 my-4">

                    <div class="text-start small">
                        <div class="mb-2"><strong class="text-muted">Mobile:</strong> <span class="font-monospace fw-semibold float-end"><?= htmlspecialchars($row['mobile'] ?? 'N/A'); ?></span></div>
                        <div class="mb-2"><strong class="text-muted">Email:</strong> <span class="fw-semibold float-end"><?= htmlspecialchars($row['email'] ?? 'N/A'); ?></span></div>
                        <div class="mb-2"><strong class="text-muted">Gender:</strong> <span class="fw-semibold float-end"><?= htmlspecialchars($row['gender'] ?? 'N/A'); ?></span></div>
                        <div class="mb-2"><strong class="text-muted">Date of Birth:</strong> <span class="font-monospace fw-semibold float-end"><?= htmlspecialchars($row['dob'] ?? 'N/A'); ?></span></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Right Column: Professional & Logistics Details -->
        <div class="col-xl-8 col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-briefcase text-primary me-2"></i>Professional & Assignment Overview</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-4">
                        
                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border-0 h-100">
                                <small class="text-muted fw-bold d-block mb-1 text-uppercase" style="font-size: 11px;">Department</small>
                                <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($row['department'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border-0 h-100">
                                <small class="text-muted fw-bold d-block mb-1 text-uppercase" style="font-size: 11px;">Designation</small>
                                <span class="fw-bold text-dark fs-5"><?= htmlspecialchars($row['designation'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border-0 h-100">
                                <small class="text-muted fw-bold d-block mb-1 text-uppercase" style="font-size: 11px;">Security Access Role</small>
                                <span class="badge bg-primary fs-6 px-3 py-1 rounded-pill mt-1"><?= htmlspecialchars($row['role'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border-0 h-100">
                                <small class="text-muted fw-bold d-block mb-1 text-uppercase" style="font-size: 11px;">Assigned Warehouse Hub</small>
                                <span class="fw-bold text-success fs-5"><i class="fa-solid fa-building me-1"></i><?= htmlspecialchars($row['warehouse'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border-0 h-100">
                                <small class="text-muted fw-bold d-block mb-1 text-uppercase" style="font-size: 11px;">Joining Date</small>
                                <span class="font-monospace fw-semibold text-dark"><?= htmlspecialchars($row['joining_date'] ?? 'N/A'); ?></span>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <div class="p-3 bg-light rounded-4 border-0 h-100">
                                <small class="text-muted fw-bold d-block mb-1 text-uppercase" style="font-size: 11px;">Last Activity</small>
                                <span class="font-monospace fw-semibold text-secondary"><?= htmlspecialchars($row['last_activity'] ?? 'Never logged in'); ?></span>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="p-3 bg-light rounded-4 border-0">
                                <small class="text-muted fw-bold d-block mb-1 text-uppercase" style="font-size: 11px;">Residential Address</small>
                                <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($row['address'] ?? 'No address provided.')); ?></p>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="p-3 bg-light rounded-4 border-0">
                                <small class="text-muted fw-bold d-block mb-1 text-uppercase" style="font-size: 11px;">HR Notes / Emergency Contact</small>
                                <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($row['remarks'] ?? 'No remarks added.')); ?></p>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>