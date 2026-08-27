<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Composer Autoload Check
$autoloadPath = $projectRoot . "/vendor/autoload.php";
if (!file_exists($autoloadPath)) {
    die("Composer autoload file not found at: {$autoloadPath}. Please run 'composer require dompdf/dompdf'.");
}
require_once $autoloadPath;

use Dompdf\Dompdf;
use Dompdf\Options;

/* ==========================================================================
   1. DYNAMIC DATABASE FETCHING WITH MASTER JOINS
   ========================================================================== */

$whTable = "warehouse";
$chkWh = @mysqli_query($conn, "SHOW TABLES LIKE 'warehouse'");
if (!$chkWh || mysqli_num_rows($chkWh) == 0) {
    $whTable = "warehouses";
}

$whNameCol = "warehouse_name";
$cChk = @mysqli_query($conn, "SHOW COLUMNS FROM `{$whTable}` LIKE 'warehouse_name'");
if (!$cChk || mysqli_num_rows($cChk) == 0) {
    $whNameCol = "name";
}

// Summary Metrics for Executive Header
$totalItems    = 0;
$totalStock    = 0;
$totalReserved = 0;
$lowStockCount = 0;
$outStockCount = 0;

$query = "
    SELECT 
        i.*,
        COALESCE(p.product_name, i.product_name, 'Stock Item') AS final_product_name,
        COALESCE(p.sku, p.product_code, i.product_code, 'SKU-00') AS final_sku,
        COALESCE(p.category, 'General') AS product_category,
        COALESCE(w.{$whNameCol}, i.warehouse, 'Main Facility') AS final_warehouse,
        COALESCE(i.batch_no, i.batch_number, '-') AS final_batch
    FROM inventory i
    LEFT JOIN products p ON p.id = i.product_id
    LEFT JOIN `{$whTable}` w ON w.id = i.warehouse_id
    ORDER BY final_product_name ASC
";

$result = @mysqli_query($conn, $query);
$rows = [];

if ($result) {
    while ($r = mysqli_fetch_assoc($result)) {
        $rows[] = $r;
        $totalItems++;
        $avail = (int)($r['available_qty'] ?? 0);
        $resv  = (int)($r['reserved_qty'] ?? 0);
        
        $totalStock    += $avail;
        $totalReserved += $resv;

        if ($avail === 0) $outStockCount++;
        elseif ($avail <= 10) $lowStockCount++;
    }
}

/* ==========================================================================
   2. DOMPDF STYLING & TEMPLATE GENERATION
   ========================================================================== */

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('isHtml5ParserEnabled', true);
$options->set('defaultFont', 'DejaVu Sans');

$dompdf = new Dompdf($options);

$html = '
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>
    @page {
        margin: 25px 30px;
    }
    body {
        font-family: "DejaVu Sans", sans-serif;
        font-size: 10px;
        color: #1e293b;
        background-color: #ffffff;
        margin: 0;
        padding: 0;
    }
    /* Header Section */
    .header-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 20px;
        border-bottom: 2px solid #2563eb;
        padding-bottom: 10px;
    }
    .brand-title {
        font-size: 20px;
        font-weight: bold;
        color: #0f172a;
        margin: 0;
        letter-spacing: 0.5px;
    }
    .brand-subtitle {
        font-size: 8px;
        color: #64748b;
        font-weight: bold;
        letter-spacing: 1px;
    }
    .report-title {
        font-size: 14px;
        font-weight: bold;
        color: #2563eb;
        text-align: right;
        margin: 0;
    }
    .meta-text {
        font-size: 9px;
        color: #64748b;
        text-align: right;
        margin: 2px 0 0 0;
    }

    /* Executive KPI Bar */
    .kpi-table {
        width: 100%;
        border-collapse: collapse;
        margin-bottom: 18px;
    }
    .kpi-box {
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 6px;
        padding: 8px 12px;
        text-align: center;
        width: 20%;
    }
    .kpi-label {
        font-size: 7.5px;
        font-weight: bold;
        text-transform: uppercase;
        color: #64748b;
        display: block;
    }
    .kpi-value {
        font-size: 13px;
        font-weight: bold;
        color: #0f172a;
        margin-top: 3px;
    }

    /* Main Inventory Table */
    .data-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 5px;
    }
    .data-table thead th {
        background-color: #0f172a;
        color: #ffffff;
        font-size: 8.5px;
        font-weight: bold;
        text-transform: uppercase;
        padding: 8px 6px;
        border: 1px solid #0f172a;
        text-align: left;
    }
    .data-table tbody td {
        border: 1px solid #e2e8f0;
        padding: 6px 6px;
        font-size: 9px;
        vertical-align: middle;
    }
    .data-table tbody tr:nth-child(even) {
        background-color: #f8fafc;
    }
    .text-center { text-align: center !important; }
    .text-right { text-align: right !important; }
    .font-mono { font-family: monospace; }
    .sku-code { font-family: monospace; font-weight: bold; color: #2563eb; }
    
    /* Badges */
    .badge {
        padding: 2px 6px;
        border-radius: 4px;
        font-size: 7.5px;
        font-weight: bold;
        display: inline-block;
    }
    .badge-in-stock  { background-color: #dcfce7; color: #15803d; }
    .badge-low-stock { background-color: #fef3c7; color: #b45309; }
    .badge-out-stock { background-color: #fee2e2; color: #b91c1c; }

    /* Footer */
    .footer-table {
        width: 100%;
        margin-top: 20px;
        border-top: 1px solid #e2e8f0;
        padding-top: 8px;
        font-size: 8px;
        color: #94a3b8;
    }
</style>
</head>
<body>

<!-- Header -->
<table class="header-table">
    <tr>
        <td style="width: 50%;">
            <div class="brand-title">VORTEX WMS</div>
            <div class="brand-subtitle">ENTERPRISE WAREHOUSE SYSTEM</div>
        </td>
        <td style="width: 50%;" class="text-right">
            <div class="report-title">OFFICIAL INVENTORY VALUATION & AUDIT REPORT</div>
            <div class="meta-text">Generated: <strong>' . date("d M Y, h:i A") . '</strong></div>
            <div class="meta-text">Authorized User: Employee ID #<strong>' . htmlspecialchars($_SESSION['employee_id']) . '</strong></div>
        </td>
    </tr>
</table>

<!-- KPI Summary Tiles -->
<table class="kpi-table">
    <tr>
        <td class="kpi-box">
            <span class="kpi-label">Total Catalog Items</span>
            <div class="kpi-value">' . number_format($totalItems) . '</div>
        </td>
        <td style="width: 2%;"></td>
        <td class="kpi-box" style="border-left: 3px solid #2563eb;">
            <span class="kpi-label">Available Stock Units</span>
            <div class="kpi-value" style="color: #2563eb;">' . number_format($totalStock) . '</div>
        </td>
        <td style="width: 2%;"></td>
        <td class="kpi-box" style="border-left: 3px solid #f59e0b;">
            <span class="kpi-label">Reserved (Picking)</span>
            <div class="kpi-value" style="color: #d97706;">' . number_format($totalReserved) . '</div>
        </td>
        <td style="width: 2%;"></td>
        <td class="kpi-box" style="border-left: 3px solid #ef4444;">
            <span class="kpi-label">Critical / Stockouts</span>
            <div class="kpi-value" style="color: #dc2626;">' . ($lowStockCount + $outStockCount) . '</div>
        </td>
    </tr>
</table>

<!-- Main Table -->
<table class="data-table">
    <thead>
        <tr>
            <th style="width: 14%;">SKU Code</th>
            <th style="width: 26%;">Product Name & Category</th>
            <th style="width: 16%;">Warehouse</th>
            <th style="width: 12%;">Coordinate</th>
            <th style="width: 10%;">Batch #</th>
            <th style="width: 7%;" class="text-center">Avail</th>
            <th style="width: 7%;" class="text-center">Resv</th>
            <th style="width: 8%;" class="text-center">Status</th>
        </tr>
    </thead>
    <tbody>';

if (!empty($rows)) {
    foreach ($rows as $row) {
        $avail = (int)$row['available_qty'];
        $resv  = (int)$row['reserved_qty'];

        if ($avail === 0) {
            $badge = '<span class="badge badge-out-stock">OUT</span>';
        } elseif ($avail <= 10) {
            $badge = '<span class="badge badge-low-stock">LOW</span>';
        } else {
            $badge = '<span class="badge badge-in-stock">IN STOCK</span>';
        }

        $html .= '<tr>
            <td><span class="sku-code">' . htmlspecialchars($row['final_sku']) . '</span></td>
            <td>
                <strong>' . htmlspecialchars($row['final_product_name']) . '</strong>
                <span style="color:#64748b; font-size: 7.5px; display:block;">Cat: ' . htmlspecialchars($row['product_category']) . '</span>
            </td>
            <td>' . htmlspecialchars($row['final_warehouse']) . '</td>
            <td><span class="font-mono" style="font-weight:bold; color:#0f172a;">' . htmlspecialchars($row['bin_location'] ?? 'L0-A1') . '</span></td>
            <td><span class="font-mono">' . htmlspecialchars($row['final_batch']) . '</span></td>
            <td class="text-center"><strong>' . number_format($avail) . '</strong></td>
            <td class="text-center" style="color:#64748b;">' . number_format($resv) . '</td>
            <td class="text-center">' . $badge . '</td>
        </tr>';
    }
} else {
    $html .= '<tr><td colspan="8" class="text-center" style="padding: 20px; color: #94a3b8;">No inventory stock records found in the database.</td></tr>';
}

$html .= '
    </tbody>
</table>

<!-- Footer -->
<table class="footer-table">
    <tr>
        <td style="width: 50%;">VORTEX WMS &bull; Live Automated System Audit Report</td>
        <td style="width: 50%;" class="text-right">Confidential &bull; Page 1 of 1</td>
    </tr>
</table>

</body>
</html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();

$fileName = 'Vortex_Inventory_Report_' . date('Y-m-d_His') . '.pdf';
$dompdf->stream($fileName, ["Attachment" => false]);
exit();