<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

// Fetch Leave Record ID
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// Fetch Current Leave Details
$leaveQuery = mysqli_query($conn, "SELECT * FROM leaves WHERE id = '$id'");
if (!$leaveQuery || mysqli_num_rows($leaveQuery) == 0) {
    $_SESSION['error'] = "Leave record not found.";
    header("Location: index.php");
    exit();
}

$leave = mysqli_fetch_assoc($leaveQuery);

// Fetch Employees List
$employees = mysqli_query($conn, "
    SELECT id, employee_id, full_name 
    FROM employees 
    WHERE status='Active' 
    ORDER BY full_name ASC
");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Edit Leave Application
                </h2>
                <p class="text-muted mb-0">Update leave request details or status (#<?= $leave['id']; ?>)</p>
            </div>
            <div>
                <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <form action="update.php" method="POST">
            <!-- Hidden Leave ID -->
            <input type="hidden" name="id" value="<?= $leave['id']; ?>">

            <div class="card shadow-sm border-0 rounded-3">
                <div class="card-body p-4">
                    <div class="row g-3">

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Employee <span class="text-danger">*</span></label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">-- Select Employee --</option>
                                <?php while ($emp = mysqli_fetch_assoc($employees)): ?>
                                    <option value="<?= $emp['id']; ?>" <?= ($emp['id'] == $leave['employee_id']) ? 'selected' : ''; ?>>
                                        <?= htmlspecialchars($emp['employee_id']); ?> - <?= htmlspecialchars($emp['full_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Leave Type <span class="text-danger">*</span></label>
                            <select name="leave_type" class="form-select" required>
                                <option value="Casual Leave" <?= ($leave['leave_type'] == 'Casual Leave') ? 'selected' : ''; ?>>Casual Leave</option>
                                <option value="Sick Leave" <?= ($leave['leave_type'] == 'Sick Leave') ? 'selected' : ''; ?>>Sick Leave</option>
                                <option value="Earn Leave" <?= ($leave['leave_type'] == 'Earn Leave') ? 'selected' : ''; ?>>Earn Leave</option>
                                <option value="Half Day" <?= ($leave['leave_type'] == 'Half Day') ? 'selected' : ''; ?>>Half Day</option>
                                <option value="Emergency Leave" <?= ($leave['leave_type'] == 'Emergency Leave') ? 'selected' : ''; ?>>Emergency Leave</option>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Status <span class="text-danger">*</span></label>
                            <select name="status" class="form-select fw-semibold" required>
                                <option value="Pending" <?= ($leave['status'] == 'Pending') ? 'selected' : ''; ?>>⏳ Pending</option>
                                <option value="Approved" <?= ($leave['status'] == 'Approved') ? 'selected' : ''; ?>>✅ Approved</option>
                                <option value="Rejected" <?= ($leave['status'] == 'Rejected') ? 'selected' : ''; ?>>❌ Rejected</option>
                            </select>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">From Date <span class="text-danger">*</span></label>
                            <input type="date" id="from_date" name="from_date" class="form-control" value="<?= $leave['from_date']; ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">To Date <span class="text-danger">*</span></label>
                            <input type="date" id="to_date" name="to_date" class="form-control" value="<?= $leave['to_date']; ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Total Days</label>
                            <input type="number" id="total_days" name="total_days" class="form-control bg-light" readonly value="<?= $leave['total_days']; ?>">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Reason / Remarks <span class="text-danger">*</span></label>
                            <textarea name="reason" class="form-control" rows="3" required><?= htmlspecialchars($leave['reason']); ?></textarea>
                        </div>

                    </div>

                    <hr class="my-4">

                    <div class="text-end">
                        <button type="submit" class="btn btn-warning px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Update Leave</button>
                        <a href="index.php" class="btn btn-outline-secondary px-3">Cancel</a>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>

<script>
function calculateDays() {
    let from = document.getElementById("from_date").value;
    let to   = document.getElementById("to_date").value;

    if (from && to) {
        let d1 = new Date(from);
        let d2 = new Date(to);

        if (d2 < d1) {
            alert("'To Date' cannot be earlier than 'From Date'");
            document.getElementById("to_date").value = "";
            document.getElementById("total_days").value = 0;
            return;
        }

        let diff = (d2 - d1) / (1000 * 60 * 60 * 24) + 1;
        document.getElementById("total_days").value = diff > 0 ? diff : 0;
    }
}

document.getElementById("from_date").addEventListener("change", function() {
    document.getElementById("to_date").min = this.value;
    calculateDays();
});

document.getElementById("to_date").addEventListener("change", calculateDays);
</script>