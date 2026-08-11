<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

require_once "config/database.php";

/* ===============================
   Fetch Dynamic Database Counts
================================ */

// 1. Inbound Orders Count
$inboundCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM asn"))['total'] ?? 0;

// 2. Inventory Products Count
$inventoryCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM products"))['total'] ?? 0;

// 3. Outbound Shipments Count
$outboundCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM sales_orders"))['total'] ?? 0;

// 4. Employees/Users Count
$userCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM employees"))['total'] ?? 0;

// 5. Low Stock Alert Query (Threshold <= 10)
$lowStockQuery = "
    SELECT p.product_code, p.product_name, COALESCE(SUM(i.available_qty), 0) as total_stock 
    FROM products p 
    LEFT JOIN inventory i ON p.id = i.product_id 
    GROUP BY p.id 
    HAVING total_stock <= 10 
    LIMIT 5
";
$lowStockResult = mysqli_query($conn, $lowStockQuery);

?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>VORTEX WMS Dashboard</title>

    <!-- Google Fonts & Bootstrap 5 -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/sidebar.css">

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

    <style>
        body {
            font-family: 'Plus Jakarta Sans', sans-serif;
            background-color: #f1f5f9;
            color: #334155;
        }

        .main-wrapper {
            margin-left: 270px;
            transition: all 0.3s ease;
        }

        /* Clean Enterprise Top Navbar */
        .top-navbar {
            background: #0f172a !important;
            border-bottom: 1px solid #1e293b;
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
        }

        .brand-logo-icon {
            width: 38px;
            height: 38px;
            background: #2563eb;
            color: #ffffff;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.1rem;
        }

        .brand-title {
            font-size: 1.15rem;
            letter-spacing: 0.5px;
            color: #ffffff;
        }

        .brand-subtitle {
            font-size: 0.65rem;
            letter-spacing: 1px;
            color: #94a3b8;
        }

        .user-profile-badge {
            background: #1e293b;
            border: 1px solid #334155;
        }

        .profile-avatar {
            width: 32px;
            height: 32px;
            background: #3b82f6;
            color: #ffffff;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 0.85rem;
        }

        .bg-role-badge {
            background: #334155;
            color: #e2e8f0;
            font-size: 0.7rem;
        }

        .btn-icon-nav {
            width: 38px;
            height: 38px;
            border-radius: 8px;
            background: #1e293b;
            border: 1px solid #334155;
            color: #cbd5e1;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s ease;
            text-decoration: none;
        }

        .btn-icon-nav:hover {
            background: #334155;
            color: #ffffff;
        }

        .btn-logout {
            background: #dc2626;
            color: #ffffff;
            border: none;
            transition: all 0.2s ease;
        }

        .btn-logout:hover {
            background: #b91c1c;
            color: #ffffff;
        }

        /* Clean Professional Welcome Heading */
        .dashboard-heading {
            font-size: 1.75rem;
            font-weight: 700;
            color: #0f172a;
        }

        /* Stat Cards Styling */
        .stat-card {
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            background: #ffffff;
            transition: all 0.2s ease;
            text-decoration: none;
            color: inherit;
        }

        .stat-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.05) !important;
            border-color: #cbd5e1;
        }

        .icon-shape {
            width: 52px;
            height: 52px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }

        .bg-inbound { background: #eff6ff; color: #1d4ed8; }
        .bg-inventory { background: #f0fdf4; color: #15803d; }
        .bg-outbound { background: #fffbe3; color: #b45309; }
        .bg-users { background: #faf5ff; color: #7e22ce; }

        .btn-action {
            border-radius: 8px;
            padding: 10px 14px;
            font-weight: 600;
            font-size: 0.9rem;
            transition: all 0.2s ease;
        }

        @media (max-width: 992px) {
            .main-wrapper { margin-left: 0; }
        }
    </style>
</head>

<body>

    <?php include "includes/sidebar.php"; ?>

    <div class="main-wrapper">

        <!-- Clean Top Navigation Bar -->
        <nav class="navbar navbar-expand-lg top-navbar px-4 py-2 sticky-top">
            <div class="container-fluid d-flex justify-content-between align-items-center">
                
                <!-- Left Brand Logo & Title -->
                <a class="navbar-brand d-flex align-items-center gap-2 m-0 text-decoration-none" href="dashboard.php">
                    <div class="brand-logo-icon">
                        <i class="fa-solid fa-boxes-stacked"></i>
                    </div>
                    <div class="d-flex flex-column">
                        <span class="brand-title fw-bold">VORTEX WMS</span>
                        <span class="brand-subtitle font-monospace">ENTERPRISE WAREHOUSE SYSTEM</span>
                    </div>
                </a>

                <!-- Right User Profile & Actions -->
                <div class="d-flex align-items-center gap-3">
                    
                    <!-- User Status & ID Badge -->
                    <div class="user-profile-badge d-none d-md-flex align-items-center gap-2 px-3 py-1.5 rounded-3">
                        <div class="profile-avatar fw-bold">
                            <?= strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 1)); ?>
                        </div>
                        <div class="text-start pe-2">
                            <div class="fw-semibold text-white small leading-none"><?= htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></div>
                            <small class="text-slate-400 font-monospace text-muted" style="font-size: 0.75rem;">ID: <?= htmlspecialchars($_SESSION['employee_id']); ?></small>
                        </div>
                        <span class="badge bg-role-badge text-uppercase px-2 py-1 rounded">
                            <?= htmlspecialchars($_SESSION['role'] ?? 'User'); ?>
                        </span>
                    </div>

                    <!-- Notification Link -->
                    <a href="modules/notifications/index.php" class="btn-icon-nav" title="Notifications">
                        <i class="fa-regular fa-bell"></i>
                    </a>

                    <!-- Logout Button -->
                    <a href="logout.php" class="btn btn-logout btn-sm d-flex align-items-center gap-2 px-3 py-2 rounded-2 text-decoration-none">
                        <i class="fa-solid fa-right-from-bracket"></i>
                        <span class="d-none d-sm-inline fw-semibold">Logout</span>
                    </a>

                </div>

            </div>
        </nav>

        <!-- Main Dashboard Content -->
        <div class="container-fluid p-4">

            <!-- Professional Welcome Banner -->
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
                <div>
                    <h2 class="dashboard-heading mb-1">
                        Welcome back, <?= htmlspecialchars($_SESSION['full_name'] ?? 'Employee'); ?> 👋
                    </h2>
                    <p class="text-muted mb-0">Overview of operational activities and inventory metrics.</p>
                </div>
                <div>
                    <a href="modules/reports/dashboard.php" class="btn btn-dark btn-action shadow-sm">
                        <i class="fa-solid fa-chart-line me-2"></i> View System Reports
                    </a>
                </div>
            </div>

            <!-- Standard Metric Cards -->
            <div class="row g-4 mb-4">

                <!-- Inbound Card -->
                <div class="col-xl-3 col-md-6">
                    <a href="modules/inbound/dashboard.php" class="card stat-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold small text-uppercase">Inbound Orders</span>
                                <h2 class="fw-bold my-1 text-dark"><?= number_format($inboundCount); ?></h2>
                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle fw-normal">Active Orders</span>
                            </div>
                            <div class="icon-shape bg-inbound">
                                <i class="fa-solid fa-truck-ramp-box"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Inventory Card -->
                <div class="col-xl-3 col-md-6">
                    <a href="modules/inventory/index.php" class="card stat-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold small text-uppercase">Total Inventory</span>
                                <h2 class="fw-bold my-1 text-dark"><?= number_format($inventoryCount); ?></h2>
                                <span class="badge bg-success-subtle text-success border border-success-subtle fw-normal">Total SKUs</span>
                            </div>
                            <div class="icon-shape bg-inventory">
                                <i class="fa-solid fa-boxes-stacked"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Outbound Card -->
                <div class="col-xl-3 col-md-6">
                    <a href="modules/outbound/sales_order/index.php" class="card stat-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold small text-uppercase">Outbound Orders</span>
                                <h2 class="fw-bold my-1 text-dark"><?= number_format($outboundCount); ?></h2>
                                <span class="badge bg-warning-subtle text-warning border border-warning-subtle fw-normal">Dispatches</span>
                            </div>
                            <div class="icon-shape bg-outbound">
                                <i class="fa-solid fa-dolly"></i>
                            </div>
                        </div>
                    </a>
                </div>

                <!-- Users Card -->
                <div class="col-xl-3 col-md-6">
                    <a href="modules/hr/employees/index.php" class="card stat-card p-3 h-100">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <span class="text-muted fw-semibold small text-uppercase">System Users</span>
                                <h2 class="fw-bold my-1 text-dark"><?= number_format($userCount); ?></h2>
                                <span class="badge bg-purple-subtle text-purple border border-purple-subtle fw-normal">Active Team</span>
                            </div>
                            <div class="icon-shape bg-users">
                                <i class="fa-solid fa-users-gear"></i>
                            </div>
                        </div>
                    </a>
                </div>

            </div>

            <!-- Analytics Chart & Low Stock Warnings -->
            <div class="row g-4 mb-4">
                
                <!-- Stock Analytics Graph -->
                <div class="col-lg-8">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-chart-area text-primary me-2"></i>Inbound vs Outbound Monthly Flow</h5>
                            <canvas id="wmsChart" height="110"></canvas>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Warning Section -->
                <div class="col-lg-4">
                    <div class="card border-0 shadow-sm rounded-3 h-100">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-triangle-exclamation text-danger me-2"></i>Low Stock Alert</h5>
                                <span class="badge bg-danger text-white">Reorder Limit</span>
                            </div>

                            <div class="list-group list-group-flush">
                                <?php if ($lowStockResult && mysqli_num_rows($lowStockResult) > 0): ?>
                                    <?php while ($row = mysqli_fetch_assoc($lowStockResult)): ?>
                                        <div class="list-group-item px-0 d-flex justify-content-between align-items-center border-bottom">
                                            <div>
                                                <strong class="d-block text-dark small"><?= htmlspecialchars($row['product_name']); ?></strong>
                                                <small class="text-muted font-monospace"><?= htmlspecialchars($row['product_code']); ?></small>
                                            </div>
                                            <span class="badge bg-danger-subtle text-danger border border-danger-subtle fs-6"><?= $row['total_stock']; ?> Units</span>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-4 text-muted">
                                        <i class="fa-solid fa-circle-check text-success fs-3 mb-2 d-block"></i>
                                        All product stock levels are optimal.
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Quick Operations Shortcuts -->
            <div class="card border-0 shadow-sm rounded-3">
                <div class="card-body p-4">
                    <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-bolt me-2 text-primary"></i> Quick Access Shortcuts</h5>
                    <div class="row g-3">
                        <div class="col-md-3 col-6">
                            <a href="modules/inventory/index.php" class="btn btn-outline-primary btn-action w-100 text-start">
                                <i class="fa-solid fa-box me-2"></i> Manage Stock
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="modules/inbound/dashboard.php" class="btn btn-outline-success btn-action w-100 text-start">
                                <i class="fa-solid fa-truck-loading me-2"></i> Inbound Orders
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="modules/outbound/sales_order/index.php" class="btn btn-outline-warning btn-action w-100 text-start">
                                <i class="fa-solid fa-paper-plane me-2"></i> Sales Orders
                            </a>
                        </div>
                        <div class="col-md-3 col-6">
                            <a href="modules/hr/employees/index.php" class="btn btn-outline-danger btn-action w-100 text-start">
                                <i class="fa-solid fa-user-plus me-2"></i> Employee Master
                            </a>
                        </div>
                    </div>
                </div>
            </div>

        </div> <!-- /.container-fluid -->

    </div>

    <!-- Chart.js Script Configuration -->
    <script>
        document.addEventListener("DOMContentLoaded", function () {
            const ctx = document.getElementById('wmsChart').getContext('2d');
            new Chart(ctx, {
                type: 'line',
                data: {
                    labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug'],
                    datasets: [
                        {
                            label: 'Inbound Orders',
                            data: [12, 19, 15, 25, 22, 30, 28, <?= $inboundCount; ?>],
                            borderColor: '#2563eb',
                            backgroundColor: 'rgba(37, 99, 235, 0.05)',
                            fill: true,
                            tension: 0.3
                        },
                        {
                            label: 'Outbound Shipments',
                            data: [8, 12, 18, 20, 15, 25, 24, <?= $outboundCount; ?>],
                            borderColor: '#d97706',
                            backgroundColor: 'rgba(217, 119, 6, 0.05)',
                            fill: true,
                            tension: 0.3
                        }
                    ]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'top' }
                    },
                    scales: {
                        y: { beginAtZero: true }
                    }
                }
            });
        });
    </script>

</body>
</html>