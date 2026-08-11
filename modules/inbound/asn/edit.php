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
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update'])) {
    $supplier_name  = trim($_POST['supplier_name'] ?? '');
    $invoice_number = trim($_POST['invoice_number'] ?? '');
    $invoice_date   = !empty($_POST['invoice_date']) ? $_POST['invoice_date'] : NULL;
    $expected_date  = !empty($_POST['expected_date']) ? $_POST['expected_date'] : NULL;
    $vehicle_number = trim($_POST['vehicle_number'] ?? '');
    $status         = $_POST['status'] ?? 'Pending';

    $stmt = $conn->prepare("
        UPDATE asn 
        SET supplier_name = ?, invoice_number = ?, invoice_date = ?, expected_date = ?, vehicle_number = ?, status = ?
        WHERE id = ?
    ");
    $stmt->bind_param("ssssssi", $supplier_name, $invoice_number, $invoice_date, $expected_date, $vehicle_number, $status, $id);

    if ($stmt->execute()) {
        $stmt->close();
        header("Location: index.php");
        exit();
    } else {
        $error = "Error updating record: " . $stmt->error;
        $stmt->close();
    }
}

// Retrieve ASN record
$stmt = $conn->prepare("SELECT * FROM asn WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    die("ASN Record Not Found.");
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

        <div class="card shadow">
            <div class="card-header bg-warning text-dark">
                <h3 class="mb-0">Edit ASN</h3>
            </div>
            <div class="card-body">
                <form method="POST" action="">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">ASN Number</label>
                            <input type="text" class="form-control" value="<?= htmlspecialchars($row['asn_number']); ?>" readonly>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Supplier Name</label>
                            <input type="text" name="supplier_name" class="form-control" value="<?= htmlspecialchars($row['supplier_name']); ?>" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Invoice Number</label>
                            <input type="text" name="invoice_number" class="form-control" value="<?= htmlspecialchars($row['invoice_number']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Invoice Date</label>
                            <input type="date" name="invoice_date" class="form-control" value="<?= htmlspecialchars($row['invoice_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Expected Date</label>
                            <input type="date" name="expected_date" class="form-control" value="<?= htmlspecialchars($row['expected_date'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vehicle Number</label>
                            <input type="text" name="vehicle_number" class="form-control" value="<?= htmlspecialchars($row['vehicle_number']); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select name="status" class="form-select">
                                <option value="Pending" <?= ($row['status'] == "Pending") ? "selected" : ""; ?>>Pending</option>
                                <option value="Received" <?= ($row['status'] == "Received") ? "selected" : ""; ?>>Received</option>
                                <option value="Completed" <?= ($row['status'] == "Completed") ? "selected" : ""; ?>>Completed</option>
                            </select>
                        </div>
                    </div>

                    <button type="submit" name="update" class="btn btn-success">Update ASN</button>
                    <a href="index.php" class="btn btn-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php include "../../../includes/footer.php"; ?>