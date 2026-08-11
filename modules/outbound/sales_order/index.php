<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$result = mysqli_query($conn, "
    SELECT *
    FROM sales_orders
    ORDER BY id DESC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Header & Action -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-file-invoice-dollar text-primary me-2"></i>Sales Orders
                </h2>
                <p class="text-muted mb-0">Manage customer outbound sales orders and shipping status</p>
            </div>
            <div>
                <a href="create.php" class="btn btn-success px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Create Sales Order
                </a>
            </div>
        </div>

        <!-- Session Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Table Card -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">

                <!-- Live Search & Filter -->
                <div class="row g-2 mb-3">
                    <div class="col-md-5">
                        <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search Order No or Customer Name...">
                    </div>
                    <div class="col-md-3">
                        <select id="statusFilter" class="form-select">
                            <option value="">All Statuses</option>
                            <option value="Pending">Pending</option>
                            <option value="Picking">Picking</option>
                            <option value="Packed">Packed</option>
                            <option value="Dispatched">Dispatched</option>
                            <option value="Delivered">Delivered</option>
                            <option value="Cancelled">Cancelled</option>
                        </select>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="salesOrderTable">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">ID</th>
                                <th>Order No</th>
                                <th>Customer</th>
                                <th>Order Date</th>
                                <th>Status</th>
                                <th width="200" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['order_number']); ?></span></td>
                                        <td><strong><?= htmlspecialchars($row['customer_name']); ?></strong></td>
                                        <td><?= date("d-m-Y", strtotime($row['order_date'])); ?></td>
                                        <td class="status-cell">
                                            <?php
                                            $st = $row['status'];
                                            if ($st == "Pending") echo '<span class="badge bg-warning text-dark">Pending</span>';
                                            elseif ($st == "Picking") echo '<span class="badge bg-info">Picking</span>';
                                            elseif ($st == "Packed") echo '<span class="badge bg-primary">Packed</span>';
                                            elseif ($st == "Dispatched") echo '<span class="badge bg-success">Dispatched</span>';
                                            elseif ($st == "Delivered") echo '<span class="badge bg-dark">Delivered</span>';
                                            else echo '<span class="badge bg-danger">Cancelled</span>';
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-primary btn-sm"><i class="fa-solid fa-eye"></i> View</a>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i> Edit</a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete this Sales Order?');"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='6' class='text-center text-muted py-4'>No Sales Orders Found</td></tr>";
                            }
                            ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("searchInput");
    const statusFilter = document.getElementById("statusFilter");

    function filterTable() {
        const searchVal = searchInput.value.toLowerCase();
        const statusVal = statusFilter.value.toLowerCase();
        const rows = document.querySelectorAll("#salesOrderTable tbody tr");

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const statusText = row.querySelector(".status-cell") ? row.querySelector(".status-cell").innerText.toLowerCase() : "";

            const matchesSearch = text.includes(searchVal);
            const matchesStatus = statusVal === "" || statusText.includes(statusVal);

            row.style.display = (matchesSearch && matchesStatus) ? "" : "none";
        });
    }

    if (searchInput) searchInput.addEventListener("keyup", filterTable);
    if (statusFilter) statusFilter.addEventListener("change", filterTable);
});
</script>