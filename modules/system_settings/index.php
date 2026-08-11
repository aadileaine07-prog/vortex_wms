<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

// Handle Settings Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_settings'])) {
    if (isset($_POST['settings']) && is_array($_POST['settings'])) {
        foreach ($_POST['settings'] as $key => $value) {
            $key = mysqli_real_escape_string($conn, $key);
            $value = mysqli_real_escape_string($conn, $value);

            mysqli_query($conn, "
                INSERT INTO system_settings (setting_key, setting_value)
                VALUES ('$key', '$value')
                ON DUPLICATE KEY UPDATE setting_value='$value'
            ");
        }
        $_SESSION['success'] = "Settings updated successfully.";
    }
    header("Location: index.php");
    exit();
}

// Handle Database Backup Trigger
if (isset($_POST['download_backup'])) {
    $tables = array();
    $result = mysqli_query($conn, "SHOW TABLES");
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }

    $sqlScript = "-- VORTEX WMS DATABASE BACKUP\n-- Generated: " . date("Y-m-d H:i:s") . "\n\n";
    foreach ($tables as $table) {
        $result = mysqli_query($conn, "SHOW CREATE TABLE $table");
        $row = mysqli_fetch_row($result);
        $sqlScript .= "\n\n" . $row[1] . ";\n\n";

        $result = mysqli_query($conn, "SELECT * FROM $table");
        while ($row = mysqli_fetch_assoc($result)) {
            $sqlScript .= "INSERT INTO $table VALUES(";
            $first = true;
            foreach ($row as $val) {
                if (!$first) { $sqlScript .= ', '; }
                $sqlScript .= "'" . mysqli_real_escape_string($conn, $val) . "'";
                $first = false;
            }
            $sqlScript .= ");\n";
        }
    }

    $backup_file_name = 'vortex_wms_backup_' . date('Y_m_d_H_i_s') . '.sql';
    header('Content-Type: application/octet-stream');
    header("Content-Transfer-Encoding: Binary");
    header("Content-disposition: attachment; filename=\"" . $backup_file_name . "\"");
    echo $sqlScript;
    exit();
}

// Fetch Settings
$settingsQuery = mysqli_query($conn, "SELECT * FROM system_settings");
$settings = [];
if ($settingsQuery && mysqli_num_rows($settingsQuery) > 0) {
    while ($row = mysqli_fetch_assoc($settingsQuery)) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
}

include "../../includes/header.php";
include "../../includes/navbar.php";
include "../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-gears text-primary me-2"></i>Advanced System Settings
                </h2>
                <p class="text-muted mb-0">System configuration, security rules, SMTP email & database backups</p>
            </div>
            <div>
                <a href="../../dashboard.php" class="btn btn-outline-secondary px-3">
                    <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
                </a>
            </div>
        </div>

        <!-- Session Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Nav Tabs -->
        <ul class="nav nav-tabs custom-tabs mb-4 border-bottom-0" id="settingsTab" role="tablist">
            <li class="nav-item">
                <button class="nav-link active fw-semibold" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
                    <i class="fa-solid fa-building me-1"></i> General & Prefixes
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold" id="smtp-tab" data-bs-toggle="tab" data-bs-target="#smtp" type="button">
                    <i class="fa-solid fa-envelope me-1"></i> SMTP Email
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">
                    <i class="fa-solid fa-shield-halved me-1"></i> Security & Session
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-semibold" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button">
                    <i class="fa-solid fa-database me-1"></i> Database Maintenance
                </button>
            </li>
        </ul>

        <form method="POST" action="index.php">
            <div class="tab-content" id="settingsTabContent">

                <!-- 1. General & Prefixes Tab -->
                <div class="tab-pane fade show active" id="general" role="tabpanel">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-circle-info text-primary me-2"></i>General Information</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Company / Organization Name</label>
                                    <input type="text" name="settings[company_name]" class="form-control" value="<?= htmlspecialchars($settings['company_name'] ?? 'Vortex WMS'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Currency Symbol</label>
                                    <input type="text" name="settings[currency_symbol]" class="form-control" value="<?= htmlspecialchars($settings['currency_symbol'] ?? '₹'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Low Stock Threshold (Units)</label>
                                    <input type="number" name="settings[low_stock_threshold]" class="form-control" value="<?= htmlspecialchars($settings['low_stock_threshold'] ?? '10'); ?>" min="1" required>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-hashtag text-primary me-2"></i>Auto-Generated Code Prefixes</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Sales Order Prefix</label>
                                    <input type="text" name="settings[sales_order_prefix]" class="form-control font-monospace" value="<?= htmlspecialchars($settings['sales_order_prefix'] ?? 'SO-'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Picking Order Prefix</label>
                                    <input type="text" name="settings[picking_prefix]" class="form-control font-monospace" value="<?= htmlspecialchars($settings['picking_prefix'] ?? 'PICK-'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">GRN Inbound Prefix</label>
                                    <input type="text" name="settings[grn_prefix]" class="form-control font-monospace" value="<?= htmlspecialchars($settings['grn_prefix'] ?? 'GRN-'); ?>" required>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 2. SMTP Email Tab -->
                <div class="tab-pane fade" id="smtp" role="tabpanel">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-paper-plane text-primary me-2"></i>Mail Server Configuration</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">SMTP Host</label>
                                    <input type="text" name="settings[smtp_host]" class="form-control" placeholder="smtp.gmail.com" value="<?= htmlspecialchars($settings['smtp_host'] ?? ''); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">SMTP Port</label>
                                    <input type="text" name="settings[smtp_port]" class="form-control" placeholder="587" value="<?= htmlspecialchars($settings['smtp_port'] ?? '587'); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Encryption</label>
                                    <select name="settings[smtp_crypto]" class="form-select">
                                        <option value="tls" <?= (($settings['smtp_crypto'] ?? '') == 'tls') ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?= (($settings['smtp_crypto'] ?? '') == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">SMTP Username / Email</label>
                                    <input type="email" name="settings[smtp_user]" class="form-control" placeholder="notifications@domain.com" value="<?= htmlspecialchars($settings['smtp_user'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">SMTP App Password</label>
                                    <input type="password" name="settings[smtp_pass]" class="form-control" value="<?= htmlspecialchars($settings['smtp_pass'] ?? ''); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 3. Security & Session Tab -->
                <div class="tab-pane fade" id="security" role="tabpanel">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-lock text-primary me-2"></i>Authentication Controls</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Session Timeout (Minutes)</label>
                                    <input type="number" name="settings[session_timeout]" class="form-control" value="<?= htmlspecialchars($settings['session_timeout'] ?? '30'); ?>" min="5">
                                    <small class="text-muted">Auto logout inactive users after specified minutes.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Max Failed Login Attempts</label>
                                    <input type="number" name="settings[max_login_attempts]" class="form-control" value="<?= htmlspecialchars($settings['max_login_attempts'] ?? '5'); ?>" min="1">
                                    <small class="text-muted">Lock account after continuous wrong passwords.</small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- 4. Database Maintenance Tab -->
                <div class="tab-pane fade" id="backup" role="tabpanel">
                    <div class="card shadow-sm border-0 rounded-3">
                        <div class="card-body p-4">
                            <h5 class="fw-bold mb-2 text-dark"><i class="fa-solid fa-file-export text-primary me-2"></i>Database Backup & Recovery</h5>
                            <p class="text-muted mb-4">Export full MySQL database script containing tables, inventory logs, and orders.</p>

                            <button type="submit" name="download_backup" class="btn btn-dark btn-lg px-4">
                                <i class="fa-solid fa-download me-2"></i> Download Full SQL Backup
                            </button>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Save Button Row -->
            <div class="card shadow-sm border-0 rounded-3 mt-4">
                <div class="card-body p-3 text-end bg-light">
                    <button type="submit" name="save_settings" class="btn btn-success btn-lg px-5">
                        <i class="fa-solid fa-floppy-disk me-1"></i> Save All Settings
                    </button>
                </div>
            </div>
        </form>

    </div>
</div>

<?php include "../../includes/footer.php"; ?>