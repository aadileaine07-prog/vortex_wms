<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id']) || !isset($_GET['asn_id'])) {
    die("Invalid Request");
}

$id     = intval($_GET['id']);
$asn_id = intval($_GET['asn_id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $quantity    = intval($_POST['quantity']);
    $batch_no    = trim($_POST['batch_no'] ?? '');
    $expiry_date = !empty($_POST['expiry_date']) ? $_POST['expiry_date'] : NULL;

    $stmt = $conn->prepare("UPDATE asn_items SET quantity = ?, batch_no = ?, expiry_date = ? WHERE id = ?");
    $stmt->bind_param("issi", $quantity, $batch_no, $expiry_date, $id);
    $stmt->execute();
    $stmt->close();

    header("Location: items.php?asn_id=" . $asn_id);
    exit();
}

$stmt = $conn->prepare("SELECT * FROM asn_items WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    die("Item Not Found");
}

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid">
        <div class="card shadow">
            <div class="card-header bg-warning text-dark">
                <h3 class="mb-0">Edit ASN Item</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="mb-3">
                        <label class="form-label">Product Code</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['product_code']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Product Name</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['product_name']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Quantity</label>
                        <input type="number" name="quantity" class="form-control" value="<?= htmlspecialchars($row['quantity']); ?>" min="1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">UOM</label>
                        <input type="text" class="form-control" value="<?= htmlspecialchars($row['uom']); ?>" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Batch No</label>
                        <input type="text" name="batch_no" class="form-control" value="<?= htmlspecialchars($row['batch_no']); ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Expiry Date</label>
                        <input type="date" name="expiry_date" class="form-control" value="<?= htmlspecialchars($row['expiry_date'] ?? ''); ?>">
                    </div>

                    <button type="submit" name="update" class="btn btn-success">Update Item</button>
                    <a href="items.php?asn_id=<?= $asn_id; ?>" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include "../../../includes/footer.php"; ?>