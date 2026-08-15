<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php"); // Absolute Path
    exit();
}

require_once $_SERVER['DOCUMENT_ROOT'] . "/vortex_wms/config/database.php";

$type = $_GET['type'] ?? 'product'; 
$code = trim($_GET['code'] ?? '');

include $_SERVER['DOCUMENT_ROOT'] . "/vortex_wms/includes/header.php";
include $_SERVER['DOCUMENT_ROOT'] . "/vortex_wms/includes/navbar.php";
include $_SERVER['DOCUMENT_ROOT'] . "/vortex_wms/includes/sidebar.php";
?>

<!-- JsBarcode & QRCode Libraries -->
<script src="https://cdn.jsdelivr.net/npm/jsbarcode@3.11.5/dist/JsBarcode.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>

<style>
@media print {
    body * { visibility: hidden; }
    .print-area, .print-area * { visibility: visible; }
    .print-area { position: absolute; left: 0; top: 0; width: 100%; }
    .no-print { display: none !important; }
}

.label-card {
    border: 2px dashed #cbd5e1;
    border-radius: 12px;
    padding: 20px;
    background: #fff;
    text-align: center;
}
</style>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4 no-print">
            <div>
                <h2 class="fw-bold mb-1"><i class="fa-solid fa-barcode text-primary me-2"></i>Barcode & QR Code Generator</h2>
                <p class="text-muted mb-0">Generate printable labels for SKUs, Bins, and Orders</p>
            </div>
            <button onclick="window.print()" class="btn btn-dark shadow-sm"><i class="fa-solid fa-print me-1"></i> Print Label</button>
        </div>

        <div class="card shadow-sm border-0 rounded-3 col-lg-6 mx-auto no-print mb-4">
            <div class="card-body p-4">
                <form method="GET" class="row g-3">
                    <div class="col-md-5">
                        <label class="form-label fw-bold">Label Type</label>
                        <select name="type" class="form-select">
                            <option value="product" <?= $type=='product'?'selected':''; ?>>📦 Product SKU</option>
                            <option value="location" <?= $type=='location'?'selected':''; ?>>📍 Bin Location</option>
                            <option value="asn" <?= $type=='asn'?'selected':''; ?>>📄 ASN / Order Code</option>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label fw-bold">Enter Code / SKU</label>
                        <div class="input-group">
                            <input type="text" name="code" class="form-control" placeholder="e.g. SKU-1001 or BIN-A1" value="<?= htmlspecialchars($code); ?>" required>
                            <button type="submit" class="btn btn-primary"><i class="fa-solid fa-wand-magic-sparkles"></i> Generate</button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <?php if (!empty($code)): ?>
        <div class="print-area col-lg-5 mx-auto">
            <div class="label-card shadow-sm">
                <span class="badge bg-primary text-uppercase px-3 py-1 mb-2"><?= strtoupper($type); ?> LABEL</span>
                <h4 class="fw-bold mb-3 text-dark"><?= htmlspecialchars($code); ?></h4>

                <!-- Barcode Canvas -->
                <div class="mb-3">
                    <svg id="barcode"></svg>
                </div>

                <!-- QR Code Container -->
                <div class="d-flex justify-content-center mb-2">
                    <div id="qrcode"></div>
                </div>

                <small class="text-muted d-block mt-2">Vortex WMS • Automated Labeling</small>
            </div>
        </div>

        <script>
            // Generate Barcode
            JsBarcode("#barcode", "<?= $code; ?>", {
                format: "CODE128",
                width: 2,
                height: 50,
                displayValue: true
            });

            // Generate QR Code
            new QRCode(document.getElementById("qrcode"), {
                text: "<?= $code; ?>",
                width: 90,
                height: 90
            });
        </script>
        <?php endif; ?>

    </div>
</div>

<?php include "../../includes/footer.php"; ?>