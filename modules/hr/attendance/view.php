<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") 
    ? dirname(__DIR__, 3) 
    : (file_exists(__DIR__ . "/../../../../config/database.php") ? dirname(__DIR__, 4) : dirname(__DIR__, 2));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Attendance ID is missing.";
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

$query = mysqli_query($conn, "
    SELECT a.*, e.full_name, e.employee_id AS emp_code, e.department, e.designation, e.email, e.phone, e.photo
    FROM attendance a
    JOIN employees e ON e.id = a.employee_id
    WHERE a.id = '$id'
    LIMIT 1
");

if (!$query || mysqli_num_rows($query) === 0) {
    $_SESSION['error'] = "Attendance record not found.";
    header("Location: index.php");
    exit();
}

$att = mysqli_fetch_assoc($query);

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-eye text-info me-2"></i>Attendance Details</h2>
            <p class="text-muted mb-0">Detailed time log view for attendance ID #<?= $att['id']; ?></p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary fw-bold rounded-pill px-3 shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to List</a>
        </div>
    </div>

    <div class="row g-4">
        <!-- Employee Profile Quick Card -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white text-center p-4 h-100">
                <div class="card-body">
                    <img src="../../../assets/images/employees/<?= empty($att['photo']) ? 'default-user.png' : htmlspecialchars($att['photo']); ?>" 
                         width="100" height="100" class="rounded-circle shadow-sm mb-3" style="object-fit:cover; border: 3px solid #f8f9fa;" onerror="this.src='../../../assets/images/employees/default-user.png'">
                    <h4 class="fw-bold text-dark mb-1"><?= htmlspecialchars($att['full_name']); ?></h4>
                    <code class="text-primary fw-bold font-monospace"><?= htmlspecialchars($att['emp_code']); ?></code>
                    <p class="text-muted small mt-2 mb-3"><?= htmlspecialchars($att['designation'] ?? 'Staff'); ?> &bull; <?= htmlspecialchars($att['department'] ?? 'General'); ?></p>
                    
                    <div class="text-start border-top pt-3 mt-3">
                        <p class="mb-2 small text-muted"><i class="fa-solid fa-envelope me-2 text-secondary"></i> <?= htmlspecialchars($att['email'] ?? 'N/A'); ?></p>
                        <p class="mb-0 small text-muted"><i class="fa-solid fa-phone me-2 text-secondary"></i> <?= htmlspecialchars($att['phone'] ?? 'N/A'); ?></p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Detailed Attendance Data Card -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 bg-white h-100">
                <div class="card-body p-4">
                    <h5 class="fw-bold text-dark mb-4 border-bottom pb-2"><i class="fa-solid fa-clipboard-user me-2 text-primary"></i>Shift Summary</h5>

                    <div class="row g-3 mb-4">
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold text-uppercase">Attendance Date</label>
                            <div class="fw-bold font-monospace fs-5 text-dark"><?= htmlspecialchars($att['attendance_date']); ?></div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold text-uppercase">Current Status</label>
                            <div>
                                <?php 
                                    $st = strtolower($att['status']);
                                    $badgeClass = ($st == 'present') ? 'bg-success' : (($st == 'absent') ? 'bg-danger' : 'bg-warning text-dark');
                                ?>
                                <span class="badge <?= $badgeClass; ?> px-3 py-2 fs-6 rounded-pill"><?= strtoupper($att['status']); ?></span>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold text-uppercase">Check-In Time</label>
                            <div class="fw-bold font-monospace fs-5 text-success">
                                <i class="fa-solid fa-right-to-bracket me-1"></i> <?= $att['check_in'] ? date('h:i:s A', strtotime($att['check_in'])) : 'Not Logged'; ?>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label small text-muted fw-bold text-uppercase">Check-Out Time</label>
                            <div class="fw-bold font-monospace fs-5 text-danger">
                                <i class="fa-solid fa-right-from-bracket me-1"></i> <?= $att['check_out'] ? date('h:i:s A', strtotime($att['check_out'])) : '<span class="text-warning small fw-semibold">Active Shift</span>'; ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-4">
                        <label class="form-label small text-muted fw-bold text-uppercase">Remarks / Notes</label>
                        <div class="p-3 bg-light rounded-3 text-secondary border">
                            <?= !empty($att['remarks']) ? nl2br(htmlspecialchars($att['remarks'])) : '<em class="text-muted">No remarks provided for this record.</em>'; ?>
                        </div>
                    </div>

                    <div class="d-flex justify-content-end gap-2 border-top pt-3">
                        <a href="edit.php?id=<?= $att['id']; ?>" class="btn btn-warning fw-bold px-4 rounded-pill text-dark"><i class="fa-solid fa-pen-to-square me-1"></i> Edit Record</a>
                        <a href="delete.php?id=<?= $att['id']; ?>" class="btn btn-outline-danger fw-bold px-4 rounded-pill" onclick="return confirm('Are you sure you want to delete this record?');"><i class="fa-solid fa-trash me-1"></i> Delete</a>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>