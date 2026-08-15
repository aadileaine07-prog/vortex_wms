<?php
session_start();

// Dynamic Project Root
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) { 
    header("Location: /vortex_wms/login.php"); 
    exit(); 
}

require_once $projectRoot . "/config/database.php";

// Fetch Bins along with total available quantity and item details from `bin_locations` and `inventory`
$query = "SELECT 
            b.id,
            b.bin_code,
            COALESCE(b.zone_name, 'Zone-A') AS zone_name,
            COALESCE(b.zone_type, 'Regular') AS zone_type,
            COALESCE(b.max_capacity, 100) AS max_capacity,
            COALESCE(SUM(i.available_qty), 0) AS current_qty,
            GROUP_CONCAT(CONCAT(i.product_code, ' - ', i.product_name, ' (', i.available_qty, ' Units)') SEPARATOR '<br>') AS item_details
          FROM bin_locations b
          LEFT JOIN inventory i ON b.bin_code = i.bin_location 
          GROUP BY b.id, b.bin_code, b.zone_name, b.zone_type, b.max_capacity
          ORDER BY b.zone_name ASC, b.bin_code ASC";

$locations = mysqli_query($conn, $query);

// Summary Stats Counters
$total_bins = 0;
$empty_bins = 0;
$partial_bins = 0;
$full_bins = 0;

$bin_data = [];
if ($locations && mysqli_num_rows($locations) > 0) {
    while ($row = mysqli_fetch_assoc($locations)) {
        $bin_data[] = $row;
        $total_bins++;
        $qty = (int)$row['current_qty'];
        $max_cap = (int)$row['max_capacity'];

        if ($qty == 0) {
            $empty_bins++;
        } elseif ($qty >= $max_cap) {
            $full_bins++;
        } else {
            $partial_bins++;
        }
    }
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<style>
.bin-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(160px, 1fr)); 
    gap: 18px; 
}

.bin-card { 
    border-radius: 14px; 
    padding: 16px; 
    text-align: center; 
    font-weight: 600; 
    color: #fff; 
    transition: all 0.25s ease-in-out;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(0,0,0,0.06);
}

.bin-card:hover { 
    transform: translateY(-5px) scale(1.03); 
    box-shadow: 0 8px 20px rgba(0,0,0,0.15);
}

.bin-empty { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
.bin-partial { background: linear-gradient(135deg, #f59e0b 0%, #d97706 100%); }
.bin-full { background: linear-gradient(135deg, #ef4444 0%, #dc2626 100%); }
</style>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header & Filter -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-border-all text-primary me-2"></i>Visual Bin Map</h2>
                <p class="text-muted mb-0">Real-time occupancy layout grid across warehouse zones & bin locations</p>
            </div>
            
            <!-- Zone Filter Dropdown -->
            <div class="d-flex align-items-center gap-2">
                <label class="fw-bold text-secondary mb-0"><i class="fa-solid fa-filter me-1"></i> Filter Zone:</label>
                <select id="zoneFilter" class="form-select rounded-3 shadow-sm" style="width: 180px;" onchange="filterZone()">
                    <option value="ALL">All Zones</option>
                    <?php
                    $zones = array_unique(array_column($bin_data, 'zone_name'));
                    foreach ($zones as $z) {
                        if(!empty($z)) echo "<option value='".htmlspecialchars($z)."'>".htmlspecialchars($z)."</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <!-- Occupancy Summary Stats -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 text-center bg-white">
                    <small class="text-muted fw-bold">TOTAL BINS</small>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= $total_bins; ?></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 text-center bg-white border-start border-4 border-success">
                    <small class="text-success fw-bold">🟢 EMPTY (AVAILABLE)</small>
                    <h3 class="fw-bold text-success mb-0 mt-1"><?= $empty_bins; ?></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 text-center bg-white border-start border-4 border-warning">
                    <small class="text-warning fw-bold">🟡 PARTIAL FILLED</small>
                    <h3 class="fw-bold text-warning mb-0 mt-1"><?= $partial_bins; ?></h3>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 text-center bg-white border-start border-4 border-danger">
                    <small class="text-danger fw-bold">🔴 FULL CAPACITY</small>
                    <h3 class="fw-bold text-danger mb-0 mt-1"><?= $full_bins; ?></h3>
                </div>
            </div>
        </div>

        <!-- Bin Layout Grid -->
        <div class="bin-grid">
            <?php if (!empty($bin_data)): ?>
                <?php foreach ($bin_data as $bin): 
                    $qty = (int)$bin['current_qty'];
                    $max_cap = (int)$bin['max_capacity'];
                    
                    // Bin status classification
                    if ($qty == 0) {
                        $class = 'bin-empty';
                    } elseif ($qty >= $max_cap) {
                        $class = 'bin-full';
                    } else {
                        $class = 'bin-partial';
                    }

                    $items = $bin['item_details'] ? htmlspecialchars_decode($bin['item_details']) : 'Bin is currently empty.';
                ?>
                    <div class="bin-card <?= $class; ?> bin-item" data-zone="<?= htmlspecialchars($bin['zone_name']); ?>" 
                         onclick="showBinDetails('<?= htmlspecialchars($bin['bin_code']); ?>', '<?= $qty; ?>', '<?= $max_cap; ?>', '<?= htmlspecialchars($bin['zone_type']); ?>', '<?= htmlspecialchars(addslashes($items)); ?>')">
                        <div class="small opacity-75 text-uppercase" style="font-size:11px;">
                            <?= htmlspecialchars($bin['zone_name']); ?> • <?= htmlspecialchars($bin['zone_type']); ?>
                        </div>
                        <div class="fs-5 fw-bold my-1"><?= htmlspecialchars($bin['bin_code']); ?></div>
                        <span class="badge bg-white text-dark rounded-pill px-3 py-1 shadow-sm fs-7"><?= $qty; ?> / <?= $max_cap; ?> Units</span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 text-muted">
                    <i class="fa-solid fa-box-open fs-1 mb-2 d-block"></i>
                    No Bins Found in Bin Locations Master.
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal for Viewing Items in Selected Bin -->
<div class="modal fade" id="binDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-dark text-white rounded-top-4">
                <h5 class="modal-title fw-bold" id="modalBinTitle"><i class="fa-solid fa-boxes-stacked me-2"></i>Bin Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="text-muted fw-semibold">Zone Type:</span>
                    <span id="modalBinZoneType" class="badge bg-secondary fs-6 px-3 py-1 rounded-pill">Regular</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted fw-semibold">Occupancy Status:</span>
                    <span id="modalBinQty" class="badge bg-primary fs-6 px-3 py-2 rounded-pill">0 / 0 Units</span>
                </div>
                <h6 class="fw-bold mb-2 text-secondary">Stored Items (Product Code & Name):</h6>
                <div id="modalBinItems" class="p-3 bg-light rounded-3 text-dark font-monospace small"></div>
            </div>
        </div>
    </div>
</div>

<script>
// Live Filter Bins by Zone
function filterZone() {
    const selectedZone = document.getElementById('zoneFilter').value;
    const cards = document.querySelectorAll('.bin-item');

    cards.forEach(card => {
        if (selectedZone === 'ALL' || card.getAttribute('data-zone') === selectedZone) {
            card.style.display = 'block';
        } else {
            card.style.display = 'none';
        }
    });
}

// Show Bin Content in Popup Modal
function showBinDetails(binCode, qty, maxCap, zoneType, items) {
    document.getElementById('modalBinTitle').innerHTML = '<i class="fa-solid fa-location-dot text-primary me-2"></i>' + binCode;
    document.getElementById('modalBinZoneType').innerText = zoneType + ' Zone';
    document.getElementById('modalBinQty').innerText = qty + ' / ' + maxCap + ' Units';
    document.getElementById('modalBinItems').innerHTML = items;
    
    var modal = new bootstrap.Modal(document.getElementById('binDetailModal'));
    modal.show();
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>