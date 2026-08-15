<?php
session_start();

// Dynamic Project Root Path
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

if (!isset($_GET['id']) || empty($_GET['id'])) {
    die("Payroll ID Missing.");
}

$payroll_id = intval($_GET['id']);

// 1. Detect Employee Table Columns Safely
$checkEmpCols = mysqli_query($conn, "SHOW COLUMNS FROM employees");
$empCols = [];
if ($checkEmpCols) {
    while ($c = mysqli_fetch_assoc($checkEmpCols)) {
        $empCols[] = $c['Field'];
    }
}

if (in_array('name', $empCols)) {
    $empNameSelect = "e.name";
} elseif (in_array('first_name', $empCols) && in_array('last_name', $empCols)) {
    $empNameSelect = "CONCAT_WS(' ', e.first_name, e.last_name)";
} elseif (in_array('full_name', $empCols)) {
    $empNameSelect = "e.full_name";
} elseif (in_array('username', $empCols)) {
    $empNameSelect = "e.username";
} else {
    $empNameSelect = "CONCAT('Employee #', e.id)";
}

$roleSelect  = in_array('role', $empCols) ? "e.role" : "'Warehouse Staff'";
$emailSelect = in_array('email', $empCols) ? "e.email" : "'-'";
$phoneSelect = in_array('phone', $empCols) ? "e.phone" : "'-'";

// 2. Fetch Payroll Details
$query = mysqli_query($conn, "
    SELECT 
        p.*, 
        {$empNameSelect} AS emp_name, 
        {$roleSelect} AS role, 
        {$emailSelect} AS email, 
        {$phoneSelect} AS phone
    FROM payroll p
    LEFT JOIN employees e ON p.employee_id = e.id
    WHERE p.id = '$payroll_id'
");

if (!$query || mysqli_num_rows($query) == 0) {
    die("Payroll record not found.");
}

$pay = mysqli_fetch_assoc($query);

// Safe extraction of leaves data
$paid_leaves       = intval($pay['paid_leaves'] ?? 0);
$paid_leave_amount = floatval($pay['paid_leave_amount'] ?? 0);
$absent_days       = intval($pay['absent_days'] ?? 0);
$absent_deduction  = floatval($pay['absent_deduction'] ?? 0);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Salary Slip - <?= htmlspecialchars($pay['emp_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Google Fonts for Digital Signature -->
    <link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Playfair+Display:ital,wght@1,600&display=swap" rel="stylesheet">
    <style>
        body { 
            background-color: #f4f6f9; 
            font-family: 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; 
        }
        .slip-card { 
            max-width: 840px; 
            margin: 30px auto; 
            background: #ffffff; 
            padding: 45px; 
            border-radius: 16px; 
            box-shadow: 0 10px 30px rgba(0,0,0,0.08); 
            position: relative;
        }
        .digital-signature {
            font-family: 'Great Vibes', cursive;
            font-size: 38px;
            color: #0b3c75;
            line-height: 1;
            transform: rotate(-4deg);
            display: inline-block;
            margin-bottom: -8px;
            user-select: none;
        }
        .verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 11px;
            background: #e8f5e9;
            color: #2e7d32;
            padding: 2px 8px;
            border-radius: 20px;
            font-weight: bold;
            margin-top: 4px;
            border: 1px solid #c8e6c9;
        }
        @media print {
            .no-print { display: none !important; }
            body { background: #fff; }
            .slip-card { box-shadow: none; border: 1px solid #ddd; padding: 25px; margin: 0 auto; }
        }
    </style>
</head>
<body>

<div class="container pb-5">
    <!-- Action Buttons -->
    <div class="no-print text-center my-4">
        <button onclick="window.print()" class="btn btn-primary px-4 fw-bold me-2 shadow-sm">
            <i class="fa-solid fa-print me-1"></i> Print Payslip
        </button>
        <a href="index.php" class="btn btn-outline-secondary px-4">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Payroll
        </a>
    </div>

    <div class="slip-card">
        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center border-bottom pb-4 mb-4">
            <div>
                <h2 class="fw-bold text-primary mb-0">VORTEX WMS</h2>
                <span class="text-muted small">Enterprise Warehouse Management System</span>
            </div>
            <div class="text-end">
                <h3 class="fw-bold text-dark mb-1">SALARY PAYSLIP</h3>
                <span class="badge bg-dark font-monospace fs-6 px-3 py-2">
                    <?= htmlspecialchars($pay['month']) . ' ' . htmlspecialchars($pay['year']); ?>
                </span>
            </div>
        </div>

        <!-- Employee Info Section -->
        <div class="row g-3 mb-4 bg-light p-3 rounded-4 border">
            <div class="col-sm-6">
                <small class="text-muted text-uppercase fw-bold d-block mb-1">Employee Name</small>
                <strong class="fs-6 text-dark"><?= htmlspecialchars($pay['emp_name']); ?></strong>
            </div>
            <div class="col-sm-6">
                <small class="text-muted text-uppercase fw-bold d-block mb-1">Designation / Role</small>
                <strong class="fs-6 text-dark"><?= htmlspecialchars($pay['role'] ?? 'Staff'); ?></strong>
            </div>
            <div class="col-sm-6">
                <small class="text-muted text-uppercase fw-bold d-block mb-1">Payment Date</small>
                <strong><?= !empty($pay['payment_date']) ? date("d M Y", strtotime($pay['payment_date'])) : '-'; ?></strong>
            </div>
            <div class="col-sm-6">
                <small class="text-muted text-uppercase fw-bold d-block mb-1">Payment Status</small>
                <?php if ($pay['status'] == 'Paid'): ?>
                    <span class="badge bg-success px-3 py-1">PAID</span>
                <?php else: ?>
                    <span class="badge bg-warning text-dark px-3 py-1">PENDING</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- Earnings & Deductions Breakdown -->
        <table class="table table-bordered align-middle mb-4">
            <thead class="table-dark">
                <tr>
                    <th>Earnings</th>
                    <th width="150" class="text-end">Amount (₹)</th>
                    <th>Deductions</th>
                    <th width="150" class="text-end">Amount (₹)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <strong>Basic Salary</strong>
                    </td>
                    <td class="text-end font-monospace">₹<?= number_format($pay['basic_salary'], 2); ?></td>
                    <td>
                        <strong>Absent / Unpaid Leave</strong>
                        <?php if ($absent_days > 0): ?>
                            <br><small class="text-danger fw-semibold"><?= $absent_days; ?> Day(s) Absent</small>
                        <?php else: ?>
                            <br><small class="text-muted">0 Days Absent</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end font-monospace text-danger">
                        -₹<?= number_format($absent_deduction, 2); ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Paid Leaves (Encashment)</strong>
                        <?php if ($paid_leaves > 0): ?>
                            <br><small class="text-success fw-semibold"><?= $paid_leaves; ?> Day(s) Paid Leave Added</small>
                        <?php else: ?>
                            <br><small class="text-muted">No Paid Leave Added</small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end font-monospace text-success">
                        +₹<?= number_format($paid_leave_amount, 2); ?>
                    </td>
                    <td>
                        <strong>Other Deductions</strong>
                        <br><small class="text-muted">(PF, Tax, Advance, etc.)</small>
                    </td>
                    <td class="text-end font-monospace text-danger">
                        -₹<?= number_format(max(0, $pay['deductions'] - $absent_deduction), 2); ?>
                    </td>
                </tr>
                <tr>
                    <td>
                        <strong>Allowances Breakdown</strong>
                        <?php if (!empty($pay['allowance_type'])): ?>
                            <br><small class="text-muted"><?= htmlspecialchars($pay['allowance_type']); ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-end font-monospace text-success">
                        +₹<?= number_format(max(0, $pay['allowances'] - $paid_leave_amount), 2); ?>
                    </td>
                    <td>-</td>
                    <td class="text-end font-monospace">-</td>
                </tr>
            </tbody>
            <tfoot class="table-light fs-5">
                <tr>
                    <th colspan="3" class="text-end fw-bold text-dark">Net Salary Payable:</th>
                    <th class="text-end text-primary fw-bold font-monospace">₹<?= number_format($pay['net_salary'], 2); ?></th>
                </tr>
            </tfoot>
        </table>

        <!-- Signatures Section with Aadil's Digital Sign -->
        <div class="row mt-5 pt-3 align-items-end text-center">
            <div class="col-6">
                <div class="pb-3">
                    <p class="mb-4 text-muted">___________________________</p>
                    <p class="fw-bold mb-0 text-dark">Employee Signature</p>
                    <small class="text-muted">(Received & Acknowledged)</small>
                </div>
            </div>

            <!-- Authorized HR Section with Aadil Digital Sign -->
            <div class="col-6">
                <div class="d-flex flex-column align-items-center">
                    <div class="digital-signature">
                        Aadil Raine
                    </div>
                    <div class="verified-badge">
                        <i class="fa-solid fa-circle-check"></i> Digitally Signed & Approved
                    </div>
                    <p class="mb-0 mt-2 text-muted">___________________________</p>
                    <p class="fw-bold mb-0 text-dark">Authorized HR Signature</p>
                    <small class="text-muted fw-semibold">Aadil Raine (HR & Operations Head)</small>
                </div>
            </div>
        </div>

    </div>
</div>

</body>
</html>