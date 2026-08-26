<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = $_SERVER['REQUEST_URI'] ?? '';
?>

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">

<style>
/* 1. Sidebar Container Lock */
#vortexSidebar {
    position: fixed !important;
    top: 0 !important;
    left: 0 !important;
    bottom: 0 !important;
    width: 260px !important;
    height: 100vh !important;
    overflow-y: auto !important;
    overflow-x: hidden !important;
    z-index: 1050 !important;
    background: #0f172a !important;
    border-right: 1px solid rgba(255, 255, 255, 0.08);
}

#vortexSidebar::-webkit-scrollbar {
    width: 5px;
}
#vortexSidebar::-webkit-scrollbar-thumb {
    background: rgba(255, 255, 255, 0.2);
    border-radius: 4px;
}

/* 2. Menu Link Styling */
.sidebar-link {
    display: flex !important;
    align-items: center !important;
    gap: 12px;
    padding: 10px 14px;
    color: #94a3b8 !important;
    text-decoration: none !important;
    border-radius: 8px;
    font-size: 13.5px;
    font-weight: 500;
    transition: background 0.2s ease, color 0.2s ease;
    cursor: pointer;
    user-select: none;
}
.sidebar-link:hover {
    background: rgba(255, 255, 255, 0.07) !important;
    color: #fff !important;
}
.sidebar-link.active {
    background: #2563eb !important;
    color: #fff !important;
    font-weight: 600;
}

/* 3. Submenu & Nested Lists */
.sidebar .submenu {
    display: none;
    list-style: none !important;
    padding: 4px 0 6px 12px !important;
    margin: 4px 0 6px 0 !important;
    background: rgba(0, 0, 0, 0.25) !important;
    border-radius: 8px;
}
.sidebar .has-submenu.open > .submenu {
    display: block !important;
}

.sidebar .nested-list {
    list-style: none !important;
    padding: 4px 0 4px 10px !important;
    margin: 4px 0 !important;
    display: none;
    background: rgba(0, 0, 0, 0.35) !important;
    border-radius: 6px;
}
.sidebar .nested-item.open > .nested-list {
    display: block !important;
}

.sidebar .sub-link {
    display: flex !important;
    align-items: center !important;
    padding: 7px 12px;
    font-size: 13px;
    color: #cbd5e1 !important;
    text-decoration: none !important;
    border-radius: 6px;
    transition: background 0.2s ease, color 0.2s ease;
    cursor: pointer;
}
.sidebar .sub-link:hover {
    background: rgba(255, 255, 255, 0.06) !important;
    color: #fff !important;
}
.sidebar .sub-link.active {
    color: #60a5fa !important;
    font-weight: 600;
}

/* Arrows */
.sidebar .arrow-icon,
.sidebar .n-arrow {
    font-size: 10px;
    transition: transform 0.25s ease;
    pointer-events: none;
}
.sidebar .has-submenu.open > .submenu-toggle .arrow-icon,
.sidebar .nested-item.open > .nested-btn .n-arrow {
    transform: rotate(90deg);
}
</style>

<div class="sidebar" id="vortexSidebar">

    <!-- Logo Area -->
    <div class="logo-area p-3 text-center border-bottom border-secondary border-opacity-25">
        <a href="/vortex_wms/dashboard.php">
            <img src="/vortex_wms/assets/images/logo.png" alt="VORTEX WMS" style="max-height: 38px;" onerror="this.src='/vortex_wms/assets/img/logo.png'">
        </a>
        <div class="brand-sub small text-muted mt-1" style="font-size: 11px;">Enterprise Warehouse OS</div>
    </div>

    <!-- Navigation Menu -->
    <ul class="menu list-unstyled p-2 mb-0">
        
        <!-- 1. DASHBOARD -->
        <li class="mb-1">
            <a href="/vortex_wms/dashboard.php" class="sidebar-link <?= (basename($current_page) == 'dashboard.php' && strpos($current_page, 'reports') === false) ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- 2. INVENTORY -->
        <li class="has-submenu mb-1 <?= (strpos($current_page, '/inventory/') !== false && strpos($current_page, 'reports') === false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="sidebar-link submenu-toggle justify-content-between">
                <span class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-boxes-stacked"></i>
                    <span>Inventory</span>
                </span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/inventory/index.php" class="sub-link <?= (basename($current_page) == 'index.php' && strpos($current_page, '/inventory/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-box me-2"></i>Inventory List</a></li>
                <li><a href="/vortex_wms/modules/inventory/item_identification/index.php" class="sub-link <?= (strpos($current_page, 'item_identification') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-camera me-2 text-primary"></i>Item Identification</a></li>

                <!-- Nested: Locations & Stock -->
                <li class="nested-item <?= (strpos($current_page, '/locations/') !== false || strpos($current_page, '/stock/') !== false || strpos($current_page, 'bin_map.php') !== false || strpos($current_page, '/transfer/') !== false || strpos($current_page, '/adjustment/') !== false) ? 'open' : ''; ?>">
                    <a href="javascript:void(0);" class="sub-link nested-btn justify-content-between">
                        <span><i class="fa-solid fa-warehouse me-2 text-info"></i>Locations & Stock</span>
                        <i class="fa-solid fa-chevron-right n-arrow"></i>
                    </a>
                    <ul class="nested-list">
                        <li><a href="/vortex_wms/modules/inventory/locations/inventory_view.php" class="sub-link <?= (strpos($current_page, 'inventory_view.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-list-ul me-2"></i>Inventory View</a></li>
                        <li><a href="/vortex_wms/modules/inventory/locations/location_view.php" class="sub-link <?= (strpos($current_page, 'location_view.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-map-location-dot me-2"></i>Location View</a></li>
                        <li><a href="/vortex_wms/modules/inventory/bin_map.php" class="sub-link <?= (strpos($current_page, 'bin_map.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-border-all me-2 text-success"></i>Visual Bin Map</a></li>
                        <li><a href="/vortex_wms/modules/inventory/stock/index.php" class="sub-link <?= (strpos($current_page, '/stock/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-cubes me-2"></i>Stock Levels</a></li>
                        <li><a href="/vortex_wms/modules/inventory/locations/item_details.php" class="sub-link <?= (strpos($current_page, 'item_details.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-circle-info me-2"></i>Item Details</a></li>
                        <li><a href="/vortex_wms/modules/inventory/locations/item_mapping.php" class="sub-link <?= (strpos($current_page, 'item_mapping.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-arrows-split-up-and-left me-2"></i>Location Mapping</a></li>
                        <li><a href="/vortex_wms/modules/inventory/transfer/index.php" class="sub-link <?= (strpos($current_page, '/transfer/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-right-left me-2"></i>Stock Transfer</a></li>
                        <li><a href="/vortex_wms/modules/inventory/adjustment/index.php" class="sub-link <?= (strpos($current_page, '/adjustment/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-sliders me-2"></i>Stock Adjustment</a></li>
                        <li><a href="/vortex_wms/modules/inventory/locations/manual_update.php" class="sub-link <?= (strpos($current_page, 'manual_update.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-pen-to-square me-2"></i>Manual Stock Update</a></li>
                    </ul>
                </li>

                <!-- Nested: Audit -->
                <li class="nested-item <?= (strpos($current_page, '/inventory/audit/') !== false) ? 'open' : ''; ?>">
                    <a href="javascript:void(0);" class="sub-link nested-btn justify-content-between">
                        <span><i class="fa-solid fa-clipboard-check me-2 text-primary"></i>Audit</span>
                        <i class="fa-solid fa-chevron-right n-arrow"></i>
                    </a>
                    <ul class="nested-list">
                        <li><a href="/vortex_wms/modules/inventory/audit/summary.php" class="sub-link <?= (strpos($current_page, 'summary.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-table-list me-2"></i>Audit Summary</a></li>
                        <li><a href="/vortex_wms/modules/inventory/audit/raise_visibility.php" class="sub-link <?= (strpos($current_page, 'raise_visibility.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-flag me-2"></i>Raise Audit Visibility</a></li>
                    </ul>
                </li>

                <!-- Nested: Migrations -->
                <li class="nested-item <?= (strpos($current_page, '/inventory/migrations/') !== false) ? 'open' : ''; ?>">
                    <a href="javascript:void(0);" class="sub-link nested-btn justify-content-between">
                        <span><i class="fa-solid fa-truck-moving me-2 text-warning"></i>Migrations</span>
                        <i class="fa-solid fa-chevron-right n-arrow"></i>
                    </a>
                    <ul class="nested-list">
                        <li><a href="/vortex_wms/modules/inventory/migrations/spr_tasks.php" class="sub-link <?= (strpos($current_page, 'spr_tasks.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-list-check me-2"></i>SPR Tasks</a></li>
                        <li><a href="/vortex_wms/modules/inventory/migrations/self_serve.php" class="sub-link <?= (strpos($current_page, 'self_serve.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-sliders me-2"></i>Migration Self Serve</a></li>
                    </ul>
                </li>

                <!-- Nested: Bad Stocks -->
                <li class="nested-item <?= (strpos($current_page, '/bad_stocks/') !== false) ? 'open' : ''; ?>">
                    <a href="javascript:void(0);" class="sub-link nested-btn justify-content-between">
                        <span><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Bad Stocks</span>
                        <i class="fa-solid fa-chevron-right n-arrow"></i>
                    </a>
                    <ul class="nested-list">
                        <li><a href="/vortex_wms/modules/inventory/bad_stocks/update.php" class="sub-link <?= (strpos($current_page, '/bad_stocks/update.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-ban me-2 text-danger"></i>Bad Stock Update</a></li>
                        <li><a href="/vortex_wms/modules/inventory/bad_stocks/details.php" class="sub-link <?= (strpos($current_page, '/bad_stocks/details.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-lines me-2"></i>Bad Inventory Details</a></li>
                        <li><a href="/vortex_wms/modules/inventory/bad_stocks/reinventorize.php" class="sub-link <?= (strpos($current_page, 'reinventorize.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-rotate-left me-2 text-success"></i>Reinventorization</a></li>
                    </ul>
                </li>

                <li><a href="/vortex_wms/modules/inventory/alerts.php" class="sub-link <?= (strpos($current_page, 'alerts.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-bell me-2 text-warning"></i>Expiry & Alerts</a></li>
            </ul>
        </li>

        <!-- 3. INBOUND -->
        <li class="has-submenu mb-1 <?= (strpos($current_page, '/inbound/') !== false || strpos($current_page, 'purchase_orders') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="sidebar-link submenu-toggle justify-content-between">
                <span class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-truck-ramp-box"></i>
                    <span>Inbound</span>
                </span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/purchase_orders/index.php" class="sub-link <?= (strpos($current_page, 'purchase_orders') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Purchase Orders</a></li>
                <li><a href="/vortex_wms/modules/inbound/asn/index.php" class="sub-link <?= (strpos($current_page, '/asn/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice me-2"></i>ASN</a></li>
                <li><a href="/vortex_wms/modules/inbound/grn/index.php" class="sub-link <?= (strpos($current_page, '/grn/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-boxes-stacked me-2"></i>GRN</a></li>
                <li><a href="/vortex_wms/modules/inbound/qc/index.php" class="sub-link <?= (strpos($current_page, '/qc/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-magnifying-glass me-2"></i>Quality Check</a></li>
                <li><a href="/vortex_wms/modules/inbound/putaway/index.php" class="sub-link <?= (strpos($current_page, '/putaway/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-dolly me-2"></i>Putaway</a></li>
            </ul>
        </li>

        <!-- 4. OUTBOUND -->
        <li class="has-submenu mb-1 <?= (strpos($current_page, '/outbound/') !== false && strpos($current_page, 'reports') === false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="sidebar-link submenu-toggle justify-content-between">
                <span class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-dolly"></i>
                    <span>Outbound</span>
                </span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/outbound/sales_order/index.php" class="sub-link <?= (strpos($current_page, 'sales_order') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-cart-shopping me-2"></i>Sales Orders</a></li>
                <li><a href="/vortex_wms/modules/outbound/picking/index.php" class="sub-link <?= (strpos($current_page, 'picking') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-hand me-2"></i>Picking</a></li>
                <li><a href="/vortex_wms/modules/outbound/pick_path.php" class="sub-link <?= (strpos($current_page, 'pick_path.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-route me-2 text-info"></i>Optimized Pick Path</a></li>
                <li><a href="/vortex_wms/modules/outbound/packing/index.php" class="sub-link <?= (strpos($current_page, 'packing') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-box-open me-2"></i>Packing</a></li>
                <li><a href="/vortex_wms/modules/outbound/dispatch/index.php" class="sub-link <?= (strpos($current_page, 'dispatch') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-truck-fast me-2"></i>Dispatch</a></li>
            </ul>
        </li>

        <!-- 5. RETURNS & LIQUIDATION -->
        <li class="has-submenu mb-1 <?= (strpos($current_page, '/returns/') !== false || strpos($current_page, '/liquidation/') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="sidebar-link submenu-toggle justify-content-between">
                <span class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-right-left"></i>
                    <span>Returns & Liquidation</span>
                </span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/returns/index.php" class="sub-link <?= (strpos($current_page, '/returns/index.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-rotate-left me-2"></i>Returns Intake (RMA)</a></li>
                <li><a href="/vortex_wms/modules/returns/create.php" class="sub-link <?= (strpos($current_page, '/returns/create.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-plus me-2"></i>Create Return Slip</a></li>
                <li><a href="/vortex_wms/modules/liquidation/index.php" class="sub-link <?= (strpos($current_page, '/liquidation/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-dumpster-fire me-2 text-danger"></i>Liquidation Lots</a></li>
            </ul>
        </li>

        <!-- 6. MANPOWER (HR & PAYROLL) -->
        <li class="has-submenu mb-1 <?= (strpos($current_page, '/hr/') !== false || strpos($current_page, '/payroll/') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="sidebar-link submenu-toggle justify-content-between">
                <span class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-users-gear"></i>
                    <span>Manpower</span>
                </span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/hr/employees/index.php" class="sub-link <?= (strpos($current_page, 'employees') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-users me-2"></i>Employees</a></li>
                <li><a href="/vortex_wms/modules/hr/attendance/index.php" class="sub-link <?= (strpos($current_page, 'attendance') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-calendar-check me-2"></i>Attendance</a></li>
                <li><a href="/vortex_wms/modules/hr/leave/index.php" class="sub-link <?= (strpos($current_page, 'leave') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-calendar-days me-2"></i>Leaves</a></li>
                <li><a href="/vortex_wms/modules/payroll/index.php" class="sub-link <?= (strpos($current_page, 'payroll') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice-dollar me-2 text-success"></i>Payroll</a></li>
            </ul>
        </li>

        <!-- 7. MASTER DATA -->
        <li class="has-submenu mb-1 <?= (strpos($current_page, '/masters/') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="sidebar-link submenu-toggle justify-content-between">
                <span class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-database"></i>
                    <span>Master Data</span>
                </span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/masters/products/index.php" class="sub-link <?= (strpos($current_page, 'products') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-cube me-2"></i>Products</a></li>
                <li><a href="/vortex_wms/modules/masters/suppliers/index.php" class="sub-link <?= (strpos($current_page, 'suppliers') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-industry me-2"></i>Suppliers</a></li>
                <li><a href="/vortex_wms/modules/masters/warehouse/index.php" class="sub-link <?= (strpos($current_page, 'warehouse') !== false && strpos($current_page, 'bin_locations') === false) ? 'active' : ''; ?>"><i class="fa-solid fa-warehouse me-2"></i>Warehouses</a></li>
                <li><a href="/vortex_wms/modules/masters/bin_locations/index.php" class="sub-link <?= (strpos($current_page, 'bin_locations') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-location-dot me-2"></i>Bin Locations</a></li>
            </ul>
        </li>

        <!-- 8. REPORTS -->
        <li class="has-submenu mb-1 <?= (strpos($current_page, '/reports/') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="sidebar-link submenu-toggle justify-content-between">
                <span class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-chart-line"></i>
                    <span>Reports & Analytics</span>
                </span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/reports/dashboard.php" class="sub-link <?= (strpos($current_page, '/reports/dashboard.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-chart-simple me-2 text-info"></i>Dashboard Report</a></li>
                <li><a href="/vortex_wms/modules/reports/inbound.php" class="sub-link <?= (strpos($current_page, '/reports/inbound.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-arrow-down me-2 text-primary"></i>Inbound Report</a></li>
                <li><a href="/vortex_wms/modules/reports/inventory.php" class="sub-link <?= (strpos($current_page, '/reports/inventory.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-boxes-stacked me-2 text-success"></i>Inventory Report</a></li>
                <li><a href="/vortex_wms/modules/reports/outbound.php" class="sub-link <?= (strpos($current_page, '/reports/outbound.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-arrow-up me-2 text-warning"></i>Outbound Report</a></li>
                <li><a href="/vortex_wms/modules/reports/audit_logs.php" class="sub-link <?= (strpos($current_page, '/reports/audit_logs.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-clock-rotate-left me-2 text-secondary"></i>Audit Logs</a></li>
                <li><a href="/vortex_wms/modules/reports/abc_analysis.php" class="sub-link <?= (strpos($current_page, '/reports/abc_analysis.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-chart-pie me-2 text-danger"></i>ABC Analysis</a></li>
            </ul>
        </li>

        <!-- 9. UTILITIES & TOOLS -->
        <li class="has-submenu mb-1 <?= (strpos($current_page, '/tools/') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="sidebar-link submenu-toggle justify-content-between">
                <span class="d-flex align-items-center gap-2">
                    <i class="fa-solid fa-screwdriver-wrench"></i>
                    <span>Tools & Utilities</span>
                </span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/tools/barcode_generator.php" class="sub-link <?= (strpos($current_page, 'barcode_generator') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-barcode me-2 text-primary"></i>Barcode / QR</a></li>
                <li><a href="/vortex_wms/modules/tools/scanner.php" class="sub-link <?= (strpos($current_page, 'scanner.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-mobile-screen-button me-2 text-danger"></i>Mobile Scanner</a></li>
                <li><a href="/vortex_wms/modules/tools/universal_import.php" class="sub-link <?= (strpos($current_page, 'universal_import') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-import me-2 text-info"></i>Universal Import</a></li>
            </ul>
        </li>

        <li class="my-2 border-top border-secondary border-opacity-25"></li>

        <!-- 10. SYSTEM SETTINGS -->
        <li class="mb-1">
            <a href="/vortex_wms/modules/system_settings/index.php" class="sidebar-link <?= (strpos($current_page, 'system_settings') !== false) ? 'active' : ''; ?>">
                <i class="fa-solid fa-gear"></i>
                <span>System Settings</span>
            </a>
        </li>

        <!-- 11. LOGOUT -->
        <li>
            <a href="/vortex_wms/logout.php" class="sidebar-link text-danger">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<script>
(function() {
    // Robust Global Click Delegation
    document.addEventListener('click', function(e) {
        const submenuToggle = e.target.closest('#vortexSidebar .submenu-toggle');
        const nestedToggle  = e.target.closest('#vortexSidebar .nested-btn');

        if (submenuToggle) {
            e.preventDefault();
            e.stopPropagation();
            
            const currentParent = submenuToggle.closest('.has-submenu');
            const sidebar = document.getElementById('vortexSidebar');

            // Close other sibling dropdowns (Accordion mode)
            sidebar.querySelectorAll('.has-submenu').forEach(item => {
                if (item !== currentParent) {
                    item.classList.remove('open');
                }
            });

            currentParent.classList.toggle('open');
            sessionStorage.setItem('vortexSidebarScroll', sidebar.scrollTop);
            return;
        }

        if (nestedToggle) {
            e.preventDefault();
            e.stopPropagation();
            
            const parentItem = nestedToggle.closest('.nested-item');
            parentItem.classList.toggle('open');
            
            const sidebar = document.getElementById('vortexSidebar');
            if (sidebar) sessionStorage.setItem('vortexSidebarScroll', sidebar.scrollTop);
            return;
        }
    }, true);

    // Retain Scroll Position on load
    window.addEventListener('load', function() {
        const sidebar = document.getElementById('vortexSidebar');
        if (!sidebar) return;

        const savedScroll = sessionStorage.getItem('vortexSidebarScroll');
        if (savedScroll !== null) {
            sidebar.scrollTop = parseInt(savedScroll, 10);
        }

        sidebar.addEventListener('scroll', function() {
            sessionStorage.setItem('vortexSidebarScroll', sidebar.scrollTop);
        }, { passive: true });
    });
})();
</script>