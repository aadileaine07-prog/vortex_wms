<?php
session_start();

$projectRoot = dirname(__DIR__, 2);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$successMsg = "";
$errorMsg   = "";

if (isset($_POST['import'])) {
    $module   = $_POST['module'] ?? '';
    $filename = $_FILES['import_file']['tmp_name'] ?? '';

    if (!empty($filename) && $_FILES['import_file']['size'] > 0) {
        $file = fopen($filename, "r");

        // 🔍 Auto-detect Delimiter (Comma, Tab \t, or Pipe |)
        $firstLine = fgets($file);
        rewind($file);

        $delimiter = ",";
        if (strpos($firstLine, "\t") !== false) {
            $delimiter = "\t"; // Excel Tab-Separated Text
        } elseif (strpos($firstLine, "|") !== false) {
            $delimiter = "|"; // Pipe Separated Text
        }

        fgetcsv($file, 10000, $delimiter); // Skip Header Row

        $successCount = 0;
        $errorCount   = 0;

        while (($row = fgetcsv($file, 10000, $delimiter)) !== FALSE) {

            // ================= INBOUND MODULES =================
            if ($module === 'asn') {
                $asn_no = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $supplier = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $date = mysqli_real_escape_string($conn, trim($row[2] ?? date('Y-m-d')));
                $item = mysqli_real_escape_string($conn, trim($row[3] ?? ''));
                $qty = intval($row[4] ?? 0);
                if (!empty($asn_no)) {
                    $sql = "INSERT INTO asn_headers (asn_number, supplier_name, expected_date, item_code, expected_qty, status) VALUES ('$asn_no', '$supplier', '$date', '$item', '$qty', 'Pending') ON DUPLICATE KEY UPDATE expected_qty='$qty'";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'grn') {
                $grn_no = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $asn_no = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $date = mysqli_real_escape_string($conn, trim($row[2] ?? date('Y-m-d')));
                $by = mysqli_real_escape_string($conn, trim($row[3] ?? $_SESSION['full_name']));
                if (!empty($grn_no)) {
                    $sql = "INSERT INTO grn_headers (grn_number, asn_number, received_date, received_by, status) VALUES ('$grn_no', '$asn_no', '$date', '$by', 'Completed') ON DUPLICATE KEY UPDATE status='Completed'";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'qc') {
                $grn_no = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $sku = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $passed = intval($row[2] ?? 0);
                $failed = intval($row[3] ?? 0);
                if (!empty($grn_no)) {
                    $sql = "INSERT INTO quality_checks (grn_number, sku, passed_qty, failed_qty, status) VALUES ('$grn_no', '$sku', '$passed', '$failed', 'Inspected')";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'putaway') {
                $sku = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $bin = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $qty = intval($row[2] ?? 0);
                if (!empty($sku)) {
                    $sql = "INSERT INTO putaway_logs (sku, bin_location, quantity, status) VALUES ('$sku', '$bin', '$qty', 'Done')";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            }

            // ================= INVENTORY MODULES =================
            elseif ($module === 'inventory' || $module === 'stock') {
                $sku = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $item_name = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $qty = intval($row[2] ?? 0);
                $location = mysqli_real_escape_string($conn, trim($row[3] ?? 'BIN-01'));
                if (!empty($sku)) {
                    $sql = "INSERT INTO inventory (sku, item_name, quantity, location) VALUES ('$sku', '$item_name', '$qty', '$location') ON DUPLICATE KEY UPDATE quantity=quantity+$qty";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'transfer') {
                $sku = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $from_bin = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $to_bin = mysqli_real_escape_string($conn, trim($row[2] ?? ''));
                $qty = intval($row[3] ?? 0);
                if (!empty($sku)) {
                    $sql = "INSERT INTO stock_transfers (sku, from_location, to_location, quantity, status) VALUES ('$sku', '$from_bin', '$to_bin', '$qty', 'Completed')";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'adjustment') {
                $sku = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $type = mysqli_real_escape_string($conn, trim($row[1] ?? 'ADD'));
                $qty = intval($row[2] ?? 0);
                $reason = mysqli_real_escape_string($conn, trim($row[3] ?? 'Audit Correction'));
                if (!empty($sku)) {
                    $sql = "INSERT INTO stock_adjustments (sku, adjustment_type, quantity, reason) VALUES ('$sku', '$type', '$qty', '$reason')";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            }

            // ================= OUTBOUND MODULES =================
            elseif ($module === 'sales_order') {
                $order_no = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $customer = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $sku = mysqli_real_escape_string($conn, trim($row[2] ?? ''));
                $qty = intval($row[3] ?? 0);
                if (!empty($order_no)) {
                    $sql = "INSERT INTO sales_orders (order_number, customer_name, sku, quantity, status) VALUES ('$order_no', '$customer', '$sku', '$qty', 'Pending') ON DUPLICATE KEY UPDATE quantity='$qty'";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'picking') {
                $order_no = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $picker = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $status = mysqli_real_escape_string($conn, trim($row[2] ?? 'Picked'));
                if (!empty($order_no)) {
                    $sql = "INSERT INTO picking_lists (order_number, picker_name, status) VALUES ('$order_no', '$picker', '$status')";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'packing') {
                $order_no = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $box_no = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $weight = mysqli_real_escape_string($conn, trim($row[2] ?? '1.0'));
                if (!empty($order_no)) {
                    $sql = "INSERT INTO packing_details (order_number, box_number, weight_kg) VALUES ('$order_no', '$box_no', '$weight')";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'dispatch') {
                $order_no = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $courier = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $awb = mysqli_real_escape_string($conn, trim($row[2] ?? ''));
                if (!empty($order_no)) {
                    $sql = "INSERT INTO dispatch_manifest (order_number, courier_partner, awb_number, status) VALUES ('$order_no', '$courier', '$awb', 'Dispatched')";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            }

            // ================= MASTER DATA MODULES =================
            elseif ($module === 'products') {
                $sku = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $name = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $cat = mysqli_real_escape_string($conn, trim($row[2] ?? 'General'));
                $uom = mysqli_real_escape_string($conn, trim($row[3] ?? 'PCS'));
                if (!empty($sku)) {
                    $sql = "INSERT INTO products (sku, item_name, category, uom, status) VALUES ('$sku', '$name', '$cat', '$uom', 'Active') ON DUPLICATE KEY UPDATE item_name='$name'";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'suppliers') {
                $code = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $name = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $contact = mysqli_real_escape_string($conn, trim($row[2] ?? ''));
                $email = mysqli_real_escape_string($conn, trim($row[3] ?? ''));
                if (!empty($code)) {
                    $sql = "INSERT INTO suppliers (supplier_code, supplier_name, contact_person, email, status) VALUES ('$code', '$name', '$contact', '$email', 'Active') ON DUPLICATE KEY UPDATE supplier_name='$name'";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'warehouses') {
                $code = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $name = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $city = mysqli_real_escape_string($conn, trim($row[2] ?? ''));
                $address = mysqli_real_escape_string($conn, trim($row[3] ?? ''));
                if (!empty($code)) {
                    $sql = "INSERT INTO warehouses (warehouse_code, warehouse_name, city, address, status) VALUES ('$code', '$name', '$city', '$address', 'Active') ON DUPLICATE KEY UPDATE warehouse_name='$name'";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'locations') {
                $wh = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $bin = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $zone = mysqli_real_escape_string($conn, trim($row[2] ?? 'Zone-A'));
                $type = mysqli_real_escape_string($conn, trim($row[3] ?? 'Rack'));
                if (!empty($bin)) {
                    $sql = "INSERT INTO locations (warehouse_code, bin_code, zone, location_type) VALUES ('$wh', '$bin', '$zone', '$type') ON DUPLICATE KEY UPDATE zone='$zone'";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            }

            // ================= HUMAN RESOURCE MODULES =================
            elseif ($module === 'employees') {
                $emp_id = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $name = mysqli_real_escape_string($conn, trim($row[1] ?? ''));
                $email = mysqli_real_escape_string($conn, trim($row[2] ?? ''));
                $mobile = mysqli_real_escape_string($conn, trim($row[3] ?? ''));
                $dept = mysqli_real_escape_string($conn, trim($row[4] ?? ''));
                $role = mysqli_real_escape_string($conn, trim($row[5] ?? ''));
                $wh = mysqli_real_escape_string($conn, trim($row[6] ?? ''));
                $user = mysqli_real_escape_string($conn, trim($row[7] ?? ''));
                $pass = password_hash(trim($row[8] ?? '123456'), PASSWORD_BCRYPT);
                if (!empty($emp_id)) {
                    $sql = "INSERT INTO employees (employee_id, full_name, email, mobile, department, role, warehouse, username, password, status) VALUES ('$emp_id', '$name', '$email', '$mobile', '$dept', '$role', '$wh', '$user', '$pass', 'Active') ON DUPLICATE KEY UPDATE full_name='$name'";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'attendance') {
                $emp_id = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $date = mysqli_real_escape_string($conn, trim($row[1] ?? date('Y-m-d')));
                $check_in = mysqli_real_escape_string($conn, trim($row[2] ?? '09:00:00'));
                $check_out = mysqli_real_escape_string($conn, trim($row[3] ?? '18:00:00'));
                $status = mysqli_real_escape_string($conn, trim($row[4] ?? 'Present'));
                if (!empty($emp_id)) {
                    $sql = "INSERT INTO attendance (employee_id, attendance_date, check_in, check_out, status) VALUES ('$emp_id', '$date', '$check_in', '$check_out', '$status') ON DUPLICATE KEY UPDATE status='$status'";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            } 
            elseif ($module === 'leaves') {
                $emp_id = mysqli_real_escape_string($conn, trim($row[0] ?? ''));
                $type = mysqli_real_escape_string($conn, trim($row[1] ?? 'Casual'));
                $from_date = mysqli_real_escape_string($conn, trim($row[2] ?? ''));
                $to_date = mysqli_real_escape_string($conn, trim($row[3] ?? ''));
                $reason = mysqli_real_escape_string($conn, trim($row[4] ?? ''));
                if (!empty($emp_id)) {
                    $sql = "INSERT INTO leave_requests (employee_id, leave_type, from_date, to_date, reason, status) VALUES ('$emp_id', '$type', '$from_date', '$to_date', '$reason', 'Approved')";
                    mysqli_query($conn, $sql) ? $successCount++ : $errorCount++;
                }
            }

        }
        fclose($file);
        $successMsg = "Import Complete! Successfully Imported: <b>$successCount</b> rows. (Errors/Skipped: $errorCount)";
    } else {
        $errorMsg = "Please upload a valid .txt, .csv, or .tsv file.";
    }
}

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<style>
@import url('https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&display=swap');

body { font-family: 'Plus Jakarta Sans', sans-serif !important; }

.import-hero {
    background: linear-gradient(135deg, #0d6efd 0%, #0a58ca 50%, #032830 100%);
    border-radius: 20px;
    padding: 35px 30px;
    color: #ffffff;
    box-shadow: 0 15px 30px rgba(13, 110, 253, 0.25);
    margin-bottom: 30px;
}

.glass-card {
    background: #ffffff;
    border: 1px solid rgba(226, 232, 240, 0.8);
    border-radius: 20px;
    box-shadow: 0 10px 30px rgba(0, 0, 0, 0.04);
}

.file-drop-zone {
    border: 2px dashed #cbd5e1;
    border-radius: 16px;
    padding: 35px 20px;
    text-align: center;
    background: #f8fafc;
    position: relative;
}

.file-drop-zone input[type="file"] {
    position: absolute;
    width: 100%;
    height: 100%;
    top: 0; left: 0;
    opacity: 0;
    cursor: pointer;
}

.sequence-box {
    background: #0f172a;
    color: #38bdf8;
    border-radius: 14px;
    padding: 18px;
    font-family: monospace;
    font-size: 0.88rem;
    border-left: 4px solid #38bdf8;
}

.btn-gradient {
    background: linear-gradient(135deg, #0d6efd 0%, #0b5ed7 100%);
    color: #ffffff;
    border: none;
    border-radius: 12px;
    padding: 16px;
    font-weight: 700;
}
</style>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Hero Header -->
        <div class="import-hero d-flex justify-content-between align-items-center flex-wrap gap-3">
            <div>
                <span class="badge bg-light text-primary rounded-pill mb-2 px-3 py-1 fw-bold"><i class="fa-solid fa-bolt me-1"></i> Text & Excel Importer Engine</span>
                <h1 class="fw-extrabold display-6 mb-1">Universal Bulk Importer</h1>
                <p class="mb-0 text-white-50 fs-6">Upload .txt or .csv files generated from Excel or TextEdit</p>
            </div>
            <div class="d-none d-md-block fs-1 opacity-50 me-3">
                <i class="fa-solid fa-file-lines"></i>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if (!empty($successMsg)): ?>
            <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm p-3 mb-4" role="alert">
                <i class="fa-solid fa-circle-check fs-4 me-2 text-success"></i><?= $successMsg; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <?php if (!empty($errorMsg)): ?>
            <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm p-3 mb-4" role="alert">
                <i class="fa-solid fa-triangle-exclamation fs-4 me-2 text-danger"></i><?= htmlspecialchars($errorMsg); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Form Card -->
        <div class="card glass-card col-lg-8 mx-auto border-0">
            <div class="card-body p-4 p-md-5">

                <form method="POST" enctype="multipart/form-data">
                    
                    <!-- Module Selection -->
                    <div class="mb-4">
                        <label class="form-label fw-bold fs-6 text-dark mb-2">
                            <i class="fa-solid fa-layer-group text-primary me-2"></i>1. Select Target Module
                        </label>
                        <select name="module" id="moduleSelect" class="form-select form-select-lg" onchange="updateTemplateGuide()" required>
                            <option value="">-- Choose WMS Module to Import --</option>
                            <optgroup label="📥 INBOUND OPERATIONS">
                                <option value="asn">📄 ASN (Advance Shipping Notice)</option>
                                <option value="grn">📋 GRN (Goods Receipt Note)</option>
                                <option value="qc">🔍 Quality Check</option>
                                <option value="putaway">🚛 Putaway Logs</option>
                            </optgroup>
                            <optgroup label="📦 INVENTORY & STOCK">
                                <option value="inventory">📦 Stock / Inventory</option>
                                <option value="transfer">🔄 Stock Transfer</option>
                                <option value="adjustment">⚙️ Stock Adjustment</option>
                            </optgroup>
                            <optgroup label="📤 OUTBOUND OPERATIONS">
                                <option value="sales_order">🛒 Sales Orders</option>
                                <option value="picking">🖐️ Picking Lists</option>
                                <option value="packing">📦 Packing Details</option>
                                <option value="dispatch">🚚 Dispatch Manifest</option>
                            </optgroup>
                            <optgroup label="🏢 MASTER DATA">
                                <option value="products">📦 Product Master (SKU)</option>
                                <option value="suppliers">🏬 Supplier Master</option>
                                <option value="warehouses">🏭 Warehouse Master</option>
                                <option value="locations">📍 Bin Location Master</option>
                            </optgroup>
                            <optgroup label="👥 HUMAN RESOURCE">
                                <option value="employees">👥 Employee Master</option>
                                <option value="attendance">📅 Attendance Log</option>
                                <option value="leaves">📝 Leave Management</option>
                            </optgroup>
                        </select>
                    </div>

                    <!-- File Drop Zone -->
                    <div class="mb-4">
                        <label class="form-label fw-bold fs-6 text-dark mb-2">
                            <i class="fa-solid fa-file-arrow-up text-primary me-2"></i>2. Select Text / Excel File (.txt, .csv, .tsv)
                        </label>
                        <div class="file-drop-zone">
                            <input type="file" name="import_file" id="fileInput" accept=".txt,.csv,.tsv" required onchange="updateFileName(this)">
                            <div class="fs-1 text-primary mb-2">
                                <i class="fa-solid fa-file-text"></i>
                            </div>
                            <h6 class="fw-bold text-dark mb-1" id="fileLabelTitle">Drag & Drop your .txt or .csv file here</h6>
                            <p class="text-muted small mb-0" id="fileLabelSub">Supports Text documents, Tab-Separated, and Comma-Separated files</p>
                        </div>
                    </div>

                    <!-- Dynamic Sequence Guide Box -->
                    <div id="formatGuide" class="d-none mb-4">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-bold fs-6 text-dark mb-0">
                                <i class="fa-solid fa-code text-primary me-2"></i>3. Required File Columns Sequence
                            </label>
                            <button type="button" onclick="downloadTXTTemplate()" class="btn btn-sm btn-outline-primary fw-semibold rounded-pill px-3">
                                <i class="fa-solid fa-download me-1"></i> Download Sample .TXT File
                            </button>
                        </div>
                        <div class="sequence-box">
                            <div class="text-white-50 small mb-1">// First row (headers) will be skipped automatically</div>
                            <span id="csvFormatCode"></span>
                        </div>
                    </div>

                    <button type="submit" name="import" class="btn btn-gradient btn-lg w-100 mt-2">
                        <i class="fa-solid fa-rocket me-2"></i> Start Bulk Import
                    </button>

                </form>

            </div>
        </div>

    </div>
</div>

<script>
const formats = {
    'asn': 'asn_number,supplier_name,expected_date,item_code,expected_qty',
    'grn': 'grn_number,asn_number,received_date,received_by',
    'qc': 'grn_number,sku,passed_qty,failed_qty',
    'putaway': 'sku,bin_location,quantity',
    'inventory': 'sku,item_name,quantity,bin_location',
    'transfer': 'sku,from_location,to_location,quantity',
    'adjustment': 'sku,adjustment_type,quantity,reason',
    'sales_order': 'order_number,customer_name,sku,quantity',
    'picking': 'order_number,picker_name,status',
    'packing': 'order_number,box_number,weight_kg',
    'dispatch': 'order_number,courier_partner,awb_number',
    'products': 'sku,item_name,category,uom',
    'suppliers': 'supplier_code,supplier_name,contact_person,email',
    'warehouses': 'warehouse_code,warehouse_name,city,address',
    'locations': 'warehouse_code,bin_code,zone,location_type',
    'employees': 'employee_id,full_name,email,mobile,department,role,warehouse,username,password',
    'attendance': 'employee_id,attendance_date,check_in,check_out,status',
    'leaves': 'employee_id,leave_type,from_date,to_date,reason'
};

function updateTemplateGuide() {
    const mod = document.getElementById('moduleSelect').value;
    const guide = document.getElementById('formatGuide');
    const code = document.getElementById('csvFormatCode');

    if (formats[mod]) {
        code.innerText = formats[mod];
        guide.classList.remove('d-none');
    } else {
        guide.classList.add('d-none');
    }
}

function updateFileName(input) {
    if (input.files && input.files[0]) {
        document.getElementById('fileLabelTitle').innerText = "Selected File: " + input.files[0].name;
        document.getElementById('fileLabelSub').innerText = (input.files[0].size / 1024).toFixed(2) + " KB";
    }
}

// 📥 Instant .TXT Sample File Generator
function downloadTXTTemplate() {
    const mod = document.getElementById('moduleSelect').value;
    if (!formats[mod]) return;

    const txtContent = "data:text/plain;charset=utf-8," + encodeURIComponent(formats[mod] + "\n");
    const link = document.createElement("a");
    link.setAttribute("href", txtContent);
    link.setAttribute("download", mod + "_template.txt");
    document.body.appendChild(link);
    
    link.click();
    document.body.removeChild(link);
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>