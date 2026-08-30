<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Multi-Level Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$id = intval($_GET['id'] ?? 0);

if ($id <= 0) {
    $_SESSION['error'] = "Warehouse ID is missing.";
    header("Location: index.php");
    exit();
}

/* ==========================================================================
   1. DYNAMIC WAREHOUSE SCHEMA RESOLUTION
   ========================================================================== */

$whTable = "warehouse";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouse'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $whTable = "warehouses";
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

$whLocCol = "location";
$cChkLoc = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'location'");
if (!$cChkLoc || mysqli_num_rows($cChkLoc) === 0) {
    $whLocCol = "address";
}

/* ==========================================================================
   2. HANDLE WAREHOUSE UPDATE SUBMISSION
   ========================================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $warehouse_code = mysqli_real_escape_string($conn, trim($_POST['warehouse_code']));
    $warehouse_name = mysqli_real_escape_string($conn, trim($_POST['warehouse_name']));
    $location       = mysqli_real_escape_string($conn, trim($_POST['location']));
    $status         = mysqli_real_escape_string($conn, trim($_POST['status']));

    $updateSql = "
        UPDATE `{$whTable}` SET
            `{$whCodeCol}` = '$warehouse_code',
            `{$whNameCol}` = '$warehouse_name',
            `{$whLocCol}`  = '$location',
            `status`       = '$status'
        WHERE `id` = '$id'
    ";

    if (mysqli_query($conn, $updateSql)) {
        $_SESSION['success'] = "Warehouse facility <strong>{$warehouse_name}</strong> updated successfully.";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Failed to update warehouse: " . mysqli_error($conn);
    }
}

/* ==========================================================================
   3. FETCH EXISTING WAREHOUSE DATA
   ========================================================================== */

$warehouseQuery = mysqli_query($conn, "
    SELECT 
        id, 
        {$whCodeCol} AS wh_code, 
        {$whNameCol} AS wh_name, 
        COALESCE({$whLocCol}, '') AS wh_location,
        COALESCE(status, 'Active') AS status
    FROM `{$whTable}` 
    WHERE id = '$id' 
    LIMIT 1
");

if (!$warehouseQuery || mysqli_num_rows($warehouseQuery) === 0) {
    $_SESSION['error'] = "Warehouse #{$id} not found.";
    header("Location: index.php");
    exit();
}

$row = mysqli_fetch_assoc($warehouseQuery);

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Header Navigation -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Warehouse Facility
            </h2>
            <p class="text-muted mb-0">Modify facility codes, logistics location addresses & operational status</p>
        </div>
        <a href="index.php" class="btn btn-secondary fw-semibold rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Facilities
        </a>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Form Card -->
    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-9 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark">
                <i class="fa-solid fa-warehouse text-primary me-2"></i>Facility Information Form
            </h5>
            <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-3 py-1 rounded-pill font-monospace">
                EDITING ID #<?= $row['id']; ?>
            </span>
        </div>

        <div class="card-body p-4">
            <form method="POST">
                <div class="row g-4">

                    <!-- Warehouse Code -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Facility / Warehouse Code *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-barcode text-muted"></i></span>
                            <input type="text" name="warehouse_code" class="form-control border-2 font-monospace fw-bold text-uppercase" value="<?= htmlspecialchars($row['wh_code']); ?>" required>
                        </div>
                    </div>

                    <!-- Warehouse Name -->
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Warehouse Name *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-building text-muted"></i></span>
                            <input type="text" name="warehouse_name" class="form-control border-2 fw-semibold" value="<?= htmlspecialchars($row['wh_name']); ?>" required>
                        </div>
                    </div>

                    <!-- Location / Address -->
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">Physical Location / Address *</label>
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2"><i class="fa-solid fa-location-dot text-danger"></i></span>
                            <input type="text" name="location" class="form-control border-2" value="<?= htmlspecialchars($row['wh_location']); ?>" required>
                        </div>
                    </div>

                    <!-- Status -->
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Operating Status *</label>
                        <select name="status" class="form-select border-2 fw-semibold" required>
                            <option value="Active" <?= (strtolower($row['status']) === 'active' || $row['status'] == '1') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?= (strtolower($row['status']) === 'inactive' || $row['status'] == '0') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                </div>

                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="update" class="btn btn-warning px-5 fw-bold shadow-sm rounded-pill text-dark">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Update Warehouse
                    </button>
                </div>
            </form>
        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>