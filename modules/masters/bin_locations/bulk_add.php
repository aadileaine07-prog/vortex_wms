<?php
session_start();

$projectRoot = dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch ONLY Active Warehouses
$warehouses = mysqli_query($conn, "
    SELECT id, warehouse_code, warehouse_name 
    FROM warehouse 
    WHERE status = 'Active'
    ORDER BY id ASC
");

if (isset($_POST['save_bulk'])) {
    $warehouse_id = intval($_POST['warehouse_id']);
    $zone_name    = mysqli_real_escape_string($conn, $_POST['zone_name'] ?? 'Zone-A');
    $zone_type    = mysqli_real_escape_string($conn, $_POST['zone_type'] ?? 'Regular');
    $max_capacity = intval($_POST['max_capacity'] ?? 100);
    $raw_codes    = trim($_POST['bin_codes'] ?? '');

    // Convert multi-line/comma input into unique array
    $codes = array_filter(array_map('trim', preg_split('/[\r\n,]+/', $raw_codes)));

    if (!empty($codes) && $warehouse_id > 0) {
        $addedCount = 0;
        $skippedCount = 0;

        foreach ($codes as $code) {
            $bin_code = mysqli_real_escape_string($conn, strtoupper($code));
            
            $sql = "INSERT IGNORE INTO bin_locations (warehouse_id, bin_code, zone_name, zone_type, max_capacity, status) 
                    VALUES ('$warehouse_id', '$bin_code', '$zone_name', '$zone_type', '$max_capacity', 'Active')";
            
            if (mysqli_query($conn, $sql) && mysqli_affected_rows($conn) > 0) {
                $addedCount++;
            } else {
                $skippedCount++;
            }
        }

        $_SESSION['success'] = "Bulk Import Complete! Successfully Added: <b>$addedCount</b> Bins in <b>$zone_type Zone</b>. (Skipped/Duplicates: $skippedCount)";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Please select an Active Warehouse and enter valid Bin Codes.";
    }
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-layer-group text-primary me-2"></i>Bulk Add Bin Locations</h2>
                <p class="text-muted mb-0">Create dozens of bins in one click using format: <code>L0-A1-001-01-A</code></p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show"><?= $_SESSION['error']; unset($_SESSION['error']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-4 col-lg-9 mx-auto">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row g-3">

                        <!-- Warehouse Selection -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Target Warehouse *</label>
                            <select name="warehouse_id" class="form-select" required>
                                <option value="">-- Select Active Warehouse --</option>
                                <?php if ($warehouses && mysqli_num_rows($warehouses) > 0): ?>
                                    <?php while ($w = mysqli_fetch_assoc($warehouses)): ?>
                                        <option value="<?= $w['id']; ?>">
                                            <?= htmlspecialchars($w['warehouse_name']); ?> (<?= htmlspecialchars($w['warehouse_code']); ?>)
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <!-- Zone / Area Name -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Zone / Area Name *</label>
                            <input type="text" name="zone_name" class="form-control" placeholder="e.g. Zone-A or Aisle-A1" value="Zone-A" required>
                        </div>

                        <!-- Zone Type Selection -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Zone Type *</label>
                            <select name="zone_type" class="form-select" required>
                                <option value="Regular">📦 Regular Zone</option>
                                <option value="Toxic">⚠️ Toxic / Hazardous Zone</option>
                                <option value="Toys">🧸 Toys Zone</option>
                                <option value="Apparel">👕 Apparel Zone</option>
                                <option value="Festive">🎉 Festive / Seasonal Zone</option>
                                <option value="SPR">⚙️ SPR (Spare Parts) Zone</option>
                                <option value="Cold Storage">❄️ Cold Storage Zone</option>
                                <option value="High Value">🔒 High Value / Secure Zone</option>
                            </select>
                        </div>

                        <!-- Max Capacity -->
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Max Capacity (per bin)</label>
                            <input type="number" name="max_capacity" class="form-control" value="100" min="1">
                        </div>

                        <!-- Bin Codes Input Area -->
                        <div class="col-12">
                            <label class="form-label fw-semibold">Bin Codes List (Format: Level-Aisle-Rack-Shelf-Bin) *</label>
                            <textarea name="bin_codes" class="form-control font-monospace" rows="10" placeholder="L0-A1-001-01-A&#10;L0-A1-001-01-B&#10;L0-A1-001-02-A&#10;L0-A1-001-02-B" required></textarea>
                            <small class="text-muted">Tip: Har line me 1 bin code rakhein ya comma se separate karke paste karein.</small>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary px-3">Cancel</a>
                        <button type="submit" name="save_bulk" class="btn btn-primary px-4">
                            <i class="fa-solid fa-bolt me-1"></i> Add All Bins At Once
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>