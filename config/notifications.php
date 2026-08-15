<?php
// Notification Helper for Email / WhatsApp Alerts

function sendOrderEmail($toEmail, $orderNo, $status) {
    $subject = "Vortex WMS: Order $orderNo Update";
    $message = "Your Order $orderNo status has been updated to: $status.";
    $headers = "From: no-reply@vortexwms.com\r\nContent-Type: text/html; charset=UTF-8";
    return mail($toEmail, $subject, $message, $headers);
}

function sendWhatsAppAlert($phone, $message) {
    // API Placeholder for WhatsApp Gateway (e.g. Twilio)
    return true;
}
?>