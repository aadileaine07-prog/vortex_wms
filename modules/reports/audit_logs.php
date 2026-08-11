<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

// Fetch Audit Logs
$query = "SELECT * FROM audit_logs ORDER BY id DESC LIMIT 100";
$result = mysqli_query($conn, $query);

include "../../includes/header.php";
include "../../includes/navbar.php";
include "../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-clock-rotate-left text-primary me-2"></i>Audit Trail & Activity Log
                </h2>
                <p class="text-muted mb-0">System activity tracking for security and operational oversight</p>
            </div>
            <div>
                <button onclick="window.print()" class="btn btn-outline-dark px-3">
                    <i class="fa-solid fa-print me-1"></i> Print Log
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm rounded-3">
            <div class="card-body p-4">
                <div class="table-responsive">
                    <table class="table table-hover align-middle border">
                        <thead class="table-dark">
                            <tr>
                                <th>Date & Time</th>
                                <th>Employee</th>
                                <th>Module</th>
                                <th>Action</th>
                                <th>Description</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($result)): ?>
                                    <?php
                                    $badgeClass = 'bg-secondary';
                                    if ($row['action_type'] == 'CREATE') $badgeClass = 'bg-success';
                                    elseif ($row['action_type'] == 'UPDATE') $badgeClass = 'bg-warning text-dark';
                                    elseif ($row['action_type'] == 'DELETE') $badgeClass = 'bg-danger';
                                    elseif ($row['action_type'] == 'LOGIN') $badgeClass = 'bg-info text-dark';
                                    ?>
                                    <tr>
                                        <td class="small font-monospace"><?= date("d M Y, h:i A", strtotime($row['created_at'])); ?></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['employee_name']); ?></strong><br>
                                            <small class="text-muted font-monospace"><?= htmlspecialchars($row['employee_id']); ?></small>
                                        </td>
                                        <td><span class="badge bg-light text-dark border"><?= htmlspecialchars($row['module_name']); ?></span></td>
                                        <td><span class="badge <?= $badgeClass; ?>"><?= $row['action_type']; ?></span></td>
                                        <td><?= htmlspecialchars($row['description']); ?></td>
                                        <td class="small font-monospace text-muted"><?= htmlspecialchars($row['ip_address']); ?></td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center text-muted py-4">No Audit Activity Recorded Yet</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

    </div>
</div>

<?php include "../../includes/footer.php"; ?>