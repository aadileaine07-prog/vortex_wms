```php
<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

$keyword = "";

if(isset($_GET['q'])){
    $keyword = mysqli_real_escape_string($conn, trim($_GET['q']));
}

include "../../includes/header.php";
?>

<div class="content">
<div class="container-fluid">

<h2 class="mb-4">🔍 Global Search</h2>

<form method="GET">

<div class="input-group mb-4">

<input
type="text"
name="q"
class="form-control"
placeholder="Search Product, ASN, Sales Order..."
value="<?= htmlspecialchars($keyword); ?>">

<button class="btn btn-primary">
Search
</button>

</div>

</form>
```
```php
<?php

if($keyword!=""){

?>

<div class="card shadow">

    <div class="card-header bg-primary text-white">
        <h5 class="mb-0">Search Results</h5>
    </div>

    <div class="card-body">

        <table class="table table-bordered table-hover">

            <thead>

            <tr>

                <th>Module</th>
                <th>Reference</th>
                <th>Description</th>
                <th>Status</th>

            </tr>

            </thead>

            <tbody>

            <?php

            /* Products */

            $products = mysqli_query($conn,"
            SELECT product_code,product_name,status
            FROM products
            WHERE product_code LIKE '%$keyword%'
            OR product_name LIKE '%$keyword%'
            ");

            while($row=mysqli_fetch_assoc($products)){
            ?>

            <tr>

                <td>Product</td>

                <td><?= $row['product_code']; ?></td>

                <td><?= $row['product_name']; ?></td>

                <td><?= $row['status']; ?></td>

            </tr>

            <?php } ?>

            <?php

            /* Sales Orders */

            $sales = mysqli_query($conn,"
            SELECT order_number,customer_name,status
            FROM sales_orders
            WHERE order_number LIKE '%$keyword%'
            OR customer_name LIKE '%$keyword%'
            ");

            while($row=mysqli_fetch_assoc($sales)){
            ?>

            <tr>

                <td>Sales Order</td>

                <td><?= $row['order_number']; ?></td>

                <td><?= $row['customer_name']; ?></td>

                <td><?= $row['status']; ?></td>

            </tr>

            <?php } ?>

            <?php

            /* Dispatch */

            $dispatch = mysqli_query($conn,"
            SELECT dispatch_number,courier_name,status
            FROM dispatch
            WHERE dispatch_number LIKE '%$keyword%'
            OR courier_name LIKE '%$keyword%'
            ");

            while($row=mysqli_fetch_assoc($dispatch)){
            ?>

            <tr>

                <td>Dispatch</td>

                <td><?= $row['dispatch_number']; ?></td>

                <td><?= $row['courier_name']; ?></td>

                <td><?= $row['status']; ?></td>

            </tr>

            <?php } ?>

            </tbody>

        </table>

    </div>

</div>

<?php } ?>

</div>
</div>

<?php include "../../includes/footer.php"; ?>
```
