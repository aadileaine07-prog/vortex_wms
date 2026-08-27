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

// Fetch inventory data with product price
$productsQuery = @mysqli_query($conn, "
    SELECT 
        p.id, p.product_name, p.sku,
        IFNULL(p.unit_price, 25.00) as unit_price,
        IFNULL(SUM(i.available_qty), 0) as total_qty,
        (IFNULL(p.unit_price, 25.00) * IFNULL(SUM(i.available_qty), 0)) as total_value
    FROM products p
    LEFT JOIN inventory i ON i.product_id = p.id
    GROUP BY p.id
    ORDER BY total_value DESC
");

$items = [];
$grandTotal = 0;

if ($productsQuery) {
    while ($r = mysqli_fetch_assoc($productsQuery)) {
        $grandTotal += (float)$r['total_value'];
        $items[] = $r;
    }
}

// Assign ABC Categories based on cumulative value
$runningTotal = 0;
foreach ($items as &$it) {
    $runningTotal += (float)$it['total_value'];
    $cumPct = ($grandTotal > 0) ? ($runningTotal / $grandTotal) * 100 : 0;
    
    if ($cumPct <= 75) {
        $it['class'] = 'A';
        $it['badge'] = 'bg-success';
        $it['strategy'] = 'Fast-Moving / L0-Aisle Front';
    } elseif ($cumPct <= 95) {
        $it['class'] = 'B';
        $it['badge'] = 'bg-warning text-dark';
        $it['strategy'] = 'Standard Velocity / L1-L2 Shelves';
    } else {
        $it['class'] = 'C';
        $it['badge'] = 'bg-danger';
        $it['strategy'] = 'Slow Mover / Deep Reserve';
    }
}
unset($it);

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-chart-pie text-danger me-2"></i>ABC Inventory Velocity Classification</h2>
            <p class="text-muted mb-0">Pareto 80/20 Value Analysis: Optimize putaway placement based on stock turnover</p>
        </div>
        <button onclick="window.print()" class="btn btn-outline-danger rounded-pill px-3 shadow-sm"><i class="fa-solid fa-print me-1"></i> Print Matrix</button>
    </div>

    <!-- Strategy Cards -->
    <div class="row g-4 mb-4">
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 border-start border-4 border-success bg-white">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="fw-bold text-success mb-0">Class A (Top 70-80% Value)</h6>
                    <span class="badge bg-success rounded-pill">Priority 1</span>
                </div>
                <small class="text-muted">High turnover items. Position at ground level (L0) near packing & outbound docks.</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 border-start border-4 border-warning bg-white">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="fw-bold text-warning mb-0">Class B (15-20% Value)</h6>
                    <span class="badge bg-warning text-dark rounded-pill">Priority 2</span>
                </div>
                <small class="text-muted">Moderate volume. Position in middle levels (L1/L2) and secondary aisles.</small>
            </div>
        </div>
        <div class="col-md-4">
            <div class="card border-0 shadow-sm rounded-4 p-3 border-start border-4 border-danger bg-white">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <h6 class="fw-bold text-danger mb-0">Class C (Top 5% Value)</h6>
                    <span class="badge bg-danger rounded-pill">Priority 3</span>
                </div>
                <small class="text-muted">Slow-moving stock. Position on upper vertical shelves (L3/L4) or deep storage.</small>
            </div>
        </div>
    </div>

    <!-- ABC Products Table -->
    <div class="card shadow-sm border-0 rounded-4 bg-white">
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Class</th>
                            <th>SKU</th>
                            <th>Product Name</th>
                            <th>Unit Price</th>
                            <th>In-Stock Qty</th>
                            <th>Total Stock Value</th>
                            <th>Placement Strategy</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($items)): ?>
                            <?php foreach ($items as $row): ?>
                                <tr>
                                    <td><span class="badge <?= $row['badge']; ?> px-3 py-1 rounded-pill fw-bold fs-6">Class <?= $row['class']; ?></span></td>
                                    <td><code class="fw-bold text-dark font-monospace"><?= htmlspecialchars($row['sku']); ?></code></td>
                                    <td class="fw-semibold text-dark"><?= htmlspecialchars($row['product_name']); ?></td>
                                    <td>$<?= number_format((float)$row['unit_price'], 2); ?></td>
                                    <td class="fw-bold"><?= number_format((int)$row['total_qty']); ?></td>
                                    <td class="fw-bold text-success">$<?= number_format((float)$row['total_value'], 2); ?></td>
                                    <td><small class="badge bg-light text-secondary border"><?= $row['strategy']; ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="7" class="text-center py-4 text-muted">No product items available for ABC calculation.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>