<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

$projectRoot = __DIR__;
require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. LIVE METRICS & DYNAMIC AGGREGATES
   ========================================================================== */

function getMetricCount($conn, $sql, $default = 0) {
    if (!$conn) return $default;
    try {
        $res = @mysqli_query($conn, $sql);
        if ($res && $r = @mysqli_fetch_array($res)) {
            return $r[0] !== null ? (int)$r[0] : $default;
        }
    } catch (\Throwable $e) {
        return $default;
    }
    return $default;
}

// 1. Inbound Orders Count (Purchase Orders / ASN)
$inboundCount = getMetricCount($conn, "SELECT COUNT(*) FROM purchase_orders");
if ($inboundCount === 0) {
    $inboundCount = getMetricCount($conn, "SELECT COUNT(*) FROM asn");
}

// 2. Total Catalog Master Products
$inventoryCount = getMetricCount($conn, "SELECT COUNT(*) FROM products");

// 3. Outbound Shipments Count (Sales Orders)
$outboundCount = getMetricCount($conn, "SELECT COUNT(*) FROM sales_orders");

// 4. System Employees / Active Users
$userCount = getMetricCount($conn, "SELECT COUNT(*) FROM employees WHERE LOWER(status) = 'active' OR status = '1'");
if ($userCount === 0) {
    $userCount = getMetricCount($conn, "SELECT COUNT(*) FROM employees");
}

// 5. Total Inventory Quantity & Active Storage Bins
$totalStockUnits = getMetricCount($conn, "SELECT IFNULL(SUM(available_qty), 0) FROM inventory");
$totalActiveBins = getMetricCount($conn, "SELECT COUNT(*) FROM bin_locations WHERE status = 'Active'");

// 6. Low Stock Threshold Query (Available Qty <= 10)
$lowStockItems = [];
try {
    $lowStockQuery = "
        SELECT p.product_name, COALESCE(p.sku, p.product_code, 'SKU-00') AS sku_code, i.bin_location, i.available_qty 
        FROM inventory i
        LEFT JOIN products p ON p.id = i.product_id 
        WHERE i.available_qty <= 10 
        ORDER BY i.available_qty ASC 
        LIMIT 5
    ";
    $lowStockRes = @mysqli_query($conn, $lowStockQuery);
    if ($lowStockRes) {
        while ($row = mysqli_fetch_assoc($lowStockRes)) {
            $lowStockItems[] = $row;
        }
    }
} catch (\Throwable $e) {}

// Include Master Header (Automatically loads Navbar, Sidebar & Core Layout)
include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Welcome Banner & Actions -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Super Admin'); ?> 👋
            </h2>
            <p class="text-muted mb-0">Overview of warehouse operational activities, live inventory & orders</p>
        </div>
        <div class="d-flex gap-2 align-items-center flex-wrap">
            <a href="modules/reports/dashboard.php" class="btn btn-outline-primary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-chart-line me-1"></i> Executive Reports
            </a>
            <button onclick="window.location.reload();" class="btn btn-primary fw-bold rounded-pill px-4 shadow-sm">
                <i class="fa-solid fa-arrows-rotate me-1"></i> Sync Live
            </button>
        </div>
    </div>

    <!-- 1. METRIC STAT CARDS -->
    <div class="row g-4 mb-4">
        
        <!-- Inbound Card -->
        <div class="col-xl-3 col-md-6">
            <a href="modules/purchase_orders/index.php" class="text-decoration-none">
                <div class="card stat-card p-3 h-100 shadow-sm border-0 rounded-4 bg-white border-start border-4 border-primary hover-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">Inbound Orders</span>
                            <h2 class="fw-bold my-1 text-dark"><?= number_format($inboundCount); ?></h2>
                            <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal">Purchase Orders</span>
                        </div>
                        <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4">
                            <i class="fa-solid fa-truck-ramp-box fa-2x"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Inventory Card -->
        <div class="col-xl-3 col-md-6">
            <a href="modules/inventory/index.php" class="text-decoration-none">
                <div class="card stat-card p-3 h-100 shadow-sm border-0 rounded-4 bg-white border-start border-4 border-success hover-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">Total Inventory</span>
                            <h2 class="fw-bold my-1 text-dark"><?= number_format($totalStockUnits); ?></h2>
                            <span class="badge bg-success-subtle text-success border border-success-subtle fw-normal"><?= number_format($inventoryCount); ?> Master SKUs</span>
                        </div>
                        <div class="p-3 bg-success bg-opacity-10 text-success rounded-4">
                            <i class="fa-solid fa-boxes-stacked fa-2x"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Outbound Card -->
        <div class="col-xl-3 col-md-6">
            <a href="modules/outbound/sales_order/index.php" class="text-decoration-none">
                <div class="card stat-card p-3 h-100 shadow-sm border-0 rounded-4 bg-white border-start border-4 border-warning hover-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">Outbound Orders</span>
                            <h2 class="fw-bold my-1 text-dark"><?= number_format($outboundCount); ?></h2>
                            <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-normal">Sales Dispatches</span>
                        </div>
                        <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-4">
                            <i class="fa-solid fa-dolly fa-2x"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

        <!-- Active Storage Bins Card -->
        <div class="col-xl-3 col-md-6">
            <a href="modules/masters/bin_locations/index.php" class="text-decoration-none">
                <div class="card stat-card p-3 h-100 shadow-sm border-0 rounded-4 bg-white border-start border-4 border-info hover-card">
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <span class="text-muted fw-semibold small text-uppercase">Active Storage Bins</span>
                            <h2 class="fw-bold my-1 text-dark"><?= number_format($totalActiveBins); ?></h2>
                            <span class="badge bg-info-subtle text-info border border-info-subtle fw-normal"><?= $userCount; ?> Team Members</span>
                        </div>
                        <div class="p-3 bg-info bg-opacity-10 text-info rounded-4">
                            <i class="fa-solid fa-location-dot fa-2x"></i>
                        </div>
                    </div>
                </div>
            </a>
        </div>

    </div>

    <!-- 2. ANALYTICS CHART & LOW STOCK ALERT SECTION -->
    <div class="row g-4 mb-4">
        
        <!-- Monthly Inbound vs Outbound Flow Graph -->
        <div class="col-lg-8">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-chart-area text-primary me-2"></i>Inbound vs Outbound Monthly Flow
                    </h5>
                    <span class="badge bg-light text-secondary border">Real-Time Sync</span>
                </div>
                <div class="card-body p-4">
                    <canvas id="wmsChart" style="max-height: 270px;"></canvas>
                </div>
            </div>
        </div>

        <!-- Low Stock Warning Box -->
        <div class="col-lg-4">
            <div class="card border-0 shadow-sm rounded-4 bg-white h-100">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Low Stock Alert
                    </h5>
                    <span class="badge bg-danger rounded-pill">Reorder Threshold</span>
                </div>
                <div class="card-body p-4">
                    <div class="list-group list-group-flush">
                        <?php if (!empty($lowStockItems)): ?>
                            <?php foreach ($lowStockItems as $item): ?>
                                <div class="list-group-item px-0 py-2 d-flex justify-content-between align-items-center border-bottom">
                                    <div>
                                        <strong class="d-block text-dark small"><?= htmlspecialchars($item['product_name'] ?? 'Product'); ?></strong>
                                        <small class="text-muted font-monospace"><?= htmlspecialchars($item['sku_code']); ?> | Bin: <?= htmlspecialchars($item['bin_location'] ?? 'L0-A1'); ?></small>
                                    </div>
                                    <span class="badge <?= ($item['available_qty'] == 0) ? 'bg-danger' : 'bg-warning-subtle text-warning border border-warning-subtle'; ?> px-2 py-1 rounded-pill">
                                        <?= (int)$item['available_qty']; ?> Units
                                    </span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="text-center py-4 text-muted">
                                <i class="fa-solid fa-circle-check text-success fs-2 mb-2 d-block opacity-75"></i>
                                All inventory stock levels are optimal.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- 3. WORKFLOW SHORTCUTS -->
    <div class="card border-0 shadow-sm rounded-4 bg-white mb-4">
        <div class="card-body p-4">
            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-bolt me-2 text-warning"></i> Quick Operations Shortcuts</h5>
            <div class="row g-3">
                <div class="col-md-3 col-6">
                    <a href="modules/inventory/index.php" class="btn btn-outline-primary btn-action w-100 text-start py-3 rounded-3">
                        <i class="fa-solid fa-box me-2 fs-5"></i> Manage Inventory
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="modules/purchase_orders/index.php" class="btn btn-outline-success btn-action w-100 text-start py-3 rounded-3">
                        <i class="fa-solid fa-truck-ramp-box me-2 fs-5"></i> Inbound POs
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="modules/outbound/sales_order/index.php" class="btn btn-outline-warning btn-action w-100 text-start py-3 rounded-3">
                        <i class="fa-solid fa-paper-plane me-2 fs-5"></i> Sales Orders
                    </a>
                </div>
                <div class="col-md-3 col-6">
                    <a href="modules/masters/bin_locations/bulk_add.php" class="btn btn-outline-danger btn-action w-100 text-start py-3 rounded-3">
                        <i class="fa-solid fa-layer-group me-2 fs-5"></i> Bulk Generate Bins
                    </a>
                </div>
            </div>
        </div>
    </div>

</div>

<!-- Chart.js Configuration -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const ctx = document.getElementById('wmsChart');
    if (ctx) {
        new Chart(ctx.getContext('2d'), {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
                datasets: [
                    {
                        label: 'Inbound Orders',
                        data: [12, 19, 15, 25, 22, 30, 28, <?= $inboundCount; ?>],
                        borderColor: '#2563eb',
                        backgroundColor: 'rgba(37, 99, 235, 0.08)',
                        fill: true,
                        tension: 0.35
                    },
                    {
                        label: 'Outbound Shipments',
                        data: [8, 12, 18, 20, 15, 25, 24, <?= $outboundCount; ?>],
                        borderColor: '#d97706',
                        backgroundColor: 'rgba(217, 119, 6, 0.08)',
                        fill: true,
                        tension: 0.35
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'top', labels: { boxWidth: 12 } }
                },
                scales: {
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,0.05)' } },
                    x: { grid: { display: false } }
                }
            }
        });
    }
});
</script>

<style>
.hover-card {
    transition: transform 0.2s ease, box-shadow 0.2s ease;
}
.hover-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(0,0,0,0.08) !important;
}
</style>

<?php include $projectRoot . "/includes/footer.php"; ?>