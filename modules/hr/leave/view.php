<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

// Get Leave ID from URL
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header("Location: index.php");
    exit();
}

$id = intval($_GET['id']);

// Fetch Leave & Employee Details
$query = "
    SELECT leaves.*, 
           employees.employee_id AS emp_code, 
           employees.full_name, 
           employees.department, 
           employees.photo,
           employees.email
    FROM leaves
    INNER JOIN employees ON employees.id = leaves.employee_id
    WHERE leaves.id = '$id'
";

$result = mysqli_query($conn, $query);

if (!$result || mysqli_num_rows($result) == 0) {
    $_SESSION['error'] = "Leave record not found.";
    header("Location: index.php");
    exit();
}

$leave = mysqli_fetch_assoc($result);

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Navigation & Action Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-file-lines text-primary me-2"></i>Leave Application Details
                </h2>
                <p class="text-muted mb-0">Application ID: #<?= $leave['id']; ?></p>
            </div>
            <div class="d-flex gap-2">
                <button onclick="window.print();" class="btn btn-dark"><i class="fa-solid fa-print me-1"></i> Print</button>
                <a href="index.php" class="btn btn-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
            </div>
        </div>

        <div class="row g-4">
            
            <!-- Employee Profile Info Card -->
            <div class="col-lg-4">
                <div class="card shadow-sm border-0 rounded-3 text-center p-4">
                    <div class="mb-3">
                        <img src="../../../assets/images/employees/<?= empty($leave['photo']) ? 'default-user.png' : htmlspecialchars($leave['photo']); ?>" 
                             alt="Employee Photo" 
                             class="rounded-circle shadow-sm border" 
                             width="110" height="110" style="object-fit: cover;">
                    </div>
                    <h5 class="fw-bold mb-1"><?= htmlspecialchars($leave['full_name']); ?></h5>
                    <p class="text-muted small mb-2"><?= htmlspecialchars($leave['department']); ?></p>
                    <span class="badge bg-primary px-3 py-2 rounded-pill">ID: <?= htmlspecialchars($leave['emp_code']); ?></span>

                    <hr class="my-3">

                    <div class="text-start small text-muted">
                        <div class="mb-2"><i class="fa-solid fa-envelope me-2"></i><?= htmlspecialchars($leave['email'] ?? 'N/A'); ?></div>
                        <div><i class="fa-solid fa-building me-2"></i>Department: <?= htmlspecialchars($leave['department']); ?></div>
                    </div>
                </div>
            </div>

            <!-- Leave Details Card -->
            <div class="col-lg-8">
                <div class="card shadow-sm border-0 rounded-3 h-100">
                    <div class="card-body p-4">
                        
                        <!-- Status Badge Top Right -->
                        <div class="d-flex justify-content-between align-items-center mb-4">
                            <h5 class="fw-bold text-dark mb-0">Application Summary</h5>
                            <div>
                                <?php
                                $status = $leave['status'];
                                if ($status == 'Pending') {
                                    echo "<span class='badge bg-warning text-dark fs-6 px-3 py-2'><i class='fa-solid fa-clock me-1'></i> Pending Approval</span>";
                                } elseif ($status == 'Approved') {
                                    echo "<span class='badge bg-success fs-6 px-3 py-2'><i class='fa-solid fa-circle-check me-1'></i> Approved</span>";
                                } else {
                                    echo "<span class='badge bg-danger fs-6 px-3 py-2'><i class='fa-solid fa-circle-xmark me-1'></i> Rejected</span>";
                                }
                                ?>
                            </div>
                        </div>

                        <div class="row g-3">
                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block text-uppercase fw-semibold">Leave Type</small>
                                    <span class="fw-bold text-dark fs-6"><?= htmlspecialchars($leave['leave_type']); ?></span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block text-uppercase fw-semibold">Duration (Days)</small>
                                    <span class="fw-bold text-primary fs-6"><?= $leave['total_days']; ?> Day(s)</span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block text-uppercase fw-semibold">From Date</small>
                                    <span class="fw-semibold text-dark"><i class="fa-regular fa-calendar me-2 text-primary"></i><?= date("d M, Y", strtotime($leave['from_date'])); ?></span>
                                </div>
                            </div>

                            <div class="col-md-6">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block text-uppercase fw-semibold">To Date</small>
                                    <span class="fw-semibold text-dark"><i class="fa-regular fa-calendar me-2 text-primary"></i><?= date("d M, Y", strtotime($leave['to_date'])); ?></span>
                                </div>
                            </div>

                            <div class="col-md-12">
                                <div class="p-3 bg-light rounded-3">
                                    <small class="text-muted d-block text-uppercase fw-semibold mb-1">Reason / Remarks</small>
                                    <p class="mb-0 text-dark"><?= nl2br(htmlspecialchars($leave['reason'])); ?></p>
                                </div>
                            </div>
                        </div>

                        <hr class="my-4">

                        <div class="d-flex justify-content-end gap-2">
                            <a href="edit.php?id=<?= $leave['id']; ?>" class="btn btn-warning px-4"><i class="fa-solid fa-pen-to-square me-1"></i> Edit Leave</a>
                            <a href="delete.php?id=<?= $leave['id']; ?>" class="btn btn-danger px-3" onclick="return confirm('Are you sure you want to delete this record?');"><i class="fa-solid fa-trash me-1"></i> Delete</a>
                        </div>

                    </div>
                </div>
            </div>

        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>