<?php
function logSystemNotification($conn, $title, $message, $type = 'info', $userId = null) {
    $title   = mysqli_real_escape_string($conn, $title);
    $message = mysqli_real_escape_string($conn, $message);
    $type    = mysqli_real_escape_string($conn, $type);
    $uid     = $userId ? intval($userId) : (isset($_SESSION['employee_id']) ? intval($_SESSION['employee_id']) : 'NULL');

    $sql = "INSERT INTO notifications (user_id, title, message, type, is_read, created_at) 
            VALUES ($uid, '$title', '$message', '$type', 0, NOW())";
    @mysqli_query($conn, $sql);
}
?>