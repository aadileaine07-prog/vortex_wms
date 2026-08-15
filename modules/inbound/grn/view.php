<?php
session_start();

// Dynamic Project Root Path
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "GRN ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// Fetch Master GRN with Linked ASN & Supplier Details
$grnQuery = mysqli_query($conn, "
    SELECT 
        g.*, 
        a.asn_number AS asn_code,
        COALESCE(a.supplier_name, 'N/A') AS supplier_name
    FROM grn g
    LEFT JOIN asn a ON g.asn_id = a.id
    WHERE g.id = '$id'
");

if (!$grnQuery || mysqli_num_rows($grnQuery) == 0) {
    $_SESSION['error'] = "GRN Record Not Found.";
    header("Location: index.php");
    exit();
}

$grn = mysqli_fetch_assoc($grnQuery);

// Fetch GRN Items
$itemsQuery = mysqli_query($conn, "SELECT * FROM grn_items WHERE grn_id = '$id'");

// Fallback to ASN items if GRN items table is empty
$itemList = [];
if ($itemsQuery && mysqli_num_rows($itemsQuery) > 0) {
    while ($row = mysqli_fetch_assoc($itemsQuery)) {
        $itemList[] = $row;
    }
} else {
    $asn_db_id = $grn['asn_id'] ?? 0;
    $asnItemsQuery = mysqli_query($conn, "SELECT * FROM asn_items WHERE asn_id = '$asn_db_id'");
    if ($asnItemsQuery && mysqli_num_rows($asnItemsQuery) > 0) {
        while ($row = mysqli_fetch_assoc($asnItemsQuery)) {
            $exp = intval($row['expected_qty'] ?? $row['quantity'] ?? 0);
            $itemList[] = [
                'product_code' => $row['product_code'],
                'product_name' => $row['product_name'],
                'ordered_qty'  => $exp,
                'received_qty' => $exp,
                'damaged_qty'  => 0,
                'accepted_qty' => $exp,
                'remarks'      => ''
            ];
        }
    }
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-receipt text-success me-2"></i>GRN Details</h2>
                <p class="text-muted mb-0">Goods Receipt Note Summary</p>
            </div>
            <div>
                <a href="items.php?grn_id=<?= $id; ?>" class="btn btn-warning px-3 me-2 fw-bold text-dark"><i class="fa-solid fa-pen-to-square me-1"></i> Edit GRN Items</a>
                <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <!-- Master Details Card -->
        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-body p-4">
                <table class="table table-bordered mb-0 align-middle">
                    <tbody>
                        <tr>
                            <th width="200" class="bg-light fw-bold">GRN No</th>
                            <td><strong class="font-monospace text-primary fs-6"><?= htmlspecialchars($grn['grn_number'] ?? 'GRN-'.$id); ?></strong></td>
                        </tr>
                        <tr>
                            <th class="bg-light fw-bold">Supplier</th>
                            <td><strong><?= htmlspecialchars($grn['supplier_name'] ?? 'N/A'); ?></strong></td>
                        </tr>
                        <tr>
                            <th class="bg-light fw-bold">ASN No</th>
                            <td><span class="badge bg-secondary font-monospace fs-6"><?= htmlspecialchars($grn['asn_code'] ?? $grn['asn_id'] ?? 'N/A'); ?></span></td>
                        </tr>
                        <tr>
                            <th class="bg-light fw-bold">Received Date</th>
                            <td><?= !empty($grn['received_date']) ? date("d M Y", strtotime($grn['received_date'])) : '-'; ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light fw-bold">Status</th>
                            <td>
                                <?php 
                                    $st = $grn['status'] ?? 'Pending';
                                    if ($st == 'Received' || $st == 'Completed') echo '<span class="badge bg-success px-3 py-1">Received</span>';
                                    else echo '<span class="badge bg-warning text-dark px-3 py-1">Pending</span>';
                                ?>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- GRN Items Card -->
        <h4 class="fw-bold text-dark mb-3">GRN Items</h4>
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-striped table-bordered align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">#</th>
                                <th>Code</th>
                                <th>Product Name</th>
                                <th width="110" class="text-center">Ordered</th>
                                <th width="110" class="text-center">Received</th>
                                <th width="110" class="text-center">Damaged</th>
                                <th width="110" class="text-center">Accepted</th>
                                <th>Remarks</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (!empty($itemList)): $i = 1; ?>
                                <?php foreach ($itemList as $item): ?>
                                    <tr>
                                        <td><?= $i++; ?></td>
                                        <td><code class="fs-6"><?= htmlspecialchars($item['product_code'] ?? ''); ?></code></td>
                                        <td><strong><?= htmlspecialchars($item['product_name'] ?? ''); ?></strong></td>
                                        <td class="text-center"><?= intval($item['ordered_qty'] ?? 0); ?></td>
                                        <td class="text-center fw-bold text-primary"><?= intval($item['received_qty'] ?? 0); ?></td>
                                        <td class="text-center fw-bold text-danger"><?= intval($item['damaged_qty'] ?? 0); ?></td>
                                        <td class="text-center fw-bold text-success"><?= intval($item['accepted_qty'] ?? 0); ?></td>
                                        <td><?= htmlspecialchars($item['remarks'] ?? '-'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="8" class="text-center text-muted py-4">No Items Found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>