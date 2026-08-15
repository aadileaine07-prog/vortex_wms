<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
?>

<link rel="stylesheet"
      href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

<div class="sidebar">

    <!-- LOGO -->

    <div class="logo-area">

        <a href="/vortex_wms/dashboard.php">

            <img
                src="/vortex_wms/assets/images/logo.png"
                alt="VORTEX WMS">

        </a>

        <div class="brand-sub">
            Enterprise Warehouse Management System
        </div>

    </div>


    <!-- MENU -->

    <ul class="menu">

        <!-- DASHBOARD -->

        <li class="menu-title">Dashboard</li>

        <li>
            <a href="/vortex_wms/dashboard.php">
                <i class="fa-solid fa-gauge-high"></i>
                Dashboard
            </a>
        </li>


        <!-- INBOUND -->

        <li class="menu-title">Inbound</li>

        <li class="nav-item">
    <a href="/vortex_wms/modules/purchase_orders/index.php" class="nav-link">
        <i class="fa-solid fa-file-invoice-dollar me-2"></i>
        <span>Purchase Orders</span>
    </a>
</li>

        <li>
            <a href="/vortex_wms/modules/inbound/asn/index.php">
                <i class="fa-solid fa-file-invoice"></i>
                ASN
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/inbound/grn/index.php">
                <i class="fa-solid fa-boxes-stacked"></i>
                GRN
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/inbound/qc/index.php">
                <i class="fa-solid fa-magnifying-glass"></i>
                Quality Check
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/inbound/putaway/index.php">
                <i class="fa-solid fa-truck-ramp-box"></i>
                Putaway
            </a>
        </li>


        <!-- INVENTORY -->

        <li class="menu-title">Inventory</li>

        <li>
            <a href="/vortex_wms/modules/inventory/index.php">
                <i class="fa-solid fa-box"></i>
                Inventory
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/inventory/stock/index.php">
                <i class="fa-solid fa-cubes"></i>
                Stock
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/inventory/transfer/index.php">
                <i class="fa-solid fa-right-left"></i>
                Transfer
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/inventory/adjustment/index.php">
                <i class="fa-solid fa-sliders"></i>
                Adjustment
            </a>
        </li>

        <li class="nav-item">
             <a class="nav-link" href="/vortex_wms/modules/inventory/bin_map.php">
        <i class="fa-solid fa-border-all me-2 text-success"></i> 
        Visual Bin Map
          </a>
        </li>

        <li class="nav-item">
            <a class="nav-link" href="/vortex_wms/modules/inventory/alerts.php">
        <i class="fa-solid fa-bell me-2 text-warning"></i> 
        Expiry & Low Stock Alerts
            </a>
        </li>
        


        <!-- OUTBOUND -->

        <li class="menu-title">Outbound</li>

        <li>
            <a href="/vortex_wms/modules/outbound/sales_order/index.php">
                <i class="fa-solid fa-cart-shopping"></i>
                Sales Order
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/outbound/picking/index.php">
                <i class="fa-solid fa-hand"></i>
                Picking
            </a>
        </li>
        <li class="nav-item">
    <a class="nav-link" href="/vortex_wms/modules/outbound/pick_path.php">
        <i class="fa-solid fa-route me-2 text-info"></i> Optimized Pick Path
    </a>
</li>

        <li>
            <a href="/vortex_wms/modules/outbound/packing/index.php">
                <i class="fa-solid fa-box-open"></i>
                Packing
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/outbound/dispatch/index.php">
                <i class="fa-solid fa-truck-fast"></i>
                Dispatch
            </a>
        </li>


        <!-- MASTER DATA -->

        <li class="menu-title">Master Data</li>

        <li>
            <a href="/vortex_wms/modules/masters/products/index.php">
                <i class="fa-solid fa-cube"></i>
                Product Master
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/masters/suppliers/index.php">
                <i class="fa-solid fa-industry"></i>
                Supplier Master
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/masters/warehouse/index.php">
                <i class="fa-solid fa-warehouse"></i>
                Warehouse Master
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/masters/bin_locations/index.php">
                <i class="fa-solid fa-location-dot"></i>
                Bin Location
            </a>
        </li>


        <!-- HUMAN RESOURCE -->

        <li class="menu-title">Human Resource</li>

        <li>
            <a href="/vortex_wms/modules/hr/employees/index.php">
                <i class="fa-solid fa-users"></i>
                Employee Master
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/hr/attendance/index.php">
                <i class="fa-solid fa-calendar-check"></i>
                Attendance
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/hr/leave/index.php">
                <i class="fa-solid fa-calendar-days"></i>
                Leave Management
            </a>
        </li>

        <!-- Payroll Link -->
<li class="nav-item">
    <a href="/vortex_wms/modules/payroll/index.php" class="nav-link text-white">
        <i class="fa-solid fa-file-invoice-dollar me-2 text-success"></i>
        <span>Payroll</span>
    </a>
</li>


        <!-- REPORTS -->

        <li class="menu-title">Reports</li>

        <li>
            <a href="/vortex_wms/modules/reports/dashboard.php">
                <i class="fa-solid fa-chart-line"></i>
                Dashboard Report
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/reports/inbound.php">
                <i class="fa-solid fa-arrow-down"></i>
                Inbound Report
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/reports/inventory.php">
                <i class="fa-solid fa-chart-column"></i>
                Inventory Report
            </a>
        </li>

        <li>
            <a href="/vortex_wms/modules/reports/outbound.php">
                <i class="fa-solid fa-arrow-up"></i>
                Outbound Report
            </a>
        </li>
        <!-- REPORTS SECTION -->
        <li><a href="/vortex_wms/modules/reports/audit_logs.php">
                <i class="fa-solid fa-clock-rotate-left"></i>
                Audit Logs
            </a>
        </li>

        <li class="nav-item">
    <a class="nav-link" href="/vortex_wms/modules/reports/abc_analysis.php">
        <i class="fa-solid fa-chart-pie me-2 text-secondary"></i> ABC Inventory Velocity
    </a>
</li>


 <!-- 🛠️ TOOLS SECTION -->
<li class="nav-item">
    <a class="nav-link" href="/vortex_wms/modules/tools/barcode_generator.php">
        <i class="fa-solid fa-barcode me-2 text-primary"></i> Barcode / QR Generator
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="/vortex_wms/modules/tools/scanner.php">
        <i class="fa-solid fa-mobile-screen-button me-2 text-danger"></i> Mobile Scanner
    </a>
</li>
<li class="nav-item">
    <a class="nav-link" href="/vortex_wms/modules/tools/universal_import.php">
        <i class="fa-solid fa-file-import me-2 text-info"></i> Universal Import
    </a>
</li>

        <!-- SYSTEM -->

        <li class="menu-title">System</li>

        <li>
            <a href="/vortex_wms/modules/system_settings/index.php">
                <i class="fa-solid fa-gear"></i>
                Settings
            </a>
        </li>

        <li>
            <a
                class="logout"
                href="/vortex_wms/logout.php">

                <i class="fa-solid fa-right-from-bracket"></i>
                Logout

            </a>
        </li>

    </ul>

</div>