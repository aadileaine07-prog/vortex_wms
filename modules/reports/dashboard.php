<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

/* ===============================
   1. Dashboard Statistics Queries
================================ */

$totalProducts = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM products"))['total'] ?? 0;
$totalEmployees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM employees"))['total'] ?? 0;
$totalWarehouses = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM warehouse"))['total'] ?? 0;
$totalInventory = mysqli_fetch_assoc(mysqli_query($conn, "SELECT IFNULL(SUM(available_qty),0) total FROM inventory"))['total'] ?? 0;

$lowStock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM inventory WHERE status='Low Stock'"))['total'] ?? 0;
$outStock = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM inventory WHERE status='Out of Stock'"))['total'] ?? 0;

$totalASN = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM asn"))['total'] ?? 0;
$totalGRN = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM grn"))['total'] ?? 0;

/* ===============================
   2. Chart Data Processing
================================ */

// Warehouse Chart Data
$warehouseQuery = mysqli_query($conn, "SELECT warehouse, SUM(available_qty) AS qty FROM inventory GROUP BY warehouse");
$warehouseLabels = [];
$warehouseQty = [];

while ($row = mysqli_fetch_assoc($warehouseQuery)) {
    $warehouseLabels[] = $row['warehouse'];
    $warehouseQty[] = (int)$row['qty'];
}

// Monthly Trends Data
$inboundLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
$inboundData = [12, 19, 3, 5, 2, 3];

$outboundLabels = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun'];
$outboundData = [8, 11, 7, 12, 9, 14];

include "../../includes/header.php";
include "../../includes/navbar.php";
include "../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid">

        <!-- Top Header & Actions -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
            <div>
                <h2 class="fw-bold">📊 Dashboard Reports</h2>
                <p class="text-muted mb-0">Enterprise Reporting Center</p>
            </div>
            
            <div class="d-flex gap-2 align-items-center flex-wrap">
                <!-- Auto Refresh Switch -->
                <div class="form-check form-switch me-2 bg-white p-2 rounded border shadow-sm">
                    <input class="form-check-input ms-0 me-2" type="checkbox" id="autoRefreshSwitch">
                    <label class="form-check-label text-dark fw-bold small" for="autoRefreshSwitch">Live Reload</label>
                </div>

                <button onclick="exportTableToCSV('dashboard-summary.csv')" class="btn btn-success">📊 Export Excel</button>
                <button onclick="window.print();" class="btn btn-dark">🖨 Print Report</button>
            </div>
        </div>

        <!-- Stat Cards Row -->
        <div class="row">
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow border-0 bg-primary text-white">
                    <div class="card-body">
                        <h6>Total Products</h6>
                        <h2><?= $totalProducts ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow border-0 bg-success text-white">
                    <div class="card-body">
                        <h6>Employees</h6>
                        <h2><?= $totalEmployees ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow border-0 bg-info text-white">
                    <div class="card-body">
                        <h6>Warehouses</h6>
                        <h2><?= $totalWarehouses ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow border-0 bg-warning text-dark">
                    <div class="card-body">
                        <h6>Total Inventory</h6>
                        <h2><?= $totalInventory ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow border-0 bg-danger text-white">
                    <div class="card-body">
                        <h6>Low Stock</h6>
                        <h2><?= $lowStock ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow border-0 bg-secondary text-white">
                    <div class="card-body">
                        <h6>Out Of Stock</h6>
                        <h2><?= $outStock ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow border-0 bg-dark text-white">
                    <div class="card-body">
                        <h6>Total ASN</h6>
                        <h2><?= $totalASN ?></h2>
                    </div>
                </div>
            </div>

            <div class="col-lg-3 col-md-6 mb-4">
                <div class="card shadow border-0" style="background:#6f42c1;color:white;">
                    <div class="card-body">
                        <h6>Total GRN</h6>
                        <h2><?= $totalGRN ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <!-- Warehouse Space Utilization Progress Bar -->
        <div class="card shadow border-0 mb-4">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0">🏭 Warehouse Capacity Usage</h6>
                    <span class="fw-bold text-primary">68% Space Occupied</span>
                </div>
                <div class="progress" style="height: 12px;">
                    <div class="progress-bar bg-primary progress-bar-striped progress-bar-animated" role="progressbar" style="width: 68%;"></div>
                </div>
            </div>
        </div>

        <!-- Inventory Charts Row -->
        <div class="row">
            <div class="col-lg-8 mb-4">
                <div class="card shadow border-0">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">📦 Inventory by Warehouse</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="warehouseChart" height="120"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-4 mb-4">
                <div class="card shadow border-0">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">📊 Stock Status</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="stockChart"></canvas>
                    </div>
                </div>
            </div>
        </div>

        <!-- Fast Moving Products & Low Stock Alert -->
        <div class="row">
            <!-- Recent Sales Table -->
            <div class="col-lg-8 mb-4">
                <div class="card shadow border-0">
                    <div class="card-header bg-dark text-white">
                        <h5 class="mb-0">🕒 Recent Sales Orders</h5>
                    </div>
                    <div class="card-body">
                        <table class="table table-hover table-bordered" id="salesTable">
                            <thead>
                                <tr>
                                    <th>Order No</th>
                                    <th>Customer</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php
                                $sales = mysqli_query($conn, "
                                    SELECT order_number, customer_name, status
                                    FROM sales_orders
                                    ORDER BY id DESC
                                    LIMIT 5
                                ");

                                if ($sales && mysqli_num_rows($sales) > 0) {
                                    while ($row = mysqli_fetch_assoc($sales)) {
                                ?>
                                        <tr>
                                            <td><?= htmlspecialchars($row['order_number']); ?></td>
                                            <td><?= htmlspecialchars($row['customer_name']); ?></td>
                                            <td><span class="badge bg-info"><?= htmlspecialchars($row['status']); ?></span></td>
                                        </tr>
                                <?php 
                                    } 
                                } else {
                                    echo "<tr><td colspan='3' class='text-center'>No recent sales orders found.</td></tr>";
                                }
                                ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Low Stock Alert Box -->
            <div class="col-lg-4 mb-4">
                <div class="card shadow border-0">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">⚠ Low Stock Alert</h5>
                    </div>
                    <div class="card-body">
                        <?php
                        $stock = mysqli_query($conn, "
                            SELECT product_name, available_qty
                            FROM inventory
                            WHERE available_qty <= 10
                            LIMIT 5
                        ");

                        if ($stock && mysqli_num_rows($stock) > 0) {
                            while ($row = mysqli_fetch_assoc($stock)) {
                        ?>
                                <div class="mb-3 border-bottom pb-2 d-flex justify-content-between align-items-center">
                                    <strong><?= htmlspecialchars($row['product_name']); ?></strong>
                                    <span class="badge bg-warning text-dark">
                                        Qty: <?= $row['available_qty']; ?>
                                    </span>
                                </div>
                        <?php 
                            } 
                        } else {
                            echo "<p class='text-muted text-center mb-0'>No low stock alerts.</p>";
                        }
                        ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Quick Actions Row -->
        <div class="row mt-2">
            <div class="col-lg-12">
                <div class="card shadow border-0">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">⚡ Quick Actions</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3 mb-3">
                                <a href="../inventory/index.php" class="btn btn-outline-primary w-100 py-3">
                                    📦<br>Inventory
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="../inbound/dashboard.php" class="btn btn-outline-success w-100 py-3">
                                    📥<br>Inbound
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="../outbound/sales_order/index.php" class="btn btn-outline-warning w-100 py-3">
                                    📤<br>Outbound
                                </a>
                            </div>
                            <div class="col-md-3 mb-3">
                                <a href="../hr/employees/index.php" class="btn btn-outline-danger w-100 py-3">
                                    👨‍💼<br>Employees
                                </a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Monthly Trends Row -->
        <div class="row mt-4 mb-4">
            <div class="col-lg-6 mb-3">
                <div class="card shadow border-0">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0">📈 Monthly Inbound</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="inboundChart" height="120"></canvas>
                    </div>
                </div>
            </div>

            <div class="col-lg-6 mb-3">
                <div class="card shadow border-0">
                    <div class="card-header bg-danger text-white">
                        <h5 class="mb-0">📉 Monthly Outbound</h5>
                    </div>
                    <div class="card-body">
                        <canvas id="outboundChart" height="120"></canvas>
                    </div>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include "../../includes/footer.php"; ?>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<script>
// 1. Live Reload Feature (Every 30 seconds)
const refreshSwitch = document.getElementById('autoRefreshSwitch');
let refreshInterval;

refreshSwitch.addEventListener('change', function() {
    if (this.checked) {
        refreshInterval = setInterval(() => {
            location.reload();
        }, 30000);
    } else {
        clearInterval(refreshInterval);
    }
});

// 2. Export Table to CSV Script
function exportTableToCSV(filename) {
    let csv = [];
    let rows = document.querySelectorAll("#salesTable tr");
    
    for (let i = 0; i < rows.length; i++) {
        let row = [], cols = rows[i].querySelectorAll("td, th");
        for (let j = 0; j < cols.length; j++) 
            row.push(cols[j].innerText);
        csv.push(row.join(","));        
    }

    let csvFile = new Blob([csv.join("\n")], {type: "text/csv"});
    let downloadLink = document.createElement("a");
    downloadLink.download = filename;
    downloadLink.href = window.URL.createObjectURL(csvFile);
    downloadLink.style.display = "none";
    document.body.appendChild(downloadLink);
    downloadLink.click();
}

// 3. Chart Instances
new Chart(document.getElementById('warehouseChart'), {
    type: 'bar',
    data: {
        labels: <?= json_encode($warehouseLabels); ?>,
        datasets: [{
            label: 'Available Qty',
            data: <?= json_encode($warehouseQty); ?>,
            backgroundColor: '#0d6efd'
        }]
    },
    options: { responsive: true }
});

new Chart(document.getElementById('stockChart'), {
    type: 'doughnut',
    data: {
        labels: ['Low Stock', 'Out Of Stock'],
        datasets: [{
            data: [<?= $lowStock; ?>, <?= $outStock; ?>],
            backgroundColor: ['#dc3545', '#6c757d']
        }]
    },
    options: { responsive: true }
});

new Chart(document.getElementById("inboundChart"), {
    type: "line",
    data: {
        labels: <?= json_encode($inboundLabels); ?>,
        datasets: [{
            label: "Inbound",
            data: <?= json_encode($inboundData); ?>,
            borderColor: "#198754",
            tension: 0.4,
            fill: false
        }]
    },
    options: { responsive: true }
});

new Chart(document.getElementById("outboundChart"), {
    type: "line",
    data: {
        labels: <?= json_encode($outboundLabels); ?>,
        datasets: [{
            label: "Outbound",
            data: <?= json_encode($outboundData); ?>,
            borderColor: "#dc3545",
            tension: 0.4,
            fill: false
        }]
    },
    options: { responsive: true }
});
</script>