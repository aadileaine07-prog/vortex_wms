<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";
require_once "../../../vendor/autoload.php";

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);

$dompdf = new Dompdf($options);

$result = mysqli_query($conn, "
SELECT
product_code,
product_name,
warehouse,
bin_location,
available_qty,
reserved_qty,
status
FROM inventory
ORDER BY product_name ASC
");

$html = '<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body{font-family: DejaVu Sans, sans-serif;font-size:12px}
h1{text-align:center;margin-bottom:5px}
p{text-align:center;margin-top:0}
table{width:100%;border-collapse:collapse;margin-top:20px}
table th{background:#222;color:#fff}
table th,table td{border:1px solid #999;padding:8px;text-align:center}
</style>
</head>
<body>
<h1>VORTEX WMS</h1>
<p>Inventory Report</p>
<p>Generated : ' . date("d-m-Y H:i") . '</p>
<table>
<tr>
<th>Product Code</th>
<th>Product Name</th>
<th>Warehouse</th>
<th>Bin</th>
<th>Available</th>
<th>Reserved</th>
<th>Status</th>
</tr>';

while ($row = mysqli_fetch_assoc($result)) {
    $html .= '<tr>' .
        '<td>' . htmlspecialchars($row['product_code']) . '</td>' .
        '<td>' . htmlspecialchars($row['product_name']) . '</td>' .
        '<td>' . htmlspecialchars($row['warehouse']) . '</td>' .
        '<td>' . htmlspecialchars($row['bin_location']) . '</td>' .
        '<td>' . htmlspecialchars($row['available_qty']) . '</td>' .
        '<td>' . htmlspecialchars($row['reserved_qty']) . '</td>' .
        '<td>' . htmlspecialchars($row['status']) . '</td>' .
        '</tr>';
}

$html .= '</table></body></html>';

$dompdf->loadHtml($html);
$dompdf->setPaper('A4', 'landscape');
$dompdf->render();
$dompdf->stream("Inventory_Report.pdf", ["Attachment" => false]);

