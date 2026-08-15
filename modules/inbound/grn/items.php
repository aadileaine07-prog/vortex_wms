<?php
session_start();

// Dynamic Project Root Path
$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['grn_id']) || empty($_GET['grn_id'])) {
    $_SESSION['error'] = "GRN ID Missing.";
    header("Location: index.php");
    exit();
}

$grn_id = intval($_GET['grn_id']);

// 1. Fetch Master GRN Details & Linked ASN
$grnQuery = mysqli_query($conn, "
    SELECT g.*, a.asn_number, a.id as asn_db_id 
    FROM grn g 
    LEFT JOIN asn a ON g.asn_id = a.id 
    WHERE g.id = '$grn_id'
");

if (!$grnQuery || mysqli_num_rows($grnQuery) == 0) {
    $_SESSION['error'] = "GRN Record Not Found.";
    header("Location: index.php");
    exit();
}

$grn = mysqli_fetch_assoc($grnQuery);
$asn_db_id  = $grn['asn_db_id'] ?? $grn['asn_id'] ?? 0;
$asn_number = mysqli_real_escape_string($conn, $grn['asn_number'] ?? '');

// 2. Handle Form Submission (Save / Update GRN Items)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_grn_items'])) {
    if (isset($_POST['product_code']) && is_array($_POST['product_code'])) {
        
        // Re-index all POST arrays to strictly start from Index 0
        $p_codes     = array_values($_POST['product_code']);
        $p_names     = isset($_POST['product_name']) ? array_values($_POST['product_name']) : [];
        $o_qtys      = isset($_POST['ordered_qty']) ? array_values($_POST['ordered_qty']) : [];
        $r_qtys      = isset($_POST['received_qty']) ? array_values($_POST['received_qty']) : [];
        $d_qtys      = isset($_POST['damaged_qty']) ? array_values($_POST['damaged_qty']) : [];
        $a_qtys      = isset($_POST['accepted_qty']) ? array_values($_POST['accepted_qty']) : [];
        $remarks_arr = isset($_POST['remarks']) ? array_values($_POST['remarks']) : [];

        $conn->begin_transaction();

        try {
            // Check grn_items table existence
            $checkTable = mysqli_query($conn, "SHOW TABLES LIKE 'grn_items'");
            if ($checkTable && mysqli_num_rows($checkTable) > 0) {
                
                // Clear existing GRN items if re-saving
                mysqli_query($conn, "DELETE FROM grn_items WHERE grn_id = '$grn_id'");

                for ($i = 0; $i < count($p_codes); $i++) {
                    $p_code = mysqli_real_escape_string($conn, trim($p_codes[$i]));
                    $p_name = mysqli_real_escape_string($conn, trim($p_names[$i] ?? ''));
                    $o_qty  = intval($o_qtys[$i] ?? 0);
                    $r_qty  = intval($r_qtys[$i] ?? 0);
                    $d_qty  = intval($d_qtys[$i] ?? 0);
                    $a_qty  = intval($a_qtys[$i] ?? 0);
                    $rem    = mysqli_real_escape_string($conn, trim($remarks_arr[$i] ?? ''));

                    if (!empty($p_code)) {
                        $sqlItem = "INSERT INTO grn_items 
                                    (grn_id, product_code, product_name, ordered_qty, received_qty, damaged_qty, accepted_qty, remarks)
                                    VALUES ('$grn_id', '$p_code', '$p_name', '$o_qty', '$r_qty', '$d_qty', '$a_qty', '$rem')";
                        
                        if (!mysqli_query($conn, $sqlItem)) {
                            throw new Exception("Error saving item {$p_code}: " . mysqli_error($conn));
                        }
                    }
                }
            }

            // Update GRN status
            mysqli_query($conn, "UPDATE grn SET status = 'Received' WHERE id = '$grn_id'");

            $conn->commit();
            $_SESSION['success'] = "GRN Items updated successfully!";
            header("Location: index.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Failed to save GRN items: " . $e->getMessage();
        }
    }
}

// 3. Fetch Existing GRN Items OR Auto-Load from Linked ASN
$itemList = [];
$itemsQuery = mysqli_query($conn, "SELECT * FROM grn_items WHERE grn_id = '$grn_id'");

if ($itemsQuery && mysqli_num_rows($itemsQuery) > 0) {
    while ($row = mysqli_fetch_assoc($itemsQuery)) {
        $itemList[] = [
            'product_code' => $row['product_code'],
            'product_name' => $row['product_name'],
            'ordered_qty'  => $row['ordered_qty'] ?? $row['quantity'] ?? 0,
            'received_qty' => $row['received_qty'] ?? 0,
            'damaged_qty'  => $row['damaged_qty'] ?? 0,
            'accepted_qty' => $row['accepted_qty'] ?? 0,
            'remarks'      => $row['remarks'] ?? ''
        ];
    }
} else {
    // Fallback: Fetch items from linked ASN table if grn_items is empty
    $asnItemsQuery = mysqli_query($conn, "
        SELECT ai.* 
        FROM asn_items ai 
        JOIN asn a ON ai.asn_id = a.id 
        WHERE a.asn_number = '$asn_number' OR a.id = '$asn_db_id'
    ");

    if ($asnItemsQuery && mysqli_num_rows($asnItemsQuery) > 0) {
        while ($row = mysqli_fetch_assoc($asnItemsQuery)) {
            $expQty = intval($row['expected_qty'] ?? $row['quantity'] ?? 0);
            $itemList[] = [
                'product_code' => $row['product_code'],
                'product_name' => $row['product_name'],
                'ordered_qty'  => $expQty,
                'received_qty' => $expQty,
                'damaged_qty'  => 0,
                'accepted_qty' => $expQty,
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

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-list-check text-success me-2"></i>GRN Items Verification</h2>
                <p class="text-muted mb-0">Record physical inbound quantities, damages, and accepted stock</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-4 mb-4">
            <div class="card-header bg-success text-white p-3 rounded-top-4">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-receipt me-2"></i>GRN Ref: <?= htmlspecialchars($grn['grn_number'] ?? 'GRN-'.$grn_id); ?></h5>
                        <small class="opacity-75">Linked ASN: <strong><?= htmlspecialchars($grn['asn_number'] ?? 'N/A'); ?></strong></small>
                    </div>
                    <span class="badge bg-light text-dark px-3 py-2 fs-6">Status: <?= htmlspecialchars($grn['status'] ?? 'Pending'); ?></span>
                </div>
            </div>

            <div class="card-body p-4">
                <form method="POST">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="grnItemsTable">
                            <thead class="table-dark">
                                <tr>
                                    <th width="160">Code</th>
                                    <th>Product Name</th>
                                    <th width="120">Ordered</th>
                                    <th width="130">Received</th>
                                    <th width="130">Damaged</th>
                                    <th width="130">Accepted</th>
                                    <th>Remarks</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($itemList)): ?>
                                    <?php foreach ($itemList as $item): ?>
                                        <tr>
                                            <td>
                                                <input type="text" name="product_code[]" class="form-control font-monospace bg-light" value="<?= htmlspecialchars($item['product_code']); ?>" readonly>
                                            </td>
                                            <td>
                                                <input type="text" name="product_name[]" class="form-control bg-light fw-bold" value="<?= htmlspecialchars($item['product_name']); ?>" readonly>
                                            </td>
                                            <td>
                                                <input type="number" name="ordered_qty[]" class="form-control bg-light ord-qty text-center" value="<?= $item['ordered_qty']; ?>" readonly>
                                            </td>
                                            <td>
                                                <input type="number" name="received_qty[]" class="form-control rec-qty fw-bold text-primary text-center" min="0" value="<?= $item['received_qty']; ?>" onchange="calcAccepted(this)" onkeyup="calcAccepted(this)" required>
                                            </td>
                                            <td>
                                                <input type="number" name="damaged_qty[]" class="form-control dmg-qty fw-bold text-danger text-center" min="0" value="<?= $item['damaged_qty']; ?>" onchange="calcAccepted(this)" onkeyup="calcAccepted(this)" required>
                                            </td>
                                            <td>
                                                <input type="number" name="accepted_qty[]" class="form-control acc-qty fw-bold text-success text-center bg-light" value="<?= $item['accepted_qty']; ?>" readonly>
                                            </td>
                                            <td>
                                                <input type="text" name="remarks[]" class="form-control" placeholder="Condition notes..." value="<?= htmlspecialchars($item['remarks']); ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="7" class="text-center py-5 text-muted">
                                            <i class="fa-solid fa-box-open fs-2 d-block mb-2 text-secondary"></i>
                                            No products found in the linked ASN (<strong><?= htmlspecialchars($asn_number); ?></strong>).
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($itemList)): ?>
                        <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-3">
                            <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                            <button type="submit" name="save_grn_items" class="btn btn-success px-4 fw-bold">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Items
                            </button>
                        </div>
                    <?php endif; ?>
                </form>
            </div>
        </div>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<script>
function calcAccepted(input) {
    let row = input.closest("tr");
    let rec = parseInt(row.querySelector(".rec-qty").value) || 0;
    let dmg = parseInt(row.querySelector(".dmg-qty").value) || 0;
    
    // Accepted = Received - Damaged
    let acc = Math.max(0, rec - dmg);
    row.querySelector(".acc-qty").value = acc;
}
</script>