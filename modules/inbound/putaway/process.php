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

$grn_id = intval($_GET['id']);

// 1. Fetch Active Warehouses List
$warehouses_res = mysqli_query($conn, "SELECT warehouse_name FROM warehouses WHERE status = 'Active' ORDER BY warehouse_name ASC");
$active_warehouses = [];
if ($warehouses_res && mysqli_num_rows($warehouses_res) > 0) {
    while ($w = mysqli_fetch_assoc($warehouses_res)) {
        $active_warehouses[] = $w['warehouse_name'];
    }
}
// Fallback if no warehouse active/exists
if (empty($active_warehouses)) {
    $active_warehouses = ['Main Warehouse', 'Warehouse B'];
}

// 2. Fetch Available / Empty Bins
// Query looks for bins in locations/bins table that are not occupied or marked Empty
$bins_res = mysqli_query($conn, "
    SELECT bin_code 
    FROM bin_locations 
    WHERE status = 'Active' AND occupied = 0 
    ORDER BY bin_code ASC
");
$available_bins = [];
if ($bins_res && mysqli_num_rows($bins_res) > 0) {
    while ($b = mysqli_fetch_assoc($bins_res)) {
        $available_bins[] = $b['bin_code'];
    }
}

// 3. Fetch Master GRN Details
$grnQuery = mysqli_query($conn, "
    SELECT 
        g.*,
        a.asn_number AS asn_code,
        COALESCE(a.supplier_name, s.supplier_name, 'N/A') AS supplier_name
    FROM grn g
    LEFT JOIN asn a ON g.asn_id = a.id
    LEFT JOIN suppliers s ON a.supplier_name = s.supplier_name
    WHERE g.id = '$grn_id'
");

if (!$grnQuery || mysqli_num_rows($grnQuery) == 0) {
    $_SESSION['error'] = "GRN Record Not Found.";
    header("Location: index.php");
    exit();
}

$grn = mysqli_fetch_assoc($grnQuery);

// 4. Handle Putaway Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_putaway'])) {
    if (isset($_POST['product_code']) && is_array($_POST['product_code'])) {
        
        $p_codes    = array_values($_POST['product_code']);
        $p_names    = isset($_POST['product_name']) ? array_values($_POST['product_name']) : [];
        $put_qtys   = isset($_POST['putaway_qty']) ? array_values($_POST['putaway_qty']) : [];
        $warehouses = isset($_POST['warehouse']) ? array_values($_POST['warehouse']) : [];
        $bins       = isset($_POST['bin_location']) ? array_values($_POST['bin_location']) : [];

        $conn->begin_transaction();

        try {
            for ($i = 0; $i < count($p_codes); $i++) {
                $p_code = mysqli_real_escape_string($conn, trim($p_codes[$i]));
                $p_name = mysqli_real_escape_string($conn, trim($p_names[$i] ?? ''));
                $p_qty  = intval($put_qtys[$i] ?? 0);
                $wh     = mysqli_real_escape_string($conn, trim($warehouses[$i] ?? 'Main Warehouse'));
                $bin    = mysqli_real_escape_string($conn, trim($bins[$i] ?? 'BIN-A1'));

                if (!empty($p_code) && $p_qty > 0) {
                    
                    // Product ID Fetch
                    $p_id = 0;
                    $prodCheck = mysqli_query($conn, "SELECT id FROM products WHERE product_code = '$p_code' LIMIT 1");
                    if ($prodCheck && mysqli_num_rows($prodCheck) > 0) {
                        $p_id = intval(mysqli_fetch_assoc($prodCheck)['id']);
                    }

                    // Check Inventory Record
                    $checkInv = mysqli_query($conn, "
                        SELECT id, available_qty FROM inventory 
                        WHERE product_code = '$p_code' AND warehouse = '$wh' AND bin_location = '$bin'
                        LIMIT 1
                    ");

                    if ($checkInv && mysqli_num_rows($checkInv) > 0) {
                        $inv = mysqli_fetch_assoc($checkInv);
                        $newQty = $inv['available_qty'] + $p_qty;
                        $status = ($newQty <= 10) ? "Low Stock" : "In Stock";

                        mysqli_query($conn, "
                            UPDATE inventory 
                            SET available_qty = '$newQty', status = '$status'
                            WHERE id = '" . $inv['id'] . "'
                        ");
                    } else {
                        $status = ($p_qty <= 10) ? "Low Stock" : "In Stock";
                        mysqli_query($conn, "
                            INSERT INTO inventory (product_id, product_code, product_name, warehouse, bin_location, available_qty, status)
                            VALUES ('$p_id', '$p_code', '$p_name', '$wh', '$bin', '$p_qty', '$status')
                        ");
                    }

                    // Mark Bin as Occupied (if bin_locations table exists)
                    mysqli_query($conn, "UPDATE bin_locations SET occupied = 1 WHERE bin_code = '$bin'");
                }
            }

            // Mark GRN Status as Completed
            mysqli_query($conn, "UPDATE grn SET status = 'Completed' WHERE id = '$grn_id'");

            $conn->commit();
            $_SESSION['success'] = "Putaway completed successfully! Stock stored in location.";
            header("Location: index.php");
            exit();

        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error'] = "Putaway Failed: " . $e->getMessage();
        }
    }
}

// 5. Fetch Items
$itemList = [];
$itemsQuery = mysqli_query($conn, "SELECT * FROM grn_items WHERE grn_id = '$grn_id'");

if ($itemsQuery && mysqli_num_rows($itemsQuery) > 0) {
    while ($row = mysqli_fetch_assoc($itemsQuery)) {
        $acc = intval($row['accepted_qty'] ?? $row['received_qty'] ?? 0);
        if ($acc > 0) {
            $itemList[] = [
                'product_code' => $row['product_code'],
                'product_name' => $row['product_name'],
                'accepted_qty' => $acc
            ];
        }
    }
} else {
    $asn_id = $grn['asn_id'] ?? 0;
    $asnItemsQuery = mysqli_query($conn, "SELECT * FROM asn_items WHERE asn_id = '$asn_id'");
    if ($asnItemsQuery && mysqli_num_rows($asnItemsQuery) > 0) {
        while ($row = mysqli_fetch_assoc($asnItemsQuery)) {
            $qty = intval($row['expected_qty'] ?? $row['quantity'] ?? 0);
            if ($qty > 0) {
                $itemList[] = [
                    'product_code' => $row['product_code'],
                    'product_name' => $row['product_name'],
                    'accepted_qty' => $qty
                ];
            }
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
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-truck-ramp-box text-success me-2"></i>Process Putaway</h2>
                <p class="text-muted mb-0">Assign active warehouse & available empty bin locations</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Datalist for Empty Bins Auto-Suggestion -->
        <datalist id="emptyBinList">
            <?php foreach ($available_bins as $bcode): ?>
                <option value="<?= htmlspecialchars($bcode); ?>"></option>
            <?php endforeach; ?>
        </datalist>

        <form method="POST">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-success text-white p-3 rounded-top-4">
                    <div class="d-flex justify-content-between align-items-center">
                        <h5 class="mb-0 fw-bold"><i class="fa-solid fa-boxes-stacked me-2"></i>GRN Ref: <?= htmlspecialchars($grn['grn_number'] ?? 'GRN-'.$grn_id); ?></h5>
                        <small class="opacity-75">Supplier: <strong><?= htmlspecialchars($grn['supplier_name']); ?></strong></small>
                    </div>
                </div>

                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle">
                            <thead class="table-dark">
                                <tr>
                                    <th width="60">#</th>
                                    <th>Product Details</th>
                                    <th width="130" class="text-center">Accepted Qty</th>
                                    <th width="130" class="text-center">Putaway Qty</th>
                                    <th width="240">Active Warehouse *</th>
                                    <th width="220">Empty Bin Location *</th>
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
                                            <td class="text-center fw-bold text-success fs-6 bg-light">
                                                <?= $item['accepted_qty']; ?>
                                            </td>
                                            <td>
                                                <input type="number" name="putaway_qty[]" class="form-control text-center font-monospace fw-bold text-primary" min="1" max="<?= $item['accepted_qty']; ?>" value="<?= $item['accepted_qty']; ?>" required>
                                            </td>
                                            <td>
                                                <!-- Dynamic Active Warehouses Dropdown -->
                                                <select name="warehouse[]" class="form-select fw-semibold" required>
                                                    <?php foreach ($active_warehouses as $whName): ?>
                                                        <option value="<?= htmlspecialchars($whName); ?>"><?= htmlspecialchars($whName); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                            </td>
                                            <td>
                                                <!-- Empty Bin Selector with Datalist Suggestions or Dropdown -->
                                                <?php if (!empty($available_bins)): ?>
                                                    <select name="bin_location[]" class="form-select font-monospace fw-bold" required>
                                                        <option value="">-- Select Empty Bin --</option>
                                                        <?php foreach ($available_bins as $bcode): ?>
                                                            <option value="<?= htmlspecialchars($bcode); ?>"><?= htmlspecialchars($bcode); ?> (Empty)</option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                <?php else: ?>
                                                    <input type="text" name="bin_location[]" class="form-control font-monospace fw-bold" value="BIN-A1" list="emptyBinList" placeholder="e.g. A-12-03" required>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-4 text-muted">No accepted items available for putaway.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>

                    <?php if (!empty($itemList)): ?>
                        <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-3">
                            <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                            <button type="submit" name="save_putaway" class="btn btn-success px-4 fw-bold">
                                <i class="fa-solid fa-boxes-packing me-1"></i> Confirm & Store Stock
                            </button>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </form>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>