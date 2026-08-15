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
    $_SESSION['error'] = "GRN / QC ID Missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// 1. Fetch GRN & Supplier Details
$grnQuery = mysqli_query($conn, "
    SELECT 
        g.*, 
        a.asn_number AS asn_code,
        COALESCE(a.supplier_name, s.supplier_name, 'N/A') AS supplier_name
    FROM grn g
    LEFT JOIN asn a ON g.asn_id = a.id
    LEFT JOIN suppliers s ON a.supplier_name = s.supplier_name
    WHERE g.id = '$id'
");

if (!$grnQuery || mysqli_num_rows($grnQuery) == 0) {
    $_SESSION['error'] = "GRN Record Not Found.";
    header("Location: index.php");
    exit();
}

$grn = mysqli_fetch_assoc($grnQuery);

// 2. Handle QC Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_qc'])) {
    if (isset($_POST['product_code']) && is_array($_POST['product_code'])) {
        
        // Array Re-indexing (Fixes First Entry Skipping Bug)
        $p_codes     = array_values($_POST['product_code']);
        $p_names     = isset($_POST['product_name']) ? array_values($_POST['product_name']) : [];
        $r_qtys      = isset($_POST['received_qty']) ? array_values($_POST['received_qty']) : [];
        $a_qtys      = isset($_POST['accepted_qty']) ? array_values($_POST['accepted_qty']) : [];
        $rej_qtys    = isset($_POST['rejected_qty']) ? array_values($_POST['rejected_qty']) : [];
        $remarks_arr = isset($_POST['remarks']) ? array_values($_POST['remarks']) : [];

        $conn->begin_transaction();

        try {
            // Check if quality_checks table exists, or update grn_items
            $checkQCTable = mysqli_query($conn, "SHOW TABLES LIKE 'quality_checks'");
            
            if ($checkQCTable && mysqli_num_rows($checkQCTable) > 0) {
                mysqli_query($conn, "DELETE FROM quality_checks WHERE grn_id = '$id'");
            }

            for ($i = 0; $i < count($p_codes); $i++) {
                $p_code = mysqli_real_escape_string($conn, trim($p_codes[$i]));
                $p_name = mysqli_real_escape_string($conn, trim($p_names[$i] ?? ''));
                $r_qty  = intval($r_qtys[$i] ?? 0);
                $a_qty  = intval($a_qtys[$i] ?? 0);
                $rej_qty= intval($rej_qtys[$i] ?? 0);
                $rem    = mysqli_real_escape_string($conn, trim($remarks_arr[$i] ?? ''));

                if (!empty($p_code)) {
                    // Update GRN Items
                    mysqli_query($conn, "
                        UPDATE grn_items 
                        SET accepted_qty = '$a_qty', damaged_qty = '$rej_qty', remarks = '$rem'
                        WHERE grn_id = '$id' AND product_code = '$p_code'
                    ");

                    // Insert QC Log if table exists
                    if ($checkQCTable && mysqli_num_rows($checkQCTable) > 0) {
                        mysqli_query($conn, "
                            INSERT INTO quality_checks (grn_id, product_code, product_name, received_qty, accepted_qty, rejected_qty, remarks, qc_status)
                            VALUES ('$id', '$p_code', '$p_name', '$r_qty', '$a_qty', '$rej_qty', '$rem', 'Passed')
                        ");
                    }
                }
            }

            // Update Master GRN Status to Passed / QC Completed
            mysqli_query($conn, "UPDATE grn SET status = 'Passed' WHERE id = '$id'");

            $conn->commit();
            $_SESSION['success'] = "Quality Inspection for GRN <strong>" . ($grn['grn_number'] ?? 'GRN-'.$id) . "</strong> saved successfully!";
            header("Location: index.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "QC Save Failed: " . $e->getMessage();
        }
    }
}

// 3. Fetch Items: Try grn_items first, then fallback to asn_items
$itemList = [];
$itemsQuery = mysqli_query($conn, "SELECT * FROM grn_items WHERE grn_id = '$id'");

if ($itemsQuery && mysqli_num_rows($itemsQuery) > 0) {
    while ($row = mysqli_fetch_assoc($itemsQuery)) {
        $rec = intval($row['received_qty'] ?? $row['ordered_qty'] ?? 0);
        $acc = intval($row['accepted_qty'] ?? $rec);
        $dmg = intval($row['damaged_qty'] ?? ($rec - $acc));

        $itemList[] = [
            'product_code' => $row['product_code'],
            'product_name' => $row['product_name'],
            'received_qty' => $rec,
            'accepted_qty' => $acc,
            'rejected_qty' => $dmg,
            'remarks'      => $row['remarks'] ?? 'Passed'
        ];
    }
} else {
    // Fallback: Fetch from linked ASN items
    $asn_id = $grn['asn_id'] ?? 0;
    $asnItemsQuery = mysqli_query($conn, "SELECT * FROM asn_items WHERE asn_id = '$asn_id'");
    
    if ($asnItemsQuery && mysqli_num_rows($asnItemsQuery) > 0) {
        while ($row = mysqli_fetch_assoc($asnItemsQuery)) {
            $qty = intval($row['expected_qty'] ?? $row['quantity'] ?? 0);
            $itemList[] = [
                'product_code' => $row['product_code'],
                'product_name' => $row['product_name'],
                'received_qty' => $qty,
                'accepted_qty' => $qty,
                'rejected_qty' => 0,
                'remarks'      => 'Passed'
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
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-clipboard-check text-info me-2"></i>Quality Inspection</h2>
                <p class="text-muted mb-0">Verify item condition, accepted quantity, and defect logs</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form method="POST">
            <!-- Inspection Items Table -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-info text-white p-3 rounded-top-4">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-boxes-stacked me-2"></i>Inspection Line Items</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="qcTable">
                            <thead class="table-dark">
                                <tr>
                                    <th width="60">ID</th>
                                    <th>Product</th>
                                    <th width="140" class="text-center">Received Qty</th>
                                    <th width="140" class="text-center">Accepted Qty</th>
                                    <th width="140" class="text-center">Rejected Qty</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($itemList)): $idx = 1; ?>
                                    <?php foreach ($itemList as $item): ?>
                                        <tr>
                                            <td><strong>#<?= $idx++; ?></strong></td>
                                            <td>
                                                <strong><?= htmlspecialchars($item['product_name']); ?></strong>
                                                <br><code class="text-muted fs-6"><?= htmlspecialchars($item['product_code']); ?></code>
                                                <input type="hidden" name="product_code[]" value="<?= htmlspecialchars($item['product_code']); ?>">
                                                <input type="hidden" name="product_name[]" value="<?= htmlspecialchars($item['product_name']); ?>">
                                            </td>
                                            <td>
                                                <input type="number" name="received_qty[]" class="form-control bg-light rec-qty text-center font-monospace fw-bold" value="<?= $item['received_qty']; ?>" readonly>
                                            </td>
                                            <td>
                                                <input type="number" name="accepted_qty[]" class="form-control acc-qty text-center font-monospace fw-bold text-success" min="0" max="<?= $item['received_qty']; ?>" value="<?= $item['accepted_qty']; ?>" onchange="calcQC(this)" onkeyup="calcQC(this)" required>
                                            </td>
                                            <td>
                                                <input type="number" name="rejected_qty[]" class="form-control rej-qty text-center font-monospace fw-bold text-danger bg-light" value="<?= $item['rejected_qty']; ?>" readonly>
                                            </td>
                                            <td>
                                                <input type="text" name="remarks[]" class="form-control" placeholder="Inspection remarks..." value="<?= htmlspecialchars($item['remarks']); ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No items found for quality inspection.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($itemList)): ?>
                        <div class="d-flex justify-content-end gap-2 mt-3">
                            <a href="index.php" class="btn btn-outline-secondary px-4">Cancel</a>
                            <button type="submit" name="save_qc" class="btn btn-success px-4 fw-bold"><i class="fa-solid fa-floppy-disk me-1"></i> Save QC</button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <!-- QC Summary Card -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-primary text-white p-3 rounded-top-4">
                <h5 class="mb-0 fw-bold"><i class="fa-solid fa-circle-info me-2"></i>QC Summary</h5>
            </div>
            <div class="card-body p-4">
                <table class="table table-bordered mb-0 align-middle">
                    <tbody>
                        <tr>
                            <th width="200" class="bg-light">GRN No</th>
                            <td><strong class="font-monospace text-primary fs-6"><?= htmlspecialchars($grn['grn_number'] ?? 'GRN-'.$id); ?></strong></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Supplier</th>
                            <td><strong><?= htmlspecialchars($grn['supplier_name']); ?></strong></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Received Date</th>
                            <td><?= !empty($grn['received_date']) ? date("Y-m-d", strtotime($grn['received_date'])) : date('Y-m-d'); ?></td>
                        </tr>
                        <tr>
                            <th class="bg-light">Status</th>
                            <td><span class="badge bg-warning text-dark px-3 py-1"><?= htmlspecialchars($grn['status'] ?? 'Pending'); ?></span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<script>
function calcQC(input) {
    let row = input.closest("tr");
    let rec = parseInt(row.querySelector(".rec-qty").value) || 0;
    let acc = parseInt(row.querySelector(".acc-qty").value) || 0;

    if (acc > rec) {
        acc = rec;
        row.querySelector(".acc-qty").value = rec;
    }

    // Rejected Qty = Received Qty - Accepted Qty
    let rej = Math.max(0, rec - acc);
    row.querySelector(".rej-qty").value = rej;
}
</script>