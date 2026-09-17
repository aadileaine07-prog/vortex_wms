<?php
session_start();

// Enable Error Reporting for Debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

/* ===============================
   Dashboard Counters
================================ */
$totalEmployees      = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees"))['total'] ?? 0;
$activeEmployees     = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE status='Active'"))['total'] ?? 0;
$inactiveEmployees   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE status='Inactive'"))['total'] ?? 0;
$managementEmployees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE employee_id LIKE 'Z%'"))['total'] ?? 0;
$shopFloorEmployees  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE employee_id LIKE 'VTX%'"))['total'] ?? 0;

/* ===============================
   Search & Filter Query Handling
================================ */
$search     = trim($_GET['search'] ?? '');
$department = trim($_GET['department'] ?? '');
$role       = trim($_GET['role'] ?? '');
$status     = trim($_GET['status'] ?? '');

$where  = ["1=1"];
$params = [];
$types  = "";

if (!empty($search)) {
    $where[] = "(employee_id LIKE ? OR full_name LIKE ? OR username LIKE ? OR mobile LIKE ? OR email LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $params[] = $searchTerm;
    $types  .= "sssss";
}

if (!empty($department)) {
    $where[] = "department = ?";
    $params[] = $department;
    $types  .= "s";
}

if (!empty($role)) {
    $where[] = "role = ?";
    $params[] = $role;
    $types  .= "s";
}

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
    $types  .= "s";
}

$sql = "SELECT * FROM employees WHERE " . implode(" AND ", $where) . " ORDER BY id DESC";
$stmt = $conn->prepare($sql);

if ($stmt === false) {
    die("Prepare failed: " . htmlspecialchars($conn->error));
}

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-3 shadow-sm rounded-4 border-0" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3 shadow-sm rounded-4 border-0" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-users text-primary me-2"></i>Employee Master</h2>
                <p class="text-muted mb-0">Manage Employees, User Accounts & Real-Time Active Sessions</p>
            </div>
            <div class="d-flex gap-2">
                <a href="import.php" class="btn btn-success px-3 fw-bold rounded-pill shadow-sm">
                    <i class="fa-solid fa-file-csv me-1"></i> Bulk Import
                </a>
                <a href="add.php" class="btn btn-primary px-3 fw-bold rounded-pill shadow-sm">
                    <i class="fa-solid fa-plus me-1"></i> Add Employee
                </a>
            </div>
        </div>

        <!-- Dashboard Stat Cards -->
        <div class="row g-3 mb-4">
            <div class="col-lg-2 col-6">
                <div class="card shadow-sm text-center py-3 border-0 border-start border-4 border-secondary rounded-4 bg-white">
                    <small class="text-muted text-uppercase fw-bold" style="font-size:11px;">Total</small>
                    <h3 class="mb-0 fw-bold text-dark mt-1"><?= $totalEmployees; ?></h3>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="card shadow-sm text-center py-3 border-0 border-start border-4 border-success rounded-4 bg-white">
                    <small class="text-muted text-uppercase fw-bold" style="font-size:11px;">Active</small>
                    <h3 class="text-success mb-0 fw-bold mt-1"><?= $activeEmployees; ?></h3>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="card shadow-sm text-center py-3 border-0 border-start border-4 border-danger rounded-4 bg-white">
                    <small class="text-muted text-uppercase fw-bold" style="font-size:11px;">Inactive</small>
                    <h3 class="text-danger mb-0 fw-bold mt-1"><?= $inactiveEmployees; ?></h3>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card shadow-sm text-center py-3 border-0 border-start border-4 border-primary rounded-4 bg-white">
                    <small class="text-muted text-uppercase fw-bold" style="font-size:11px;">Management</small>
                    <h3 class="text-primary mb-0 fw-bold mt-1"><?= $managementEmployees; ?></h3>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card shadow-sm text-center py-3 border-0 border-start border-4 border-warning rounded-4 bg-white">
                    <small class="text-muted text-uppercase fw-bold" style="font-size:11px;">Shop Floor</small>
                    <h3 class="text-warning mb-0 fw-bold mt-1"><?= $shopFloorEmployees; ?></h3>
                </div>
            </div>
        </div>

        <!-- Main Content Card -->
        <div class="card shadow-sm border-0 rounded-4 bg-white">
            <div class="card-body p-4">
                
                <!-- Search & Filter Form (Autocomplete Off added to prevent auto-selection) -->
                <form method="GET" action="" class="row g-2 mb-4 align-items-center" autocomplete="off">
                    <div class="col-md-3">
                        <div class="input-group">
                            <span class="input-group-text bg-light border-2 border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                            <input type="text" id="liveSearchInput" name="search" class="form-control border-2 border-start-0 fw-semibold" placeholder="Search Name, ID, Username..." value="<?= htmlspecialchars($search); ?>">
                        </div>
                    </div>

                    <div class="col-md-2">
                        <select name="department" class="form-select border-2 fw-semibold" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php 
                            $deptRes = mysqli_query($conn, "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department!='' ORDER BY department ASC");
                            if ($deptRes) {
                                while($d = mysqli_fetch_assoc($deptRes)):
                            ?>
                                <option value="<?= htmlspecialchars($d['department']); ?>" <?= ($department === $d['department']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($d['department']); ?>
                                </option>
                            <?php 
                                endwhile;
                            } 
                            ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <select name="role" class="form-select border-2 fw-semibold" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            <?php 
                            $roleRes = mysqli_query($conn, "SELECT DISTINCT role FROM employees WHERE role IS NOT NULL AND role!='' ORDER BY role ASC");
                            if ($roleRes) {
                                while($r = mysqli_fetch_assoc($roleRes)):
                                    $sel = ($role === $r['role']) ? 'selected' : '';
                            ?>
                                <option value="<?= htmlspecialchars($r['role']); ?>" <?= $sel; ?>>
                                    <?= htmlspecialchars($r['role']); ?>
                                </option>
                            <?php 
                                endwhile;
                            } 
                            ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <select name="status" class="form-select border-2 fw-semibold" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="Active" <?= ($status === 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?= ($status === 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-3 text-end d-flex gap-1 justify-content-end">
                        <button type="submit" class="btn btn-dark shadow-sm rounded-pill px-3 fw-bold" title="Filter"><i class="fa-solid fa-filter me-1"></i> Filter</button>
                        <a href="index.php" class="btn btn-outline-secondary rounded-pill px-3 fw-bold" title="Reset Filters"><i class="fa-solid fa-rotate me-1"></i> Reset</a>
                        <a href="pdf.php" class="btn btn-outline-danger rounded-circle" title="Export PDF"><i class="fa-solid fa-file-pdf"></i></a>
                        <a href="export.php" class="btn btn-outline-success rounded-circle" title="Export Excel"><i class="fa-solid fa-file-excel"></i></a>
                    </div>
                </form>

                <!-- Employee Table -->
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="employeeTable">
                        <thead class="table-light">
                            <tr>
                                <th width="60" class="text-center">Photo</th>
                                <th>Emp ID</th>    
                                <th>Username</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Warehouse</th>
                                <th>Mobile</th>
                                <th class="text-center">Live Status</th>
                                <th class="text-center">Status</th>
                                <th width="180" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <?php 
                                    $is_online = false;
                                    if (!empty($row['last_activity'])) {
                                        $last_active = strtotime($row['last_activity']);
                                        if ((time() - $last_active) <= 300) { 
                                            $is_online = true;
                                        }
                                    }

                                    $emp_id     = $row['employee_id'] ?? 'N/A';
                                    $username   = $row['username'] ?? '-';
                                    $full_name  = $row['full_name'] ?? 'Unknown';
                                    $department = $row['department'] ?? 'N/A';
                                    $role       = $row['role'] ?? 'N/A';
                                    $warehouse  = $row['warehouse'] ?? 'N/A';
                                    $mobile     = $row['mobile'] ?? '-';
                                    $status     = $row['status'] ?? 'Active';
                                    $photo      = $row['photo'] ?? 'default.png';
                                    ?>
                                    <tr>
                                        <!-- Photo -->
                                        <td class="text-center">
                                            <img src="../../../assets/images/employees/<?= (empty($photo) || $photo == 'default.png') ? 'default.png' : htmlspecialchars($photo); ?>" width="40" height="40" class="rounded-circle shadow-sm" style="object-fit:cover; border: 2px solid #f8f9fa;" alt="Avatar">
                                        </td>

                                        <!-- Employee ID -->
                                        <td>
                                            <?php if (strncmp($emp_id, "Z", 1) === 0): ?>
                                                <span class="badge bg-primary-subtle text-primary border border-primary-subtle px-2 py-1 font-monospace"><?= htmlspecialchars($emp_id); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success-subtle text-success border border-success-subtle px-2 py-1 font-monospace"><?= htmlspecialchars($emp_id); ?></span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Username -->
                                        <td>
                                            <span class="fw-semibold text-primary font-monospace"><i class="fa-solid fa-at me-1 text-muted small"></i><?= htmlspecialchars($username); ?></span>
                                        </td>

                                        <!-- Name -->
                                        <td><strong class="text-dark"><?= htmlspecialchars($full_name); ?></strong></td>

                                        <!-- Department -->
                                        <td><span class="text-secondary fw-semibold"><?= htmlspecialchars($department); ?></span></td>

                                        <!-- Role -->
                                        <td><span class="badge bg-light text-dark border px-2 py-1"><?= htmlspecialchars($role); ?></span></td>

                                        <!-- Warehouse -->
                                        <td><span class="badge bg-info-subtle text-info border border-info-subtle px-2 py-1"><?= htmlspecialchars($warehouse); ?></span></td>

                                        <!-- Mobile -->
                                        <td class="font-monospace"><?= htmlspecialchars($mobile); ?></td>

                                        <!-- Live Status -->
                                        <td class="text-center">
                                            <?php if ($is_online): ?>
                                                <span class="badge bg-success px-3 py-1 rounded-pill"><i class="fa-solid fa-circle me-1 small text-white animate-pulse"></i> Online</span>
                                            <?php else: ?>
                                                <span class="badge bg-light text-muted border px-3 py-1 rounded-pill"><i class="fa-regular fa-circle me-1 small"></i> Offline</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Status -->
                                        <td class="text-center">
                                            <?php if ($status == "Active"): ?>
                                                <span class="badge bg-success px-3 py-1 rounded-pill">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger px-3 py-1 rounded-pill">Inactive</span>
                                            <?php endif; ?>
                                        </td>

                                        <!-- Action -->
                                        <td class="text-center">
                                            <div class="btn-group btn-group-sm" role="group">
                                                <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-outline-info rounded-circle me-1" title="View"><i class="fa-solid fa-eye"></i></a>
                                                <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-outline-warning rounded-circle me-1" title="Edit"><i class="fa-solid fa-pen-to-square"></i></a>
                                                
                                                <?php if (isset($_SESSION['role']) && in_array($_SESSION['role'], ['Admin', 'Super Admin', 'Manager']) && $row['id'] != $_SESSION['employee_id']): ?>
                                                    <a href="force_logout.php?id=<?= $row['id']; ?>" class="btn btn-outline-dark rounded-circle me-1" title="Force Logout" onclick="return confirm('Are you sure you want to force logout this user?');">
                                                        <i class="fa-solid fa-power-off text-danger"></i>
                                                    </a>
                                                <?php endif; ?>

                                                <a href="delete.php?id=<?= (int)$row['id']; ?>" class="btn btn-outline-danger rounded-circle" title="Delete" onclick="return confirm('Are you sure you want to delete this employee?');"><i class="fa-solid fa-trash"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="11" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-user-slash fs-2 mb-2 d-block"></i>
                                        No Employees Found Matching Criteria
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>

    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function() {
    const liveSearch = document.getElementById("liveSearchInput");
    if(liveSearch) {
        liveSearch.addEventListener("keyup", function() {
            const val = this.value.toLowerCase();
            const rows = document.querySelectorAll("#employeeTable tbody tr");
            
            rows.forEach(row => {
                const text = row.innerText.toLowerCase();
                row.style.display = text.includes(val) ? "" : "none";
            });
        });
    }
});
</script>

<?php 
if (isset($stmt)) {
    $stmt->close();
}
include "../../../includes/footer.php"; 
?>