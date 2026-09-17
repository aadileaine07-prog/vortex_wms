<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

$host = "127.0.0.1";
$username = "root";
$password = "root";
$port = 8889;

$conn = @mysqli_connect($host, $username, $password, "", $port);

if (!$conn) {
    echo "<h3 style='color:red;'>Server Connection Failed: " . mysqli_connect_error() . "</h3>";
    echo "<p><b>Possible Reason:</b> MAMP MySQL server start nahi hai ya port 8889 block hai.</p>";
} else {
    echo "<h3 style='color:green;'>MySQL Server Connected Successfully!</h3>";
    
    // Check if database exists
    $db_check = @mysqli_select_db($conn, "vortex_wms");
    if ($db_check) {
        echo "<p style='color:green;'>✔ Database 'vortex_wms' also exists and selected!</p>";
    } else {
        echo "<p style='color:orange;'>⚠ Server connected, but database 'vortex_wms' does not exist yet. Please create it in phpMyAdmin.</p>";
    }
}
?>