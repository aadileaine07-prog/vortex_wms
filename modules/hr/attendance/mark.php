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

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-clock me-2 text-primary"></i>Mark Attendance</h2>
                <p class="text-muted mb-0">Record daily check-in & check-out times</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <form action="save.php" method="POST">
            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Employee <span class="text-danger">*</span></label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">-- Choose Employee --</option>
                                <?php while ($emp = mysqli_fetch_assoc($employees)): ?>
                                    <option value="<?= $emp['id']; ?>">
                                        <?= htmlspecialchars($emp['employee_id']); ?> - <?= htmlspecialchars($emp['full_name']); ?> (<?= htmlspecialchars($emp['department']); ?>)
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Attendance Date <span class="text-danger">*</span></label>
                            <input type="date" name="attendance_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select" required>
                                <option value="Present">Present</option>
                                <option value="Absent">Absent</option>
                                <option value="Leave">Leave</option>
                                <option value="Half Day">Half Day</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Check In Time <span class="text-danger">*</span></label>
                            <input type="time" name="check_in" class="form-control" required>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Check Out Time</label>
                            <input type="time" name="check_out" class="form-control">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Remarks</label>
                            <input type="text" name="remarks" class="form-control" placeholder="Optional notes...">
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="text-end">
                        <button type="submit" class="btn btn-primary px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Save Attendance</button>
                        <a href="index.php" class="btn btn-outline-secondary px-3">Cancel</a>
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