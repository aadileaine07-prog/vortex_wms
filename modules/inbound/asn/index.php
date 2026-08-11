<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$result = mysqli_query($conn, "SELECT id, asn_number, supplier_name, invoice_number, expected_date, status FROM asn ORDER BY id DESC");

include "../../../includes/header.php";
?>

<div class="content">
    <div class="container-fluid">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>📥 Advance Shipment Notice (ASN)</h2>
            <a href="add.php" class="btn btn-primary">+ Create ASN</a>
        </div>

        <div class="card shadow">
            <div class="card-body">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>ASN No.</th>
                            <th>Supplier</th>
                            <th>Invoice No.</th>
                            <th>Expected Date</th>
                            <th>Status</th>
                            <th width="240">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($result && mysqli_num_rows($result) > 0): ?>
                            <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                <tr>
                                    <td><?= $row['id']; ?></td>
                                    <td><?= htmlspecialchars($row['asn_number']); ?></td>
                                    <td><?= htmlspecialchars($row['supplier_name']); ?></td>
                                    <td><?= htmlspecialchars($row['invoice_number']); ?></td>
                                    <td><?= htmlspecialchars($row['expected_date'] ?? ''); ?></td>
                                    <td>
                                        <?php if ($row['status'] == "Pending"): ?>
                                            <span class="badge bg-warning text-dark">Pending</span>
                                        <?php elseif ($row['status'] == "Received"): ?>
                                            <span class="badge bg-primary">Received</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Completed</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm">View</a>
                                        <a href="items.php?asn_id=<?= $row['id']; ?>" class="btn btn-success btn-sm">Items</a>
                                        <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                        <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Are you sure you want to delete this ASN?');">Delete</a>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" class="text-center">No ASN Found</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php include "../../../includes/footer.php"; ?>