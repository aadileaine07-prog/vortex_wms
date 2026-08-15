<?php
session_start();

$projectRoot = file_exists(__DIR__ . "/../../../config/database.php") ? dirname(__DIR__, 3) : dirname(__DIR__, 4);

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

// Fetch Pending Purchase Orders
$po_list = mysqli_query($conn, "
    SELECT po.id, po.po_number, s.supplier_name 
    FROM purchase_orders po 
    LEFT JOIN suppliers s ON po.supplier_id = s.id 
    WHERE po.status IN ('Pending', 'Approved') 
    ORDER BY po.id DESC
");

// Auto Generate ASN Number
$asn_number = "ASN-" . date("YmdHis");

include $projectRoot . "/includes/header.php";
include $projectRoot . "/includes/navbar.php";
include $projectRoot . "/includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-truck-ramp-box text-primary me-2"></i>Create Advanced Shipping Notice (ASN)</h2>
                <p class="text-muted mb-0">Select Purchase Order (PO) to auto-populate supplier & items data</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <form action="save.php" method="POST">
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-primary text-white p-3 rounded-top-4">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-link me-2"></i>Link Purchase Order</h5>
                </div>
                <div class="card-body p-4">
                    <div class="row g-3">

                        <!-- Select PO Dropdown -->
                        <div class="col-md-4">
                            <label class="form-label fw-bold">Select Purchase Order (PO) *</label>
                            <select name="po_id" id="po_select" class="form-select fw-bold" onchange="loadPODetails()" required>
                                <option value="">-- Choose Pending PO --</option>
                                <?php if ($po_list && mysqli_num_rows($po_list) > 0): ?>
                                    <?php while ($po = mysqli_fetch_assoc($po_list)): ?>
                                        <option value="<?= $po['id']; ?>">
                                            <?= htmlspecialchars($po['po_number']); ?> — <?= htmlspecialchars($po['supplier_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">ASN Number</label>
                            <input type="text" name="asn_number" class="form-control font-monospace bg-light" value="<?= $asn_number; ?>" readonly>
                        </div>

                        <div class="col-md-3">
                            <label class="form-label fw-semibold">Supplier Name</label>
                            <input type="text" id="supplier_name_display" class="form-control bg-light fw-bold" readonly placeholder="Auto Loaded from PO">
                            <input type="hidden" name="supplier_id" id="supplier_id">
                        </div>

                        <div class="col-md-2">
                            <label class="form-label fw-semibold">Expected Arrival</label>
                            <input type="date" name="expected_date" id="expected_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                    </div>
                </div>
            </div>

            <!-- Auto-Populated Items Table -->
            <div class="card shadow-sm border-0 rounded-4 mb-4">
                <div class="card-header bg-dark text-white p-3 rounded-top-4">
                    <h5 class="mb-0 fw-bold"><i class="fa-solid fa-boxes-stacked me-2"></i>Inbound Shipment Items (Auto-Fetched from PO)</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-bordered align-middle" id="asnItemsTable">
                            <thead class="table-light">
                                <tr>
                                    <th>Product Name</th>
                                    <th width="200">SKU Code</th>
                                    <th width="180">PO Ordered Qty</th>
                                    <th width="180">Expected Inbound Qty</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td colspan="4" class="text-center py-4 text-muted">
                                        👈 Select a Purchase Order (PO) above to load items automatically
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center border-top pt-4 mt-3">
                        <a href="index.php" class="btn btn-outline-secondary px-4"><i class="fa-solid fa-arrow-left me-1"></i> Cancel</a>
                        <button type="submit" name="save_asn" class="btn btn-success px-4 fw-bold">
                            <i class="fa-solid fa-floppy-disk me-1"></i> Generate ASN Entry
                        </button>
                    </div>
                </div>
            </div>
        </form>

    </div>
</div>

<?php include $projectRoot . "/includes/footer.php"; ?>

<script>
function loadPODetails() {
    let poId = document.getElementById("po_select").value;
    let tbody = document.querySelector("#asnItemsTable tbody");

    if (!poId) {
        document.getElementById("supplier_name_display").value = "";
        document.getElementById("supplier_id").value = "";
        tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-muted">👈 Select a Purchase Order (PO) above to load items automatically</td></tr>';
        return;
    }

    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-primary"><i class="fa-solid fa-spinner fa-spin me-2"></i> Fetching PO Details & Items...</td></tr>';

    fetch(`get_po_details.php?po_id=${poId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Fill Master Details
                document.getElementById("supplier_name_display").value = data.po.supplier_name + " (" + data.po.supplier_code + ")";
                document.getElementById("supplier_id").value = data.po.supplier_id;
                if(data.po.expected_date) {
                    document.getElementById("expected_date").value = data.po.expected_date;
                }

                // Fill Items Table
                tbody.innerHTML = "";
                if (data.items.length === 0) {
                    tbody.innerHTML = '<tr><td colspan="4" class="text-center py-4 text-danger">⚠️ No items found in selected PO</td></tr>';
                } else {
                    data.items.forEach(item => {
                        let tr = document.createElement("tr");
                        tr.innerHTML = `
                            <td>
                                <strong>${item.product_name}</strong>
                                <input type="hidden" name="product_name[]" value="${item.product_name}">
                            </td>
                            <td>
                                <code class="fs-6 fw-bold">${item.product_code}</code>
                                <input type="hidden" name="product_code[]" value="${item.product_code}">
                            </td>
                            <td>
                                <span class="badge bg-secondary fs-6">${item.ordered_qty} Units</span>
                            </td>
                            <td>
                                <input type="number" name="expected_qty[]" class="form-control fw-bold text-primary" min="1" value="${item.ordered_qty}" required>
                            </td>
                        `;
                        tbody.appendChild(tr);
                    });
                }
            } else {
                alert("Error: " + data.message);
            }
        })
        .catch(err => {
            console.error(err);
            alert("Failed to load PO data");
        });
}
</script>