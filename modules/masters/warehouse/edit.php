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

$conn = null;
require_once $projectRoot . "/config/database.php";

$id = intval($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = "Invalid Warehouse ID.";
    header("Location: index.php");
    exit();
}

// Table schema check
$whTable = "warehouses";
$chkTable = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouses'");
if (!$chkTable || mysqli_num_rows($chkTable) === 0) {
    $whTable = "warehouse";
}

// Fetch Warehouse Record Safely
$query = "SELECT * FROM `{$whTable}` WHERE id = $id LIMIT 1";
$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) === 0) {
    $_SESSION['error'] = "Warehouse record not found.";
    header("Location: index.php");
    exit();
}

$row = mysqli_fetch_assoc($result) ?? [];

// Column name fallbacks
$codeVal = $row['warehouse_code'] ?? $row['code'] ?? '';
$nameVal = $row['warehouse_name'] ?? $row['name'] ?? '';
$locVal  = $row['address'] ?? $row['location'] ?? '';
$statusVal = $row['status'] ?? 'Active';

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Warehouse Facility</h2>
            <p class="text-muted mb-0">Modify facility codes, logistics location addresses & operational status</p>
        </div>
        <a href="index.php" class="btn btn-secondary rounded-pill px-4 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Facilities
        </a>
    </div>

    <div class="card shadow-sm border-0 rounded-4 bg-white col-xl-9 mx-auto mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-building text-primary me-2"></i>Edit Facility Information Form</h5>
            <span class="badge bg-warning-subtle text-dark border border-warning-subtle px-3 py-1 rounded-pill font-monospace">EDITING ID: <?= $row['id'] ?? $id; ?></span>
        </div>
        <div class="card-body p-4">
            <form action="update.php" method="POST">
                <input type="hidden" name="id" value="<?= $row['id'] ?? $id; ?>">
                
                <div class="row g-4">
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Warehouse / Facility Code *</label>
                        <input type="text" name="warehouse_code" class="form-control border-2 font-monospace fw-bold text-uppercase" value="<?= htmlspecialchars($codeVal); ?>" required>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label small fw-bold text-muted">Warehouse Name *</label>
                        <input type="text" name="warehouse_name" class="form-control border-2 fw-semibold" value="<?= htmlspecialchars($nameVal); ?>" required>
                    </div>
                    <div class="col-md-8">
                        <label class="form-label small fw-bold text-muted">Physical Location / Full Address *</label>
                        <input type="text" name="address" class="form-control border-2" value="<?= htmlspecialchars($locVal); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Operating Status *</label>
                        <select name="status" class="form-select border-2 fw-semibold" required>
                            <option value="Active" <?= ($statusVal === 'Active' || $statusVal == '1') ? 'selected' : ''; ?>>Active & Operational</option>
                            <option value="Inactive" <?= ($statusVal === 'Inactive' || $statusVal == '0') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>
                </div>
                <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4">
                    <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                    <button type="submit" name="update_warehouse" class="btn btn-warning px-5 fw-bold shadow-sm rounded-pill text-dark">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Update Warehouse
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>