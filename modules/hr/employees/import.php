<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

// 1. Handle Sample CSV Download Request
if (isset($_GET['download']) && $_GET['download'] === 'sample') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=employee_import_sample.csv');
    $output = fopen('php://output', 'w');
    
    // CSV Headers (Without employee_id since it's auto-generated)
    fputcsv($output, ['full_name', 'email', 'mobile', 'department', 'role', 'warehouse', 'username', 'password']);
    
    // Sample Rows
    fputcsv($output, ['Rajesh Sharma', 'rajesh.sharma@vortex.com', '9876543210', 'Management', 'Warehouse Manager', 'Surat Central Logistics Park', 'rajesh_mgr', '123456']);
    fputcsv($output, ['Amit Patel', 'amit.patel@vortex.com', '9123456789', 'Operations', 'Picker', 'Surat Central Logistics Park', 'amit_picker', '123456']);
    
    fclose($output);
    exit();
}

// 2. Handle CSV Import Process
if (isset($_POST['import'])) {
    $filename = $_FILES['csv_file']['tmp_name'];

    if (!empty($_FILES['csv_file']['size']) && $_FILES['csv_file']['size'] > 0) {
        $file = fopen($filename, "r");

        // Skip header row
        fgetcsv($file);

        $successCount = 0;
        $errorCount = 0;

        while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
            
            // CSV Columns Mapping (ID removed, auto-generated below)
            $full_name   = mysqli_real_escape_string($conn, trim($column[0] ?? ''));
            $email       = mysqli_real_escape_string($conn, trim($column[1] ?? ''));
            $mobile      = mysqli_real_escape_string($conn, trim($column[2] ?? ''));
            $department  = mysqli_real_escape_string($conn, trim($column[3] ?? ''));
            $role        = mysqli_real_escape_string($conn, trim($column[4] ?? ''));
            $warehouse   = mysqli_real_escape_string($conn, trim($column[5] ?? ''));
            $username    = mysqli_real_escape_string($conn, trim($column[6] ?? ''));
            $rawPassword = trim($column[7] ?? '123456');
            $password    = password_hash(!empty($rawPassword) ? $rawPassword : '123456', PASSWORD_DEFAULT);

            if (!empty($full_name) && !empty($username) && !empty($role)) {
                
                // Check if username already exists
                $check = $conn->query("SELECT id FROM employees WHERE username='$username'");
                if ($check && $check->num_rows == 0) {
                    
                    /* ==========================================================
                       SMART AUTO-GENERATED ID LOGIC (Z series vs VRTX series)
                       ========================================================== */
                    $isManagement = ($role === 'Super Admin' || $role === 'Admin' || $role === 'Management' || strpos($role, 'Manager') !== false);
                    
                    if ($isManagement) {
                        $q = mysqli_query($conn, "SELECT employee_id FROM employees WHERE employee_id LIKE 'Z%' ORDER BY id DESC LIMIT 1");
                        if ($q && mysqli_num_rows($q) > 0) {
                            $lastId = mysqli_fetch_assoc($q)['employee_id'];
                            $num = intval(substr($lastId, 1)) + 1;
                            $employee_id = 'Z' . str_pad($num, 3, '0', STR_PAD_LEFT);
                        } else {
                            $employee_id = 'Z001';
                        }
                    } else {
                        $q = mysqli_query($conn, "SELECT employee_id FROM employees WHERE employee_id LIKE 'VRTX%' ORDER BY id DESC LIMIT 1");
                        if ($q && mysqli_num_rows($q) > 0) {
                            $lastId = mysqli_fetch_assoc($q)['employee_id'];
                            $num = intval(substr($lastId, 4)) + 1;
                            $employee_id = 'VRTX' . str_pad($num, 4, '0', STR_PAD_LEFT);
                        } else {
                            $employee_id = 'VRTX0001';
                        }
                    }

                    // Ensure absolute uniqueness loop
                    $existsCheck = true;
                    while ($existsCheck) {
                        $chkDup = mysqli_query($conn, "SELECT id FROM employees WHERE employee_id = '$employee_id' LIMIT 1");
                        if ($chkDup && mysqli_num_rows($chkDup) > 0) {
                            if ($isManagement) {
                                $num = intval(substr($employee_id, 1)) + 1;
                                $employee_id = 'Z' . str_pad($num, 3, '0', STR_PAD_LEFT);
                            } else {
                                $num = intval(substr($employee_id, 4)) + 1;
                                $employee_id = 'VRTX' . str_pad($num, 4, '0', STR_PAD_LEFT);
                            }
                        } else {
                            $existsCheck = false;
                        }
                    }

                    // Insert query with auto-generated employee_id
                    $sql = "INSERT INTO employees (employee_id, full_name, email, mobile, department, role, warehouse, username, password, status) 
                            VALUES ('$employee_id', '$full_name', '$email', '$mobile', '$department', '$role', '$warehouse', '$username', '$password', 'Active')";
                    
                    if ($conn->query($sql)) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } else {
                    $errorCount++; // Duplicate username skipped
                }
            } else {
                $errorCount++;
            }
        }

        fclose($file);

        $_SESSION['success'] = "Bulk Upload Done! Successfully added: $successCount employees with auto-generated IDs. (Failed/Skipped: $errorCount)";
        header("Location: index.php");
        exit();
    } else {
        $_SESSION['error'] = "Please upload a valid CSV file.";
    }
}

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">
        
        <!-- Flash Messages -->
        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show shadow-sm rounded-4 border-0 mb-3" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= htmlspecialchars($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
            <?php unset($_SESSION['error']); ?>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-file-csv text-success me-2"></i>Bulk Import Employees</h2>
                <p class="text-muted mb-0">Upload CSV file to add multiple employee records at once with automatic ID generation</p>
            </div>
            <div class="d-flex gap-2">
                <a href="import.php?download=sample" class="btn btn-outline-success fw-bold rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-download me-1"></i> Download Sample CSV
                </a>
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-3 fw-bold shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to List</a>
            </div>
        </div>

        <div class="card shadow-sm border-0 rounded-4 bg-white col-lg-8 mx-auto">
            <div class="card-body p-4">
                
                <div class="alert alert-info border-0 rounded-4 shadow-sm bg-info-subtle text-info-emphasis mb-4 p-3">
                    <h6 class="fw-bold mb-2"><i class="fa-solid fa-circle-info me-1"></i> CSV Format Guidelines:</h6>
                    <small class="d-block mb-1">Employee IDs are generated automatically based on department & role. CSV columns ka exact sequence yeh hona chahiye:</small>
                    <code class="font-monospace text-dark bg-white px-2 py-1 rounded d-block">full_name, email, mobile, department, role, warehouse, username, password</code>
                    <small class="d-block text-muted mt-2">*Note: Management roles get 'Z' series IDs, and Shop Floor roles get 'VRTX' series IDs automatically.</small>
                </div>

                <form method="POST" enctype="multipart/form-data" class="mt-2">
                    <div class="mb-4">
                        <label class="form-label fw-semibold text-secondary">Select CSV File <span class="text-danger">*</span></label>
                        <input type="file" name="csv_file" class="form-control border-2 py-2" accept=".csv" required>
                    </div>

                    <div class="d-flex gap-2 justify-content-end">
                        <button type="submit" name="import" class="btn btn-success px-4 fw-bold rounded-pill shadow-sm">
                            <i class="fa-solid fa-upload me-1"></i> Upload & Import
                        </button>
                        <a href="index.php" class="btn btn-outline-secondary px-4 fw-bold rounded-pill">Cancel</a>
                    </div>
                </form>

            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>