<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if (isset($_GET['id']) && !empty($_GET['id'])) {
    
    $id = intval($_GET['id']);

    if ($id > 0) {
        $deleteQuery = "DELETE FROM leaves WHERE id = '$id'";

        if (mysqli_query($conn, $deleteQuery)) {
            $_SESSION['success'] = "Leave record #{$id} deleted successfully.";
        } else {
            $_SESSION['error'] = "Failed to delete record: " . mysqli_error($conn);
        }
    } else {
        $_SESSION['error'] = "Invalid Leave Record ID.";
    }

} else {
    $_SESSION['error'] = "No Leave ID provided for deletion.";
}

header("Location: index.php");
exit();
?>