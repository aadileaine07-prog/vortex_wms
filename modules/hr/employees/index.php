<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

/* ===============================
   Dashboard Counters
================================ */
$totalEmployees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees"))['total'];
$activeEmployees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE status='Active'"))['total'];
$inactiveEmployees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE status='Inactive'"))['total'];
$managementEmployees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE employee_id LIKE 'Z%'"))['total'];
$shopFloorEmployees = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) AS total FROM employees WHERE employee_id LIKE 'VTX%'"))['total'];

/* ===============================
   Search & Filter Query Handling
================================ */
$search     = trim($_GET['search'] ?? '');
$department = trim($_GET['department'] ?? '');
$role       = trim($_GET['role'] ?? '');
$status     = trim($_GET['status'] ?? '');

$where = ["1=1"];
$params = [];
$types = "";

if (!empty($search)) {
    $where[] = "(employee_id LIKE ? OR full_name LIKE ? OR mobile LIKE ? OR email LIKE ?)";
    $searchTerm = "%" . $search . "%";
    $params = array_merge($params, [$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
    $types .= "ssss";
}

if (!empty($department)) {
    $where[] = "department = ?";
    $params[] = $department;
    $types .= "s";
}

if (!empty($role)) {
    $where[] = "role = ?";
    $params[] = $role;
    $types .= "s";
}

if (!empty($status)) {
    $where[] = "status = ?";
    $params[] = $status;
    $types .= "s";
}

$sql = "SELECT * FROM employees WHERE " . implode(" AND ", $where) . " ORDER BY id DESC";
$stmt = $conn->prepare($sql);

if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}

$stmt->execute();
$result = $stmt->get_result();

include "../../../includes/header.php";
?>

<div class="content">
    <div class="container-fluid">

        <!-- Flash Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show mb-3" role="alert">
                <?= htmlspecialchars($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['success']); ?>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show mb-3" role="alert">
                <?= htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold">👨 Employee Master</h2>
                <p class="text-muted mb-0">Manage Employees & Access Control</p>
            </div>
            <div>
                <a href="add.php" class="btn btn-primary">➕ Add Employee</a>
            </div>
        </div>

        <div class="row g-3 mb-4">
            <div class="col-lg-2 col-6">
                <div class="card shadow-sm text-center py-2">
                    <h6 class="text-muted">Total</h6>
                    <h2 class="mb-0"><?= $totalEmployees; ?></h2>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="card shadow-sm text-center py-2">
                    <h6 class="text-muted">Active</h6>
                    <h2 class="text-success mb-0"><?= $activeEmployees; ?></h2>
                </div>
            </div>
            <div class="col-lg-2 col-6">
                <div class="card shadow-sm text-center py-2">
                    <h6 class="text-muted">Inactive</h6>
                    <h2 class="text-danger mb-0"><?= $inactiveEmployees; ?></h2>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card shadow-sm text-center py-2">
                    <h6 class="text-muted">Management</h6>
                    <h2 class="text-primary mb-0"><?= $managementEmployees; ?></h2>
                </div>
            </div>
            <div class="col-lg-3 col-6">
                <div class="card shadow-sm text-center py-2">
                    <h6 class="text-muted">Shop Floor</h6>
                    <h2 class="text-warning mb-0"><?= $shopFloorEmployees; ?></h2>
                </div>
            </div>
        </div>

        <div class="card shadow">
            <div class="card-body">
                <!-- Active Search & Filter Form -->
                <form method="GET" action="" class="row g-2 mb-3">
                    <div class="col-md-3">
                        <input type="text" name="search" class="form-control" placeholder="🔍 Search Name, ID, Mobile..." value="<?= htmlspecialchars($search); ?>">
                    </div>

                    <div class="col-md-2">
                        <select name="department" class="form-select" onchange="this.form.submit()">
                            <option value="">All Departments</option>
                            <?php 
                            $deptRes = mysqli_query($conn, "SELECT DISTINCT department FROM employees WHERE department IS NOT NULL AND department!=''");
                            while($d = mysqli_fetch_assoc($deptRes)):
                            ?>
                                <option value="<?= htmlspecialchars($d['department']); ?>" <?= ($department == $d['department']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($d['department']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <select name="role" class="form-select" onchange="this.form.submit()">
                            <option value="">All Roles</option>
                            <?php 
                            $roleRes = mysqli_query($conn, "SELECT DISTINCT role FROM employees WHERE role IS NOT NULL AND role!=''");
                            while($r = mysqli_fetch_assoc($roleRes)):
                            ?>
                                <option value="<?= htmlspecialchars($r['role']); ?>" <?= ($role == $r['role']) ? 'selected' : ''; ?>>
                                    <?= htmlspecialchars($r['role']); ?>
                                </option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <div class="col-md-2">
                        <select name="status" class="form-select" onchange="this.form.submit()">
                            <option value="">All Status</option>
                            <option value="Active" <?= ($status == 'Active') ? 'selected' : ''; ?>>Active</option>
                            <option value="Inactive" <?= ($status == 'Inactive') ? 'selected' : ''; ?>>Inactive</option>
                        </select>
                    </div>

                    <div class="col-md-3 text-end">
                        <button type="submit" class="btn btn-dark">Filter</button>
                        <a href="index.php" class="btn btn-outline-secondary">Reset</a>
                        <a href="pdf.php" class="btn btn-danger">PDF</a>
                        <a href="export.php" class="btn btn-success">Excel</a>
                    </div>
                </form>

                <div class="table-responsive">
                    <table class="table table-hover table-bordered align-middle">
                        <thead class="table-dark">
                            <tr>
                                <th width="70">Photo</th>
                                <th>Employee ID</th>
                                <th>Name</th>
                                <th>Department</th>
                                <th>Role</th>
                                <th>Warehouse</th>
                                <th>Mobile</th>
                                <th>Status</th>
                                <th width="200">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr>
                                        <td class="text-center">
                                            <img src="../../../assets/images/employees/<?= (empty($row['photo']) || $row['photo'] == 'default.png') ? 'default.png' : htmlspecialchars($row['photo']); ?>" width="45" height="45" style="border-radius:50%;object-fit:cover;" alt="Avatar">
                                        </td>
                                        <td>
                                            <?php if (str_starts_with($row['employee_id'], "Z")): ?>
                                                <span class="badge bg-primary"><?= htmlspecialchars($row['employee_id']); ?></span>
                                            <?php else: ?>
                                                <span class="badge bg-success"><?= htmlspecialchars($row['employee_id']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td><strong><?= htmlspecialchars($row['full_name']); ?></strong></td>
                                        <td><?= htmlspecialchars($row['department']); ?></td>
                                        <td><?= htmlspecialchars($row['role']); ?></td>
                                        <td><?= htmlspecialchars($row['warehouse']); ?></td>
                                        <td><?= htmlspecialchars($row['mobile']); ?></td>
                                        <td>
                                            <?php if ($row['status'] == "Active"): ?>
                                                <span class="badge bg-success">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Inactive</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="view.php?id=<?= $row['id']; ?>" class="btn btn-info btn-sm">View</a>
                                            <a href="edit.php?id=<?= $row['id']; ?>" class="btn btn-warning btn-sm">Edit</a>
                                            <a href="delete.php?id=<?= (int)$row['id']; ?>"class="btn btn-sm btn-danger"onclick="return confirm('Are you sure you want to delete this employee?');"><i class="fa-solid fa-trash"></i></a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center">No Employee Found</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php 
$stmt->close();
include "../../../includes/footer.php"; 
?>