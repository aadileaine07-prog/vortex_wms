<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || !filter_var($_GET['id'], FILTER_VALIDATE_INT)) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$stmt = $conn->prepare("SELECT * FROM asn WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$asn = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$asn) {
    die("ASN Record Not Found");
}

$stmt_items = $conn->prepare("SELECT * FROM asn_items WHERE asn_id = ?");
$stmt_items->bind_param("i", $id);
$stmt_items->execute();
$items = $stmt_items->get_result();

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid">
        <div class="card shadow">
            <div class="card-header bg-primary text-white">
                <h3 class="mb-0">ASN Details</h3>
            </div>
            <div class="card-body">
                <table class="table table-bordered">
                    <tr><th width="250">ASN Number</th><td><?= htmlspecialchars($asn['asn_number']); ?></td></tr>
                    <tr><th>Supplier</th><td><?= htmlspecialchars($asn['supplier_name']); ?></td></tr>
                    <tr><th>Invoice Number</th><td><?= htmlspecialchars($asn['invoice_number']); ?></td></tr>
                    <tr><th>Invoice Date</th><td><?= htmlspecialchars($asn['invoice_date'] ?? ''); ?></td></tr>
                    <tr><th>Expected Date</th><td><?= htmlspecialchars($asn['expected_date'] ?? ''); ?></td></tr>
                    <tr><th>Vehicle Number</th><td><?= htmlspecialchars($asn['vehicle_number']); ?></td></tr>
                    <tr><th>Status</th><td><?= htmlspecialchars($asn['status']); ?></td></tr>
                </table>

                <h4 class="mt-4">Products</h4>
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>#</th>
                            <th>Product Code</th>
                            <th>Product Name</th>
                            <th>Quantity</th>
                            <th>UOM</th>
                            <th>Batch</th>
                            <th>Expiry</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php $i = 1; while ($row = $items->fetch_assoc()): ?>
                        <tr>
                            <td><?= $i++; ?></td>
                            <td><?= htmlspecialchars($row['product_code']); ?></td>
                            <td><?= htmlspecialchars($row['product_name']); ?></td>
                            <td><?= htmlspecialchars($row['quantity']); ?></td>
                            <td><?= htmlspecialchars($row['uom']); ?></td>
                            <td><?= htmlspecialchars($row['batch_no']); ?></td>
                            <td><?= htmlspecialchars($row['expiry_date'] ?? ''); ?></td>
                        </tr>
                        <?php endwhile; ?>
                    </tbody>
                </table>

                <a href="index.php" class="btn btn-secondary">Back</a>
            </div>
        </div>
    </div>
</div>

<?php 
$stmt_items->close();
include "../../../includes/footer.php"; 
?>