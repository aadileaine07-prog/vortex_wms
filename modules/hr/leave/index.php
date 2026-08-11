<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

/* Dashboard Metrics Queries */
$totalLeave    = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM leaves"))['total'] ?? 0;
$pendingLeave  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM leaves WHERE status='Pending'"))['total'] ?? 0;
$approvedLeave = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM leaves WHERE status='Approved'"))['total'] ?? 0;
$rejectedLeave = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM leaves WHERE status='Rejected'"))['total'] ?? 0;

$result = mysqli_query($conn, "
    SELECT leaves.*, employees.employee_id, employees.full_name, employees.department
    FROM leaves
    INNER JOIN employees ON employees.id = leaves.employee_id
    ORDER BY leaves.id DESC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-calendar-minus text-primary me-2"></i>Leave Management
                </h2>
                <p class="text-muted mb-0">Employee Leave Requests & Approval System</p>
            </div>
            <div>
                <a href="add.php" class="btn btn-primary px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Apply Leave
                </a>
            </div>
        </div>

        <!-- Success/Error Alert Messages -->
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

        <!-- Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-primary text-white text-center rounded-3">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-bold">Total Leaves</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= $totalLeave; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-warning text-dark text-center rounded-3">
                    <div class="card-body p-3">
                        <small class="text-dark-50 text-uppercase fw-bold">Pending</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= $pendingLeave; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-success text-white text-center rounded-3">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-bold">Approved</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= $approvedLeave; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card shadow-sm border-0 bg-danger text-white text-center rounded-3">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-bold">Rejected</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= $rejectedLeave; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Filter & Table Card -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">

                <!-- Filters -->
                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-md-3">
                        <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search Employee...">
                    </div>
                    <div class="col-md-2">
                        <select id="statusSelect" class="form-select">
                            <option value="">All Status</option>
                            <option value="Pending">Pending</option>
                            <option value="Approved">Approved</option>
                            <option value="Rejected">Rejected</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select id="typeSelect" class="form-select">
                            <option value="">All Leave Types</option>
                            <option value="Casual Leave">Casual Leave</option>
                            <option value="Sick Leave">Sick Leave</option>
                            <option value="Earn Leave">Earn Leave</option>
                            <option value="Half Day">Half Day</option>
                            <option value="Emergency Leave">Emergency Leave</option>
                        </select>
                    </div>
                    <div class="col-md-4 text-end">
                        <button onclick="window.print()" class="btn btn-dark btn-sm px-3"><i class="fa-solid fa-print me-1"></i> Print</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="leaveTable">
                        <thead class="table-dark">
                            <tr>
                                <th>ID</th>
                                <th>Employee</th>
                                <th>Department</th>
                                <th>Leave Type</th>
                                <th>From Date</th>
                                <th>To Date</th>
                                <th>Total Days</th>
                                <th>Status</th>
                                <th class="text-center" width="180">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                                    <tr>
                                        <td><strong>#<?= $row['id']; ?></strong></td>
                                        <td>
                                            <span class="badge bg-primary mb-1"><?= htmlspecialchars($row['employee_id']); ?></span><br>
                                            <strong><?= htmlspecialchars($row['full_name']); ?></strong>
                                        </td>
                                        <td><?= htmlspecialchars($row['department']); ?></td>
                                        <td class="type-cell"><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['leave_type']); ?></span></td>
                                        <td><?= date("d-m-Y", strtotime($row['from_date'])); ?></td>
                                        <td><?= date("d-m-Y", strtotime($row['to_date'])); ?></td>
                                        <td><span class="fw-bold"><?= $row['total_days']; ?></span> Days</td>
                                        <td class="status-cell">
                                            <?php
                                            if ($row['status'] == "Pending") echo "<span class='badge bg-warning text-dark'>Pending</span>";
                                            elseif ($row['status'] == "Approved") echo "<span class='badge bg-success'>Approved</span>";
                                            else echo "<span class='badge bg-danger'>Rejected</span>";
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm text-white"><i class="fa-solid fa-eye"></i></a>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                                            <a href="delete.php?id=<?= $row['id']; ?>" class="btn btn-danger btn-sm" onclick="return confirm('Delete Leave Record?')"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='9' class='text-center text-muted py-4'>No Leave Records Found</td></tr>";
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
// Filter Leave Table Rows
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("searchInput");
    const statusSelect = document.getElementById("statusSelect");
    const typeSelect = document.getElementById("typeSelect");

    function filterTable() {
        const searchVal = searchInput.value.toLowerCase();
        const statusVal = statusSelect.value.toLowerCase();
        const typeVal   = typeSelect.value.toLowerCase();
        const rows      = document.querySelectorAll("#leaveTable tbody tr");

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const statusText = row.querySelector(".status-cell") ? row.querySelector(".status-cell").innerText.toLowerCase() : "";
            const typeText   = row.querySelector(".type-cell") ? row.querySelector(".type-cell").innerText.toLowerCase() : "";

            const matchesSearch = text.includes(searchVal);
            const matchesStatus = statusVal === "" || statusText.includes(statusVal);
            const matchesType   = typeVal === "" || typeText.includes(typeVal);

            row.style.display = (matchesSearch && matchesStatus && matchesType) ? "" : "none";
        });
    }

    if (searchInput) searchInput.addEventListener("keyup", filterTable);
    if (statusSelect) statusSelect.addEventListener("change", filterTable);
    if (typeSelect) typeSelect.addEventListener("change", filterTable);
});
</script>