<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

/* ===============================
   Dashboard Cards Data
================================ */
$today = date("Y-m-d");

$totalEmployees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM employees WHERE status='Active'"))['total'] ?? 0;
$present        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM attendance WHERE attendance_date='$today' AND status='Present'"))['total'] ?? 0;
$absent         = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM attendance WHERE attendance_date='$today' AND status='Absent'"))['total'] ?? 0;
$leave          = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM attendance WHERE attendance_date='$today' AND status='Leave'"))['total'] ?? 0;
$halfday        = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) total FROM attendance WHERE attendance_date='$today' AND status='Half Day'"))['total'] ?? 0;

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header & Action -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-calendar-check text-primary me-2"></i>Attendance Management
                </h2>
                <p class="text-muted mb-0">Manage Daily Employee Attendance & Time Logs</p>
            </div>
            <div>
                <a href="mark.php" class="btn btn-primary px-3 shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Mark Attendance
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
            <div class="col">
                <div class="card shadow-sm border-0 bg-primary text-white text-center rounded-3">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-bold">Total Staff</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= $totalEmployees; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card shadow-sm border-0 bg-success text-white text-center rounded-3">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-bold">Present</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= $present; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card shadow-sm border-0 bg-danger text-white text-center rounded-3">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-bold">Absent</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= $absent; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card shadow-sm border-0 bg-warning text-dark text-center rounded-3">
                    <div class="card-body p-3">
                        <small class="text-dark-50 text-uppercase fw-bold">On Leave</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= $leave; ?></h3>
                    </div>
                </div>
            </div>
            <div class="col">
                <div class="card shadow-sm border-0 text-white text-center rounded-3" style="background:#6f42c1;">
                    <div class="card-body p-3">
                        <small class="text-white-50 text-uppercase fw-bold">Half Day</small>
                        <h3 class="fw-bold mb-0 mt-1"><?= $halfday; ?></h3>
                    </div>
                </div>
            </div>
        </div>

        <!-- Attendance Records Table Card -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">

                <!-- Filter Controls -->
                <div class="row g-2 mb-3 align-items-center">
                    <div class="col-md-3">
                        <input type="text" id="searchInput" class="form-control" placeholder="🔍 Search Employee...">
                    </div>
                    <div class="col-md-3">
                        <input type="date" id="dateInput" class="form-control" value="<?= $today; ?>">
                    </div>
                    <div class="col-md-2">
                        <select id="statusSelect" class="form-select">
                            <option value="">All Status</option>
                            <option value="Present">Present</option>
                            <option value="Absent">Absent</option>
                            <option value="Leave">Leave</option>
                            <option value="Half Day">Half Day</option>
                        </select>
                    </div>
                    <div class="col-md-4 text-end">
                        <button onclick="window.print()" class="btn btn-dark btn-sm"><i class="fa-solid fa-print me-1"></i> Print</button>
                    </div>
                </div>

                <!-- Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle border" id="attendanceTable">
                        <thead class="table-dark">
                            <tr>
                                <th>Photo</th>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Check In</th>
                                <th>Check Out</th>
                                <th>Status</th>
                                <th class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $result = mysqli_query($conn, "
                                SELECT attendance.*, employees.full_name, employees.employee_id, employees.department, employees.photo
                                FROM attendance
                                INNER JOIN employees ON attendance.employee_id = employees.id
                                WHERE attendance.attendance_date='$today'
                                ORDER BY attendance.id DESC
                            ");

                            if ($result && mysqli_num_rows($result) > 0) {
                                while ($row = mysqli_fetch_assoc($result)) {
                            ?>
                                    <tr>
                                        <td>
                                            <img src="../../../assets/images/employees/<?= empty($row['photo']) ? 'default-user.png' : htmlspecialchars($row['photo']); ?>"
                                                 width="40" height="40" class="rounded-circle" style="object-fit:cover;">
                                        </td>
                                        <td><strong><?= htmlspecialchars($row['employee_id']); ?></strong></td>
                                        <td><?= htmlspecialchars($row['full_name']); ?></td>
                                        <td><?= htmlspecialchars($row['department']); ?></td>
                                        <td><?= $row['check_in'] ? date("h:i A", strtotime($row['check_in'])) : '-'; ?></td>
                                        <td><?= $row['check_out'] ? date("h:i A", strtotime($row['check_out'])) : '-'; ?></td>
                                        <td class="status-cell">
                                            <?php
                                            $st = $row['status'];
                                            if ($st == "Present") echo "<span class='badge bg-success'>Present</span>";
                                            elseif ($st == "Absent") echo "<span class='badge bg-danger'>Absent</span>";
                                            elseif ($st == "Leave") echo "<span class='badge bg-warning text-dark'>Leave</span>";
                                            else echo "<span class='badge bg-purple text-white' style='background:#6f42c1;'>Half Day</span>";
                                            ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm text-white"><i class="fa-solid fa-eye"></i></a>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm"><i class="fa-solid fa-pen-to-square"></i></a>
                                        </td>
                                    </tr>
                            <?php
                                }
                            } else {
                                echo "<tr><td colspan='8' class='text-center text-muted py-4'>No Attendance Records Found for Today</td></tr>";
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
// Filter Table Rows dynamically
document.addEventListener("DOMContentLoaded", function() {
    const searchInput = document.getElementById("searchInput");
    const statusSelect = document.getElementById("statusSelect");

    function filterTable() {
        const searchVal = searchInput.value.toLowerCase();
        const statusVal = statusSelect.value.toLowerCase();
        const rows = document.querySelectorAll("#attendanceTable tbody tr");

        rows.forEach(row => {
            const text = row.innerText.toLowerCase();
            const statusText = row.querySelector(".status-cell") ? row.querySelector(".status-cell").innerText.toLowerCase() : "";
            
            const matchesSearch = text.includes(searchVal);
            const matchesStatus = statusVal === "" || statusText.includes(statusVal);

            row.style.display = (matchesSearch && matchesStatus) ? "" : "none";
        });
    }

    if(searchInput) searchInput.addEventListener("keyup", filterTable);
    if(statusSelect) statusSelect.addEventListener("change", filterTable);
});
</script>