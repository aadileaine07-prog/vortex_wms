<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$current_page = $_SERVER['REQUEST_URI'] ?? '';
?>
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
<style>
.sidebar .nested-list {
    list-style: none !important;
    padding: 0 0 0 10px !important;
    margin: 4px 0 !important;
    display: none;
    background: rgba(0, 0, 0, 0.25);
    border-radius: 6px;
}
.sidebar .nested-item.open > .nested-list {
    display: block !important;
}
.sidebar .nested-btn {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    padding: 8px 12px !important;
    font-size: 13px !important;
    color: #cbd5e1 !important;
    cursor: pointer !important;
    border-radius: 6px;
    transition: all 0.2s ease;
}
.sidebar .nested-btn:hover {
    background: rgba(255, 255, 255, 0.05);
    color: #fff !important;
}
.sidebar .nested-item.open > .nested-btn {
    color: #60a5fa !important;
}
.sidebar .nested-item.open > .nested-btn .n-arrow {
    transform: rotate(90deg);
}
.sidebar .n-arrow {
    font-size: 10px;
    transition: transform 0.25s ease;
}
</style>

<div class="sidebar" id="vortexSidebar">
    <div class="logo-area">
        <a href="/vortex_wms/dashboard.php">
            <img src="/vortex_wms/assets/images/logo.png" alt="VORTEX WMS" onerror="this.src='/vortex_wms/assets/img/logo.png'">
        </a>
        <div class="brand-sub">Enterprise Warehouse Management System</div>
    </div>

    <ul class="menu">
        <!-- 1. DASHBOARD -->
        <li>
            <a href="/vortex_wms/dashboard.php" class="single-link <?= (basename($current_page) == 'dashboard.php' && strpos($current_page, 'reports') === false) ? 'active' : ''; ?>">
                <i class="fa-solid fa-gauge-high"></i>
                <span>Dashboard</span>
            </a>
        </li>

        <!-- 2. INVENTORY (COMPACT COLLAPSIBLE) -->
        <li class="has-submenu <?= (strpos($current_page, '/inventory/') !== false && strpos($current_page, 'reports') === false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="submenu-toggle">
                <i class="fa-solid fa-boxes-stacked"></i>
                <span>Inventory</span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/inventory/index.php" class="<?= (basename($current_page) == 'index.php' && strpos($current_page, '/inventory/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-box me-2"></i>Inventory List</a></li>
                <li><a href="/vortex_wms/modules/inventory/item_identification/index.php" class="<?= (strpos($current_page, 'item_identification') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-camera me-2 text-primary"></i>Item Identification</a></li>

                <!-- Nested: Locations & Stock -->
                <li class="nested-item <?= (strpos($current_page, '/locations/') !== false || strpos($current_page, '/stock/') !== false || strpos($current_page, 'bin_map.php') !== false || strpos($current_page, '/transfer/') !== false || strpos($current_page, '/adjustment/') !== false) ? 'open' : ''; ?>">
                    <a href="javascript:void(0);" class="nested-btn">
                        <span><i class="fa-solid fa-warehouse me-2 text-info"></i>Locations & Stock</span>
                        <i class="fa-solid fa-chevron-right n-arrow"></i>
                    </a>
                    <ul class="nested-list">
                        <li><a href="/vortex_wms/modules/inventory/locations/inventory_view.php" class="<?= (strpos($current_page, 'inventory_view.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-list-ul me-2"></i>Inventory View</a></li>
                        <li><a href="/vortex_wms/modules/inventory/locations/location_view.php" class="<?= (strpos($current_page, 'location_view.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-map-location-dot me-2"></i>Location View</a></li>
                        <li><a href="/vortex_wms/modules/inventory/bin_map.php" class="<?= (strpos($current_page, 'bin_map.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-border-all me-2 text-success"></i>Visual Bin Map</a></li>
                        <li><a href="/vortex_wms/modules/inventory/stock/index.php" class="<?= (strpos($current_page, '/stock/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-cubes me-2"></i>Stock Levels</a></li>
                        <li><a href="/vortex_wms/modules/inventory/locations/item_details.php" class="<?= (strpos($current_page, 'item_details.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-circle-info me-2"></i>Item Details</a></li>
                        <li><a href="/vortex_wms/modules/inventory/locations/item_mapping.php" class="<?= (strpos($current_page, 'item_mapping.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-arrows-split-up-and-left me-2"></i>Location Mapping</a></li>
                        <li><a href="/vortex_wms/modules/inventory/transfer/index.php" class="<?= (strpos($current_page, '/transfer/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-right-left me-2"></i>Stock Transfer</a></li>
                        <li><a href="/vortex_wms/modules/inventory/adjustment/index.php" class="<?= (strpos($current_page, '/adjustment/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-sliders me-2"></i>Stock Adjustment</a></li>
                        <li><a href="/vortex_wms/modules/inventory/locations/manual_update.php" class="<?= (strpos($current_page, 'manual_update.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-pen-to-square me-2"></i>Manual Stock Update</a></li>
                    </ul>
                </li>

                <!-- Nested: Audit -->
                <li class="nested-item <?= (strpos($current_page, '/inventory/audit/') !== false) ? 'open' : ''; ?>">
                    <a href="javascript:void(0);" class="nested-btn">
                        <span><i class="fa-solid fa-clipboard-check me-2 text-primary"></i>Audit</span>
                        <i class="fa-solid fa-chevron-right n-arrow"></i>
                    </a>
                    <ul class="nested-list">
                        <li><a href="/vortex_wms/modules/inventory/audit/summary.php" class="<?= (strpos($current_page, 'summary.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-table-list me-2"></i>Audit Summary</a></li>
                        <li><a href="/vortex_wms/modules/inventory/audit/raise_visibility.php" class="<?= (strpos($current_page, 'raise_visibility.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-flag me-2"></i>Raise Audit Visibility</a></li>
                    </ul>
                </li>

                <!-- Nested: Migrations -->
                <li class="nested-item <?= (strpos($current_page, '/inventory/migrations/') !== false) ? 'open' : ''; ?>">
                    <a href="javascript:void(0);" class="nested-btn">
                        <span><i class="fa-solid fa-truck-moving me-2 text-warning"></i>Migrations</span>
                        <i class="fa-solid fa-chevron-right n-arrow"></i>
                    </a>
                    <ul class="nested-list">
                        <li><a href="/vortex_wms/modules/inventory/migrations/spr_tasks.php" class="<?= (strpos($current_page, 'spr_tasks.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-list-check me-2"></i>SPR Tasks</a></li>
                        <li><a href="/vortex_wms/modules/inventory/migrations/self_serve.php" class="<?= (strpos($current_page, 'self_serve.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-sliders me-2"></i>Migration Self Serve</a></li>
                    </ul>
                </li>

                <!-- Nested: Bad Stocks -->
                <li class="nested-item <?= (strpos($current_page, '/bad_stocks/') !== false) ? 'open' : ''; ?>">
                    <a href="javascript:void(0);" class="nested-btn">
                        <span><i class="fa-solid fa-triangle-exclamation me-2 text-danger"></i>Bad Stocks</span>
                        <i class="fa-solid fa-chevron-right n-arrow"></i>
                    </a>
                    <ul class="nested-list">
                        <li><a href="/vortex_wms/modules/inventory/bad_stocks/update.php" class="<?= (strpos($current_page, '/bad_stocks/update.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-ban me-2 text-danger"></i>Bad Stock Update</a></li>
                        <li><a href="/vortex_wms/modules/inventory/bad_stocks/details.php" class="<?= (strpos($current_page, '/bad_stocks/details.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-lines me-2"></i>Bad Inventory Details</a></li>
                        <li><a href="/vortex_wms/modules/inventory/bad_stocks/reinventorize.php" class="<?= (strpos($current_page, 'reinventorize.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-rotate-left me-2 text-success"></i>Reinventorization</a></li>
                    </ul>
                </li>

                <li><a href="/vortex_wms/modules/inventory/alerts.php" class="<?= (strpos($current_page, 'alerts.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-bell me-2 text-warning"></i>Expiry & Alerts</a></li>
            </ul>
        </li>

        <!-- 3. INBOUND -->
        <li class="has-submenu <?= (strpos($current_page, '/inbound/') !== false || strpos($current_page, 'purchase_orders') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="submenu-toggle">
                <i class="fa-solid fa-truck-ramp-box"></i>
                <span>Inbound</span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/purchase_orders/index.php" class="<?= (strpos($current_page, 'purchase_orders') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice-dollar me-2"></i>Purchase Orders</a></li>
                <li><a href="/vortex_wms/modules/inbound/asn/index.php" class="<?= (strpos($current_page, '/asn/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice me-2"></i>ASN</a></li>
                <li><a href="/vortex_wms/modules/inbound/grn/index.php" class="<?= (strpos($current_page, '/grn/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-boxes-stacked me-2"></i>GRN</a></li>
                <li><a href="/vortex_wms/modules/inbound/qc/index.php" class="<?= (strpos($current_page, '/qc/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-magnifying-glass me-2"></i>Quality Check</a></li>
                <li><a href="/vortex_wms/modules/inbound/putaway/index.php" class="<?= (strpos($current_page, '/putaway/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-dolly me-2"></i>Putaway</a></li>
            </ul>
        </li>

        <!-- 4. OUTBOUND -->
        <li class="has-submenu <?= (strpos($current_page, '/outbound/') !== false && strpos($current_page, 'reports') === false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="submenu-toggle">
                <i class="fa-solid fa-dolly"></i>
                <span>Outbound</span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/outbound/sales_order/index.php" class="<?= (strpos($current_page, 'sales_order') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-cart-shopping me-2"></i>Sales Orders</a></li>
                <li><a href="/vortex_wms/modules/outbound/picking/index.php" class="<?= (strpos($current_page, 'picking') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-hand me-2"></i>Picking</a></li>
                <li><a href="/vortex_wms/modules/outbound/pick_path.php" class="<?= (strpos($current_page, 'pick_path.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-route me-2 text-info"></i>Optimized Pick Path</a></li>
                <li><a href="/vortex_wms/modules/outbound/packing/index.php" class="<?= (strpos($current_page, 'packing') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-box-open me-2"></i>Packing</a></li>
                <li><a href="/vortex_wms/modules/outbound/dispatch/index.php" class="<?= (strpos($current_page, 'dispatch') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-truck-fast me-2"></i>Dispatch</a></li>
            </ul>
        </li>

        <!-- 5. LIQUIDATION & RETURNS -->
        <li class="has-submenu <?= (strpos($current_page, '/returns/') !== false || strpos($current_page, '/liquidation/') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="submenu-toggle">
                <i class="fa-solid fa-right-left"></i>
                <span>Liquidation & Returns</span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/returns/index.php" class="<?= (strpos($current_page, '/returns/index.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-rotate-left me-2"></i>Returns Intake (RMA)</a></li>
                <li><a href="/vortex_wms/modules/returns/create.php" class="<?= (strpos($current_page, '/returns/create.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-plus me-2"></i>Create Return Slip</a></li>
                <li><a href="/vortex_wms/modules/liquidation/index.php" class="<?= (strpos($current_page, '/liquidation/') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-dumpster-fire me-2 text-danger"></i>Liquidation Lots</a></li>
            </ul>
        </li>

        <!-- 6. MANPOWER -->
        <li class="has-submenu <?= (strpos($current_page, '/hr/') !== false || strpos($current_page, '/payroll/') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="submenu-toggle">
                <i class="fa-solid fa-users-gear"></i>
                <span>Manpower</span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/hr/employees/index.php" class="<?= (strpos($current_page, 'employees') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-users me-2"></i>Employees</a></li>
                <li><a href="/vortex_wms/modules/hr/attendance/index.php" class="<?= (strpos($current_page, 'attendance') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-calendar-check me-2"></i>Attendance</a></li>
                <li><a href="/vortex_wms/modules/hr/leave/index.php" class="<?= (strpos($current_page, 'leave') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-calendar-days me-2"></i>Leaves</a></li>
                <li><a href="/vortex_wms/modules/payroll/index.php" class="<?= (strpos($current_page, 'payroll') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-invoice-dollar me-2 text-success"></i>Payroll</a></li>
            </ul>
        </li>

        <!-- 7. MASTER DATA -->
        <li class="has-submenu <?= (strpos($current_page, '/masters/') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="submenu-toggle">
                <i class="fa-solid fa-database"></i>
                <span>Master Data</span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/masters/products/index.php" class="<?= (strpos($current_page, 'products') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-cube me-2"></i>Products</a></li>
                <li><a href="/vortex_wms/modules/masters/suppliers/index.php" class="<?= (strpos($current_page, 'suppliers') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-industry me-2"></i>Suppliers</a></li>
                <li><a href="/vortex_wms/modules/masters/warehouse/index.php" class="<?= (strpos($current_page, 'warehouse') !== false && strpos($current_page, 'bin_locations') === false) ? 'active' : ''; ?>"><i class="fa-solid fa-warehouse me-2"></i>Warehouses</a></li>
                <li><a href="/vortex_wms/modules/masters/bin_locations/index.php" class="<?= (strpos($current_page, 'bin_locations') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-location-dot me-2"></i>Bin Locations</a></li>
            </ul>
        </li>

        <!-- 8. REPORTS & ANALYTICS -->
        <li class="has-submenu <?= (strpos($current_page, '/reports/') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="submenu-toggle">
                <i class="fa-solid fa-chart-line"></i>
                <span>Reports & Analytics</span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/reports/dashboard.php" class="<?= (strpos($current_page, '/reports/dashboard.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-chart-simple me-2 text-info"></i>Dashboard Report</a></li>
                <li><a href="/vortex_wms/modules/reports/inbound.php" class="<?= (strpos($current_page, '/reports/inbound.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-arrow-down me-2 text-primary"></i>Inbound Report</a></li>
                <li><a href="/vortex_wms/modules/reports/inventory.php" class="<?= (strpos($current_page, '/reports/inventory.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-boxes-stacked me-2 text-success"></i>Inventory Report</a></li>
                <li><a href="/vortex_wms/modules/reports/outbound.php" class="<?= (strpos($current_page, '/reports/outbound.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-arrow-up me-2 text-warning"></i>Outbound Report</a></li>
                <li><a href="/vortex_wms/modules/reports/audit_logs.php" class="<?= (strpos($current_page, '/reports/audit_logs.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-clock-rotate-left me-2 text-secondary"></i>Audit Logs</a></li>
                <li><a href="/vortex_wms/modules/reports/abc_analysis.php" class="<?= (strpos($current_page, '/reports/abc_analysis.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-chart-pie me-2 text-danger"></i>ABC Analysis</a></li>
            </ul>
        </li>

        <!-- 9. TOOLS -->
        <li class="has-submenu <?= (strpos($current_page, '/tools/') !== false || strpos($current_page, 'check_tables.php') !== false) ? 'open' : ''; ?>">
            <a href="javascript:void(0);" class="submenu-toggle">
                <i class="fa-solid fa-screwdriver-wrench"></i>
                <span>Tools & Utilities</span>
                <i class="fa-solid fa-chevron-right arrow-icon"></i>
            </a>
            <ul class="submenu">
                <li><a href="/vortex_wms/modules/tools/barcode_generator.php" class="<?= (strpos($current_page, 'barcode_generator') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-barcode me-2 text-primary"></i>Barcode / QR</a></li>
                <li><a href="/vortex_wms/modules/tools/scanner.php" class="<?= (strpos($current_page, 'scanner.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-mobile-screen-button me-2 text-danger"></i>Mobile Scanner</a></li>
                <li><a href="/vortex_wms/modules/tools/universal_import.php" class="<?= (strpos($current_page, 'universal_import') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-file-import me-2 text-info"></i>Universal Import</a></li>
                <!-- NEW: Database Table Inspector -->
                <li><a href="/vortex_wms/modules/check_tables.php" class="<?= (strpos($current_page, 'check_tables.php') !== false) ? 'active' : ''; ?>"><i class="fa-solid fa-table-columns me-2 text-warning"></i>Database Studio</a></li>
            </ul>
        </li>

        <li class="menu-divider"></li>

        <!-- 10. SYSTEM SETTINGS -->
        <li>
            <a href="/vortex_wms/modules/system_settings/index.php" class="single-link <?= (strpos($current_page, 'system_settings') !== false) ? 'active' : ''; ?>">
                <i class="fa-solid fa-gear"></i>
                <span>System Settings</span>
            </a>
        </li>

        <!-- 11. LOGOUT -->
        <li>
            <a class="logout single-link" href="/vortex_wms/logout.php">
                <i class="fa-solid fa-right-from-bracket"></i>
                <span>Logout</span>
            </a>
        </li>
    </ul>
</div>

<script>
(function() {
    function setupSidebarToggles() {
        const sidebar = document.getElementById("vortexSidebar");
        if (!sidebar) return;

        const savedScroll = sessionStorage.getItem("vortexSidebarScroll");
        if (savedScroll !== null) {
            sidebar.scrollTop = parseInt(savedScroll, 10);
            setTimeout(() => {
                sidebar.scrollTop = parseInt(savedScroll, 10);
            }, 50);
        }

        sidebar.addEventListener("scroll", function () {
            sessionStorage.setItem("vortexSidebarScroll", sidebar.scrollTop);
        }, { passive: true });

        sidebar.querySelectorAll(".submenu-toggle").forEach(btn => {
            btn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                const parent = this.closest(".has-submenu");
                if (parent) {
                    parent.classList.toggle("open");
                    sessionStorage.setItem("vortexSidebarScroll", sidebar.scrollTop);
                }
            };
        });

        sidebar.querySelectorAll(".nested-btn").forEach(btn => {
            btn.onclick = function(e) {
                e.preventDefault();
                e.stopPropagation();
                const parentItem = this.closest(".nested-item");
                if (parentItem) {
                    parentItem.classList.toggle("open");
                    sessionStorage.setItem("vortexSidebarScroll", sidebar.scrollTop);
                }
            };
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener("DOMContentLoaded", setupSidebarToggles);
    } else {
        setupSidebarToggles();
    }
})();
</script>