<?php
session_start();

// Dynamic Project Root Path
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// 1. Detect Available Columns in Employees Table Safely
$checkEmpCols = mysqli_query($conn, "SHOW COLUMNS FROM employees");
$empCols = [];
if ($checkEmpCols) {
    while ($c = mysqli_fetch_assoc($checkEmpCols)) {
        $empCols[] = $c['Field'];
    }
}

if (in_array('name', $empCols)) {
    $empNameSelect = "e.name";
    $empNameSingle = "name AS full_name";
} elseif (in_array('first_name', $empCols) && in_array('last_name', $empCols)) {
    $empNameSelect = "CONCAT_WS(' ', e.first_name, e.last_name)";
    $empNameSingle = "CONCAT_WS(' ', first_name, last_name) AS full_name";
} elseif (in_array('full_name', $empCols)) {
    $empNameSelect = "e.full_name";
    $empNameSingle = "full_name";
} elseif (in_array('username', $empCols)) {
    $empNameSelect = "e.username";
    $empNameSingle = "username AS full_name";
} else {
    $empNameSelect = "CONCAT('Employee #', e.id)";
    $empNameSingle = "CONCAT('Employee #', id) AS full_name";
}

$roleSelect = in_array('role', $empCols) ? "e.role AS emp_role" : "'Staff' AS emp_role";
$roleSingle = in_array('role', $empCols) ? "role" : "'Staff' AS role";

// 2. Handle Payroll Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_payroll'])) {
    $emp_id         = intval($_POST['employee_id']);
    $month          = mysqli_real_escape_string($conn, trim($_POST['month']));
    $year           = intval($_POST['year']);
    $basic          = floatval($_POST['basic_salary']);
    $paid_leaves    = intval($_POST['paid_leaves'] ?? 0);
    $absent_days    = intval($_POST['absent_days'] ?? 0);
    $other_deduct   = floatval($_POST['other_deductions'] ?? 0);
    $pay_date       = mysqli_real_escape_string($conn, $_POST['payment_date']);
    $status         = mysqli_real_escape_string($conn, $_POST['status']);

    // Per day rate calculation (based on 30 working days)
    $daily_rate     = ($basic > 0) ? ($basic / 30) : 0;
    $paid_leave_amt = round($paid_leaves * $daily_rate, 2);
    $absent_deduct  = round($absent_days * $daily_rate, 2);

    // Process Multiple Allowances
    $total_allowance = 0;
    $allowance_breakdown = [];

    if ($paid_leave_amt > 0) {
        $allowance_breakdown[] = "Paid Leaves ({$paid_leaves} Days): ₹" . number_format($paid_leave_amt, 2);
    }

    if (isset($_POST['allowance_amount']) && is_array($_POST['allowance_amount'])) {
        $types   = $_POST['allowance_type'] ?? [];
        $amounts = $_POST['allowance_amount'];

        for ($i = 0; $i < count($amounts); $i++) {
            $amt  = floatval($amounts[$i] ?? 0);
            $type = trim($types[$i] ?? 'Allowance');
            if ($amt > 0) {
                $total_allowance += $amt;
                $allowance_breakdown[] = "$type: ₹" . number_format($amt, 2);
            }
        }
    }

    $final_allowances = $total_allowance + $paid_leave_amt;
    $final_deductions = $other_deduct + $absent_deduct;
    $allowance_summary_str = mysqli_real_escape_string($conn, implode(", ", $allowance_breakdown));

    // Final Net Salary Calculation
    $net_salary = max(0, ($basic + $final_allowances) - $final_deductions);

    if ($emp_id <= 0 || $basic <= 0) {
        $_SESSION['error'] = "Please select a valid employee and enter a basic salary.";
    } else {
        // Safe check for schema columns
        $checkCols = mysqli_query($conn, "SHOW COLUMNS FROM payroll");
        $cols = [];
        while ($c = mysqli_fetch_assoc($checkCols)) { $cols[] = $c['Field']; }

        if (in_array('paid_leaves', $cols) && in_array('allowance_type', $cols)) {
            $insertQuery = "
                INSERT INTO payroll (employee_id, month, year, basic_salary, paid_leaves, paid_leave_amount, absent_days, absent_deduction, allowance_type, allowances, deductions, net_salary, payment_date, status)
                VALUES ('$emp_id', '$month', '$year', '$basic', '$paid_leaves', '$paid_leave_amt', '$absent_days', '$absent_deduct', '$allowance_summary_str', '$final_allowances', '$final_deductions', '$net_salary', '$pay_date', '$status')
            ";
        } else {
            $insertQuery = "
                INSERT INTO payroll (employee_id, month, year, basic_salary, allowances, deductions, net_salary, payment_date, status)
                VALUES ('$emp_id', '$month', '$year', '$basic', '$final_allowances', '$final_deductions', '$net_salary', '$pay_date', '$status')
            ";
        }

        if (mysqli_query($conn, $insertQuery)) {
            $_SESSION['success'] = "Payroll for <strong>$month $year</strong> processed successfully!";
        } else {
            $_SESSION['error'] = "Payroll Failed: " . mysqli_error($conn);
        }
    }
    header("Location: index.php");
    exit();
}

// 3. Handle Delete
if (isset($_GET['delete']) && !empty($_GET['delete'])) {
    $del_id = intval($_GET['delete']);
    mysqli_query($conn, "DELETE FROM payroll WHERE id = '$del_id'");
    $_SESSION['success'] = "Payroll record deleted successfully!";
    header("Location: index.php");
    exit();
}

// 4. Payroll Statistics
$stats = ['total_paid' => 0, 'total_pending' => 0, 'total_records' => 0];
$statQuery = mysqli_query($conn, "
    SELECT 
        COUNT(id) as total_count,
        SUM(CASE WHEN status = 'Paid' THEN net_salary ELSE 0 END) as paid_sum,
        SUM(CASE WHEN status = 'Pending' THEN net_salary ELSE 0 END) as pending_sum
    FROM payroll
");
if ($statQuery && $row = mysqli_fetch_assoc($statQuery)) {
    $stats['total_records'] = $row['total_count'] ?? 0;
    $stats['total_paid']    = $row['paid_sum'] ?? 0;
    $stats['total_pending'] = $row['pending_sum'] ?? 0;
}

// 5. Fetch Records
$payrollQuery = mysqli_query($conn, "
    SELECT p.*, {$empNameSelect} AS emp_name, {$roleSelect}
    FROM payroll p
    LEFT JOIN employees e ON p.employee_id = e.id
    ORDER BY p.id DESC
");

// 6. Fetch Employees List
$empQuery = mysqli_query($conn, "SELECT id, {$empNameSingle}, {$roleSingle} FROM employees ORDER BY full_name ASC");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-file-invoice-dollar text-success me-2"></i>Payroll & Attendance Management
                </h2>
                <p class="text-muted mb-0">Automatic Paid Leave addition, Absent deduction, and Salary processing</p>
            </div>
            <div class="d-flex gap-2">
                <a href="/vortex_wms/dashboard.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Dashboard</a>
                <button class="btn btn-success px-4 fw-bold shadow-sm" data-bs-toggle="modal" data-bs-target="#generatePayrollModal">
                    <i class="fa-solid fa-plus me-1"></i> Process Payroll
                </button>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3"><?= $_SESSION['success']; unset($_SESSION['success']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3"><?= $_SESSION['error']; unset($_SESSION['error']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- KPI Metric Cards -->
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary">
                    <small class="text-muted fw-bold text-uppercase">Total Payrolls Processed</small>
                    <h3 class="fw-bold text-dark mb-0 mt-1"><?= number_format($stats['total_records']); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
                    <small class="text-muted fw-bold text-uppercase">Total Disbursed (Paid)</small>
                    <h3 class="fw-bold text-success mb-0 mt-1">₹<?= number_format($stats['total_paid'], 2); ?></h3>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning">
                    <small class="text-muted fw-bold text-uppercase">Pending Payouts</small>
                    <h3 class="fw-bold text-warning mb-0 mt-1">₹<?= number_format($stats['total_pending'], 2); ?></h3>
                </div>
            </div>
        </div>

        <!-- Payroll Table Card -->
        <div class="card shadow-sm border-0 rounded-4">
            <div class="card-header bg-white py-3 border-0">
                <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list text-primary me-2"></i>Salary Records</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0">
                        <thead class="table-dark">
                            <tr>
                                <th width="60">#</th>
                                <th>Employee</th>
                                <th>Period</th>
                                <th>Basic Pay</th>
                                <th>Allowances + Paid Leave</th>
                                <th>Deductions + Absent</th>
                                <th>Net Salary</th>
                                <th>Status</th>
                                <th width="140" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($payrollQuery && mysqli_num_rows($payrollQuery) > 0): $idx = 1; ?>
                                <?php while ($row = mysqli_fetch_assoc($payrollQuery)): ?>
                                    <tr>
                                        <td><strong>#<?= $idx++; ?></strong></td>
                                        <td>
                                            <strong><?= htmlspecialchars($row['emp_name']); ?></strong>
                                            <br><small class="text-muted font-monospace"><?= htmlspecialchars($row['emp_role'] ?? 'Staff'); ?></small>
                                        </td>
                                        <td>
                                            <span class="badge bg-light text-dark border font-monospace fs-6">
                                                <?= htmlspecialchars($row['month']) . ' ' . $row['year']; ?>
                                            </span>
                                        </td>
                                        <td>₹<?= number_format($row['basic_salary'], 2); ?></td>
                                        <td class="text-success">
                                            <strong>+₹<?= number_format($row['allowances'], 2); ?></strong>
                                            <?php if (!empty($row['allowance_type'])): ?>
                                                <br><small class="text-muted" style="font-size: 11px;"><?= htmlspecialchars($row['allowance_type']); ?></small>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-danger">
                                            <strong>-₹<?= number_format($row['deductions'], 2); ?></strong>
                                        </td>
                                        <td class="fw-bold text-primary fs-6">₹<?= number_format($row['net_salary'], 2); ?></td>
                                        <td>
                                            <?php if ($row['status'] == 'Paid'): ?>
                                                <span class="badge bg-success px-3 py-1">Paid</span>
                                            <?php else: ?>
                                                <span class="badge bg-warning text-dark px-3 py-1">Pending</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="slip.php?id=<?= $row['id']; ?>" class="btn btn-outline-primary btn-sm me-1" title="View / Print Slip">
                                                <i class="fa-solid fa-print"></i>
                                            </a>
                                            <a href="index.php?delete=<?= $row['id']; ?>" class="btn btn-outline-danger btn-sm" onclick="return confirm('Are you sure you want to delete this payroll record?');" title="Delete">
                                                <i class="fa-solid fa-trash"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="9" class="text-center py-5 text-muted">
                                        No payroll records found.
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

<!-- Process Payroll Modal (With Auto Leave Calculation) -->
<div class="modal fade" id="generatePayrollModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content rounded-4 border-0 shadow">
            <div class="modal-header bg-success text-white">
                <h5 class="modal-title fw-bold">
                    <i class="fa-solid fa-calculator me-2"></i>Process Employee Salary & Leaves
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body p-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Select Employee *</label>
                            <select name="employee_id" class="form-select" required>
                                <option value="">-- Choose Employee --</option>
                                <?php if ($empQuery && mysqli_num_rows($empQuery) > 0): ?>
                                    <?php while ($e = mysqli_fetch_assoc($empQuery)): ?>
                                        <option value="<?= $e['id']; ?>"><?= htmlspecialchars($e['full_name']); ?> (<?= htmlspecialchars($e['role'] ?? 'Staff'); ?>)</option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Month *</label>
                            <select name="month" class="form-select" required>
                                <?php 
                                $months = ["January","February","March","April","May","June","July","August","September","October","November","December"];
                                foreach ($months as $m) {
                                    $selected = ($m == date('F')) ? 'selected' : '';
                                    echo "<option value='$m' $selected>$m</option>";
                                }
                                ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Year *</label>
                            <input type="number" name="year" class="form-control" value="<?= date('Y'); ?>" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Basic Salary (₹) *</label>
                            <input type="number" step="0.01" name="basic_salary" class="form-control basic-salary" placeholder="e.g. 30000" onkeyup="calcNetSalary()" onchange="calcNetSalary()" required>
                            <small class="text-muted">Per Day Rate: <span class="per-day-rate font-monospace text-primary fw-bold">₹0.00</span></small>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Other Deductions (Tax / PF) (₹)</label>
                            <input type="number" step="0.01" name="other_deductions" class="form-control other-deduct" value="0.00" onkeyup="calcNetSalary()" onchange="calcNetSalary()">
                        </div>

                        <!-- AUTO LEAVE & ABSENT SECTION -->
                        <div class="col-12">
                            <div class="card border border-warning bg-light rounded-3 p-3">
                                <h6 class="fw-bold text-dark mb-2"><i class="fa-solid fa-calendar-check text-warning me-2"></i>Attendance & Leave Auto-Adjuster</h6>
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-success">Paid Leaves (Encashment / Extra Days)</label>
                                        <div class="input-group">
                                            <input type="number" name="paid_leaves" class="form-control paid-leaves" min="0" value="0" onkeyup="calcNetSalary()" onchange="calcNetSalary()">
                                            <span class="input-group-text bg-white text-success font-monospace paid-leave-val">+₹0.00</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-bold text-danger">Absent / Unpaid Leave Days (Auto-Deduct)</label>
                                        <div class="input-group">
                                            <input type="number" name="absent_days" class="form-control absent-days" min="0" value="0" onkeyup="calcNetSalary()" onchange="calcNetSalary()">
                                            <span class="input-group-text bg-white text-danger font-monospace absent-deduct-val">-₹0.00</span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- MULTI ALLOWANCES CONTAINER -->
                        <div class="col-12 mt-3">
                            <div class="d-flex justify-content-between align-items-center bg-light p-2 px-3 rounded-top border">
                                <span class="fw-bold text-dark"><i class="fa-solid fa-hand-holding-dollar text-success me-1"></i> Additional Allowances</span>
                                <button type="button" class="btn btn-outline-success btn-sm fw-bold" onclick="addAllowanceRow()">
                                    <i class="fa-solid fa-plus me-1"></i> Add Another Allowance
                                </button>
                            </div>
                            <div class="border border-top-0 p-3 rounded-bottom bg-white" id="allowanceContainer">
                                <div class="row g-2 allowance-row mb-2 align-items-center">
                                    <div class="col-md-6">
                                        <select name="allowance_type[]" class="form-select">
                                            <option value="House Rent Allowance (HRA)">House Rent Allowance (HRA)</option>
                                            <option value="Dearness Allowance (DA)">Dearness Allowance (DA)</option>
                                            <option value="Conveyance / Travel Allowance">Conveyance / Travel Allowance</option>
                                            <option value="Medical Allowance">Medical Allowance</option>
                                            <option value="Overtime (OT) Pay">Overtime (OT) Pay</option>
                                            <option value="Performance / Festive Bonus">Performance / Festive Bonus</option>
                                            <option value="Special Allowance" selected>Special Allowance</option>
                                        </select>
                                    </div>
                                    <div class="col-md-5">
                                        <input type="number" step="0.01" name="allowance_amount[]" class="form-control allow-amt" placeholder="Amount (₹)" value="0.00" onkeyup="calcNetSalary()" onchange="calcNetSalary()">
                                    </div>
                                    <div class="col-md-1 text-center">
                                        <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeAllowanceRow(this)"><i class="fa-solid fa-trash"></i></button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-4 mt-3">
                            <label class="form-label fw-semibold">Total Allowances</label>
                            <input type="text" class="form-control font-monospace fw-bold text-success bg-light total-allowances" value="₹0.00" readonly>
                        </div>

                        <div class="col-md-4 mt-3">
                            <label class="form-label fw-semibold">Total Deductions</label>
                            <input type="text" class="form-control font-monospace fw-bold text-danger bg-light total-deductions" value="₹0.00" readonly>
                        </div>

                        <div class="col-md-4 mt-3">
                            <label class="form-label fw-semibold">Net Salary (Calculated)</label>
                            <input type="text" class="form-control font-monospace fw-bold text-primary bg-light net-salary" value="₹0.00" readonly>
                        </div>

                        <div class="col-md-6 mt-3">
                            <label class="form-label fw-semibold">Payment Date</label>
                            <input type="date" name="payment_date" class="form-control" value="<?= date('Y-m-d'); ?>">
                        </div>

                        <div class="col-md-6 mt-3">
                            <label class="form-label fw-semibold">Payment Status *</label>
                            <select name="status" class="form-select">
                                <option value="Paid" selected>Paid</option>
                                <option value="Pending">Pending</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="modal-footer border-top pt-3">
                    <button type="button" class="btn btn-outline-secondary px-4" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="save_payroll" class="btn btn-success px-4 fw-bold">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save Payroll
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<script>
function calcNetSalary() {
    let basic      = parseFloat(document.querySelector(".basic-salary").value) || 0;
    let otherDeduct= parseFloat(document.querySelector(".other-deduct").value) || 0;
    let paidLeaves = parseInt(document.querySelector(".paid-leaves").value) || 0;
    let absentDays = parseInt(document.querySelector(".absent-days").value) || 0;

    // Per day calculation (30 days standard)
    let dailyRate = basic > 0 ? (basic / 30) : 0;
    document.querySelector(".per-day-rate").innerText = "₹" + dailyRate.toFixed(2);

    let paidLeaveAmount = paidLeaves * dailyRate;
    let absentDeduction = absentDays * dailyRate;

    document.querySelector(".paid-leave-val").innerText = "+₹" + paidLeaveAmount.toFixed(2);
    document.querySelector(".absent-deduct-val").innerText = "-₹" + absentDeduction.toFixed(2);

    // Sum all dynamic allowance rows
    let customAllowances = 0;
    document.querySelectorAll(".allow-amt").forEach(function(input) {
        customAllowances += parseFloat(input.value) || 0;
    });

    let totalAllow = customAllowances + paidLeaveAmount;
    let totalDeduct= otherDeduct + absentDeduction;

    document.querySelector(".total-allowances").value = "₹" + totalAllow.toFixed(2);
    document.querySelector(".total-deductions").value = "₹" + totalDeduct.toFixed(2);

    let net = Math.max(0, (basic + totalAllow) - totalDeduct);
    document.querySelector(".net-salary").value = "₹" + net.toFixed(2);
}

function addAllowanceRow() {
    let container = document.getElementById("allowanceContainer");
    let row = document.createElement("div");
    row.className = "row g-2 allowance-row mb-2 align-items-center";
    row.innerHTML = `
        <div class="col-md-6">
            <select name="allowance_type[]" class="form-select">
                <option value="House Rent Allowance (HRA)">House Rent Allowance (HRA)</option>
                <option value="Dearness Allowance (DA)">Dearness Allowance (DA)</option>
                <option value="Conveyance / Travel Allowance">Conveyance / Travel Allowance</option>
                <option value="Medical Allowance">Medical Allowance</option>
                <option value="Overtime (OT) Pay">Overtime (OT) Pay</option>
                <option value="Performance / Festive Bonus">Performance / Festive Bonus</option>
                <option value="Special Allowance">Special Allowance</option>
            </select>
        </div>
        <div class="col-md-5">
            <input type="number" step="0.01" name="allowance_amount[]" class="form-control allow-amt" placeholder="Amount (₹)" value="0.00" onkeyup="calcNetSalary()" onchange="calcNetSalary()">
        </div>
        <div class="col-md-1 text-center">
            <button type="button" class="btn btn-outline-danger btn-sm" onclick="removeAllowanceRow(this)"><i class="fa-solid fa-trash"></i></button>
        </div>
    `;
    container.appendChild(row);
    calcNetSalary();
}

function removeAllowanceRow(btn) {
    let rows = document.querySelectorAll(".allowance-row");
    if (rows.length > 1) {
        btn.closest(".allowance-row").remove();
        calcNetSalary();
    } else {
        btn.closest(".allowance-row").querySelector(".allow-amt").value = "0.00";
        calcNetSalary();
    }
}
</script>