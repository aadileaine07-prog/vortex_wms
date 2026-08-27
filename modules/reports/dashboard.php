<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Error reporting safety
error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);
ini_set('display_errors', 0);

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. FAIL-SAFE AGGREGATE FETCHER
   ========================================================================== */

function safeScalar($conn, $sql, $default = 0) {
    if (!$conn) return $default;
    try {
        $res = @mysqli_query($conn, $sql);
        if ($res && $r = @mysqli_fetch_array($res)) {
            return $r[0] !== null ? $r[0] : $default;
        }
    } catch (\Throwable $e) {
        return $default;
    }
    return $default;
}

// Table name resolution
$whTable = "warehouse";
$chkWh = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouse'");
if (!$chkWh || mysqli_num_rows($chkWh) == 0) {
    $whTable = "warehouses";
}

$totalProducts   = (int)safeScalar($conn, "SELECT COUNT(*) FROM products", 0);
$totalWarehouses = (int)safeScalar($conn, "SELECT COUNT(*) FROM `{$whTable}` WHERE LOWER(status) = 'active' OR status = '1'", 1);
$totalActiveBins = (int)safeScalar($conn, "SELECT COUNT(*) FROM bin_locations WHERE status = 'Active'", 0);
$totalInventory  = (int)safeScalar($conn, "SELECT IFNULL(SUM(available_qty), 0) FROM inventory", 0);

// Inventory Health
$lowStockCount   = (int)safeScalar($conn, "SELECT COUNT(*) FROM inventory WHERE available_qty > 0 AND available_qty <= 10", 0);
$outOfStockCount = (int)safeScalar($conn, "SELECT COUNT(*) FROM inventory WHERE available_qty = 0", 0);

// Orders & Fulfilment
$totalPOs        = (int)safeScalar($conn, "SELECT COUNT(*) FROM purchase_orders", 0);
$totalOrders     = (int)safeScalar($conn, "SELECT COUNT(*) FROM sales_orders", 0);
$completedOrders = (int)safeScalar($conn, "SELECT COUNT(*) FROM sales_orders WHERE LOWER(status) IN ('shipped', 'delivered', 'completed')", 0);
$fulfillmentRate = ($totalOrders > 0) ? round(($completedOrders / $totalOrders) * 100, 1) : 100.0;

// Warehouse Capacity %
$usedCapPct = ($totalActiveBins > 0) ? min(100, round(($totalInventory / ($totalActiveBins * 100)) * 100, 1)) : 0.0;

/* ==========================================================================
   2. RECENT RECORDS & CHART DATA
   ========================================================================== */

$whLabels = [];
$whStock  = [];

try {
    $whDistQuery = @mysqli_query($conn, "
        SELECT 
            COALESCE(w.warehouse_name, w.name, 'Main Warehouse') AS wh_name,
            IFNULL(SUM(i.available_qty), 0) AS total_stock
        FROM inventory i
        LEFT JOIN `{$whTable}` w ON w.id = i.warehouse_id
        GROUP BY i.warehouse_id, w.warehouse_name, w.name
        LIMIT 6
    ");

    if ($whDistQuery && mysqli_num_rows($whDistQuery) > 0) {
        while ($r = mysqli_fetch_assoc($whDistQuery)) {
            $whLabels[] = $r['wh_name'] ?? 'Facility';
            $whStock[]  = (int)$r['total_stock'];
        }
    }
} catch (\Throwable $e) {}

if (empty($whLabels)) {
    $whLabels = ['Main Facility'];
    $whStock  = [$totalInventory];
}

// Recent Orders
$recentOrders = [];
try {
    $soRes = @mysqli_query($conn, "SELECT * FROM sales_orders ORDER BY id DESC LIMIT 5");
    if ($soRes) {
        while ($r = mysqli_fetch_assoc($soRes)) { $recentOrders[] = $r; }
    }
} catch (\Throwable $e) {}

// Low Stock Items
$lowStockItems = [];
try {
    $lsRes = @mysqli_query($conn, "
        SELECT i.*, p.product_name, p.sku 
        FROM inventory i 
        LEFT JOIN products p ON p.id = i.product_id 
        WHERE i.available_qty <= 10 
        ORDER BY i.available_qty ASC 
        LIMIT 5
    ");
    if ($lsRes) {
        while ($r = mysqli_fetch_assoc($lsRes)) { $lowStockItems[] = $r; }
    }
} catch (\Throwable $e) {}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Executive Header & Export Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-chart-line text-primary me-2"></i>Executive WMS Analytics
            </h2>
            <p class="text-muted mb-0">Live enterprise inventory metrics, operational throughput, and fulfillment audits</p>
        </div>
        
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <div class="form-check form-switch bg-white px-3 py-2 rounded-pill border shadow-sm d-flex align-items-center me-1">
                <input class="form-check-input me-2 ms-0 cursor-pointer" type="checkbox" id="autoReloadSwitch">
                <label class="form-check-label text-dark fw-bold small cursor-pointer" for="autoReloadSwitch">Auto Reload</label>
            </div>

            <button onclick="exportReportToCSV()" class="btn btn-outline-success fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-file-excel me-1"></i> Export CSV
            </button>
            <button onclick="window.print()" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-print me-1"></i> Print / PDF
            </button>
        </div>
    </div>

    <!-- 1. KPI STATS TILES -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-primary">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Total Inventory Units</div>
                        <div class="fs-3 fw-bold text-dark my-1"><?= number_format($totalInventory); ?></div>
                        <small class="text-primary fw-semibold"><i class="fa-solid fa-cube me-1"></i><?= $totalProducts; ?> catalog master items</small>
                    </div>
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4">
                        <i class="fa-solid fa-boxes-stacked fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-success">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Order Fulfillment</div>
                        <div class="fs-3 fw-bold text-dark my-1"><?= $fulfillmentRate; ?>%</div>
                        <small class="text-muted"><span class="text-success fw-bold"><?= $completedOrders; ?></span> of <?= $totalOrders; ?> completed</small>
                    </div>
                    <div class="p-3 bg-success bg-opacity-10 text-success rounded-4">
                        <i class="fa-solid fa-circle-check fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-warning">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Low & Out of Stock</div>
                        <div class="fs-3 fw-bold text-warning my-1"><?= ($lowStockCount + $outOfStockCount); ?></div>
                        <small class="text-danger fw-semibold"><i class="fa-solid fa-triangle-exclamation me-1"></i><?= $outOfStockCount; ?> depleted items</small>
                    </div>
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-4">
                        <i class="fa-solid fa-bell fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-3 col-md-6">
            <div class="card shadow-sm border-0 rounded-4 bg-white p-3 h-100 border-start border-4 border-info">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <div class="text-muted small fw-semibold text-uppercase">Active Facilities</div>
                        <div class="fs-3 fw-bold text-dark my-1"><?= $totalWarehouses; ?></div>
                        <small class="text-info fw-semibold"><i class="fa-solid fa-location-dot me-1"></i><?= $totalActiveBins; ?> active storage bins</small>
                    </div>
                    <div class="p-3 bg-info bg-opacity-10 text-info rounded-4">
                        <i class="fa-solid fa-warehouse fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 2. WAREHOUSE CAPACITY UTILIZATION GAUGE -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <div>
                    <h6 class="fw-bold text-dark mb-0"><i class="fa-solid fa-gauge-high text-primary me-2"></i>Global Storage Capacity Occupancy</h6>
                    <small class="text-muted">Total inventory load mapped against nominal bin units</small>
                </div>
                <div class="badge bg-primary fs-6 px-3 py-2 rounded-pill"><?= $usedCapPct; ?>% Occupied</div>
            </div>
            <div class="progress rounded-pill bg-light" style="height: 14px;">
                <div class="progress-bar progress-bar-striped progress-bar-animated <?= ($usedCapPct > 85) ? 'bg-danger' : (($usedCapPct > 65) ? 'bg-warning' : 'bg-success'); ?>" 
                     role="progressbar" style="width: <?= $usedCapPct; ?>%;"></div>
            </div>
        </div>
    </div>

    <!-- 3. VISUAL ANALYTICS CHARTS -->
    <div class="row g-4 mb-4">
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-chart-column text-primary me-2"></i>Stock Volume by Warehouse</h5>
                    <span class="badge bg-light text-secondary border">Real-time DB</span>
                </div>
                <div class="card-body p-4">
                    <canvas id="whStockChart" style="max-height: 280px;"></canvas>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-chart-pie text-info me-2"></i>Inventory Health</h5>
                </div>
                <div class="card-body p-4 d-flex align-items-center justify-content-center">
                    <div style="width: 100%; max-width: 250px;">
                        <canvas id="healthChart"></canvas>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 4. OPERATIONS TABLES -->
    <div class="row g-4 mb-4">
        <!-- Recent Orders -->
        <div class="col-lg-7">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-dolly text-primary me-2"></i>Recent Sales Orders</h5>
                    <a href="/vortex_wms/modules/outbound/sales_order/index.php" class="btn btn-sm btn-light rounded-pill px-3 fw-bold">View All</a>
                </div>
                <div class="card-body p-4 pt-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0" id="salesOrdersReportTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($recentOrders)): ?>
                                    <?php foreach ($recentOrders as $so): ?>
                                        <tr>
                                            <td><code class="fw-bold text-primary font-monospace"><?= htmlspecialchars($so['order_number'] ?? ('SO-' . $so['id'])); ?></code></td>
                                            <td class="fw-semibold text-dark"><?= htmlspecialchars($so['customer_name'] ?? 'Client'); ?></td>
                                            <td>
                                                <?php 
                                                $st = strtolower($so['status'] ?? 'pending');
                                                if ($st === 'delivered' || $st === 'completed' || $st === 'shipped') echo '<span class="badge bg-success-subtle text-success px-2 py-1 rounded-pill">Completed</span>';
                                                else echo '<span class="badge bg-warning-subtle text-warning px-2 py-1 rounded-pill">Processing</span>';
                                                ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">No outbound orders recorded.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Low Stock Action List -->
        <div class="col-lg-5">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-2 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Critical Replenishment</h5>
                    <span class="badge bg-danger rounded-pill"><?= $lowStockCount; ?> items</span>
                </div>
                <div class="card-body p-4 pt-0">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Item / SKU</th>
                                    <th>Bin</th>
                                    <th class="text-end">Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($lowStockItems)): ?>
                                    <?php foreach ($lowStockItems as $ls): ?>
                                        <tr>
                                            <td>
                                                <strong class="d-block text-dark"><?= htmlspecialchars($ls['product_name'] ?? 'Item'); ?></strong>
                                                <small class="text-muted font-monospace"><?= htmlspecialchars($ls['sku'] ?? '-'); ?></small>
                                            </td>
                                            <td><span class="badge bg-light text-primary border font-monospace"><?= htmlspecialchars($ls['bin_location'] ?? 'L0-A1'); ?></span></td>
                                            <td class="text-end">
                                                <span class="badge <?= ($ls['available_qty'] == 0) ? 'bg-danger' : 'bg-warning text-dark'; ?> px-2 py-1 rounded-pill">
                                                    <?= $ls['available_qty']; ?> Units
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="3" class="text-center py-4 text-muted">All stock levels are optimal.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- ChartJS Execution -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// 1. Live Reload Engine
const autoSwitch = document.getElementById('autoReloadSwitch');
let autoReloadTimer;

if (autoSwitch) {
    autoSwitch.addEventListener('change', function() {
        if (this.checked) {
            autoReloadTimer = setInterval(() => { window.location.reload(); }, 25000);
        } else {
            clearInterval(autoReloadTimer);
        }
    });
}

// 2. CSV Data Exporter
function exportReportToCSV() {
    let csv = ["Order Number,Customer Name,Status"];
    const rows = document.querySelectorAll("#salesOrdersReportTable tbody tr");
    
    rows.forEach(r => {
        const cols = r.querySelectorAll("td");
        if (cols.length >= 3) {
            const rowData = [
                `"${cols[0].innerText.trim()}"`,
                `"${cols[1].innerText.trim()}"`,
                `"${cols[2].innerText.trim()}"`
            ];
            csv.push(rowData.join(","));
        }
    });

    const blob = new Blob([csv.join("\n")], { type: "text/csv;charset=utf-8;" });
    const link = document.createElement("a");
    link.href = URL.createObjectURL(blob);
    link.download = `WMS_Dashboard_Report_${new Date().toISOString().slice(0,10)}.csv`;
    link.click();
}

// 3. Stock Volume Bar Chart
new Chart(document.getElementById('whStockChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($whLabels); ?>,
        datasets: [{
            label: 'Stock Units',
            data: <?= json_encode($whStock); ?>,
            backgroundColor: '#2563eb',
            borderRadius: 8,
            barThickness: 32
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
            x: { grid: { display: false } }
        }
    }
});

// 4. Inventory Health Doughnut
new Chart(document.getElementById('healthChart'), {
    type: 'doughnut',
    data: {
        labels: ['Optimal Stock', 'Low Stock', 'Out of Stock'],
        datasets: [{
            data: [
                <?= max(0, $totalInventory - $lowStockCount - $outOfStockCount); ?>, 
                <?= $lowStockCount; ?>, 
                <?= $outOfStockCount; ?>
            ],
            backgroundColor: ['#10b981', '#f59e0b', '#ef4444'],
            borderWidth: 0
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { boxWidth: 12, font: { size: 11 } } }
        },
        cutout: '72%'
    }
});
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>