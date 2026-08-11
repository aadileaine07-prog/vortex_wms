<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if(!isset($_GET['id'])){
    header("Location:index.php");
    exit();
}

$id = $_GET['id'];

$sql = "DELETE FROM companies WHERE id='$id'";

if(mysqli_query($conn,$sql)){
    header("Location:index.php");
    exit();
}else{
    echo "Error : ".mysqli_error($conn);
}
?>