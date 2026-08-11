<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$error = '';

// Fetch all products for items dropdown
$productsList = [];
$pResult = mysqli_query($conn, "SELECT id, product_code, product_name, uom FROM products ORDER BY product_name");
while ($row = mysqli_fetch_assoc($pResult)) {
    $productsList[] = $row;
}

// Auto-calculate next ID for display preview
$preview_result = mysqli_query($conn, "SELECT id FROM asn ORDER BY id DESC LIMIT 1");
if ($preview_result && mysqli_num_rows($preview_result) > 0) {
    $last = mysqli_fetch_assoc($preview_result);
    $next_id = intval($last['id']) + 1;
} else {
    $next_id = 1;
}

$asn_number_preview     = "ASN" . str_pad($next_id, 6, "0", STR_PAD_LEFT);
$invoice_number_preview = "INV" . str_pad($next_id, 6, "0", STR_PAD_LEFT);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    
    $conn->begin_transaction();

    try {
        // Generate ASN and Invoice Number on submit
        $result = mysqli_query($conn, "SELECT id FROM asn ORDER BY id DESC LIMIT 1");
        if ($result && mysqli_num_rows($result) > 0) {
            $last = mysqli_fetch_assoc($result);
            $next_id = intval($last['id']) + 1;
        } else {
            $next_id = 1;
        }

        $asn_number     = "ASN" . str_pad($next_id, 6, "0", STR_PAD_LEFT);
        $invoice_number = "INV" . str_pad($next_id, 6, "0", STR_PAD_LEFT);

        $supplier_name  = trim($_POST['supplier_name'] ?? '');
        $invoice_date   = !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : NULL;
        $expected_date  = !empty($_POST['expected_date']) ? $_POST['expected_date'] : NULL;
        $vehicle_number = trim($_POST['vehicle_number'] ?? '');
        $status         = $_POST['status'] ?? 'Pending';
        $created_by     = $_SESSION['employee_id'];

        // 1. Insert Header Record into `asn`
        $stmt = $conn->prepare("
            INSERT INTO asn 
            (asn_number, supplier_name, invoice_number, invoice_date, expected_date, vehicle_number, status, created_by) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("ssssssss", $asn_number, $supplier_name, $invoice_number, $invoice_date, $expected_date, $vehicle_number, $status, $created_by);
        $stmt->execute();
        $asn_id = $conn->insert_id;
        $stmt->close();

        // 2. Insert Multiple Products into `asn_items`
        if (!empty($_POST['product_id']) && is_array($_POST['product_id'])) {
            $itemStmt = $conn->prepare("
                INSERT INTO asn_items 
                (asn_id, product_id, product_code, product_name, quantity, uom, batch_no, expiry_date) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");

            foreach ($_POST['product_id'] as $key => $pid) {
                $product_id = intval($pid);
                $quantity   = intval($_POST['quantity'][$key] ?? 0);
                $batch_no   = trim($_POST['batch_no'][$key] ?? '');
                $exp_date   = !empty($_POST['expiry_date'][$key]) ? $_POST['expiry_date'][$key] : NULL;

                if ($product_id > 0 && $quantity > 0) {
                    // Get product details
                    $pStmt = $conn->prepare("SELECT product_code, product_name, uom FROM products WHERE id = ?");
                    $pStmt->bind_param("i", $product_id);
                    $pStmt->execute();
                    $pData = $pStmt->get_result()->fetch_assoc();
                    $pStmt->close();

                    if ($pData) {
                        $itemStmt->bind_param(
                            "iississs", 
                            $asn_id, 
                            $product_id, 
                            $pData['product_code'], 
                            $pData['product_name'], 
                            $quantity, 
                            $pData['uom'], 
                            $batch_no, 
                            $exp_date
                        );
                        $itemStmt->execute();
                    }
                }
            }
            $itemStmt->close();
        }

        $conn->commit();
        header("Location: view.php?id=" . $asn_id);
        exit();

    } catch (Exception $e) {
        $conn->rollback();
        $error = "Error saving ASN: " . $e->getMessage();
    }
}

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid">
        <?php if ($error): ?>
            <div class="alert alert-danger"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">
            <div class="card shadow mb-4">
                <div class="card-header bg-primary text-white">
                    <h3 class="mb-0">Create ASN</h3>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">ASN Number</label>
                            <input type="text" class="form-control" value="<?= $asn_number_preview; ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Invoice Number (Auto Generated)</label>
                            <input type="text" class="form-control" value="<?= $invoice_number_preview; ?>" readonly>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Supplier Name</label>
                            <input type="text" name="supplier_name" class="form-control" required>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Invoice Date</label>
                            <input type="date" name="invoice_date" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Expected Date</label>
                            <input type="date" name="expected_date" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Vehicle Number</label>
                            <input type="text" name="vehicle_number" class="form-control">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Pending">Pending</option>
                                <option value="Received">Received</option>
                                <option value="Completed">Completed</option>
                            </select>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow mb-4">
                <div class="card-header bg-secondary text-white d-flex justify-content-between align-items-center">
                    <h4 class="mb-0">ASN Items</h4>
                    <button type="button" class="btn btn-light btn-sm" onclick="addItemRow()">+ Add Item Row</button>
                </div>
                <div class="card-body">
                    <table class="table table-bordered" id="itemsTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Product <span class="text-danger">*</span></th>
                                <th width="150">Quantity <span class="text-danger">*</span></th>
                                <th width="180">Batch No</th>
                                <th width="180">Expiry Date</th>
                                <th width="80">Action</th>
                            </tr>
                        </thead>
                        <tbody id="itemsTableBody">
                            <tr>
                                <td>
                                    <select name="product_id[]" class="form-select" required>
                                        <option value="">Select Product</option>
                                        <?php foreach ($productsList as $p): ?>
                                            <option value="<?= $p['id']; ?>">
                                                <?= htmlspecialchars($p['product_code']) . " - " . htmlspecialchars($p['product_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="quantity[]" class="form-control" min="1" required>
                                </td>
                                <td>
                                    <input type="text" name="batch_no[]" class="form-control">
                                </td>
                                <td>
                                    <input type="date" name="expiry_date[]" class="form-control">
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-danger btn-sm" onclick="removeRow(this)">X</button>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="mb-4">
                <button type="submit" name="save" class="btn btn-success btn-lg">Save Entire ASN</button>
                <a href="index.php" class="btn btn-secondary btn-lg">Cancel</a>
            </div>
        </form>
    </div>
</div>

<script>
function addItemRow() {
    const tableBody = document.getElementById('itemsTableBody');
    const firstRow = tableBody.children[0];
    const newRow = firstRow.cloneNode(true);

    // Reset values in cloned inputs
    const inputs = newRow.querySelectorAll('input, select');
    inputs.forEach(input => {
        if (input.tagName === 'SELECT') {
            input.selectedIndex = 0;
        } else {
            input.value = '';
        }
    });

    tableBody.appendChild(newRow);
}

function removeRow(button) {
    const tableBody = document.getElementById('itemsTableBody');
    if (tableBody.children.length > 1) {
        button.closest('tr').remove();
    } else {
        alert("At least one product row is required.");
    }
}
</script>

<?php include "../../../includes/footer.php"; ?>