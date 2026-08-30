<?php
if (session_status() === PHP_SESSION_NONE) session_start();

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}
require_once $projectRoot . "/config/database.php";

$id = intval($_GET['id'] ?? 0);
$query = mysqli_query($conn, "SELECT * FROM inbound_shipments WHERE id = '$id' LIMIT 1");
if (!$query || mysqli_num_rows($query) === 0) {
    header("Location: index.php");
    exit();
}
$shipment = mysqli_fetch_assoc($query);
$totalRecv = (int)$shipment['received_qty'];

/* ==========================================================================
   1. QC SUBMISSION & DEFECT TRACKING
   ========================================================================== */

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_qc'])) {
    $accepted_qty    = max(0, intval($_POST['accepted_qty'] ?? 0));
    $rejected_qty    = max(0, intval($_POST['rejected_qty'] ?? 0));
    $defect_reason   = mysqli_real_escape_string($conn, trim($_POST['defect_reason'] ?? ''));
    $inspector_notes = mysqli_real_escape_string($conn, trim($_POST['inspector_notes'] ?? 'Standard Inbound Verification Passed'));
    $goto_putaway    = intval($_POST['goto_putaway'] ?? 0);

    if (($accepted_qty + $rejected_qty) !== $totalRecv) {
        $_SESSION['error'] = "Total verified units (" . ($accepted_qty + $rejected_qty) . ") must exactly equal received units ($totalRecv).";
    } else {
        $qc_status = ($rejected_qty === 0) ? 'Passed' : (($accepted_qty > 0) ? 'Passed' : 'Failed');

        // Verify and fetch existing table columns
        $existingCols = [];
        $colRes = @mysqli_query($conn, "SHOW COLUMNS FROM inbound_shipments");
        if ($colRes) {
            while ($c = mysqli_fetch_assoc($colRes)) { 
                $existingCols[] = strtolower($c['Field']); 
            }
        }

        // Auto add columns if missing in database
        if (!in_array('accepted_qty', $existingCols)) {
            @mysqli_query($conn, "ALTER TABLE inbound_shipments ADD COLUMN `accepted_qty` INT DEFAULT 0");
        }
        if (!in_array('rejected_qty', $existingCols)) {
            @mysqli_query($conn, "ALTER TABLE inbound_shipments ADD COLUMN `rejected_qty` INT DEFAULT 0");
        }
        if (!in_array('defect_reason', $existingCols)) {
            @mysqli_query($conn, "ALTER TABLE inbound_shipments ADD COLUMN `defect_reason` VARCHAR(255) NULL");
        }
        if (!in_array('inspector_notes', $existingCols)) {
            @mysqli_query($conn, "ALTER TABLE inbound_shipments ADD COLUMN `inspector_notes` TEXT NULL");
        }

        $updateSql = "
            UPDATE inbound_shipments 
            SET 
                `accepted_qty` = '$accepted_qty',
                `rejected_qty` = '$rejected_qty',
                `qc_status` = '$qc_status',
                `defect_reason` = '$defect_reason',
                `inspector_notes` = '$inspector_notes'
            WHERE id = '$id'
        ";

        if (mysqli_query($conn, $updateSql)) {
            $_SESSION['success'] = "Quality inspection completed! <strong>{$accepted_qty} Accepted</strong>, <strong>{$rejected_qty} Rejected</strong>.";
            
            if ($accepted_qty > 0 && $goto_putaway === 1) {
                header("Location: putaway.php?id={$id}");
            } else {
                header("Location: index.php");
            }
            exit();
        } else {
            $_SESSION['error'] = "Failed to record QC: " . mysqli_error($conn);
        }
    }
}

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Action Bar -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-clipboard-check text-warning me-2"></i>Quality Assurance & Inspection Protocol
            </h2>
            <p class="text-muted mb-0">Inspect received cargo batches, identify damaged units & certify putaway readiness</p>
        </div>
        <a href="index.php" class="btn btn-secondary rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Inbound
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 mb-4 shadow-sm" role="alert">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4 justify-content-center">

        <div class="col-xl-9 col-lg-10">
            <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
                
                <!-- Card Header -->
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0 d-flex justify-content-between align-items-center flex-wrap gap-2">
                    <div>
                        <h5 class="fw-bold mb-0 text-dark">
                            <i class="fa-solid fa-boxes-packing text-primary me-2"></i>GRN Receipt: <span class="font-monospace text-primary"><?= htmlspecialchars($shipment['grn_no']); ?></span>
                        </h5>
                        <small class="text-muted">PO Ref: <?= !empty($shipment['po_number']) ? htmlspecialchars($shipment['po_number']) : 'Direct Inward Delivery'; ?></small>
                    </div>
                    <span class="badge bg-warning-subtle text-warning border border-warning-subtle px-3 py-2 rounded-pill font-monospace fw-bold">
                        <i class="fa-solid fa-microscope me-1"></i> QC IN-PROGRESS
                    </span>
                </div>

                <div class="card-body p-4">

                    <!-- Shipment Snapshot Banner -->
                    <div class="p-3 bg-light rounded-4 border mb-4">
                        <div class="row g-3 align-items-center">
                            <div class="col-md-5">
                                <small class="text-muted text-uppercase fw-semibold d-block" style="font-size: 11px;">Inspecting Product</small>
                                <strong class="text-dark fs-6"><?= htmlspecialchars($shipment['product_name']); ?></strong>
                                <code class="text-primary font-monospace small d-block"><?= htmlspecialchars($shipment['product_code']); ?></code>
                            </div>
                            <div class="col-md-3">
                                <small class="text-muted text-uppercase fw-semibold d-block" style="font-size: 11px;">Supplier / Vendor</small>
                                <strong class="text-dark d-block"><?= htmlspecialchars($shipment['supplier_name']); ?></strong>
                            </div>
                            <div class="col-md-2">
                                <small class="text-muted text-uppercase fw-semibold d-block" style="font-size: 11px;">Batch #</small>
                                <span class="badge bg-dark font-monospace text-light"><?= htmlspecialchars($shipment['batch_no'] ?? 'N/A'); ?></span>
                            </div>
                            <div class="col-md-2 text-md-end">
                                <small class="text-muted text-uppercase fw-semibold d-block" style="font-size: 11px;">Total Units</small>
                                <div class="fs-4 fw-bold font-monospace text-primary" id="totalQtyBadge"><?= number_format($totalRecv); ?></div>
                            </div>
                        </div>
                    </div>

                    <form method="POST" id="qcInspectionForm">

                        <!-- 1. Physical Verification Checklist -->
                        <div class="mb-4">
                            <label class="form-label fw-bold text-dark mb-2">
                                <i class="fa-solid fa-list-check text-primary me-1"></i> Inbound Verification Checklist:
                            </label>
                            <div class="row g-2">
                                <div class="col-md-4">
                                    <div class="form-check p-3 bg-light rounded-3 border">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" id="chk1" checked>
                                        <label class="form-check-label small fw-semibold" for="chk1">Packaging & Seals Intact</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check p-3 bg-light rounded-3 border">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" id="chk2" checked>
                                        <label class="form-check-label small fw-semibold" for="chk2">Barcode Readable / Scanned</label>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="form-check p-3 bg-light rounded-3 border">
                                        <input class="form-check-input ms-0 me-2" type="checkbox" id="chk3" checked>
                                        <label class="form-check-label small fw-semibold" for="chk3">Expiry Date Valid (>90 Days)</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- 2. Quantity Splitter Cards (Accepted vs Rejected) -->
                        <div class="row g-4 mb-4">
                            
                            <!-- Fit Units -->
                            <div class="col-md-6">
                                <div class="card border-2 border-success bg-success bg-opacity-10 rounded-4 p-3 shadow-none">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-bold text-success mb-0">
                                            <i class="fa-solid fa-circle-check me-1"></i> Accepted / Fit Units *
                                        </label>
                                        <span class="badge bg-success font-monospace" id="acceptedPercentBadge">100%</span>
                                    </div>
                                    <div class="input-group">
                                        <input type="number" name="accepted_qty" id="acceptedInput" class="form-control border-2 text-center fs-4 fw-bold font-monospace text-success" value="<?= $totalRecv; ?>" min="0" max="<?= $totalRecv; ?>" required oninput="calcReject()">
                                        <span class="input-group-text bg-white border-2 fw-semibold">Units</span>
                                    </div>
                                    <small class="text-success mt-1 d-block fw-semibold">Ready for immediate bin putaway</small>
                                </div>
                            </div>

                            <!-- Damaged Units -->
                            <div class="col-md-6">
                                <div class="card border-2 border-danger bg-danger bg-opacity-10 rounded-4 p-3 shadow-none">
                                    <div class="d-flex justify-content-between align-items-center mb-2">
                                        <label class="form-label fw-bold text-danger mb-0">
                                            <i class="fa-solid fa-circle-xmark me-1"></i> Damaged / Rejected Units *
                                        </label>
                                        <span class="badge bg-danger font-monospace" id="rejectedPercentBadge">0%</span>
                                    </div>
                                    <div class="input-group">
                                        <input type="number" name="rejected_qty" id="rejectedInput" class="form-control border-2 text-center fs-4 fw-bold font-monospace text-danger" value="0" min="0" max="<?= $totalRecv; ?>" required oninput="calcAccept()">
                                        <span class="input-group-text bg-white border-2 fw-semibold">Units</span>
                                    </div>
                                    <small class="text-danger mt-1 d-block fw-semibold">To be quarantined / vendor credit note</small>
                                </div>
                            </div>

                        </div>

                        <!-- 3. Defect Reason & Remarks (Conditional) -->
                        <div class="row g-3 mb-4" id="defectSection" style="display: none;">
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-danger">Primary Defect Category *</label>
                                <select name="defect_reason" class="form-select border-2 border-danger fw-semibold">
                                    <option value="Damaged in Transit / Crushed Box">Damaged in Transit / Crushed Box</option>
                                    <option value="Seal Broken / Tampered">Seal Broken / Tampered</option>
                                    <option value="Near Expiry / Expired Batch">Near Expiry / Expired Batch</option>
                                    <option value="Wrong Item / SKU Tag Mismatch">Wrong Item / SKU Tag Mismatch</option>
                                    <option value="Leakage / Moisture Damage">Leakage / Moisture Damage</option>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label small fw-bold text-danger">Defect Description / Notes</label>
                                <input type="text" name="inspector_notes" class="form-control border-2" placeholder="e.g. 5 boxes crushed in pallet base layer">
                            </div>
                        </div>

                        <!-- Action Buttons -->
                        <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-4 flex-wrap gap-2">
                            <a href="index.php" class="btn btn-outline-secondary px-4 rounded-pill">Cancel</a>
                            <div class="d-flex gap-2">
                                <button type="submit" name="submit_qc" class="btn btn-outline-primary px-4 fw-bold rounded-pill">
                                    <i class="fa-solid fa-floppy-disk me-1"></i> Save QC Report
                                </button>
                                <button type="submit" name="submit_qc" value="putaway" class="btn btn-success px-4 fw-bold rounded-pill shadow-sm" onclick="setPutawayFlag()">
                                    <i class="fa-solid fa-dolly me-1"></i> Approve & Direct to Putaway &rarr;
                                </button>
                                <input type="hidden" name="goto_putaway" id="gotoPutawayFlag" value="0">
                            </div>
                        </div>

                    </form>

                </div>
            </div>
        </div>

    </div>

</div>

<script>
const maxUnits = <?= $totalRecv; ?>;

function calcReject() {
    let accept = parseInt(document.getElementById('acceptedInput').value) || 0;
    if (accept > maxUnits) accept = maxUnits;
    if (accept < 0) accept = 0;
    
    document.getElementById('acceptedInput').value = accept;
    const reject = maxUnits - accept;
    document.getElementById('rejectedInput').value = reject;
    updateBadges(accept, reject);
}

function calcAccept() {
    let reject = parseInt(document.getElementById('rejectedInput').value) || 0;
    if (reject > maxUnits) reject = maxUnits;
    if (reject < 0) reject = 0;

    document.getElementById('rejectedInput').value = reject;
    const accept = maxUnits - reject;
    document.getElementById('acceptedInput').value = accept;
    updateBadges(accept, reject);
}

function updateBadges(accept, reject) {
    const accPct = maxUnits > 0 ? Math.round((accept / maxUnits) * 100) : 0;
    const rejPct = maxUnits > 0 ? Math.round((reject / maxUnits) * 100) : 0;

    document.getElementById('acceptedPercentBadge').innerText = accPct + '%';
    document.getElementById('rejectedPercentBadge').innerText = rejPct + '%';

    const defectSec = document.getElementById('defectSection');
    if (reject > 0) {
        defectSec.style.display = 'flex';
    } else {
        defectSec.style.display = 'none';
    }
}

function setPutawayFlag() {
    document.getElementById('gotoPutawayFlag').value = "1";
}
</script>

<?php include $projectRoot . "/includes/footer.php"; ?>