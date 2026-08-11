<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (!isset($_GET['id'])) {
    die("Invalid Request");
}

$id = intval($_GET['id']);

/* Check GRN */

$check = mysqli_query($conn,"
SELECT *
FROM grn
WHERE id='$id'
");

if(mysqli_num_rows($check)==0){
    die("GRN Not Found");
}

/* Delete GRN Items */

mysqli_query($conn,"
DELETE FROM grn_items
WHERE grn_id='$id'
");

/* Delete GRN */

mysqli_query($conn,"
DELETE FROM grn
WHERE id='$id'
");

/* Redirect */

header("Location: index.php");
exit();
?>