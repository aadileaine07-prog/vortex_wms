<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../config/database.php") 
    ? dirname(__DIR__, 1) 
    : (file_exists(__DIR__ . "/../../config/database.php") 
        ? dirname(__DIR__, 2) 
        : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4)));

require_once $projectRoot . "/config/database.php";

$selectedTable = isset($_GET['table']) ? trim($_GET['table']) : '';
$action        = isset($_GET['action']) ? trim($_GET['action']) : '';
$editId        = isset($_GET['id']) ? intval($_GET['id']) : 0;

// Security check: Only allow existing tables
$validTables = [];
$tRes = mysqli_query($conn, "SHOW TABLES");
if ($tRes) {
    while ($tRow = mysqli_fetch_array($tRes)) {
        $validTables[] = $tRow[0];
    }
}

if (!empty($selectedTable) && !in_array($selectedTable, $validTables) && $action !== 'drop_table') {
    $_SESSION['error'] = "Invalid table selected!";
    header("Location: check_tables.php");
    exit();
}

// -------------------------------------------------------------
// 1. HANDLE COMPLETE TABLE DROP / DELETE
// -------------------------------------------------------------
if ($action === 'drop_table' && !empty($selectedTable)) {
    if (in_array($selectedTable, $validTables)) {
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0;");
        $dropQuery = "DROP TABLE `$selectedTable`";
        
        if (mysqli_query($conn, $dropQuery)) {
            $_SESSION['success'] = "Table <strong>`$selectedTable`</strong> has been completely dropped from the database.";
        } else {
            $_SESSION['error'] = "Failed to drop table: " . mysqli_error($conn);
        }
        mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1;");
    } else {
        $_SESSION['error'] = "Table not found.";
    }
    header("Location: check_tables.php");
    exit();
}

// -------------------------------------------------------------
// 2. HANDLE ROW UPDATE (POST)
// -------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_row_edit'])) {
    $targetTable = mysqli_real_escape_string($conn, $_POST['target_table']);
    $primaryKey  = mysqli_real_escape_string($conn, $_POST['primary_key']);
    $primaryVal  = mysqli_real_escape_string($conn, $_POST['primary_val']);

    if (in_array($targetTable, $validTables)) {
        $updateParts = [];
        foreach ($_POST['fields'] as $colName => $colVal) {
            $cEsc = mysqli_real_escape_string($conn, $colName);
            if ($colVal === '') {
                $updateParts[] = "`$cEsc` = NULL";
            } else {
                $vEsc = mysqli_real_escape_string($conn, $colVal);
                $updateParts[] = "`$cEsc` = '$vEsc'";
            }
        }

        if (!empty($updateParts)) {
            $updSql = "UPDATE `$targetTable` SET " . implode(", ", $updateParts) . " WHERE `$primaryKey` = '$primaryVal' LIMIT 1";
            if (mysqli_query($conn, $updSql)) {
                $_SESSION['success'] = "Row #$primaryVal in table <strong>`$targetTable`</strong> updated successfully!";
            } else {
                $_SESSION['error'] = "Update failed: " . mysqli_error($conn);
            }
        }
    }
    header("Location: check_tables.php?table=" . urlencode($targetTable));
    exit();
}

// -------------------------------------------------------------
// 3. HANDLE ROW DELETE (GET)
// -------------------------------------------------------------
if ($action === 'delete_row' && !empty($selectedTable) && $editId > 0) {
    $pkCol = 'id';
    $pkRes = mysqli_query($conn, "SHOW KEYS FROM `$selectedTable` WHERE Key_name = 'PRIMARY'");
    if ($pkRes && $pkRow = mysqli_fetch_assoc($pkRes)) {
        $pkCol = $pkRow['Column_name'];
    }

    if (mysqli_query($conn, "DELETE FROM `$selectedTable` WHERE `$pkCol` = '$editId' LIMIT 1")) {
        $_SESSION['success'] = "Row #$editId removed from `$selectedTable`.";
    } else {
        $_SESSION['error'] = "Delete failed: " . mysqli_error($conn);
    }
    header("Location: check_tables.php?table=" . urlencode($selectedTable));
    exit();
}

// -------------------------------------------------------------
// 4. HANDLE TABLE TRUNCATE
// -------------------------------------------------------------
if ($action === 'truncate' && !empty($selectedTable)) {
    if (mysqli_query($conn, "TRUNCATE TABLE `$selectedTable`")) {
        $_SESSION['success'] = "Table `$selectedTable` emptied successfully!";
    } else {
        $_SESSION['error'] = "Truncate failed: " . mysqli_error($conn);
    }
    header("Location: check_tables.php?table=" . urlencode($selectedTable));
    exit();
}

// Global Database Stats
$totalDatabaseRows = 0;
$totalDatabaseSizeMB = 0;
$dbStatsRes = mysqli_query($conn, "
    SELECT 
        SUM(TABLE_ROWS) as total_rows,
        ROUND(SUM(DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) as total_mb
    FROM information_schema.tables 
    WHERE table_schema = DATABASE()
");
if ($dbStatsRes && $sRow = mysqli_fetch_assoc($dbStatsRes)) {
    $totalDatabaseRows = (int)($sRow['total_rows'] ?? 0);
    $totalDatabaseSizeMB = (float)($sRow['total_mb'] ?? 0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Studio & Live Inspector &bull; Vortex WMS</title>
    
    <!-- Google Fonts & Bootstrap 5 -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    
    <style>
        :root {
            --primary: #4f46e5;
            --primary-light: #eef2ff;
            --primary-dark: #3730a3;
            --dark-bg: #0f172a;
            --card-bg: #ffffff;
            --border: #e2e8f0;
            --text-main: #0f172a;
            --text-muted: #64748b;
        }

        body {
            background-color: #f1f5f9;
            font-family: 'Plus Jakarta Sans', sans-serif;
            color: var(--text-main);
            min-height: 100vh;
            padding-bottom: 50px;
        }

        .font-mono {
            font-family: 'JetBrains Mono', monospace !important;
        }

        /* Glassmorphism Header Bar */
        .glass-header {
            background: linear-gradient(135deg, #1e1b4b 0%, #0f172a 100%);
            color: #ffffff;
            border-radius: 20px;
            padding: 24px 30px;
            box-shadow: 0 10px 30px rgba(15, 23, 42, 0.15);
            position: relative;
            overflow: hidden;
        }
        .glass-header::after {
            content: '';
            position: absolute;
            top: -50%;
            right: -10%;
            width: 300px;
            height: 300px;
            background: radial-gradient(circle, rgba(99, 102, 241, 0.25) 0%, rgba(99, 102, 241, 0) 70%);
            border-radius: 50%;
            pointer-events: none;
        }

        /* Metric Cards */
        .metric-card {
            background: #ffffff;
            border: 1px solid rgba(226, 232, 240, 0.8);
            border-radius: 16px;
            padding: 18px 22px;
            transition: all 0.2s cubic-bezier(0.16, 1, 0.3, 1);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.03);
        }
        .metric-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 12px 25px rgba(0, 0, 0, 0.06);
            border-color: #cbd5e1;
        }

        /* Content Panel */
        .content-card {
            background: #ffffff;
            border: 1px solid var(--border);
            border-radius: 18px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        /* Table Aesthetics */
        .table-custom thead th {
            background: #f8fafc;
            color: #475569;
            font-size: 0.76rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            padding: 14px 18px;
            border-bottom: 2px solid #e2e8f0;
        }
        .table-custom tbody td {
            padding: 14px 18px;
            vertical-align: middle;
            font-size: 0.875rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .table-custom tbody tr {
            transition: background 0.15s ease;
        }
        .table-custom tbody tr:hover {
            background-color: #f8fafc;
        }

        /* Modern Badges */
        .badge-pill-soft {
            padding: 6px 12px;
            font-weight: 600;
            border-radius: 30px;
            font-size: 0.75rem;
            display: inline-flex;
            align-items: center;
            gap: 5px;
        }
        .badge-indigo { background: #e0e7ff; color: #4338ca; }
        .badge-emerald { background: #d1fae5; color: #065f46; }
        .badge-amber { background: #fef3c7; color: #92400e; }
        .badge-rose { background: #ffe4e6; color: #9f1239; }

        /* Buttons & Actions */
        .btn-action {
            width: 32px;
            height: 32px;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            border-radius: 8px;
            font-size: 0.82rem;
            transition: all 0.2s ease;
        }

        /* Custom Scrollbar for Large Data */
        .table-scroll-container::-webkit-scrollbar {
            height: 8px;
            width: 8px;
        }
        .table-scroll-container::-webkit-scrollbar-thumb {
            background: #cbd5e1;
            border-radius: 6px;
        }
    </style>
</head>
<body class="p-3 p-md-4">

<div class="container-fluid px-md-4">

    <!-- Top Hero Header -->
    <div class="glass-header mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <div class="d-flex align-items-center gap-2 mb-1">
                <span class="badge bg-primary bg-opacity-25 text-white border border-primary border-opacity-50 px-2.5 py-1 rounded-pill small font-mono">
                    <i class="fa-solid fa-server me-1"></i> MYSQL CORE
                </span>
                <span class="text-white-50 small">&bull; Database Schema Inspector</span>
            </div>
            <h3 class="fw-bold mb-0 text-white">Database Studio & Data Inspector</h3>
            <p class="text-white-50 mb-0 small mt-1">Live table audit, inline record modifications, and relational integrity controls</p>
        </div>
        <div class="d-flex gap-2">
            <?php if (!empty($selectedTable)): ?>
                <a href="check_tables.php" class="btn btn-light bg-opacity-10 text-white border-0 rounded-pill px-3 shadow-sm">
                    <i class="fa-solid fa-arrow-left me-1"></i> Master Schema
                </a>
            <?php endif; ?>
            <a href="inventory/index.php" class="btn btn-primary rounded-pill px-3 shadow-sm fw-semibold">
                <i class="fa-solid fa-boxes-stacked me-1"></i> Open WMS App
            </a>
        </div>
    </div>

    <!-- KPI Metric Summary Tiles -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6">
            <div class="metric-card d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Active Tables</span>
                    <h3 class="fw-bold mb-0 text-dark mt-1 font-mono"><?= count($validTables); ?></h3>
                </div>
                <div class="p-3 bg-indigo-subtle text-primary rounded-4" style="background:#eef2ff;">
                    <i class="fa-solid fa-table-cells-large fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Database Records</span>
                    <h3 class="fw-bold mb-0 text-dark mt-1 font-mono"><?= number_format($totalDatabaseRows); ?></h3>
                </div>
                <div class="p-3 rounded-4" style="background:#ecfdf5; color:#059669;">
                    <i class="fa-solid fa-database fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Estimated Volume</span>
                    <h3 class="fw-bold mb-0 text-dark mt-1 font-mono"><?= $totalDatabaseSizeMB; ?> <small class="fs-6 text-muted">MB</small></h3>
                </div>
                <div class="p-3 rounded-4" style="background:#fef3c7; color:#d97706;">
                    <i class="fa-solid fa-hard-drive fa-2x"></i>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6">
            <div class="metric-card d-flex justify-content-between align-items-center">
                <div>
                    <span class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Engine Status</span>
                    <h4 class="fw-bold mb-0 text-dark mt-1 text-success"><i class="fa-solid fa-circle-check text-success me-1 fs-6"></i> Connected</h4>
                </div>
                <div class="p-3 rounded-4" style="background:#f1f5f9; color:#475569;">
                    <i class="fa-solid fa-network-wired fa-2x"></i>
                </div>
            </div>
        </div>
    </div>

    <!-- Alert Notifications -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm p-3 mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="fa-solid fa-circle-check text-success fs-5"></i>
            <div><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm p-3 mb-4 d-flex align-items-center gap-2" role="alert">
            <i class="fa-solid fa-triangle-exclamation text-danger fs-5"></i>
            <div><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
            <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <?php if (empty($selectedTable)): ?>
        <!-- ===================================================================
             VIEW 1: ALL TABLES MASTER GRID VIEW
             =================================================================== -->
        <div class="content-card">
            <div class="p-3 px-4 border-bottom bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <h5 class="fw-bold mb-0 text-dark">Database Tables Directory</h5>
                    <span class="badge bg-light text-muted border px-2.5 py-1 font-mono"><?= count($validTables); ?> Items</span>
                </div>
                <div style="min-width: 280px;">
                    <div class="input-group">
                        <span class="input-group-text bg-light border-end-0 text-muted"><i class="fa-solid fa-magnifying-glass"></i></span>
                        <input type="text" id="tableFilterInput" class="form-control border-start-0" placeholder="Filter schema tables...">
                    </div>
                </div>
            </div>

            <div class="table-responsive">
                <table class="table table-custom mb-0 align-middle" id="masterTableDirectory">
                    <thead>
                        <tr>
                            <th width="70">#</th>
                            <th>Table Name</th>
                            <th class="text-center">Total Rows</th>
                            <th>Primary Key</th>
                            <th>Columns</th>
                            <th width="220" class="text-center">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i = 1;
                        foreach ($validTables as $tName): 
                            $cntRes = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM `$tName`");
                            $rowCount = ($cntRes) ? mysqli_fetch_assoc($cntRes)['cnt'] : 0;
                            
                            $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `$tName`");
                            $colCount = ($colRes) ? mysqli_num_rows($colRes) : 0;

                            $pkCol = '-';
                            $pkRes = mysqli_query($conn, "SHOW KEYS FROM `$tName` WHERE Key_name = 'PRIMARY'");
                            if ($pkRes && $pkRow = mysqli_fetch_assoc($pkRes)) {
                                $pkCol = $pkRow['Column_name'];
                            }
                        ?>
                            <tr class="table-row-item">
                                <td class="text-muted fw-bold"><?= sprintf("%02d", $i++); ?></td>
                                <td>
                                    <a href="check_tables.php?table=<?= urlencode($tName); ?>" class="fw-bold text-decoration-none text-dark d-flex align-items-center gap-2 table-name-label">
                                        <div class="p-2 bg-light rounded-3 text-primary border">
                                            <i class="fa-solid fa-table"></i>
                                        </div>
                                        <span><?= htmlspecialchars($tName); ?></span>
                                    </a>
                                </td>
                                <td class="text-center">
                                    <span class="badge-pill-soft <?= ($rowCount > 0) ? 'badge-emerald' : 'bg-light text-muted border'; ?> font-mono">
                                        <i class="fa-solid fa-bars-staggered"></i> <?= number_format($rowCount); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($pkCol !== '-'): ?>
                                        <span class="badge-pill-soft badge-indigo font-mono"><i class="fa-solid fa-key"></i> <?= htmlspecialchars($pkCol); ?></span>
                                    <?php else: ?>
                                        <span class="text-muted small">&mdash;</span>
                                    <?php endif; ?>
                                </td>
                                <td><span class="text-secondary small fw-semibold font-mono"><?= $colCount; ?> Columns</span></td>
                                <td class="text-center">
                                    <div class="d-inline-flex gap-2">
                                        <a href="check_tables.php?table=<?= urlencode($tName); ?>" class="btn btn-sm btn-primary rounded-pill px-3 shadow-sm">
                                            <i class="fa-solid fa-pen-to-square me-1"></i> Inspect
                                        </a>
                                        <a href="check_tables.php?table=<?= urlencode($tName); ?>&action=drop_table" class="btn btn-action btn-outline-danger" onclick="return confirm('⚠️ DANGER: Permanently DROP table `<?= $tName; ?>`?');" title="Drop Table">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

    <?php elseif ($action === 'edit_row' && $editId > 0): ?>
        <!-- ===================================================================
             VIEW 2: EDIT SINGLE ROW FORM VIEW
             =================================================================== -->
        <?php 
        $pkCol = 'id';
        $pkRes = mysqli_query($conn, "SHOW KEYS FROM `$selectedTable` WHERE Key_name = 'PRIMARY'");
        if ($pkRes && $pkRow = mysqli_fetch_assoc($pkRes)) {
            $pkCol = $pkRow['Column_name'];
        }

        $rowRes = mysqli_query($conn, "SELECT * FROM `$selectedTable` WHERE `$pkCol` = '$editId' LIMIT 1");
        if (!$rowRes || mysqli_num_rows($rowRes) === 0) {
            echo "<div class='alert alert-danger'>Record not found!</div>";
        } else {
            $rowData = mysqli_fetch_assoc($rowRes);
        ?>
        <div class="content-card col-lg-8 mx-auto">
            <div class="p-3 px-4 border-bottom bg-white d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="fw-bold mb-0 text-dark">
                        <i class="fa-solid fa-pen-to-square text-warning me-2"></i>Modify Record #<?= $editId; ?>
                    </h5>
                    <small class="text-muted">Target Table: <code class="text-primary font-mono"><?= htmlspecialchars($selectedTable); ?></code></small>
                </div>
                <a href="check_tables.php?table=<?= urlencode($selectedTable); ?>" class="btn btn-sm btn-light border rounded-pill px-3">
                    <i class="fa-solid fa-xmark me-1"></i> Cancel
                </a>
            </div>
            
            <div class="p-4">
                <form method="POST">
                    <input type="hidden" name="target_table" value="<?= htmlspecialchars($selectedTable); ?>">
                    <input type="hidden" name="primary_key" value="<?= htmlspecialchars($pkCol); ?>">
                    <input type="hidden" name="primary_val" value="<?= htmlspecialchars($editId); ?>">

                    <div class="row g-3">
                        <?php foreach ($rowData as $col => $val): ?>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-muted mb-1 d-flex justify-content-between">
                                    <span><?= htmlspecialchars($col); ?></span>
                                    <?php if ($col === $pkCol): ?><span class="badge badge-indigo">PRIMARY KEY</span><?php endif; ?>
                                </label>
                                <?php if ($col === $pkCol): ?>
                                    <input type="text" class="form-control bg-light font-mono text-primary fw-bold" value="<?= htmlspecialchars($val ?? ''); ?>" readonly>
                                <?php elseif (strlen($val ?? '') > 60): ?>
                                    <textarea name="fields[<?= htmlspecialchars($col); ?>]" class="form-control" rows="3"><?= htmlspecialchars($val ?? ''); ?></textarea>
                                <?php else: ?>
                                    <input type="text" name="fields[<?= htmlspecialchars($col); ?>]" class="form-control" value="<?= htmlspecialchars($val ?? ''); ?>">
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <div class="mt-4 pt-3 border-top text-end d-flex justify-content-between align-items-center">
                        <a href="check_tables.php?table=<?= urlencode($selectedTable); ?>" class="btn btn-outline-secondary rounded-pill px-4">Discard</a>
                        <button type="submit" name="save_row_edit" class="btn btn-success fw-bold rounded-pill px-5 shadow-sm">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Commit & Save Changes
                        </button>
                    </div>
                </form>
            </div>
        </div>
        <?php } ?>

    <?php else: ?>
        <!-- ===================================================================
             VIEW 3: LIVE ROW INSPECTOR FOR SELECTED TABLE
             =================================================================== -->
        <?php 
        $pkCol = 'id';
        $pkRes = mysqli_query($conn, "SHOW KEYS FROM `$selectedTable` WHERE Key_name = 'PRIMARY'");
        if ($pkRes && $pkRow = mysqli_fetch_assoc($pkRes)) {
            $pkCol = $pkRow['Column_name'];
        }

        $tableData = mysqli_query($conn, "SELECT * FROM `$selectedTable` ORDER BY `$pkCol` DESC LIMIT 100");
        $cols = [];
        if ($tableData && mysqli_num_rows($tableData) > 0) {
            $first = mysqli_fetch_assoc($tableData);
            $cols = array_keys($first);
            mysqli_data_seek($tableData, 0);
        } else {
            $cRes = mysqli_query($conn, "SHOW COLUMNS FROM `$selectedTable`");
            while ($c = mysqli_fetch_assoc($cRes)) {
                $cols[] = $c['Field'];
            }
        }
        ?>
        <div class="content-card mb-4">
            <div class="p-3 px-4 border-bottom bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="fs-5 fw-bold text-dark"><i class="fa-solid fa-table text-primary me-2"></i>Table: <code class="font-mono"><?= htmlspecialchars($selectedTable); ?></code></span>
                    <span class="badge-pill-soft badge-indigo font-mono"><?= mysqli_num_rows($tableData); ?> Rows Loaded</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="check_tables.php?table=<?= urlencode($selectedTable); ?>&action=truncate" class="btn btn-outline-warning text-dark btn-sm rounded-pill px-3" onclick="return confirm('⚠️ Empty all data in <?= $selectedTable; ?>?');">
                        <i class="fa-solid fa-eraser me-1"></i> Truncate Data
                    </a>
                    <a href="check_tables.php?table=<?= urlencode($selectedTable); ?>&action=drop_table" class="btn btn-outline-danger btn-sm rounded-pill px-3" onclick="return confirm('⚠️ DANGER: Drop table `<?= $selectedTable; ?>`?');">
                        <i class="fa-solid fa-trash me-1"></i> Drop Table
                    </a>
                    <a href="check_tables.php" class="btn btn-secondary btn-sm rounded-pill px-3">
                        <i class="fa-solid fa-arrow-left me-1"></i> Back
                    </a>
                </div>
            </div>

            <div class="p-0">
                <div class="table-responsive table-scroll-container" style="max-height: 580px;">
                    <table class="table table-custom mb-0 text-nowrap align-middle">
                        <thead class="sticky-top shadow-xs">
                            <tr>
                                <th width="90" class="text-center bg-light">Action</th>
                                <?php foreach ($cols as $col): ?>
                                    <th class="bg-light"><?= htmlspecialchars($col); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($tableData && mysqli_num_rows($tableData) > 0): ?>
                                <?php while ($row = mysqli_fetch_assoc($tableData)): ?>
                                    <tr>
                                        <td class="text-center">
                                            <div class="d-inline-flex gap-1">
                                                <a href="check_tables.php?table=<?= urlencode($selectedTable); ?>&action=edit_row&id=<?= urlencode($row[$pkCol] ?? 0); ?>" class="btn-action bg-primary text-white text-decoration-none shadow-xs" title="Edit Row">
                                                    <i class="fa-solid fa-pen-to-square"></i>
                                                </a>
                                                <a href="check_tables.php?table=<?= urlencode($selectedTable); ?>&action=delete_row&id=<?= urlencode($row[$pkCol] ?? 0); ?>" class="btn-action bg-danger text-white text-decoration-none shadow-xs" onclick="return confirm('Delete this row?');" title="Delete Row">
                                                    <i class="fa-solid fa-trash"></i>
                                                </a>
                                            </div>
                                        </td>
                                        <?php foreach ($cols as $col): ?>
                                            <td>
                                                <span class="small font-mono text-dark"><?= htmlspecialchars($row[$col] ?? 'NULL'); ?></span>
                                            </td>
                                        <?php endforeach; ?>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="<?= count($cols) + 1; ?>" class="text-center py-5 text-muted">
                                        <i class="fa-solid fa-folder-open fa-3x mb-3 d-block opacity-25"></i>
                                        <h6 class="text-dark fw-bold">Table is currently empty</h6>
                                        <p class="small mb-0">No records found in `<?= htmlspecialchars($selectedTable); ?>`.</p>
                                    </td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    <?php endif; ?>

</div>

<script>
// Real-time Dynamic Table Search Filter
document.addEventListener('DOMContentLoaded', function() {
    const filterInput = document.getElementById('tableFilterInput');
    if (filterInput) {
        filterInput.addEventListener('keyup', function() {
            const query = this.value.toLowerCase().trim();
            const rows = document.querySelectorAll('#masterTableDirectory tbody tr.table-row-item');
            
            rows.forEach(row => {
                const label = row.querySelector('.table-name-label span').innerText.toLowerCase();
                row.style.display = (query === '' || label.includes(query)) ? '' : 'none';
            });
        });
    }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>