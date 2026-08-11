<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

if ($_SERVER['REQUEST_METHOD'] != "POST") {
    header("Location:index.php");
    exit();
}

$grn_id = intval($_POST['grn_id']);

foreach($_POST['received_qty'] as $id => $received){

    $received = intval($received);

    $damaged = intval($_POST['damaged_qty'][$id]);

    $accepted = $received - $damaged;

    if($accepted < 0){
        $accepted = 0;
    }

    $remarks = mysqli_real_escape_string($conn,$_POST['remarks'][$id]);

    mysqli_query($conn,"
    UPDATE grn_items
    SET

    received_qty='$received',

    damaged_qty='$damaged',

    accepted_qty='$accepted',

    remarks='$remarks'

    WHERE id='$id'

    ");

}

/* Complete GRN */

mysqli_query($conn,"
UPDATE grn
SET status='Completed'
WHERE id='$grn_id'
");

/* ASN Status Update */

$result = mysqli_query($conn,"
SELECT asn_id
FROM grn
WHERE id='$grn_id'
");

$row = mysqli_fetch_assoc($result);

$asn_id = $row['asn_id'];

mysqli_query($conn,"
UPDATE asn
SET status='Received'
WHERE id='$asn_id'
");

header("Location:view.php?id=".$grn_id);

exit();

?>