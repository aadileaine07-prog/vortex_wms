<?php
session_start();

$projectRoot = dirname(__DIR__, 2);

if (!isset($_SESSION['employee_id'])) { 
    header("Location: /vortex_wms/login.php"); 
    exit(); 
}

require_once $projectRoot . "/config/database.php";

$order_no = trim($_GET['order_no'] ?? '');
$picks = [];

if (!empty($order_no)) {
    $order_no_safe = mysqli_real_escape_string($conn, $order_no);
    $picks = mysqli_query($conn, "SELECT p.*, COALESCE(i.location, 'Aisle-1') as bin_location 
        FROM sales_orders p 
        LEFT JOIN inventory i ON (p.sku = i.sku) 
        WHERE p.order_number = '$order_no_safe' 
        ORDER BY i.location ASC");
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content"><div class="container-fluid p-4">
    <h2 class="fw-bold mb-4"><i class="fa-solid fa-route text-primary me-2"></i>Optimized Pick Path Router</h2>

    <form method="GET" class="row g-2 mb-4 col-md-6">
        <div class="input-group">
            <input type="text" name="order_no" class="form-control" placeholder="Enter Order No (e.g. ORD-1001)" value="<?= htmlspecialchars($order_no); ?>" required>
            <button class="btn btn-primary"><i class="fa-solid fa-compass"></i> Generate Route</button>
        </div>
    </form>

    <?php if(!empty($picks) && mysqli_num_rows($picks) > 0): ?>
        <div class="card shadow-sm border-0 rounded-3 col-md-8">
            <div class="card-body p-4">
                <h5 class="fw-bold text-success mb-3"><i class="fa-solid fa-shoe-prints me-2"></i>Shortest Walking Route Sequence:</h5>
                <ol class="list-group list-group-numbered">
                    <?php while($row = mysqli_fetch_assoc($picks)): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <div>
                                <strong class="text-primary"><?= htmlspecialchars($row['sku'] ?? 'N/A'); ?></strong> - Qty: <?= $row['quantity'] ?? 1; ?>
                            </div>
                            <span class="badge bg-dark fs-6"><i class="fa-solid fa-location-dot me-1"></i> Bin: <?= htmlspecialchars($row['bin_location']); ?></span>
                        </li>
                    <?php endwhile; ?>
                </ol>
            </div>
        </div>
    <?php elseif(!empty($order_no)): ?>
        <div class="alert alert-warning col-md-8">No order items found for Order No: <strong><?= htmlspecialchars($order_no); ?></strong></div>
    <?php endif; ?>
</div></div>

<?php include $projectRoot . "/includes/footer.php"; ?>