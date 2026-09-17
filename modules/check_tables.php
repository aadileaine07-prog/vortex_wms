<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$conn = mysqli_connect("localhost", "root", "root", "vortex_wms", 8889);
if (!$conn) {
    die("Database Connection Failed");
}
mysqli_set_charset($conn, "utf8mb4");

$selectedTable = isset($_GET['table']) ? trim($_GET['table']) : '';
$action        = isset($_GET['action']) ? trim($_GET['action']) : '';

// Get all tables
$validTables = [];
$tRes = mysqli_query($conn, "SHOW TABLES");
if ($tRes) {
    while ($tRow = mysqli_fetch_array($tRes)) {
        $validTables[] = $tRow[0];
    }
}

// Handle Table Drop
if ($action === 'drop_table' && !empty($selectedTable)) {
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 0;");
    mysqli_query($conn, "DROP TABLE `$selectedTable`");
    mysqli_query($conn, "SET FOREIGN_KEY_CHECKS = 1;");
    $_SESSION['success'] = "Table `$selectedTable` dropped successfully.";
    header("Location: check_tables.php");
    exit();
}

// Handle Row Delete
if ($action === 'delete_row' && !empty($selectedTable) && isset($_GET['id'])) {
    $rowId = intval($_GET['id']);
    mysqli_query($conn, "DELETE FROM `$selectedTable` WHERE id = $rowId LIMIT 1");
    $_SESSION['success'] = "Row deleted successfully from `$selectedTable`.";
    header("Location: check_tables.php?table=$selectedTable&action=view_table");
    exit();
}

// Handle Row Insert/Update (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_row'])) {
    $tblName = $_POST['table_name'] ?? '';
    $rowId   = intval($_POST['row_id'] ?? 0);
    $data    = $_POST['col_data'] ?? [];

    if (!empty($tblName)) {
        $columns = [];
        $values  = [];
        $updates = [];

        foreach ($data as $col => $val) {
            $safeCol = mysqli_real_escape_string($conn, $col);
            $safeVal = mysqli_real_escape_string($conn, $val);

            $columns[] = "`$safeCol`";
            $values[]  = "'$safeVal'";
            $updates[] = "`$safeCol` = '$safeVal'";
        }

        if ($rowId > 0) {
            $setStr = implode(", ", $updates);
            $sql = "UPDATE `$tblName` SET $setStr WHERE id = $rowId";
            $msg = "Row updated successfully!";
        } else {
            $colStr = implode(", ", $columns);
            $valStr = implode(", ", $values);
            $sql = "INSERT INTO `$tblName` ($colStr) VALUES ($valStr)";
            $msg = "New row inserted successfully!";
        }

        if (mysqli_query($conn, $sql)) {
            $_SESSION['success'] = $msg;
        } else {
            $_SESSION['error'] = "Database Error: " . mysqli_error($conn);
        }
    }
    header("Location: check_tables.php?table=$tblName&action=view_table");
    exit();
}

// Handle Table Creation (POST)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_new_table'])) {
    $newTableName = trim($_POST['new_table_name'] ?? '');
    $colNames     = $_POST['col_name'] ?? [];
    $colTypes     = $_POST['col_type'] ?? [];
    $colLengths   = $_POST['col_length'] ?? [];
    $colNulls     = $_POST['col_null'] ?? [];
    $colKeys      = $_POST['col_key'] ?? [];

    if (!empty($newTableName)) {
        $sqlParts = [];
        $hasPrimary = false;

        for ($k = 0; $k < count($colNames); $k++) {
            $cName = trim($colNames[$k]);
            if (empty($cName)) continue;

            $cType = $colTypes[$k] ?? 'VARCHAR';
            $cLen  = trim($colLengths[$k] ?? '');
            $cNull = isset($colNulls[$k]) ? 'NULL' : 'NOT NULL';
            $cKey  = $colKeys[$k] ?? '';

            $lenStr = !empty($cLen) ? "($cLen)" : "";
            if ($cType === 'TEXT' || $cType === 'DATE' || $cType === 'TIMESTAMP' || $cType === 'DATETIME') $lenStr = "";

            $line = "`$cName` $cType $lenStr $cNull";
            if ($cKey === 'PRIMARY') {
                $line .= " AUTO_INCREMENT";
                $hasPrimary = true;
            }
            if ($cKey === 'UNIQUE') $line .= " UNIQUE";

            $sqlParts[] = $line;
        }

        if ($hasPrimary) {
            for ($k = 0; $k < count($colNames); $k++) {
                if (($colKeys[$k] ?? '') === 'PRIMARY') {
                    $sqlParts[] = "PRIMARY KEY (`" . trim($colNames[$k]) . "`)";
                    break;
                }
            }
        }

        $createSql = "CREATE TABLE `$newTableName` (" . implode(", ", $sqlParts) . ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;";
        if (mysqli_query($conn, $createSql)) {
            $_SESSION['success'] = "Table `$newTableName` created successfully!";
            header("Location: check_tables.php?table=$newTableName&action=view_table");
            exit();
        } else {
            $_SESSION['error'] = "Error: " . mysqli_error($conn);
        }
    }
    header("Location: check_tables.php?action=create_table");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Database Studio &bull; Vortex WMS</title>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700&family=JetBrains+Mono:wght@400;500&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
    <style>
        body { background: #1cccdf; font-family: 'Plus Jakarta Sans', sans-serif; color: #2b497b; padding: 30px; }
        .glass-header { 
            background: linear-gradient(135deg, #0f172a 0%, #1e1b4b 100%) !important; 
            color: #ffffff !important; 
            border-radius: 24px; 
            padding: 30px; 
            box-shadow: 0 10px 25px rgba(15,23,42,0.15); 
        }
        .glass-header h3, .glass-header p { color: #ffffff !important; }
        .content-card { background: #fff; border: 1px solid #e2e8f0; border-radius: 20px; box-shadow: 0 4px 20px rgba(0,0,0,0.03); overflow: hidden; }
        .font-mono { font-family: 'JetBrains Mono', monospace; }
        .table th { font-weight: 600; font-size: 13px; text-transform: uppercase; letter-spacing: 0.5px; }
        .btn-action { transition: all 0.2s ease; }
        .btn-action:hover { transform: translateY(-1px); }
    </style>
</head>
<body>
<div class="container-fluid px-md-4">
    <!-- HEADER -->
    <div class="glass-header mb-4 d-flex justify-content-between align-items-center flex-wrap gap-3">
        <div>
            <h3 class="fw-bold mb-1 text-white"><i class="fa-solid fa-database me-2 text-info"></i>Database Studio & Data Inspector</h3>
            <p class="text-white-50 mb-0 small">Advanced schema inspector, live table builder, and inline record manager</p>
        </div>
        <div class="d-flex gap-2">
            <a href="check_tables.php?action=create_table" class="btn btn-success rounded-pill px-4 fw-bold shadow-sm">
                <i class="fa-solid fa-circle-plus me-1"></i> New Table
            </a>
            <a href="../../../dashboard.php" class="btn btn-outline-light rounded-pill px-4 fw-semibold">
                <i class="fa-solid fa-arrow-left me-1"></i> Dashboard
            </a>
        </div>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success rounded-4 border-0 shadow-sm p-3 mb-4 d-flex align-items-center">
            <i class="fa-solid fa-circle-check me-2 fs-5"></i><div><?= $_SESSION['success']; unset($_SESSION['success']); ?></div>
        </div>
    <?php endif; ?>
    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger rounded-4 border-0 shadow-sm p-3 mb-4 d-flex align-items-center">
            <i class="fa-solid fa-triangle-exclamation me-2 fs-5"></i><div><?= $_SESSION['error']; unset($_SESSION['error']); ?></div>
        </div>
    <?php endif; ?>

    <!-- VIEW & BROWSE TABLE DATA / EDIT ROW -->
    <?php if ($action === 'view_table' && !empty($selectedTable)): ?>
        <?php
        $columns = [];
        $colRes = mysqli_query($conn, "SHOW COLUMNS FROM `$selectedTable`");
        while ($cRow = mysqli_fetch_assoc($colRes)) {
            $columns[] = $cRow;
        }

        $editRowData = [];
        if (isset($_GET['edit_id'])) {
            $editId = intval($_GET['edit_id']);
            $eRes = mysqli_query($conn, "SELECT * FROM `$selectedTable` WHERE id = $editId LIMIT 1");
            if ($eRes) $editRowData = mysqli_fetch_assoc($eRes);
        }
        ?>
        <div class="content-card p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <div>
                    <h5 class="fw-bold mb-1"><i class="fa-solid fa-table-cells me-2 text-primary"></i>Active Table: <span class="text-dark font-mono bg-light px-2 py-1 rounded"><?= htmlspecialchars($selectedTable); ?></span></h5>
                    <span class="text-muted small">Manage rows, execute inline modifications, or add new records.</span>
                </div>
                <div class="d-flex gap-2">
                    <a href="check_tables.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back to Tables</a>
                    <a href="check_tables.php?table=<?= $selectedTable; ?>&action=view_table" class="btn btn-primary btn-sm rounded-pill px-3"><i class="fa-solid fa-plus me-1"></i> Add Record</a>
                </div>
            </div>

            <!-- INSERT / EDIT FORM -->
            <div class="card bg-light border-0 rounded-4 p-4 mb-4 shadow-sm">
                <h6 class="fw-bold mb-3 text-dark"><i class="fa-solid fa-pen-to-square me-2 text-warning"></i><?= !empty($editRowData) ? "Edit Existing Record (ID: {$editRowData['id']})" : "Insert New Record Form"; ?></h6>
                <form method="POST">
                    <input type="hidden" name="table_name" value="<?= htmlspecialchars($selectedTable); ?>">
                    <input type="hidden" name="row_id" value="<?= $editRowData['id'] ?? 0; ?>">
                    <div class="row g-3">
                        <?php foreach ($columns as $col): 
                            if ($col['Field'] === 'id' && empty($editRowData)) continue; 
                            if ($col['Field'] === 'created_at' && empty($editRowData)) continue;
                            $val = $editRowData[$col['Field']] ?? '';
                        ?>
                            <div class="col-md-4">
                                <label class="form-label small fw-bold font-mono text-secondary"><?= $col['Field']; ?> <span class="badge bg-secondary-subtle text-secondary fw-normal" style="font-size:10px;"><?= $col['Type']; ?></span></label>
                                <input type="text" name="col_data[<?= $col['Field']; ?>]" class="form-control form-control-sm font-mono shadow-none" value="<?= htmlspecialchars($val); ?>" placeholder="Enter value...">
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div class="mt-4 text-end">
                        <?php if(!empty($editRowData)): ?>
                            <a href="check_tables.php?table=<?= $selectedTable; ?>&action=view_table" class="btn btn-outline-secondary btn-sm rounded-pill px-4 me-2">Cancel</a>
                        <?php endif; ?>
                        <button type="submit" name="save_row" class="btn btn-success btn-sm rounded-pill px-4 fw-bold shadow-sm"><i class="fa-solid fa-floppy-disk me-1"></i> Save Record</button>
                    </div>
                </form>
            </div>

            <!-- TABLE ROWS BROWSER -->
            <h6 class="fw-bold mb-3 text-dark">Existing Table Rows (Latest 100)</h6>
            <div class="table-responsive">
                <table class="table table-hover table-bordered align-middle small">
                    <thead class="table-dark">
                        <tr>
                            <th width="90" class="text-center">Actions</th>
                            <?php foreach ($columns as $c): ?>
                                <th class="font-mono"><?= $c['Field']; ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $rowsRes = mysqli_query($conn, "SELECT * FROM `$selectedTable` ORDER BY id DESC LIMIT 100");
                        if ($rowsRes && mysqli_num_rows($rowsRes) > 0) {
                            while ($r = mysqli_fetch_assoc($rowsRes)) {
                                echo "<tr>";
                                echo "<td class='text-center text-nowrap'>
                                        <a href='check_tables.php?table=$selectedTable&action=view_table&edit_id={$r['id']}' class='btn btn-warning btn-sm py-0 px-2 text-white btn-action' title='Edit'><i class='fa-solid fa-pen'></i></a>
                                        <a href='check_tables.php?table=$selectedTable&action=delete_row&id={$r['id']}' class='btn btn-danger btn-sm py-0 px-2 btn-action' title='Delete' onclick=\"return confirm('Delete this record permanently?');\"><i class='fa-solid fa-trash'></i></a>
                                      </td>";
                                foreach ($columns as $c) {
                                    $val = $r[$c['Field']] ?? '';
                                    echo "<td class='font-mono'>" . htmlspecialchars(substr($val, 0, 50)) . (strlen($val) > 50 ? '...' : '') . "</td>";
                                }
                                echo "</tr>";
                            }
                        } else {
                            echo "<tr><td colspan='" . (count($columns) + 1) . "' class='text-center text-muted p-4'>No records found in this table.</td></tr>";
                        }
                        ?>
                    </tbody>
                </table>
            </div>
        </div>

    <!-- CREATE NEW TABLE FORM -->
    <?php elseif ($action === 'create_table'): ?>
        <div class="content-card col-lg-10 mx-auto p-4 mb-4">
            <div class="d-flex justify-content-between align-items-center mb-4 pb-3 border-bottom">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-circle-plus me-2 text-success"></i>Create New Database Table</h5>
                <a href="check_tables.php" class="btn btn-outline-secondary btn-sm rounded-pill px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back to Tables</a>
            </div>
            <form method="POST">
                <div class="mb-4">
                    <label class="form-label fw-bold text-dark">Table Name <span class="text-danger">*</span></label>
                    <input type="text" name="new_table_name" class="form-control font-mono shadow-none" placeholder="e.g. inventory_batches" required>
                </div>
                
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <h6 class="fw-bold mb-0 text-dark">Table Columns Definition</h6>
                    <button type="button" class="btn btn-sm btn-outline-primary rounded-pill px-3" onclick="addColumnRow()"><i class="fa-solid fa-plus me-1"></i> Add Column</button>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered align-middle" id="colTable">
                        <thead class="table-light">
                            <tr>
                                <th>Column Name</th>
                                <th width="160">Data Type</th>
                                <th width="110">Length/Values</th>
                                <th width="90" class="text-center">Allow Null</th>
                                <th width="160">Key / Index</th>
                                <th width="60" class="text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody id="columnRowsContainer">
                            <tr>
                                <td><input type="text" name="col_name[]" class="form-control form-control-sm font-mono" value="id" required></td>
                                <td><select name="col_type[]" class="form-select form-select-sm"><option value="INT">INT</option><option value="VARCHAR">VARCHAR</option><option value="TEXT">TEXT</option></select></td>
                                <td><input type="text" name="col_length[]" class="form-control form-control-sm font-mono text-center" value="11"></td>
                                <td class="text-center"><input type="checkbox" name="col_null[0]" value="1" class="form-check-input"></td>
                                <td><select name="col_key[]" class="form-select form-select-sm"><option value="PRIMARY">PRIMARY (AI)</option><option value="UNIQUE">UNIQUE</option><option value="NONE">None</option></select></td>
                                <td class="text-center text-muted"><i class="fa-solid fa-lock"></i></td>
                            </tr>
                            <tr>
                                <td><input type="text" name="col_name[]" class="form-control form-control-sm font-mono" value="created_at" required></td>
                                <td><select name="col_type[]" class="form-select form-select-sm"><option value="TIMESTAMP">TIMESTAMP</option><option value="DATETIME">DATETIME</option></select></td>
                                <td><input type="text" name="col_length[]" class="form-control form-control-sm font-mono text-center" value=""></td>
                                <td class="text-center"><input type="checkbox" name="col_null[1]" value="1" class="form-check-input" checked></td>
                                <td><select name="col_key[]" class="form-select form-select-sm"><option value="NONE">None</option></select></td>
                                <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash"></i></button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>

                <div class="text-end mt-4">
                    <a href="check_tables.php" class="btn btn-outline-secondary rounded-pill px-4 me-2">Cancel</a>
                    <button type="submit" name="create_new_table" class="btn btn-success rounded-pill px-5 fw-bold shadow-sm"><i class="fa-solid fa-hammer me-1"></i> Build Table Now</button>
                </div>
            </form>
        </div>

        <script>
        let colIndex = 2;
        function addColumnRow() {
            const tbody = document.getElementById('columnRowsContainer');
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><input type="text" name="col_name[]" class="form-control form-control-sm font-mono" placeholder="column_name" required></td>
                <td>
                    <select name="col_type[]" class="form-select form-select-sm">
                        <option value="VARCHAR">VARCHAR</option>
                        <option value="INT">INT</option>
                        <option value="TEXT">TEXT</option>
                        <option value="DATE">DATE</option>
                        <option value="DECIMAL">DECIMAL</option>
                    </select>
                </td>
                <td><input type="text" name="col_length[]" class="form-control form-control-sm font-mono text-center" placeholder="255"></td>
                <td class="text-center"><input type="checkbox" name="col_null[${colIndex}]" value="1" class="form-check-input" checked></td>
                <td>
                    <select name="col_key[]" class="form-select form-select-sm">
                        <option value="NONE">None</option>
                        <option value="UNIQUE">UNIQUE</option>
                    </select>
                </td>
                <td class="text-center"><button type="button" class="btn btn-outline-danger btn-sm py-0 px-2" onclick="this.closest('tr').remove()"><i class="fa-solid fa-trash"></i></button></td>
            `;
            tbody.appendChild(tr);
            colIndex++;
        }
        </script>

    <!-- TABLES DIRECTORY LIST -->
    <?php else: ?>
        <?php
        $totalRowsSum = 0;
        foreach($validTables as $tbl) {
            $rCntQ = mysqli_query($conn, "SELECT COUNT(*) as cnt FROM `$tbl`");
            if($rCntQ && $rRow = mysqli_fetch_assoc($rCntQ)) {
                $totalRowsSum += $rRow['cnt'];
            }
        }
        ?>
        <div class="row g-3 mb-4">
            <div class="col-md-4">
                <div class="content-card p-3 d-flex align-items-center">
                    <div class="bg-primary-subtle text-primary p-3 rounded-4 me-3 fs-4"><i class="fa-solid fa-database"></i></div>
                    <div>
                        <span class="text-muted small d-block">ACTIVE TABLES</span>
                        <h4 class="fw-bold mb-0"><?= count($validTables); ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="content-card p-3 d-flex align-items-center">
                    <div class="bg-success-subtle text-success p-3 rounded-4 me-3 fs-4"><i class="fa-solid fa-table-cells"></i></div>
                    <div>
                        <span class="text-muted small d-block">TOTAL RECORDS</span>
                        <h4 class="fw-bold mb-0"><?= $totalRowsSum; ?></h4>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="content-card p-3 d-flex align-items-center">
                    <div class="bg-warning-subtle text-warning p-3 rounded-4 me-3 fs-4"><i class="fa-solid fa-server"></i></div>
                    <div>
                        <span class="text-muted small d-block">ENGINE STATUS</span>
                        <h4 class="fw-bold mb-0 text-success fs-5 mt-1"><i class="fa-solid fa-circle-check me-1"></i> Connected & Active</h4>
                    </div>
                </div>
            </div>
        </div>

        <div class="content-card p-4">
            <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-3">
                <h5 class="fw-bold mb-0"><i class="fa-solid fa-folder-open me-2 text-secondary"></i>Database Tables Directory <span class="badge bg-secondary fs-6 ms-2"><?= count($validTables); ?></span></h5>
                <div class="w-25 min-w-200">
                    <input type="text" id="tableSearchInput" class="form-control form-control-sm rounded-pill px-3 shadow-none" placeholder="Search table schema..." onkeyup="filterTables()">
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover align-middle" id="tablesDirectoryTable">
                    <thead>
                        <tr class="table-light">
                            <th>#</th>
                            <th>Table Name</th>
                            <th>Total Rows</th>
                            <th>Primary Key</th>
                            <th>Columns</th>
                            <th class="text-end">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $i=1; 
                        foreach($validTables as $tbl): 
                            $rowCnt = 0;
                            $cntQ = mysqli_query($conn, "SELECT COUNT(*) as c FROM `$tbl`");
                            if($cntQ && $cr = mysqli_fetch_assoc($cntQ)) $rowCnt = $cr['c'];

                            $pk = 'id';
                            $pkQ = mysqli_query($conn, "SHOW KEYS FROM `$tbl` WHERE Key_name = 'PRIMARY'");
                            if($pkQ && $pkr = mysqli_fetch_assoc($pkQ)) $pk = $pkr['Column_name'];

                            $colCount = 0;
                            $colQ = mysqli_query($conn, "SHOW COLUMNS FROM `$tbl`");
                            if($colQ) $colCount = mysqli_num_rows($colQ);
                        ?>
                        <tr>
                            <td><?= sprintf("%02d", $i++); ?></td>
                            <td class="fw-bold font-mono text-primary"><i class="fa-solid fa-table me-2 text-secondary"></i><?= htmlspecialchars($tbl); ?></td>
                            <td><span class="badge bg-light text-dark border px-2 py-1"><i class="fa-solid fa-rows me-1"></i> <?= $rowCnt; ?> Rows</span></td>
                            <td><span class="font-mono small text-muted"><i class="fa-solid fa-key text-warning me-1"></i> <?= $pk; ?></span></td>
                            <td><span class="text-secondary small"><?= $colCount; ?> Columns</span></td>
                            <td class="text-end">
                                <a href="check_tables.php?table=<?= urlencode($tbl); ?>&action=view_table" class="btn btn-outline-primary btn-sm rounded-pill px-3 me-1 btn-action">
                                    <i class="fa-solid fa-pen-to-square me-1"></i> Inspect & Edit
                                </a>
                                <a href="check_tables.php?table=<?= urlencode($tbl); ?>&action=drop_table" class="btn btn-outline-danger btn-sm rounded-pill px-3 btn-action" onclick="return confirm('Drop table `<?= $tbl; ?>` permanently?');">
                                    <i class="fa-solid fa-trash me-1"></i> Drop
                                </a>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <script>
        function filterTables() {
            let input = document.getElementById('tableSearchInput').value.toLowerCase();
            let table = document.getElementById('tablesDirectoryTable');
            let tr = table.getElementsByTagName('tr');
            for (let i = 1; i < tr.length; i++) {
                let td = tr[i].getElementsByTagName('td')[1];
                if (td) {
                    let txtValue = td.textContent || td.innerText;
                    tr[i].style.display = txtValue.toLowerCase().indexOf(input) > -1 ? "" : "none";
                }
            }
        }
        </script>
    <?php endif; ?>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>