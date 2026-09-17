<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (isset($_POST['import'])) {
    $filename = $_FILES['csv_file']['tmp_name'];

    if (!empty($_FILES['csv_file']['size']) && $_FILES['csv_file']['size'] > 0) {
        $file = fopen($filename, "r");

        // Skip header row
        fgetcsv($file);

        $successCount = 0;
        $errorCount = 0;

        while (($column = fgetcsv($file, 10000, ",")) !== FALSE) {
            
            // CSV Columns Mapping
            $employee_id = mysqli_real_escape_string($conn, trim($column[0] ?? ''));
            $full_name   = mysqli_real_escape_string($conn, trim($column[1] ?? ''));
            $email       = mysqli_real_escape_string($conn, trim($column[2] ?? ''));
            $mobile      = mysqli_real_escape_string($conn, trim($column[3] ?? ''));
            $department  = mysqli_real_escape_string($conn, trim($column[4] ?? ''));
            $role        = mysqli_real_escape_string($conn, trim($column[5] ?? ''));
            $warehouse   = mysqli_real_escape_string($conn, trim($column[6] ?? ''));
            $username    = mysqli_real_escape_string($conn, trim($column[7] ?? ''));
            $password    = password_hash(trim($column[8] ?? '123456'), PASSWORD_BCRYPT); // Default pass: 123456

            if (!empty($employee_id) && !empty($full_name) && !empty($username)) {
                
                // Check if employee_id or username already exists
                $check = $conn->query("SELECT id FROM employees WHERE employee_id='$employee_id' OR username='$username'");
                if ($check && $check->num_rows == 0) {
                    $sql = "INSERT INTO employees (employee_id, full_name, email, mobile, department, role, warehouse, username, password, status) 
                            VALUES ('$employee_id', '$full_name', '$email', '$mobile', '$department', '$role', '$warehouse', '$username', '$password', 'Active')";
                    
                    if ($conn->query($sql)) {
                        $successCount++;
                    } else {
                        $errorCount++;
                    }
                } else {
                    $errorCount++; // Duplicate record
                }
            }
        }

        fclose($file);

        $_SESSION['success'] = "Bulk Upload Done! Successfully added: $successCount employees. (Failed/Skipped: $errorCount)";
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
                <p class="text-muted mb-0">Upload CSV file to add multiple employee records at once</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary rounded-pill px-3 fw-bold shadow-sm"><i class="fa-solid fa-arrow-left me-1"></i> Back to List</a>
        </div>

        <div class="card shadow-sm border-0 rounded-4 bg-white col-lg-8 mx-auto">
            <div class="card-body p-4">
                
                <div class="alert alert-info border-0 rounded-4 shadow-sm bg-info-subtle text-info-emphasis mb-4 p-3">
                    <h6 class="fw-bold mb-2"><i class="fa-solid fa-circle-info me-1"></i> CSV Format Guidelines:</h6>
                    <small class="d-block mb-1">CSV file ke andar columns ka exact sequence yahi hona chahiye:</small>
                    <code class="font-monospace text-dark bg-white px-2 py-1 rounded d-block">employee_id, full_name, email, mobile, department, role, warehouse, username, password</code>
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