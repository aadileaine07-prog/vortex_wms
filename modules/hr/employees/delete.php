<?php

session_start();

/* ==============================
   LOGIN CHECK
============================== */

if (!isset($_SESSION['employee_id'])) {

    header("Location: ../../../login.php");
    exit();

}


/* ==============================
   DATABASE
============================== */

require_once "../../../config/database.php";


/* ==============================
   GET EMPLOYEE ID
============================== */

if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {

    header("Location: index.php");
    exit();

}

$id = (int) $_GET['id'];


/* ==============================
   GET EMPLOYEE PHOTO
============================== */

$stmt = mysqli_prepare(
    $conn,
    "SELECT photo FROM employees WHERE id = ? LIMIT 1"
);

mysqli_stmt_bind_param($stmt, "i", $id);

mysqli_stmt_execute($stmt);

$result = mysqli_stmt_get_result($stmt);

$employee = mysqli_fetch_assoc($result);

mysqli_stmt_close($stmt);


if (!$employee) {

    header("Location: index.php?error=not_found");
    exit();

}


/* ==============================
   DELETE EMPLOYEE
============================== */

$stmt = mysqli_prepare(
    $conn,
    "DELETE FROM employees WHERE id = ?"
);

mysqli_stmt_bind_param($stmt, "i", $id);

if (mysqli_stmt_execute($stmt)) {

    mysqli_stmt_close($stmt);


    /* ==============================
       DELETE PHOTO
    ============================== */

    if (!empty($employee['photo'])) {

        $photoPath =
            "../../../assets/images/employees/"
            . basename($employee['photo']);

        if (file_exists($photoPath)) {

            unlink($photoPath);

        }

    }


    header("Location: index.php?success=deleted");
    exit();

}


/* ==============================
   DELETE FAILED
============================== */

mysqli_stmt_close($stmt);

header("Location: index.php?error=delete_failed");
exit();

?>