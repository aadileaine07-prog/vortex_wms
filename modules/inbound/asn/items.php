<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['asn_id']) || !filter_var($_GET['asn_id'], FILTER_VALIDATE_INT)) {
    die("Invalid ASN ID");
}

$asn_id = intval($_GET['asn_id']);

/* ADD ITEM */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save'])) {
    $product_id  = intval($_POST['product_id']);
    $quantity    = intval($_POST['quantity']);
    $batch_no    = trim($_POST['batch_no'] ?? '');
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;

    // Retrieve Product metadata
    $stmt = $conn->prepare("SELECT id, product_code, product_name, uom FROM products WHERE id = ?");
    $stmt->bind_param("i", $product_id);
    $stmt->execute();
    $p = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($p) {
        $insertStmt = $conn->prepare("
            INSERT INTO asn_items (asn_id, product_id, product_code, product_name, quantity, uom, batch_no, expiry_date)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $insertStmt->bind_param("iississs", $asn_id, $p['id'], $p['product_code'], $p['product_name'], $quantity, $p['uom'], $batch_no, $expiry_date);
        $insertStmt->execute();
        $insertStmt->close();

        header("Location: items.php?asn_id=" . $asn_id);
        exit();
    }
}

/* DELETE ITEM */
if (isset($_GET['delete']) && filter_var($_GET['delete'], FILTER_VALIDATE_INT)) {
    $delete_id = intval($_GET['delete']);
    $delStmt = $conn->prepare("DELETE FROM asn_items WHERE id = ? AND asn_id = ?");
    $delStmt->bind_param("ii", $delete_id, $asn_id);
    $delStmt->execute();
    $delStmt->close();

    header("Location: items.php?asn_id=" . $asn_id);
    exit();
}

/* FETCH ITEMS */
$itemStmt = $conn->prepare("SELECT * FROM asn_items WHERE asn_id = ? ORDER BY id DESC");
$itemStmt->bind_param("i", $asn_id);
$itemStmt->execute();
$itemsResult = $itemStmt->get_result();

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid">
        <div class="card shadow">
            <div class="card-header bg-success text-white">
                <h3 class="mb-0">ASN Product Items</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-5">
                            <label class="form-label">Product</label>
                            <select name="product_id" class="form-select" required>
                                <option value="">Select Product</option>
                                <?php
                                $products = mysqli_query($conn, "SELECT id, product_code, product_name FROM products ORDER BY product_name");
                                while ($pro = mysqli_fetch_assoc($products)):
                                ?>
                                    <option value="<?= $pro['id']; ?>">
                                        <?= htmlspecialchars($pro['product_code']) . " - " . htmlspecialchars($pro['product_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Quantity</label>
                            <input type="number" name="quantity" class="form-control" min="1" required>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Batch</label>
                            <input type="text" name="batch_no" class="form-control">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Expiry</label>
                            <input type="date" name="expiry_date" class="form-control">
                        </div>
                    </div>
                    <button type="submit" name="save" class="btn btn-success mt-3">+ Add Product</button>
                </form>

                <hr class="my-4">

                <table class="table table-bordered table-striped">
                    <thead class="table-dark">
                        <tr>
                            <th width="60">ID</th>
                            <th>Product Code</th>
                            <th>Product Name</th>
                            <th width="90">Qty</th>
                            <th width="90">UOM</th>
                            <th>Batch</th>
                            <th>Expiry</th>
                            <th width="180">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($itemsResult->num_rows > 0): ?>
                            <?php while ($row = $itemsResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?= $row['id']; ?></td>
                                    <td><?= htmlspecialchars($row['product_code']); ?></td>
                                    <td><?= htmlspecialchars($row['product_name']); ?></td>
                                    <td><?= htmlspecialchars($row['quantity']); ?></td>
                                    <td><?= htmlspecialchars($row['uom']); ?></td>
                                    <td><?= htmlspecialchars($row['batch_no']); ?></td>
                                    <td><?= htmlspecialchars($row['expiry_date'] ?? ''); ?></td>
                                    <td>
                                        <a href="edit_item.php?id=<?= $row['id']; ?>&asn_id=<?= $asn_id; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="items.php?asn_id=<?= $asn_id; ?>&delete=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this product?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" class="text-center">No Products Found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>

                <div class="d-flex justify-content-between mt-4">
                    <a href="index.php" class="btn btn-secondary">← Back to ASNs</a>
                    <a href="../grn/create.php?asn_id=<?= $asn_id; ?>" class="btn btn-primary">Next → Create GRN</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$itemStmt->close();
include "../../../includes/footer.php"; 
?>