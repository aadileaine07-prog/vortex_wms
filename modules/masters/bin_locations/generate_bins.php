<?php
session_start();

$projectRoot = dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch Active Warehouses
$warehouses = mysqli_query($conn, "
    SELECT id, COALESCE(warehouse_name, name, warehouse_code) AS warehouse_name 
    FROM warehouses 
    WHERE status='Active' 
    ORDER BY warehouse_name ASC
");

if (isset($_POST['generate'])) {
    $warehouse_id = intval($_POST['warehouse_id']);
    $level        = trim($_POST['level']);          // e.g., L0
    $aisle_prefix = trim($_POST['aisle_prefix']);   // e.g., A
    $aisle_start  = intval($_POST['aisle_start']);  // e.g., 1
    $aisle_end    = intval($_POST['aisle_end']);    // e.g., 2
    $rack_start   = intval($_POST['rack_start']);   // e.g., 1
    $rack_end     = intval($_POST['rack_end']);     // e.g., 5
    $shelf_start  = intval($_POST['shelf_start']);  // e.g., 1
    $shelf_end    = intval($_POST['shelf_end']);    // e.g., 3
    $positions    = explode(',', $_POST['positions']); // e.g., A,B
    $zone_name    = mysqli_real_escape_string($conn, $_POST['zone_name'] ?? 'General Area');
    $max_capacity = intval($_POST['max_capacity'] ?? 100);

    $addedCount = 0;
    $skippedCount = 0;

    for ($a = $aisle_start; $a <= $aisle_end; $a++) {
        $aisleStr = $aisle_prefix . $a; // e.g., A1
        
        for ($r = $rack_start; $r <= $rack_end; $r++) {
            $rackStr = sprintf("%03d", $r); // e.g., 001
            
            for ($s = $shelf_start; $s <= $shelf_end; $s++) {
                $shelfStr = sprintf("%02d", $s); // e.g., 01
                
                foreach ($positions as $pos) {
                    $posStr = trim(strtoupper($pos));
                    if (empty($posStr)) continue;

                    // Code Pattern: L0-A1-001-01-A
                    $bin_code = "{$level}-{$aisleStr}-{$rackStr}-{$shelfStr}-{$posStr}";

                    $sql = "INSERT IGNORE INTO bin_locations (warehouse_id, bin_code, zone_name, max_capacity, status) 
                            VALUES ('$warehouse_id', '$bin_code', '$zone_name', '$max_capacity', 'Active')";
                    
                    if (mysqli_query($conn, $sql) && mysqli_affected_rows($conn) > 0) {
                        $addedCount++;
                    } else {
                        $skippedCount++;
                    }
                }
            }
        }
    }

    $_SESSION['success'] = "Generated <b>$addedCount</b> Bins successfully! (Duplicates Skipped: $skippedCount)";
    header("Location: index.php");
    exit();
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-wand-magic-sparkles text-primary me-2"></i>Automated Bin Generator</h2>
                <p class="text-muted mb-0">Auto-create matrix bins in pattern: <code>L0-A1-001-01-A</code></p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <div class="card shadow-sm border-0 rounded-4 col-lg-9 mx-auto">
            <div class="card-body p-4">
                <form method="POST">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Active Warehouse *</label>
                            <select name="warehouse_id" class="form-select" required>
                                <option value="">-- Choose Warehouse --</option>
                                <?php while ($w = mysqli_fetch_assoc($warehouses)): ?>
                                    <option value="<?= $w['id']; ?>"><?= htmlspecialchars($w['warehouse_name']); ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Level Code</label>
                            <input type="text" name="level" class="form-control" value="L0" required placeholder="e.g. L0 or L1">
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Zone Name</label>
                            <input type="text" name="zone_name" class="form-control" value="Zone-A">
                        </div>

                        <hr class="my-3">

                        <!-- Aisle Range -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Aisle Prefix</label>
                            <input type="text" name="aisle_prefix" class="form-control" value="A" required placeholder="e.g. A">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Aisle Range (Start - End)</label>
                            <div class="input-group">
                                <input type="number" name="aisle_start" class="form-control" value="1" min="1" required>
                                <span class="input-group-text">to</span>
                                <input type="number" name="aisle_end" class="form-control" value="2" min="1" required>
                            </div>
                        </div>

                        <!-- Rack Range -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Rack Range (001 - 099)</label>
                            <div class="input-group">
                                <input type="number" name="rack_start" class="form-control" value="1" min="1" required>
                                <span class="input-group-text">to</span>
                                <input type="number" name="rack_end" class="form-control" value="5" min="1" required>
                            </div>
                        </div>

                        <!-- Shelf Range -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Shelf Range (01 - 99)</label>
                            <div class="input-group">
                                <input type="number" name="shelf_start" class="form-control" value="1" min="1" required>
                                <span class="input-group-text">to</span>
                                <input type="number" name="shelf_end" class="form-control" value="3" min="1" required>
                            </div>
                        </div>

                        <!-- Bin Positions -->
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Bin Positions (Comma Separated)</label>
                            <input type="text" name="positions" class="form-control" value="A, B" required placeholder="e.g. A, B, C">
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Max Capacity per Bin</label>
                            <input type="number" name="max_capacity" class="form-control" value="100">
                        </div>

                    </div>

                    <div class="alert alert-info mt-4 mb-0 small">
                        <i class="fa-solid fa-circle-info me-1"></i> Upar wali settings se <b>(2 Aisles × 5 Racks × 3 Shelves × 2 Positions) = Total 60 Bins</b> 1-click me generate ho jayenge.
                    </div>

                    <hr class="my-4">

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary px-3">Cancel</a>
                        <button type="submit" name="generate" class="btn btn-primary px-4">
                            <i class="fa-solid fa-wand-magic-sparkles me-1"></i> Generate All Bins Now
                        </button>
                    </div>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>