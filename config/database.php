<?php

$host = "localhost";
$username = "root";
$password = "root";
$database = "vortex_wms";
$port = 8889;

$conn = new mysqli($host, $username, $password, $database, $port);

if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");