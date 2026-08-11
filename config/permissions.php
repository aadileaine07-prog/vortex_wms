<?php
$module_permissions = [
    'dashboard'     => ['Super Admin', 'Warehouse Manager', 'Inventory Clerk', 'HR Manager'],
    'inbound'       => ['Super Admin', 'Warehouse Manager'],
    'outbound'      => ['Super Admin', 'Warehouse Manager', 'Inventory Clerk'],
    'inventory'     => ['Super Admin', 'Warehouse Manager', 'Inventory Clerk'],
    'masters'       => ['Super Admin', 'Warehouse Manager'],
    'hr'            => ['Super Admin', 'HR Manager'],
    'reports'       => ['Super Admin', 'Warehouse Manager', 'HR Manager'],
    'notifications' => ['Super Admin', 'Warehouse Manager', 'Inventory Clerk', 'HR Manager'],
    'settings'      => ['Super Admin']
];

function hasAccess($module) {
    global $module_permissions;
    $user_role = $_SESSION['role'] ?? 'Inventory Clerk';

    if ($user_role === 'Super Admin') {
        return true;
    }

    if (isset($module_permissions[$module])) {
        return in_array($user_role, $module_permissions[$module]);
    }

    return false;
}

function checkModuleAccess($module) {
    if (!hasAccess($module)) {
        $_SESSION['error'] = "Access Denied: You do not have permission to view this page.";
        header("Location: /vortex_wms/dashboard.php");
        exit();
    }
}
?>