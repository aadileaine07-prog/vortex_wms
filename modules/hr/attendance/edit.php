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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $attendance_date = mysqli_real_escape_string($conn, $_POST['attendance_date']);
    $check_in = !empty($_POST['check_in']) ? mysqli_real_escape_string($conn, $_POST['check_in']) : NULL;
    $check_out = !empty($_POST['check_out']) ? mysqli_real_escape_string($conn, $_POST['check_out']) : NULL;
    $status = mysqli_real_escape_string($conn, $_POST['status']);
    $remarks = mysqli_real_escape_string($conn, $_POST['remarks']);

    $updateQuery = "UPDATE attendance SET attendance_date = '$attendance_date', check_in = " . ($check_in ? "'$check_in'" : "NULL") . ", check_out = " . ($check_out ? "'$check_out'" : "NULL") . ", status = '$status', remarks = '$remarks' WHERE id = '$id'";
    
    if (mysqli_query($conn, $updateQuery)) {
        $_SESSION['success'] = "Attendance record updated successfully.";
        header("Location: index.php");
        exit();
    } else {
        $errorMsg = "Failed to update attendance: " . mysqli_error($conn);
    }
}

$query = mysqli_query($conn, "SELECT a.*, e.full_name, e.employee_id AS emp_code FROM attendance a JOIN employees e ON e.id = a.employee_id WHERE a.id = '$id' LIMIT 1");
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
            <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Attendance</h2>
            <p class="text-muted mb-0">Modify time logs for <strong><?= htmlspecialchars($att['full_name']); ?></strong> (<?= htmlspecialchars($att['emp_code']); ?>)</p>
        </div>
        <div>
            <a href="index.php" class="btn btn-outline-secondary fw-bold rounded-pill px-3 shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to List</a>
        </div>
    </div>

    <?php if (isset($errorMsg)): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $errorMsg; ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm border-0 rounded-4 bg-white">
        <div class="card-body p-4">
            <form method="POST" class="row g-3">
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted">Attendance Date</label>
                    <input type="date" name="attendance_date" class="form-control border-2 fw-semibold" value="<?= htmlspecialchars($att['attendance_date']); ?>" required>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted">Status</label>
                    <select name="status" class="form-select border-2 fw-semibold" required>
                        <option value="Present" <?= ($att['status'] == 'Present') ? 'selected' : ''; ?>>Present</option>
                        <option value="Absent" <?= ($att['status'] == 'Absent') ? 'selected' : ''; ?>>Absent</option>
                        <option value="Leave" <?= ($att['status'] == 'Leave') ? 'selected' : ''; ?>>Leave</option>
                        <option value="Half Day" <?= ($att['status'] == 'Half Day') ? 'selected' : ''; ?>>Half Day</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted">Check-In Time</label>
                    <input type="time" name="check_in" class="form-control border-2 fw-semibold" value="<?= $att['check_in'] ? date('H:i', strtotime($att['check_in'])) : ''; ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label small fw-bold text-muted">Check-Out Time</label>
                    <input type="time" name="check_out" class="form-control border-2 fw-semibold" value="<?= $att['check_out'] ? date('H:i', strtotime($att['check_out'])) : ''; ?>">
                </div>
                <div class="col-md-12">
                    <label class="form-label small fw-bold text-muted">Remarks / Notes</label>
                    <textarea name="remarks" class="form-control border-2" rows="3"><?= htmlspecialchars($att['remarks'] ?? ''); ?></textarea>
                </div>
                <div class="col-md-12 text-end mt-4">
                    <button type="submit" class="btn btn-warning px-4 fw-bold rounded-pill text-dark"><i class="fa-solid fa-floppy-disk me-1"></i> Update Attendance</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>