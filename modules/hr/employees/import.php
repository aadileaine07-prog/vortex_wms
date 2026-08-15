<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (isset($_POST['import'])) {
    $filename = $_FILES['csv_file']['tmp_name'];

    if ($_FILES['csv_file']['size'] > 0) {
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
                $check = mysqli_query($conn, "SELECT id FROM employees WHERE employee_id='$employee_id' OR username='$username'");
                if (mysqli_num_rows($check) == 0) {
                    $sql = "INSERT INTO employees (employee_id, full_name, email, mobile, department, role, warehouse, username, password, status) 
                            VALUES ('$employee_id', '$full_name', '$email', '$mobile', '$department', '$role', '$warehouse', '$username', '$password', 'Active')";
                    
                    if (mysqli_query($conn, $sql)) {
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
        
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold"><i class="fa-solid fa-file-import text-primary me-2"></i>Bulk Import Employees</h2>
                <p class="text-muted mb-0">Upload CSV file to add multiple employees at once</p>
            </div>
            <a href="index.php" class="btn btn-outline-secondary"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <div class="card shadow-sm border-0 col-md-8 mx-auto">
            <div class="card-body p-4">
                
                <div class="alert alert-info">
                    <h6 class="fw-bold mb-2"><i class="fa-solid fa-circle-info me-1"></i> CSV Format Guidelines:</h6>
                    <small>
                        CSV file me ye columns isi order me hone chahiye:<br>
                        <code>employee_id, full_name, email, mobile, department, role, warehouse, username, password</code>
                    </small>
                </div>

                <form method="POST" enctype="multipart/form-data" class="mt-4">
                    <div class="mb-3">
                        <label class="form-label fw-bold">Select CSV File</label>
                        <input type="file" name="csv_file" class="form-control" accept=".csv" required>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" name="import" class="btn btn-primary px-4">
                            <i class="fa-solid fa-upload me-1"></i> Upload & Import
                        </button>
                    </div>
                </form>

            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>