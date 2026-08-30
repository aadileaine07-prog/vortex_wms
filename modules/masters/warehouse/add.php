<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

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

$whCodeCol = "warehouse_code";
$cChkCode = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_code'");
if (!$cChkCode || mysqli_num_rows($cChkCode) === 0) {
    $whCodeCol = "code";
}

$whLocCol = "address";
$cChkLoc = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'address'");
if (!$cChkLoc || mysqli_num_rows($cChkLoc) === 0) {
    $whLocCol = "location";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_warehouse'])) {
    $wh_code = mysqli_real_escape_string($conn, strtoupper(trim($_POST['warehouse_code'] ?? '')));
    $wh_name = mysqli_real_escape_string($conn, trim($_POST['warehouse_name'] ?? ''));
    $address = mysqli_real_escape_string($conn, trim($_POST['address'] ?? ''));
    $status  = mysqli_real_escape_string($conn, trim($_POST['status'] ?? 'Active'));

    if (empty($wh_code) || empty($wh_name)) {
        $_SESSION['error'] = "Warehouse code and name are mandatory.";
    } else {
        $checkDup = mysqli_query($conn, "SELECT id FROM `{$whTable}` WHERE `{$whCodeCol}` = '$wh_code' LIMIT 1");
        if ($checkDup && mysqli_num_rows($checkDup) > 0) {
            $_SESSION['error'] = "Warehouse code <strong>{$wh_code}</strong> already exists.";
        } else {
            $insertSql = "INSERT INTO `{$whTable}` (`{$whCodeCol}`, `{$whNameCol}`, `{$whLocCol}`, `status`) VALUES ('$wh_code', '$wh_name', '$address', '$status')";
            if (mysqli_query($conn, $insertSql)) {
                $_SESSION['success'] = "Warehouse <strong>{$wh_name}</strong> created successfully!";
                header("Location: index.php");
                exit();
            } else {
                $_SESSION['error'] = "Database error: " . mysqli_error($conn);
            }
        }
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-plus text-primary me-2"></i>Add Warehouse Facility</h2>
            <p class="text-muted mb-0">Register physical storage hubs and facility addresses</p>
        </div>
        <a href="index.php" class="btn btn-secondary rounded-pill px-4 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Facilities
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-9 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-building text-primary me-2"></i>New Storage Facility Intake</h5>
            <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-3 py-1 rounded-pill font-monospace">MASTER HUB DATA</span>
        </div>
        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Warehouse / Facility Code *</label>
                        <input type="text" name="warehouse_code" class="form-control border-2 font-monospace fw-bold text-uppercase" placeholder="e.g. WH-01, WH-GUJ-01" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Warehouse Name *</label>
                        <input type="text" name="warehouse_name" class="form-control border-2 fw-semibold" placeholder="e.g. Surat Central Logistics Park" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">Physical Location / Full Address *</label>
                        <input type="text" name="address" class="form-control border-2" placeholder="e.g. Plot 42-45, Sachin GIDC Industrial Area, Surat" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Operating Status *</label>
                        <select name="status" class="form-select border-2 fw-semibold" required>
                            <option value="Active">Active & Operational</option>
                            <option value="Inactive">Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="save_warehouse" class="btn btn-primary px-5 fw-bold shadow-sm rounded-pill">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Warehouse
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>