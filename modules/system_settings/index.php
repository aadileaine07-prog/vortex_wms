<?php
session_start();

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") ? dirname(__DIR__, 2) : dirname(__DIR__, 3);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// 1. One-Click Complete SQL Database Export
if (isset($_GET['action']) && $_GET['action'] == 'download_backup') {
    $tables = [];
    $result = mysqli_query($conn, "SHOW TABLES");
    while ($row = mysqli_fetch_row($result)) {
        $tables[] = $row[0];
    }

    $sqlDump = "-- VORTEX WMS PRO BACKUP\n-- Date: " . date("Y-m-d H:i:s") . "\n\nSET FOREIGN_KEY_CHECKS=0;\n\n";

    foreach ($tables as $table) {
        $createTable = mysqli_fetch_row(mysqli_query($conn, "SHOW CREATE TABLE `$table`"));
        $sqlDump .= "DROP TABLE IF EXISTS `$table`;\n" . $createTable[1] . ";\n\n";

        $rows = mysqli_query($conn, "SELECT * FROM `$table`");
        while ($row = mysqli_fetch_assoc($rows)) {
            $sqlDump .= "INSERT INTO `$table` VALUES(";
            $values = [];
            foreach ($row as $val) {
                $values[] = is_null($val) ? "NULL" : "'" . mysqli_real_escape_string($conn, $val) . "'";
            }
            $sqlDump .= implode(",", $values) . ");\n";
        }
        $sqlDump .= "\n";
    }
    $sqlDump .= "SET FOREIGN_KEY_CHECKS=1;\n";

    $filename = "vortex_wms_backup_" . date("Ymd_His") . ".sql";
    header('Content-Type: application/sql');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $sqlDump;
    exit();
}

// 2. Database Optimization Action
if (isset($_GET['action']) && $_GET['action'] == 'optimize_db') {
    $tablesRes = mysqli_query($conn, "SHOW TABLES");
    while ($t = mysqli_fetch_row($tablesRes)) {
        mysqli_query($conn, "OPTIMIZE TABLE `{$t[0]}`");
    }
    $_SESSION['success'] = "Database optimized and table indexes refreshed!";
    header("Location: index.php");
    exit();
}

// 3. Test Email Trigger
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['test_email_btn'])) {
    $testTo = mysqli_real_escape_string($conn, trim($_POST['test_email_recipient']));
    if (!empty($testTo)) {
        $headers = "From: Vortex WMS <no-reply@vortexwms.com>\r\nContent-Type: text/html; charset=UTF-8";
        $subject = "VORTEX WMS - SMTP Connection Test";
        $body = "<h3>Vortex WMS Email Connection Successful!</h3><p>Your mail server settings are configured properly.</p>";
        @mail($testTo, $subject, $body, $headers);
        $_SESSION['success'] = "Test email trigger initiated to <strong>$testTo</strong>!";
    }
    header("Location: index.php");
    exit();
}

// 4. Save Settings Handler
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['save_settings'])) {
    $colsCheck = mysqli_query($conn, "SHOW COLUMNS FROM system_settings");
    $dbCols = [];
    while ($c = mysqli_fetch_assoc($colsCheck)) {
        $dbCols[] = $c['Field'];
    }

    $company_name        = mysqli_real_escape_string($conn, trim($_POST['company_name']));
    $company_gst         = mysqli_real_escape_string($conn, trim($_POST['company_gst'] ?? ''));
    $company_email       = mysqli_real_escape_string($conn, trim($_POST['company_email']));
    $company_phone       = mysqli_real_escape_string($conn, trim($_POST['company_phone']));
    $company_address     = mysqli_real_escape_string($conn, trim($_POST['company_address']));
    $invoice_footer_note = mysqli_real_escape_string($conn, trim($_POST['invoice_footer_note'] ?? ''));

    $currency_symbol     = mysqli_real_escape_string($conn, trim($_POST['currency_symbol']));
    $timezone            = mysqli_real_escape_string($conn, trim($_POST['timezone']));
    $barcode_prefix      = mysqli_real_escape_string($conn, trim($_POST['barcode_prefix'] ?? 'VX-'));
    $low_stock_threshold = intval($_POST['low_stock_threshold']);
    $expiry_alert_days   = intval($_POST['expiry_alert_days']);
    $auto_occupy_bin     = isset($_POST['auto_occupy_bin']) ? 1 : 0;
    $enable_auto_picking = isset($_POST['enable_auto_picking']) ? 1 : 0;

    $hr_head_name        = mysqli_real_escape_string($conn, trim($_POST['hr_head_name']));
    $hr_designation      = mysqli_real_escape_string($conn, trim($_POST['hr_designation']));
    $sign_font_style     = mysqli_real_escape_string($conn, trim($_POST['sign_font_style'] ?? 'Great Vibes'));
    $enable_hr_stamp     = isset($_POST['enable_hr_stamp']) ? 1 : 0;
    $signature_image     = mysqli_real_escape_string($conn, trim($_POST['signature_image'] ?? ''));

    $smtp_host           = mysqli_real_escape_string($conn, trim($_POST['smtp_host'] ?? ''));
    $smtp_port           = intval($_POST['smtp_port'] ?? 587);
    $smtp_user           = mysqli_real_escape_string($conn, trim($_POST['smtp_user'] ?? ''));
    $smtp_pass           = mysqli_real_escape_string($conn, trim($_POST['smtp_pass'] ?? ''));
    $smtp_encryption     = mysqli_real_escape_string($conn, trim($_POST['smtp_encryption'] ?? 'tls'));

    $wa_api_key          = mysqli_real_escape_string($conn, trim($_POST['wa_api_key'] ?? ''));
    $wa_phone_number_id  = mysqli_real_escape_string($conn, trim($_POST['wa_phone_number_id'] ?? ''));
    $enable_email_alerts = isset($_POST['enable_email_alerts']) ? 1 : 0;
    $enable_whatsapp_alerts = isset($_POST['enable_whatsapp_alerts']) ? 1 : 0;

    $session_timeout_min = intval($_POST['session_timeout_min'] ?? 60);
    $max_login_attempts  = intval($_POST['max_login_attempts'] ?? 5);
    $log_retention_days  = intval($_POST['log_retention_days'] ?? 90);

    $updates = [
        "company_name = '$company_name'",
        "company_email = '$company_email'",
        "company_phone = '$company_phone'",
        "currency_symbol = '$currency_symbol'",
        "timezone = '$timezone'",
        "low_stock_threshold = '$low_stock_threshold'",
        "expiry_alert_days = '$expiry_alert_days'",
        "auto_occupy_bin = '$auto_occupy_bin'",
        "hr_head_name = '$hr_head_name'",
        "hr_designation = '$hr_designation'",
        "company_address = '$company_address'"
    ];

    if (in_array('company_gst', $dbCols))         $updates[] = "company_gst = '$company_gst'";
    if (in_array('barcode_prefix', $dbCols))      $updates[] = "barcode_prefix = '$barcode_prefix'";
    if (in_array('enable_auto_picking', $dbCols)) $updates[] = "enable_auto_picking = '$enable_auto_picking'";
    if (in_array('enable_hr_stamp', $dbCols))     $updates[] = "enable_hr_stamp = '$enable_hr_stamp'";
    if (in_array('invoice_footer_note', $dbCols)) $updates[] = "invoice_footer_note = '$invoice_footer_note'";
    if (in_array('sign_font_style', $dbCols))     $updates[] = "sign_font_style = '$sign_font_style'";
    if (in_array('signature_image', $dbCols) && !empty($signature_image)) $updates[] = "signature_image = '$signature_image'";
    if (in_array('smtp_host', $dbCols))           $updates[] = "smtp_host = '$smtp_host'";
    if (in_array('smtp_port', $dbCols))           $updates[] = "smtp_port = '$smtp_port'";
    if (in_array('smtp_user', $dbCols))           $updates[] = "smtp_user = '$smtp_user'";
    if (in_array('smtp_pass', $dbCols))           $updates[] = "smtp_pass = '$smtp_pass'";
    if (in_array('smtp_encryption', $dbCols))     $updates[] = "smtp_encryption = '$smtp_encryption'";
    if (in_array('wa_api_key', $dbCols))          $updates[] = "wa_api_key = '$wa_api_key'";
    if (in_array('wa_phone_number_id', $dbCols))  $updates[] = "wa_phone_number_id = '$wa_phone_number_id'";
    if (in_array('enable_email_alerts', $dbCols)) $updates[] = "enable_email_alerts = '$enable_email_alerts'";
    if (in_array('enable_whatsapp_alerts', $dbCols)) $updates[] = "enable_whatsapp_alerts = '$enable_whatsapp_alerts'";
    if (in_array('session_timeout_min', $dbCols)) $updates[] = "session_timeout_min = '$session_timeout_min'";
    if (in_array('max_login_attempts', $dbCols))  $updates[] = "max_login_attempts = '$max_login_attempts'";
    if (in_array('log_retention_days', $dbCols))  $updates[] = "log_retention_days = '$log_retention_days'";

    $updateSql = "UPDATE system_settings SET " . implode(", ", $updates) . " WHERE id = 1";

    if (mysqli_query($conn, $updateSql)) {
        $_SESSION['success'] = "All Enterprise Settings updated successfully!";
    } else {
        $_SESSION['error'] = "Update Failed: " . mysqli_error($conn);
    }

    header("Location: index.php");
    exit();
}

// 5. Fetch Settings
$settingsQuery = mysqli_query($conn, "SELECT * FROM system_settings WHERE id = 1 LIMIT 1");
$settings = ($settingsQuery && mysqli_num_rows($settingsQuery) > 0) ? mysqli_fetch_assoc($settingsQuery) : [];

// 6. Real-time System Metrics
$phpVersion = phpversion();
$dbSizeRes = mysqli_query($conn, "SELECT round(SUM(data_length + index_length) / 1024 / 1024, 2) AS db_size FROM information_schema.TABLES WHERE table_schema = DATABASE()");
$dbSize = mysqli_fetch_assoc($dbSizeRes)['db_size'] ?? '0.00';
$diskFree = round(disk_free_space("/") / (1024 * 1024 * 1024), 2);

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<link href="https://fonts.googleapis.com/css2?family=Great+Vibes&family=Pacifico&family=Alex+Brush&family=Dancing+Script:wght@700&display=swap" rel="stylesheet">

<div class="content">
    <div class="container-fluid p-4">

        <!-- Top Header & System Metrics Bar -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-solid fa-sliders text-primary me-2"></i>Global Enterprise Settings
                </h2>
                <p class="text-muted mb-0">System Control Center, Automation Rules, Signatures, WhatsApp API & Security</p>
            </div>
            <div class="d-flex gap-2">
                <a href="index.php?action=optimize_db" class="btn btn-outline-success px-3 fw-bold" onclick="return confirm('Optimize database tables?');">
                    <i class="fa-solid fa-bolt me-1"></i> Optimize DB
                </a>
                <a href="index.php?action=download_backup" class="btn btn-dark px-3 fw-bold shadow-sm">
                    <i class="fa-solid fa-database me-2 text-warning"></i> SQL Backup
                </a>
            </div>
        </div>

        <!-- System Health Widget Bar -->
        <div class="row g-3 mb-4">
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary">
                    <small class="text-muted text-uppercase fw-bold">Database Size</small>
                    <h4 class="fw-bold text-dark mb-0"><?= $dbSize; ?> MB</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-success">
                    <small class="text-muted text-uppercase fw-bold">Disk Free Space</small>
                    <h4 class="fw-bold text-success mb-0"><?= $diskFree; ?> GB</h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-info">
                    <small class="text-muted text-uppercase fw-bold">PHP Environment</small>
                    <h4 class="fw-bold text-info mb-0">v<?= $phpVersion; ?></h4>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning">
                    <small class="text-muted text-uppercase fw-bold">Active Engine</small>
                    <h4 class="fw-bold text-dark mb-0">InnoDB / MySQLi</h4>
                </div>
            </div>
        </div>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-3"><?= $_SESSION['success']; unset($_SESSION['success']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-3"><?= $_SESSION['error']; unset($_SESSION['error']); ?><button class="btn-close" data-bs-dismiss="alert"></button></div>
        <?php endif; ?>

        <!-- Nav Tabs Navigation -->
        <ul class="nav nav-pills mb-4 bg-white p-2 rounded-4 shadow-sm" id="settingsTabs">
            <li class="nav-item">
                <button class="nav-link active fw-bold px-3" data-bs-toggle="pill" data-bs-target="#tabCompany"><i class="fa-solid fa-building me-1"></i> Company Profile</button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold px-3" data-bs-toggle="pill" data-bs-target="#tabWarehouse"><i class="fa-solid fa-boxes-stacked me-1"></i> WMS & Automation</button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold px-3" data-bs-toggle="pill" data-bs-target="#tabHr"><i class="fa-solid fa-file-signature me-1"></i> HR & Digital Sign</button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold px-3" data-bs-toggle="pill" data-bs-target="#tabIntegration"><i class="fa-solid fa-bolt me-1"></i> SMTP & WhatsApp API</button>
            </li>
            <li class="nav-item">
                <button class="nav-link fw-bold px-3" data-bs-toggle="pill" data-bs-target="#tabSecurity"><i class="fa-solid fa-shield-halved me-1"></i> Security & Audit</button>
            </li>
        </ul>

        <form method="POST" id="mainSettingsForm">
            <div class="tab-content" id="settingsTabsContent">
                
                <!-- TAB 1: COMPANY & BRANDING -->
                <div class="tab-pane fade show active" id="tabCompany">
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-primary mb-3"><i class="fa-solid fa-id-card me-2"></i>Company & Document Header</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Company / Entity Name *</label>
                                    <input type="text" name="company_name" class="form-control" value="<?= htmlspecialchars($settings['company_name'] ?? 'VORTEX WMS'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">GSTIN / Tax Identification No.</label>
                                    <input type="text" name="company_gst" class="form-control font-monospace" value="<?= htmlspecialchars($settings['company_gst'] ?? '24AAACV1234F1Z5'); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Official Business Email *</label>
                                    <input type="email" name="company_email" class="form-control" value="<?= htmlspecialchars($settings['company_email'] ?? 'admin@vortexwms.com'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Official Phone Number</label>
                                    <input type="text" name="company_phone" class="form-control" value="<?= htmlspecialchars($settings['company_phone'] ?? '+91 9876543210'); ?>">
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Registered Warehouse / Branch Address</label>
                                    <textarea name="company_address" rows="3" class="form-control"><?= htmlspecialchars($settings['company_address'] ?? ''); ?></textarea>
                                </div>
                                <div class="col-md-12">
                                    <label class="form-label fw-semibold">Invoice & Payslip Footer Note</label>
                                    <input type="text" name="invoice_footer_note" class="form-control" value="<?= htmlspecialchars($settings['invoice_footer_note'] ?? 'Thank you for choosing VORTEX WMS.'); ?>">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 2: WAREHOUSE & AUTOMATION RULES -->
                <div class="tab-pane fade" id="tabWarehouse">
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-dark mb-3"><i class="fa-solid fa-gears me-2"></i>Stock, Picking & Inventory Automation</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Base Currency Symbol *</label>
                                    <input type="text" name="currency_symbol" class="form-control font-monospace fw-bold" value="<?= htmlspecialchars($settings['currency_symbol'] ?? '₹'); ?>" required>
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Product Barcode Prefix</label>
                                    <input type="text" name="barcode_prefix" class="form-control font-monospace" value="<?= htmlspecialchars($settings['barcode_prefix'] ?? 'VX-'); ?>">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">System Timezone</label>
                                    <select name="timezone" class="form-select">
                                        <option value="Asia/Kolkata" <?= (($settings['timezone'] ?? '') == 'Asia/Kolkata') ? 'selected' : ''; ?>>Asia/Kolkata (IST)</option>
                                        <option value="UTC" <?= (($settings['timezone'] ?? '') == 'UTC') ? 'selected' : ''; ?>>UTC (Standard)</option>
                                        <option value="America/New_York" <?= (($settings['timezone'] ?? '') == 'America/New_York') ? 'selected' : ''; ?>>America/New_York (EST)</option>
                                        <option value="Asia/Dubai" <?= (($settings['timezone'] ?? '') == 'Asia/Dubai') ? 'selected' : ''; ?>>Asia/Dubai (GST)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Low Stock Threshold Limit</label>
                                    <input type="number" name="low_stock_threshold" class="form-control" value="<?= intval($settings['low_stock_threshold'] ?? 10); ?>" min="1" required>
                                    <small class="text-muted">Highlights products when stock quantity is below this number.</small>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Batch Expiry Alert (Days)</label>
                                    <input type="number" name="expiry_alert_days" class="form-control" value="<?= intval($settings['expiry_alert_days'] ?? 30); ?>" min="1" required>
                                    <small class="text-muted">Early alert for batch expiration notice.</small>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch p-2 bg-light rounded border ps-5">
                                        <input class="form-check-input" type="checkbox" name="auto_occupy_bin" id="autoBin" <?= (!empty($settings['auto_occupy_bin'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold text-dark" for="autoBin">Auto-Occupy Bins on Putaway Confirmation</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check form-switch p-2 bg-light rounded border ps-5">
                                        <input class="form-check-input" type="checkbox" name="enable_auto_picking" id="autoPick" <?= (!empty($settings['enable_auto_picking'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold text-dark" for="autoPick">Enable Shortest Pick-Path Optimizer</label>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 3: HR SIGNATORY & LIVE CANVAS SIGNATURE -->
                <div class="tab-pane fade" id="tabHr">
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-success mb-3"><i class="fa-solid fa-signature me-2"></i>Signatory Authority & Live Signature Drawing</h5>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Signatory Head Name *</label>
                                    <input type="text" name="hr_head_name" id="hrNameInput" class="form-control fw-bold" value="<?= htmlspecialchars($settings['hr_head_name'] ?? 'Aadil Raine'); ?>" onkeyup="updateSignPreview()" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Authorized Official Designation *</label>
                                    <input type="text" name="hr_designation" class="form-control" value="<?= htmlspecialchars($settings['hr_designation'] ?? 'HR & Operations Head'); ?>" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Digital Signature Cursive Font</label>
                                    <select name="sign_font_style" id="signFontSelect" class="form-select" onchange="updateSignPreview()">
                                        <option value="Great Vibes" <?= (($settings['sign_font_style'] ?? '') == 'Great Vibes') ? 'selected' : ''; ?>>Great Vibes (Standard Elegant)</option>
                                        <option value="Pacifico" <?= (($settings['sign_font_style'] ?? '') == 'Pacifico') ? 'selected' : ''; ?>>Pacifico (Smooth Brush)</option>
                                        <option value="Alex Brush" <?= (($settings['sign_font_style'] ?? '') == 'Alex Brush') ? 'selected' : ''; ?>>Alex Brush (Classic Cursive)</option>
                                        <option value="Dancing Script" <?= (($settings['sign_font_style'] ?? '') == 'Dancing Script') ? 'selected' : ''; ?>>Dancing Script (Modern Bold)</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Verified Badge Option</label>
                                    <div class="form-check form-switch p-2 bg-light rounded border ps-5">
                                        <input class="form-check-input" type="checkbox" name="enable_hr_stamp" id="hrStamp" <?= (!empty($settings['enable_hr_stamp'])) ? 'checked' : ''; ?>>
                                        <label class="form-check-label fw-semibold text-dark" for="hrStamp">Show "Digitally Verified & Approved" Seal on Payslips</label>
                                    </div>
                                </div>
                                
                                <!-- Hand-Drawn Signature Pad -->
                                <div class="col-md-6 mt-3">
                                    <label class="form-label fw-semibold"><i class="fa-solid fa-pen-nib me-1"></i> Draw Real Signature (Touch / Mouse)</label>
                                    <div class="border rounded-3 p-2 bg-light text-center">
                                        <canvas id="signaturePad" width="360" height="130" class="border bg-white rounded shadow-sm" style="cursor: crosshair;"></canvas>
                                        <input type="hidden" name="signature_image" id="signatureImageInput" value="<?= htmlspecialchars($settings['signature_image'] ?? ''); ?>">
                                        <div class="mt-2 d-flex justify-content-center gap-2">
                                            <button type="button" class="btn btn-outline-danger btn-sm" onclick="clearSignature()"><i class="fa-solid fa-eraser me-1"></i> Clear</button>
                                            <button type="button" class="btn btn-outline-success btn-sm" onclick="saveDrawnSignature()"><i class="fa-solid fa-check me-1"></i> Apply Drawn Sign</button>
                                        </div>
                                    </div>
                                </div>

                                <!-- Live Signature Preview Box -->
                                <div class="col-md-6 mt-3">
                                    <label class="form-label fw-semibold text-muted">Payslip Output Preview:</label>
                                    <div class="border rounded-4 p-4 text-center bg-light h-100 d-flex flex-column justify-content-center align-items-center">
                                        <?php if (!empty($settings['signature_image'])): ?>
                                            <img src="<?= $settings['signature_image']; ?>" id="savedDrawnSign" alt="Sign" style="max-height: 65px;" class="mb-2">
                                        <?php endif; ?>
                                        <div id="signPreview" style="font-family: '<?= $settings['sign_font_style'] ?? 'Great Vibes'; ?>', cursive; font-size: 38px; color: #0b3c75; transform: rotate(-3deg);">
                                            <?= htmlspecialchars($settings['hr_head_name'] ?? 'Aadil Raine'); ?>
                                        </div>
                                        <div class="badge bg-success mt-2"><i class="fa-solid fa-circle-check me-1"></i> Verified & Approved</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 4: SMTP & WHATSAPP API INTEGRATION -->
                <div class="tab-pane fade" id="tabIntegration">
                    <div class="card shadow-sm border-0 rounded-4 mb-4">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-paper-plane text-primary me-2"></i>Outgoing SMTP Mailer</h5>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enable_email_alerts" id="emailAlerts" <?= (!empty($settings['enable_email_alerts'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="emailAlerts">Enable Automated Email Alerts</label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">SMTP Host</label>
                                    <input type="text" name="smtp_host" class="form-control font-monospace" value="<?= htmlspecialchars($settings['smtp_host'] ?? 'smtp.gmail.com'); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Port</label>
                                    <input type="number" name="smtp_port" class="form-control" value="<?= intval($settings['smtp_port'] ?? 587); ?>">
                                </div>
                                <div class="col-md-3">
                                    <label class="form-label fw-semibold">Encryption</label>
                                    <select name="smtp_encryption" class="form-select">
                                        <option value="tls" <?= (($settings['smtp_encryption'] ?? '') == 'tls') ? 'selected' : ''; ?>>TLS</option>
                                        <option value="ssl" <?= (($settings['smtp_encryption'] ?? '') == 'ssl') ? 'selected' : ''; ?>>SSL</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">SMTP Username / Email</label>
                                    <input type="text" name="smtp_user" class="form-control" value="<?= htmlspecialchars($settings['smtp_user'] ?? ''); ?>">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">SMTP Password / App Key</label>
                                    <input type="password" name="smtp_pass" class="form-control font-monospace" value="<?= htmlspecialchars($settings['smtp_pass'] ?? ''); ?>" placeholder="••••••••••••••••">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- WhatsApp API Card -->
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-body p-4">
                            <div class="d-flex justify-content-between align-items-center mb-3">
                                <h5 class="fw-bold text-success mb-0"><i class="fa-brands fa-whatsapp me-2"></i>WhatsApp Cloud Notification API</h5>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" name="enable_whatsapp_alerts" id="waAlerts" <?= (!empty($settings['enable_whatsapp_alerts'])) ? 'checked' : ''; ?>>
                                    <label class="form-check-label fw-bold" for="waAlerts">Enable WhatsApp Order Updates</label>
                                </div>
                            </div>
                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">WhatsApp Permanent Access Token</label>
                                    <input type="password" name="wa_api_key" class="form-control font-monospace" value="<?= htmlspecialchars($settings['wa_api_key'] ?? ''); ?>" placeholder="EAAG...">
                                </div>
                                <div class="col-md-6">
                                    <label class="form-label fw-semibold">Phone Number ID</label>
                                    <input type="text" name="wa_phone_number_id" class="form-control font-monospace" value="<?= htmlspecialchars($settings['wa_phone_number_id'] ?? ''); ?>" placeholder="1029384756...">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- TAB 5: SECURITY POLICIES & AUDIT -->
                <div class="tab-pane fade" id="tabSecurity">
                    <div class="card shadow-sm border-0 rounded-4">
                        <div class="card-body p-4">
                            <h5 class="fw-bold text-danger mb-3"><i class="fa-solid fa-shield-halved me-2"></i>Access Control & Security Rules</h5>
                            <div class="row g-3">
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Session Idle Timeout (Minutes)</label>
                                    <input type="number" name="session_timeout_min" class="form-control" value="<?= intval($settings['session_timeout_min'] ?? 60); ?>" min="5">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Max Login Attempt Lockout</label>
                                    <input type="number" name="max_login_attempts" class="form-control" value="<?= intval($settings['max_login_attempts'] ?? 5); ?>" min="3">
                                </div>
                                <div class="col-md-4">
                                    <label class="form-label fw-semibold">Audit Logs Retention (Days)</label>
                                    <input type="number" name="log_retention_days" class="form-control" value="<?= intval($settings['log_retention_days'] ?? 90); ?>" min="30">
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Submit Button Bar -->
            <div class="d-flex justify-content-end align-items-center mt-4">
                <button type="submit" name="save_settings" class="btn btn-primary px-5 py-2 fw-bold shadow">
                    <i class="fa-solid fa-floppy-disk me-2"></i> Save All Enterprise Settings
                </button>
            </div>
        </form>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<script>
// Live Signature Font & Name Preview
function updateSignPreview() {
    let name = document.getElementById("hrNameInput").value;
    let font = document.getElementById("signFontSelect").value;
    let preview = document.getElementById("signPreview");

    preview.innerText = name.trim() !== "" ? name : "Aadil Raine";
    preview.style.fontFamily = `'${font}', cursive`;
}

// Canvas Live Signature Drawing Logic
const canvas = document.getElementById("signaturePad");
const ctx = canvas.getContext("2d");
let isDrawing = false;

ctx.strokeStyle = "#0b3c75";
ctx.lineWidth = 2.5;
ctx.lineCap = "round";

function getPos(e) {
    const rect = canvas.getBoundingClientRect();
    const clientX = e.touches ? e.touches[0].clientX : e.clientX;
    const clientY = e.touches ? e.touches[0].clientY : e.clientY;
    return { x: clientX - rect.left, y: clientY - rect.top };
}

function startDraw(e) {
    isDrawing = true;
    const pos = getPos(e);
    ctx.beginPath();
    ctx.moveTo(pos.x, pos.y);
    e.preventDefault();
}

function draw(e) {
    if (!isDrawing) return;
    const pos = getPos(e);
    ctx.lineTo(pos.x, pos.y);
    ctx.stroke();
    e.preventDefault();
}

function stopDraw() { isDrawing = false; }

canvas.addEventListener("mousedown", startDraw);
canvas.addEventListener("mousemove", draw);
canvas.addEventListener("mouseup", stopDraw);
canvas.addEventListener("mouseleave", stopDraw);

canvas.addEventListener("touchstart", startDraw);
canvas.addEventListener("touchmove", draw);
canvas.addEventListener("touchend", stopDraw);

function clearSignature() {
    ctx.clearRect(0, 0, canvas.width, canvas.height);
    document.getElementById("signatureImageInput").value = "";
    let saved = document.getElementById("savedDrawnSign");
    if(saved) saved.style.display = "none";
}

function saveDrawnSignature() {
    const dataURL = canvas.toDataURL("image/png");
    document.getElementById("signatureImageInput").value = dataURL;
    alert("Drawn Signature Captured! Click 'Save All Enterprise Settings' to apply.");
}
</script>