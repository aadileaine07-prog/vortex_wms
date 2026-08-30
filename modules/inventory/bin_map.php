<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) { 
    header("Location: /vortex_wms/login.php"); 
    exit(); 
}

require_once $projectRoot . "/config/database.php";

// 1. Fetch live stock from inventory
$inventoryMap = [];
$invRes = mysqli_query($conn, "
    SELECT 
        bin_location,
        warehouse,
        product_code,
        product_name,
        available_qty
    FROM inventory 
    WHERE available_qty > 0
");

if ($invRes) {
    while ($r = mysqli_fetch_assoc($invRes)) {
        $bin = strtoupper(trim($r['bin_location']));
        if (!isset($inventoryMap[$bin])) {
            $inventoryMap[$bin] = [
                'total_qty' => 0,
                'items' => []
            ];
        }
        $inventoryMap[$bin]['total_qty'] += (int)$r['available_qty'];
        $inventoryMap[$bin]['items'][] = $r;
    }
}

// 2. Fetch ONLY REAL BINS from `bin_locations` table (Zero Dummy Fallback)
$masterBins = [];
$binDbRes = @mysqli_query($conn, "
    SELECT 
        bin_code, 
        COALESCE(zone_name, zone, 'General Zone') AS zone_name, 
        COALESCE(capacity, max_capacity, 150) AS max_capacity 
    FROM bin_locations 
    WHERE status = 'Active' OR status IS NULL OR status = '1'
    ORDER BY bin_code ASC
");

if ($binDbRes && mysqli_num_rows($binDbRes) > 0) {
    while ($b = mysqli_fetch_assoc($binDbRes)) {
        $masterBins[] = [
            'bin_code'     => strtoupper(trim($b['bin_code'])),
            'zone_name'    => $b['zone_name'],
            'max_capacity' => (int)$b['max_capacity']
        ];
    }
}

// Summary stats calculation
$total_bins = count($masterBins);
$empty_bins = 0;
$partial_bins = 0;
$full_bins = 0;

foreach ($masterBins as &$mb) {
    $code = $mb['bin_code'];
    $currentQty = $inventoryMap[$code]['total_qty'] ?? 0;
    $maxCap = $mb['max_capacity'];
    
    $mb['current_qty'] = $currentQty;
    $mb['items'] = $inventoryMap[$code]['items'] ?? [];

    if ($currentQty === 0) {
        $empty_bins++;
        $mb['status_class'] = 'bin-empty';
        $mb['status_label'] = 'Empty';
    } elseif ($currentQty >= $maxCap) {
        $full_bins++;
        $mb['status_class'] = 'bin-full';
        $mb['status_label'] = 'Full';
    } else {
        $partial_bins++;
        $mb['status_class'] = 'bin-partial';
        $mb['status_label'] = round(($currentQty / $maxCap) * 100) . '% Fill';
    }
}
unset($mb);

include $projectRoot . "/includes/header.php";
?>

<style>
.bin-grid { 
    display: grid; 
    grid-template-columns: repeat(auto-fill, minmax(185px, 1fr)); 
    gap: 16px; 
}
.bin-card { 
    border-radius: 16px; 
    padding: 16px; 
    color: #fff; 
    cursor: pointer;
    box-shadow: 0 4px 14px rgba(0,0,0,0.06);
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    transition: all 0.2s ease-in-out;
}
.bin-card:hover { transform: translateY(-4px) scale(1.02); }
.bin-empty   { background: linear-gradient(135deg, #10b981 0%, #047857 100%); }
.bin-partial { background: linear-gradient(135deg, #f59e0b 0%, #b45309 100%); }
.bin-full    { background: linear-gradient(135deg, #ef4444 0%, #b91c1c 100%); }
.progress-track { background: rgba(0, 0, 0, 0.25); border-radius: 20px; height: 6px; overflow: hidden; margin-top: 8px; }
.progress-fill { background: #fff; height: 100%; border-radius: 20px; }
</style>

<div class="container-fluid p-0">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-border-all text-primary me-2"></i>Visual Bin Map</h2>
            <p class="text-muted mb-0">Live real-time occupancy grid layout synced with active stock ledger</p>
        </div>
        
        <div class="d-flex align-items-center gap-2 flex-wrap">
            <input type="text" id="binSearchInput" class="form-control rounded-pill" placeholder="Search Bin..." style="width: 200px;" onkeyup="filterBins()">
            <select id="zoneFilter" class="form-select border-2 rounded-pill fw-semibold" style="width: 160px;" onchange="filterBins()">
                <option value="ALL">All Zones</option>
                <?php
                $zones = array_unique(array_column($masterBins, 'zone_name'));
                foreach ($zones as $z) {
                    echo "<option value='".htmlspecialchars($z)."'>".htmlspecialchars($z)."</option>";
                }
                ?>
            </select>
            <a href="/vortex_wms/modules/masters/bin_locations/bulk_add.php" class="btn btn-primary fw-bold shadow-sm rounded-pill px-3">
                <i class="fa-solid fa-plus me-1"></i> Add Bins
            </a>
        </div>
    </div>

    <!-- Occupancy Summary -->
    <div class="row g-3 mb-4">
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white">
                <small class="text-muted fw-bold">TOTAL LOCATIONS</small>
                <h3 class="fw-bold text-dark mb-0 mt-1"><?= $total_bins; ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
                <small class="text-success fw-bold">🟢 EMPTY (VACANT)</small>
                <h3 class="fw-bold text-success mb-0 mt-1"><?= $empty_bins; ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning">
                <small class="text-warning fw-bold">🟡 PARTIAL OCCUPIED</small>
                <h3 class="fw-bold text-warning mb-0 mt-1"><?= $partial_bins; ?></h3>
            </div>
        </div>
        <div class="col-md-3 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger">
                <small class="text-danger fw-bold">🔴 FULL CAPACITY</small>
                <h3 class="fw-bold text-danger mb-0 mt-1"><?= $full_bins; ?></h3>
            </div>
        </div>
    </div>

    <!-- Visual Grid Layout -->
    <?php if (!empty($masterBins)): ?>
        <div class="bin-grid">
            <?php foreach ($masterBins as $bin): 
                $pct = min(100, round(($bin['current_qty'] / $bin['max_capacity']) * 100));
                $jsonItems = htmlspecialchars(json_encode($bin['items']), ENT_QUOTES, 'UTF-8');
            ?>
                <div class="bin-card <?= $bin['status_class']; ?> bin-item" 
                     data-zone="<?= htmlspecialchars($bin['zone_name']); ?>" 
                     data-bincode="<?= htmlspecialchars($bin['bin_code']); ?>"
                     onclick="showBinDetails('<?= htmlspecialchars($bin['bin_code']); ?>', <?= $bin['current_qty']; ?>, <?= $bin['max_capacity']; ?>, '<?= htmlspecialchars($bin['zone_name']); ?>', <?= $jsonItems; ?>)">
                    
                    <div>
                        <div class="d-flex justify-content-between align-items-center mb-1">
                            <span class="small opacity-75 font-monospace text-uppercase" style="font-size:10px;"><?= htmlspecialchars($bin['zone_name']); ?></span>
                            <span class="badge bg-black bg-opacity-25 rounded-pill px-2 py-0" style="font-size: 10px;"><?= $bin['status_label']; ?></span>
                        </div>
                        <div class="fs-6 fw-bold font-monospace text-truncate"><?= htmlspecialchars($bin['bin_code']); ?></div>
                    </div>

                    <div>
                        <div class="d-flex justify-content-between align-items-center mt-2 small opacity-90">
                            <span>Stored:</span>
                            <strong class="font-monospace"><?= $bin['current_qty']; ?> / <?= $bin['max_capacity']; ?></strong>
                        </div>
                        <div class="progress-track">
                            <div class="progress-fill" style="width: <?= $pct; ?>%;"></div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="card border-0 shadow-sm rounded-4 bg-white p-5 text-center">
            <i class="fa-solid fa-boxes-packing fa-3x text-secondary opacity-25 mb-3 d-block"></i>
            <h4 class="fw-bold text-dark">No Bin Locations Configured</h4>
            <p class="text-muted small mb-4">Database me koi bin locations exist nahi karti hain. Naye rack coordinates create karein.</p>
            <div>
                <a href="/vortex_wms/modules/masters/bin_locations/bulk_add.php" class="btn btn-primary rounded-pill px-4 fw-bold">
                    <i class="fa-solid fa-plus me-1"></i> Add Bins Now
                </a>
            </div>
        </div>
    <?php endif; ?>

</div>

<!-- Modal: Live Bin Items Breakdown -->
<div class="modal fade" id="binDetailModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow-lg">
            <div class="modal-header bg-dark text-white rounded-top-4 py-3">
                <h5 class="modal-title fw-bold" id="modalBinTitle"><i class="fa-solid fa-location-dot text-primary me-2"></i>Bin Details</h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body p-4">
                <div class="d-flex justify-content-between align-items-center mb-2 pb-2 border-bottom">
                    <span class="text-muted fw-semibold">Zone:</span>
                    <span id="modalBinZone" class="badge bg-secondary-subtle text-dark border fs-6 px-3 py-1 rounded-pill">Zone A</span>
                </div>
                <div class="d-flex justify-content-between align-items-center mb-3 pb-2 border-bottom">
                    <span class="text-muted fw-semibold">Capacity Utilized:</span>
                    <span id="modalBinQty" class="badge bg-primary fs-6 px-3 py-1 rounded-pill">0 / 150 Units</span>
                </div>
                
                <h6 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-boxes-stacked me-1 text-primary"></i> Stored Stock Items:</h6>
                <div id="modalBinItemsList"></div>
            </div>
        </div>
    </div>
</div>

<script>
function filterBins() {
    const zone = document.getElementById('zoneFilter').value;
    const search = document.getElementById('binSearchInput').value.toUpperCase().trim();
    const cards = document.querySelectorAll('.bin-item');

    cards.forEach(card => {
        const matchZone = (zone === 'ALL' || card.getAttribute('data-zone') === zone);
        const matchSearch = (search === '' || card.getAttribute('data-bincode').includes(search));
        card.style.display = (matchZone && matchSearch) ? 'flex' : 'none';
    });
}

function showBinDetails(binCode, qty, maxCap, zone, items) {
    document.getElementById('modalBinTitle').innerHTML = '<i class="fa-solid fa-location-dot text-primary me-2"></i>' + binCode;
    document.getElementById('modalBinZone').innerText = zone;
    document.getElementById('modalBinQty').innerText = qty + ' / ' + maxCap + ' Units';
    
    const container = document.getElementById('modalBinItemsList');
    container.innerHTML = '';

    if (!items || items.length === 0) {
        container.innerHTML = '<div class="p-3 bg-light text-muted text-center rounded-3 small">Bin is completely empty. Ready for inbound putaway.</div>';
    } else {
        let html = '<div class="d-flex flex-column gap-2">';
        items.forEach(it => {
            html += `
                <div class="p-2 border rounded-3 bg-light d-flex justify-content-between align-items-center">
                    <div>
                        <strong class="font-monospace text-primary small">${it.product_code}</strong>
                        <div class="small text-dark fw-semibold">${it.product_name}</div>
                    </div>
                    <span class="badge bg-primary px-3 py-2 rounded-pill font-monospace">${it.available_qty} Units</span>
                </div>
            `;
        });
        html += '</div>';
        container.innerHTML = html;
    }
    
    new bootstrap.Modal(document.getElementById('binDetailModal')).show();
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>