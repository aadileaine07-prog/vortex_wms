<?php
require_once "../../../config/database.php";
include_once "../../../includes/header.php";

// Handle form submission for new QC inspection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $refNo     = mysqli_real_escape_string($conn, $_POST['reference_no'] ?? '');
    $inspector = mysqli_real_escape_string($conn, $_POST['inspector_name'] ?? '');
    $status    = mysqli_real_escape_string($conn, $_POST['status'] ?? 'Passed');
    $date      = date('Y-m-d');

    // Ensure quality_control table exists
    @mysqli_query($conn, "CREATE TABLE IF NOT EXISTS quality_control (
        id INT AUTO_INCREMENT PRIMARY KEY,
        reference_no VARCHAR(100),
        inspector_name VARCHAR(100),
        inspection_date DATE,
        status VARCHAR(50),
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    $insertQ = mysqli_query($conn, "INSERT INTO quality_control (reference_no, inspector_name, inspection_date, status) VALUES ('$refNo', '$inspector', '$date', '$status')");
    
    if ($insertQ) {
        $_SESSION['success'] = "Quality Control inspection logged successfully!";
    } else {
        $_SESSION['error'] = "Inspection Log Failed: " . mysqli_error($conn);
    }
    header("Location: index.php");
    exit();
}
?>

<div class="container-fluid py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <div>
            <h3 class="fw-bold mb-1"><i class="fa-solid fa-clipboard-check me-2 text-primary"></i>New QC Inspection</h3>
            <p class="text-muted small mb-0">Record inspection results for inbound shipments.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back to QC Logs</a>
    </div>

    <div class="card border-0 shadow-sm rounded-4 p-4 col-lg-8 mx-auto bg-white">
        <form method="POST">
            <div class="mb-3">
                <label class="form-label small fw-bold font-mono">Reference / PO Number *</label>
                <input type="text" name="reference_no" class="form-control font-mono" placeholder="e.g. PO-2026-001" required>
            </div>

            <div class="mb-3">
                <label class="form-label small fw-bold font-mono">Inspector Name *</label>
                <input type="text" name="inspector_name" class="form-control font-mono" value="<?= htmlspecialchars($_SESSION['full_name'] ?? 'Admin Inspector'); ?>" required>
            </div>

            <div class="mb-4">
                <label class="form-label small fw-bold font-mono">Inspection Status *</label>
                <select name="status" class="form-select font-mono" required>
                    <option value="Passed">Passed</option>
                    <option value="Failed">Failed</option>
                    <option value="Pending Review">Pending Review</option>
                </select>
            </div>

            <div class="text-end">
                <a href="index.php" class="btn btn-outline-secondary rounded-pill px-4 me-2">Cancel</a>
                <button type="submit" class="btn btn-primary rounded-pill px-4 fw-bold shadow-sm"><i class="fa-solid fa-floppy-disk me-1"></i> Save Inspection</button>
            </div>
        </form>
    </div>
</div>

<?php 
include_once "../../../includes/footer.php"; 
?>