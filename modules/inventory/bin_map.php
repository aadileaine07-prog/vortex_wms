<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) { 
    header("Location: /vortex_wms/login.php"); 
    exit(); 
}

require_once $projectRoot . "/config/database.php";

// 1. Detect Schema Column Names Dynamically
$zoneCol = 'zone';
$capCol  = 'max_capacity_kg';

$colCheckZone = @mysqli_query($conn, "SHOW COLUMNS FROM bin_locations LIKE 'zone_name'");
if ($colCheckZone && mysqli_num_rows($colCheckZone) > 0) {
    $zoneCol = 'zone_name';
}

$colCheckCap = @mysqli_query($conn, "SHOW COLUMNS FROM bin_locations LIKE 'max_capacity'");
if ($colCheckCap && mysqli_num_rows($colCheckCap) > 0) {
    $capCol = 'max_capacity';
}

// 2. Optimized Join Query
$query = "SELECT 
            b.id,
            b.bin_code,
            COALESCE(b.{$zoneCol}, 'Zone A') AS zone_name,
            COALESCE(b.{$capCol}, 500) AS max_capacity,
            COALESCE(SUM(i.available_qty), 0) AS current_qty,
            GROUP_CONCAT(CONCAT(i.product_code, '|||', i.product_name, '|||', i.available_qty) SEPARATOR '###') AS raw_items
          FROM bin_locations b
          LEFT JOIN inventory i ON b.bin_code = i.bin_location 
          GROUP BY b.id, b.bin_code, b.{$zoneCol}, b.{$capCol}
          ORDER BY zone_name ASC, b.bin_code ASC";

$locations = mysqli_query($conn, $query);

// Summary Stats Counters
$total_bins   = 0;
$empty_bins   = 0;
$partial_bins = 0;
$full_bins    = 0;

$bin_data = [];
if ($locations && mysqli_num_rows($locations) > 0) {
    while ($row = mysqli_fetch_assoc($locations)) {
        $bin_data[] = $row;
        $total_bins++;
        $qty     = (int)$row['current_qty'];
        $max_cap = (int)$row['max_capacity'] > 0 ? (int)$row['max_capacity'] : 500;

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
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); 
    gap: 16px; 
}

.bin-card { 
    border-radius: 16px; 
    padding: 16px; 
    color: #fff; 
    transition: all 0.22s ease-in-out;
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
    border: 1px solid rgba(255,255,255,0.15);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
}

.bin-card:hover { 
    transform: translateY(-4px) scale(1.02); 
    box-shadow: 0 10px 24px rgba(0,0,0,0.18);
}

.bin-empty   { background: linear-gradient(135deg, #10b981 0%, #047857 100%); }
.bin-partial { background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%); }
.bin-full    { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); }

.progress-track {
    background: rgba(0, 0, 0, 0.25);
    border-radius: 20px;
    height: 6px;
    overflow: hidden;
    margin-top: 8px;
}
.progress-fill {
    background: #fff;
    height: 100%;
    border-radius: 20px;
}
</style>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Navigation -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-border-all text-primary me-2"></i>Visual Bin Map</h2>
                <p class="text-muted mb-0">Live real-time occupancy grid layout across warehouse zones and storage locations</p>
            </div>
            
            <!-- Filters & Search Toolbar -->
            <div class="d-flex align-items-center gap-2 flex-wrap">
                <!-- Search Box -->
                <div class="input-group" style="width: 220px;">
                    <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                    <input type="text" id="binSearchInput" class="form-control border-start-0" placeholder="Search Bin Code..." onkeyup="filterBins()">
                </div>

                <!-- Zone Filter -->
                <select id="zoneFilter" class="form-select border-2 shadow-sm fw-semibold" style="width: 170px;" onchange="filterBins()">
                    <option value="ALL">All Zones</option>
                    <?php
                    $zones = array_unique(array_column($bin_data, 'zone_name'));
                    foreach ($zones as $z) {
                        if(!empty($z)) echo "<option value='".htmlspecialchars($z)."'>".htmlspecialchars($z)."</option>";
                    }
                    ?>
                </select>

                <a href="/vortex_wms/modules/masters/bin_locations/bulk_add.php" class="btn btn-primary fw-bold shadow-sm rounded-pill px-3">
                    <i class="fa-solid fa-plus me-1"></i> Add Bins
                </a>
            </div>
        </div>

        <!-- Occupancy Summary Stats Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-muted fw-bold">TOTAL LOCATIONS</small>
                            <h3 class="fw-bold text-dark mb-0 mt-1"><?= $total_bins; ?></h3>
                        </div>
                        <i class="fa-solid fa-warehouse fa-2x text-muted opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-success fw-bold">🟢 EMPTY (READY)</small>
                            <h3 class="fw-bold text-success mb-0 mt-1"><?= $empty_bins; ?></h3>
                        </div>
                        <i class="fa-solid fa-circle-check fa-2x text-success opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-warning fw-bold">🟡 PARTIAL OCCUPIED</small>
                            <h3 class="fw-bold text-warning mb-0 mt-1"><?= $partial_bins; ?></h3>
                        </div>
                        <i class="fa-solid fa-boxes-stacked fa-2x text-warning opacity-50"></i>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <small class="text-danger fw-bold">🔴 FULL CAPACITY</small>
                            <h3 class="fw-bold text-danger mb-0 mt-1"><?= $full_bins; ?></h3>
                        </div>
                        <i class="fa-solid fa-ban fa-2x text-danger opacity-50"></i>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bin Layout Visual Grid -->
        <div class="bin-grid">
            <?php if (!empty($bin_data)): ?>
                <?php foreach ($bin_data as $bin): 
                    $qty     = (int)$bin['current_qty'];
                    $max_cap = (int)$bin['max_capacity'] > 0 ? (int)$bin['max_capacity'] : 500;
                    $pct     = min(100, round(($qty / $max_cap) * 100));
                    
                    if ($qty == 0) {
                        $class = 'bin-empty';
                        $statusBadge = 'Empty';
                    } elseif ($qty >= $max_cap) {
                        $class = 'bin-full';
                        $statusBadge = 'Full';
                    } else {
                        $class = 'bin-partial';
                        $statusBadge = $pct . '% Fill';
                    }

                    $rawItems = !empty($bin['raw_items']) ? htmlspecialchars($bin['raw_items'], ENT_QUOTES) : '';
                ?>
                    <div class="bin-card <?= $class; ?> bin-item" 
                         data-zone="<?= htmlspecialchars($bin['zone_name']); ?>" 
                         data-bincode="<?= htmlspecialchars(strtoupper($bin['bin_code'])); ?>"
                         onclick="showBinDetails('<?= htmlspecialchars($bin['bin_code']); ?>', '<?= $qty; ?>', '<?= $max_cap; ?>', '<?= htmlspecialchars($bin['zone_name']); ?>', '<?= $rawItems; ?>')">
                        
                        <div>
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="small opacity-75 font-monospace text-uppercase" style="font-size:10px;"><?= htmlspecialchars($bin['zone_name']); ?></span>
                                <span class="badge bg-black bg-opacity-25 rounded-pill px-2 py-0" style="font-size: 10px;"><?= $statusBadge; ?></span>
                            </div>
                            <div class="fs-5 fw-bold font-monospace text-truncate"><?= htmlspecialchars($bin['bin_code']); ?></div>
                        </div>

                        <div>
                            <div class="d-flex justify-content-between align-items-center mt-2 small opacity-90">
                                <span>Stock:</span>
                                <strong class="font-monospace"><?= $qty; ?> / <?= $max_cap; ?></strong>
                            </div>
                            <div class="progress-track">
                                <div class="progress-fill" style="width: <?= $pct; ?>%;"></div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="col-12 text-center py-5 text-muted bg-white rounded-4 shadow-sm w-100">
                    <i class="fa-solid fa-boxes-packing fs-1 mb-2 d-block text-primary"></i>
                    <h5 class="fw-bold text-dark">No Bin Locations Configured</h5>
                    <p class="small text-muted mb-3">Add or auto-generate your warehouse grid coordinates.</p>
                    <a href="/vortex_wms/modules/masters/bin_locations/bulk_add.php" class="btn btn-primary px-4 fw-bold rounded-pill">
                        + Create Locations Now
                    </a>
                </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- Modal: Bin Items Breakdown -->
<div class="modal fade" id="binDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-4 py-3">
                <h5 class="modal-title fw-bold" id="modalBinTitle"><i class="fa-solid fa-location-dot text-primary me-2"></i>Bin Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="text-muted fw-semibold">Zone Location:</span>
                    <span id="modalBinZoneType" class="badge bg-secondary-subtle text-dark border fs-6 px-3 py-1 rounded-pill">Zone A</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted fw-semibold">Stock Utilization:</span>
                    <span id="modalBinQty" class="badge bg-primary fs-6 px-3 py-1 rounded-pill">0 / 500 Units</span>
                </div>
                
                <h6 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-cubes-stacked me-1 text-primary"></i> Stored SKU Inventory:</h6>
                <div id="modalBinItems" class="rounded-3"></div>
            </div>
        </div>
    </div>
</div>

<script>
// Filter Bins by Zone and Search Code
function filterBins() {
    const selectedZone = document.getElementById('zoneFilter').value;
    const searchVal    = document.getElementById('binSearchInput').value.toUpperCase().trim();
    const cards        = document.querySelectorAll('.bin-item');

    cards.forEach(card => {
        const zoneMatch   = (selectedZone === 'ALL' || card.getAttribute('data-zone') === selectedZone);
        const searchMatch = (searchVal === '' || card.getAttribute('data-bincode').includes(searchVal));

        if (zoneMatch && searchMatch) {
            card.style.display = 'flex';
        } else {
            card.style.display = 'none';
        }
    });
}

// Show Bin Popup Modal
function showBinDetails(binCode, qty, maxCap, zoneName, rawItems) {
    document.getElementById('modalBinTitle').innerHTML = '<i class="fa-solid fa-location-dot text-primary me-2"></i>' + binCode;
    document.getElementById('modalBinZoneType').innerText = zoneName;
    document.getElementById('modalBinQty').innerText = qty + ' / ' + maxCap + ' Units';
    
    const itemsContainer = document.getElementById('modalBinItems');
    itemsContainer.innerHTML = '';

    if (!rawItems || rawItems.trim() === '') {
        itemsContainer.innerHTML = '<div class="p-3 bg-light text-muted text-center rounded-3 small">Bin is completely empty. Ready for putaway.</div>';
    } else {
        let html = '<div class="d-flex flex-column gap-2">';
        const rows = rawItems.split('###');
        rows.forEach(r => {
            const parts = r.split('|||');
            if (parts.length >= 3) {
                html += `
                    <div class="p-2 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                        <div>
                            <strong class="font-monospace text-primary small">${parts[0]}</strong>
                            <div class="small text-dark fw-semibold">${parts[1]}</div>
                        </div>
                        <span class="badge bg-primary px-3 py-2 rounded-pill font-monospace">${parts[2]} Units</span>
                    </div>
                `;
            }
        });
        html += '</div>';
        itemsContainer.innerHTML = html;
    }
    
    const modal = new bootstrap.Modal(document.getElementById('binDetailModal'));
    modal.show();
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>