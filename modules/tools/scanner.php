<?php
session_start();
if (!isset($_SESSION['employee_id'])) { header("Location: ../../login.php"); exit(); }
include "../../includes/header.php";
include "../../includes/navbar.php";
include "../../includes/sidebar.php";
?>

<script src="https://unpkg.com/html5-qrcode"></script>

<div class="content"><div class="container-fluid p-4">
    <div class="col-md-6 mx-auto text-center">
        <h3 class="fw-bold mb-3"><i class="fa-solid fa-mobile-screen-button text-primary me-2"></i>Mobile Handheld Scanner</h3>
        <div id="reader" class="rounded-3 shadow-sm border p-2 mb-3 bg-white"></div>
        <div class="alert alert-info" id="scannedResult">Scan Barcode / QR Code via Camera</div>
    </div>
</div></div>

<script>
function onScanSuccess(decodedText) {
    document.getElementById('scannedResult').innerHTML = '<strong class="text-success">Scanned Code: ' + decodedText + '</strong>';
}
let html5QrcodeScanner = new Html5QrcodeScanner("reader", { fps: 10, qrbox: 250 });
html5QrcodeScanner.render(onScanSuccess);
</script>

<?php include "../../includes/footer.php"; ?>