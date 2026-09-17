<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 1));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

$po_id = intval($_GET['po_id'] ?? 0);
$po_data = null;
$po_items = [];

if ($po_id > 0) {
    $poRes = mysqli_query($conn, "SELECT * FROM purchase_orders WHERE id = $po_id LIMIT 1");
    if ($poRes && mysqli_num_rows($poRes) > 0) {
        $po_data = mysqli_fetch_assoc($poRes);
        
        $itemRes = mysqli_query($conn, "SELECT * FROM purchase_order_items WHERE po_id = $po_id");
        if ($itemRes) {
            while ($it = mysqli_fetch_assoc($itemRes)) {
                $po_items[] = $it;
            }
        }
    }
}

// Handle Form Submission for GRN
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_grn'])) {
    $p_id         = intval($_POST['po_id'] ?? 0);
    $grn_no       = mysqli_real_escape_string($conn, trim($_POST['grn_number']));
    $dock_bay     = mysqli_real_escape_string($conn, trim($_POST['dock_bay']));
    $receipt_date = $_POST['receipt_date'] ?? date('Y-m-d');
    $employee_id  = $_SESSION['employee_id'];

    mysqli_begin_transaction($conn);

    try {
        // Create GRN Table if not exists
        mysqli_query($conn, "CREATE TABLE IF NOT EXISTS grn (
            id INT AUTO_INCREMENT PRIMARY KEY,
            po_id INT NOT NULL,
            grn_number VARCHAR(100) NOT NULL,
            dock_bay VARCHAR(50),
            receipt_date DATE,
            received_by VARCHAR(50),
            status VARCHAR(50) DEFAULT 'Completed',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");

        $sqlGrn = "INSERT INTO grn (po_id, grn_number, dock_bay, receipt_date, received_by, status) 
                   VALUES ('$p_id', '$grn_no', '$dock_bay', '$receipt_date', '$employee_id', 'Completed')";
        
        if (!mysqli_query($conn, $sqlGrn)) {
            throw new Exception("Failed to create GRN record: " . mysqli_error($conn));
        }

        // Update PO Status to Received
        if ($p_id > 0) {
            mysqli_query($conn, "UPDATE purchase_orders SET status = 'Received' WHERE id = '$p_id' LIMIT 1");
        }

        mysqli_commit($conn);
        $_SESSION['success'] = "Goods Receiving Note <strong>{$grn_no}</strong> logged successfully and stock inbound queued!";
        header("Location: index.php");
        exit();

    } catch (\Throwable $e) {
        mysqli_rollback($conn);
        $_SESSION['error'] = "GRN processing failed: " . $e->getMessage();
    }
}

$autoGrnNo = "GRN-" . date("Ymd") . "-" . rand(100, 999);

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-truck-ramp-box text-primary me-2"></i>Inward Goods Receiving (GRN)
            </h2>
            <p class="text-muted mb-0">Inspect dock delivery shipments against Purchase Order contracts</p>
        </div>
        <a href="../purchase_orders/index.php" class="btn btn-secondary fw-bold rounded-pill px-3">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Orders
        </a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
            <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <form method="POST">
        <input type="hidden" name="po_id" value="<?= $po_id; ?>">

        <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
            <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-clipboard-check text-primary me-2"></i>Dock Inspection Details</h5>
            </div>
            <div class="card-body p-4">
                <div class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">GRN Reference # *</label>
                        <input type="text" name="grn_number" class="form-control border-2 font-monospace fw-bold text-primary" value="<?= $autoGrnNo; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Linked Purchase Order</label>
                        <input type="text" class="form-control border-2 font-monospace bg-light" value="<?= htmlspecialchars($po_data['po_number'] ?? 'Direct Intake'); ?>" readonly>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Receiving Dock Bay *</label>
                        <input type="text" name="dock_bay" class="form-control border-2 fw-semibold" value="DOCK-BAY-01" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label small fw-bold text-muted">Receipt Date *</label>
                        <input type="date" name="receipt_date" class="form-control border-2 fw-semibold" value="<?= date('Y-m-d'); ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <?php if (!empty($po_items)): ?>
            <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
                <div class="card-header bg-white border-0 pt-4 px-4 pb-0">
                    <h5 class="fw-bold text-dark mb-0"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Expected Line Items Verification</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Product Name</th>
                                    <th>SKU Code</th>
                                    <th class="text-center">Ordered Qty</th>
                                    <th class="text-center">Received Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($po_items as $item): ?>
                                    <tr>
                                        <td><strong><?= htmlspecialchars($item['product_name']); ?></strong></td>
                                        <td><code class="font-monospace text-primary"><?= htmlspecialchars($item['product_code']); ?></code></td>
                                        <td class="text-center font-monospace fw-bold"><?= (int)$item['ordered_qty']; ?></td>
                                        <td class="text-center">
                                            <input type="number" name="received_qty[<?= $item['id']; ?>]" class="form-control form-control-sm w-50 mx-auto text-center font-monospace fw-bold" value="<?= (int)$item['ordered_qty']; ?>" min="0">
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>

        <div class="text-end mb-4">
            <button type="submit" name="save_grn" class="btn btn-primary px-5 fw-bold rounded-pill shadow-sm py-2">
                <i class="fa-solid fa-check-double me-1"></i> Complete GRN & Authorize Putaway
            </button>
        </div>
    </form>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>