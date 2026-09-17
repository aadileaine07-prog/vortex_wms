<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/config/database.php") 
    ? __DIR__ 
    : (file_exists(__DIR__ . "/../config/database.php") ? dirname(__DIR__, 1) : dirname(__DIR__, 2));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// 1. Database Connection Status
$dbStatus = true;
$dbError = "";
if (!$conn || mysqli_connect_errno()) {
    $dbStatus = false;
    $dbError = mysqli_connect_error();
}

// 2. Core Tables Audit
$tablesToCheck = [
    'products'         => 'Product Master Catalog',
    'warehouses'       => 'Warehouse Master',
    'bin_locations'    => 'Bin Locations Master',
    'inventory'        => 'Inventory Ledger Stock',
    'purchase_orders'  => 'Purchase Orders (PO)',
    'asn_headers'      => 'Advance Shipping Notices (ASN)',
    'inbound_shipments'=> 'Inbound GRN Receipts',
    'stock_movements'  => 'Stock Transfer Logs',
    'suppliers'        => 'Suppliers Master',
    'employees'        => 'HR / Employees Master'
];

$tableDiagnostics = [];
foreach ($tablesToCheck as $tbl => $label) {
    $exists = false;
    $rowCount = 0;
    $chk = @mysqli_query($conn, "SHOW TABLES LIKE '{$tbl}'");
    if ($chk && mysqli_num_rows($chk) > 0) {
        $exists = true;
        $cntRes = @mysqli_query($conn, "SELECT COUNT(*) as cnt FROM `{$tbl}`");
        if ($cntRes) {
            $cntRow = mysqli_fetch_assoc($cntRes);
            $rowCount = (int)$cntRow['cnt'];
        }
    }
    $tableDiagnostics[$tbl] = [
        'label' => $label,
        'exists' => $exists,
        'count' => $rowCount
    ];
}

// 3. Recursive Scan for Project PHP Files
$projectFiles = [];
if (is_dir($projectRoot)) {
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($projectRoot, RecursiveDirectoryIterator::SKIP_DOTS));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getExtension() === 'php') {
            // Exclude system_health itself or vendor folders if any
            $relativePath = str_replace($projectRoot, '', $file->getPathname());
            if (strpos($relativePath, 'system_health.php') === false && strpos($relativePath, '.git') === false) {
                $projectFiles[] = [
                    'path' => ltrim($relativePath, '/\\'),
                    'size' => round($file->getSize() / 1024, 2) . ' KB',
                    'modified' => date("d M Y H:i", $file->getMTime())
                ];
            }
        }
    }
}
sort($projectFiles);

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-stethoscope text-primary me-2"></i>System Health & File Scanner
            </h2>
            <p class="text-muted mb-0">Complete audit of database tables, record counts, and all project source files.</p>
        </div>
        <div>
            <a href="/vortex_wms/modules/dashboard/index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
                <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
            </a>
        </div>
    </div>

    <!-- DB Status Cards -->
    <div class="row g-3 mb-4">
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 <?= $dbStatus ? 'border-success' : 'border-danger'; ?> h-100">
                <small class="text-muted fw-bold text-uppercase">Database Connection</small>
                <div class="fs-4 fw-bold <?= $dbStatus ? 'text-success' : 'text-danger'; ?> my-1">
                    <?= $dbStatus ? '🟢 Connected Successfully' : '🔴 Connection Failed'; ?>
                </div>
                <small class="text-muted"><?= $dbStatus ? 'MySQL server responding normally.' : htmlspecialchars($dbError); ?></small>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary h-100">
                <small class="text-muted fw-bold text-uppercase">Total Project PHP Files Scanned</small>
                <div class="fs-4 fw-bold text-primary my-1 font-monospace">
                    📁 <?= count($projectFiles); ?> Files Detected
                </div>
                <small class="text-muted">Active source code structure inside vortex_wms</small>
            </div>
        </div>
    </div>

    <!-- Database Tables Audit -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-database text-primary me-2"></i>Database Tables Status</h5>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light">
                        <tr>
                            <th>Module Name</th>
                            <th>Table Name</th>
                            <th class="text-center">Status</th>
                            <th class="text-center">Total Records</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tableDiagnostics as $tblKey => $info): ?>
                            <tr>
                                <td><strong class="text-dark"><?= htmlspecialchars($info['label']); ?></strong></td>
                                <td><code class="text-primary font-monospace"><?= htmlspecialchars($tblKey); ?></code></td>
                                <td class="text-center">
                                    <?php if ($info['exists']): ?>
                                        <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Exists</span>
                                    <?php else: ?>
                                        <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Missing</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center font-monospace fw-bold"><?= number_format($info['count']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Project Files Directory Scanner -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center">
            <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-folder-tree text-primary me-2"></i>Project Source Files Directory</h5>
            <span class="badge bg-secondary-subtle text-dark border px-3 py-1 rounded-pill font-monospace"><?= count($projectFiles); ?> Files</span>
        </div>
        <div class="card-body p-4">
            <div class="table-responsive" style="max-height: 450px; overflow-y: auto;">
                <table class="table table-hover align-middle mb-0">
                    <thead class="table-light sticky-top">
                        <tr>
                            <th>#</th>
                            <th>File Path (Relative to vortex_wms)</th>
                            <th>File Size</th>
                            <th>Last Modified</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($projectFiles)): ?>
                            <?php foreach ($projectFiles as $idx => $file): ?>
                                <tr>
                                    <td class="text-muted font-monospace">#<?= $idx + 1; ?></td>
                                    <td><code class="text-dark font-monospace fw-bold"><?= htmlspecialchars($file['path']); ?></code></td>
                                    <td><span class="badge bg-light text-dark border"><?= $file['size']; ?></span></td>
                                    <td><small class="text-muted"><?= $file['modified']; ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" class="text-center py-4 text-muted">No PHP files found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>