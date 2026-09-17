<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$employees = mysqli_query($conn, "
    SELECT id, employee_id, full_name, department
    FROM employees
    WHERE status='Active'
    ORDER BY full_name
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-clock me-2 text-primary"></i>Mark Attendance</h2>
                <p class="text-muted mb-0">Record daily check-in & check-out times</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary px-3 fw-bold rounded-pill shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to List</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="save.php" method="POST">
            <div class="card shadow-sm border-0 rounded-4 bg-white">
                <div class="card-body p-4">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Select Employee <span class="text-danger">*</span></label>
                            <select name="employee_id" class="form-select border-2 fw-semibold" required>
                                <option value="">-- Choose Employee --</option>
                                <?php while ($emp = mysqli_fetch_assoc($employees)): ?>
                                    <option value="<?= $emp['id']; ?>">
                                        <?= htmlspecialchars($emp['employee_id']); ?> - <?= htmlspecialchars($emp['full_name']); ?> (<?= htmlspecialchars($emp['department']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Attendance Date <span class="text-danger">*</span></label>
                            <input type="date" name="attendance_date" class="form-control border-2 fw-semibold" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select border-2 fw-semibold" required>
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Leave">Leave</option>
                                <option value="Half Day">Half Day</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Check In Time <span class="text-danger">*</span></label>
                            <input type="time" name="check_in" class="form-control border-2 fw-semibold" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-bold small text-muted">Check Out Time</label>
                            <input type="time" name="check_out" class="form-control border-2 fw-semibold">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-bold small text-muted">Remarks</label>
                            <input type="text" name="remarks" class="form-control border-2" placeholder="Optional notes...">
                        </div>

                    </div>

                    <hr class="my-4 text-muted">

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4 fw-bold rounded-pill"><i class="fa-solid fa-floppy-disk me-1"></i> Save Attendance</button>
                        <a href="index.php" class="btn btn-outline-secondary px-3 fw-bold rounded-pill">Cancel</a>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>

<script>
document.addEventListener("DOMContentLoaded", function(){
    const checkIn = document.querySelector("input[name='check_in']");
    const checkOut = document.querySelector("input[name='check_out']");

    if(checkIn && checkOut){
        checkOut.addEventListener("change", function(){
            if(checkIn.value && checkOut.value && checkOut.value < checkIn.value){
                alert("Check Out time cannot be earlier than Check In time.");
                checkOut.value = "";
            }
        });
    }
});
</script>