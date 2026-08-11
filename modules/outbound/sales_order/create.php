<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../../login.php");
    exit();
}

require_once "../../../config/database.php";

$products = mysqli_query($conn, "
    SELECT *
    FROM inventory
    WHERE available_qty > 0
    ORDER BY product_name ASC
");

$order_no = "SO-" . date("YmdHis");

include "../../../includes/header.php";
include "../../../includes/navbar.php";
include "../../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="fw-bold text-dark mb-1"><i class="fa-solid fa-plus-circle text-success me-2"></i>Create Sales Order</h2>
                <p class="text-muted mb-0">Issue new outbound customer order</p>
            </div>
            <a href="index.php" class="btn btn-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
        </div>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-triangle-exclamation me-2"></i><?= $_SESSION['error']; unset($_SESSION['error']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">
                <form action="save.php" method="POST">
                    
                    <div class="row g-3 mb-3">
                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Order Number</label>
                            <input type="text" name="order_number" class="form-control bg-light" value="<?= $order_no; ?>" readonly>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Order Date <span class="text-danger">*</span></label>
                            <input type="date" name="order_date" class="form-control" value="<?= date('Y-m-d'); ?>" required>
                        </div>

                        <div class="col-md-4">
                            <label class="form-label fw-semibold">Customer Name <span class="text-danger">*</span></label>
                            <input type="text" name="customer_name" class="form-control" placeholder="Enter customer full name" required>
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer Phone</label>
                            <input type="text" name="customer_phone" class="form-control" placeholder="+91 00000 00000">
                        </div>

                        <div class="col-md-6">
                            <label class="form-label fw-semibold">Customer Email</label>
                            <input type="email" name="customer_email" class="form-control" placeholder="customer@email.com">
                        </div>

                        <div class="col-md-12">
                            <label class="form-label fw-semibold">Shipping Address</label>
                            <textarea name="shipping_address" class="form-control" rows="2" placeholder="Delivery address..."></textarea>
                        </div>
                    </div>

                    <h5 class="fw-bold mt-4 mb-3"><i class="fa-solid fa-boxes-stacked text-primary me-2"></i>Order Items</h5>

                    <div class="table-responsive mb-4">
                        <table class="table table-bordered align-middle" id="itemTable">
                            <thead class="table-dark">
                                <tr>
                                    <th width="40%">Product <span class="text-danger">*</span></th>
                                    <th width="20%">Available Stock</th>
                                    <th width="25%">Order Qty <span class="text-danger">*</span></th>
                                    <th width="15%" class="text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <select name="product_id[]" class="form-select product" required>
                                            <option value="">-- Select Product --</option>
                                            <?php
                                            mysqli_data_seek($products, 0);
                                            while ($p = mysqli_fetch_assoc($products)) {
                                            ?>
                                                <option value="<?= $p['product_id'] ?? $p['id']; ?>"
                                                        data-code="<?= htmlspecialchars($p['product_code']); ?>"
                                                        data-name="<?= htmlspecialchars($p['product_name']); ?>"
                                                        data-qty="<?= $p['available_qty']; ?>">
                                                    <?= htmlspecialchars($p['product_code']); ?> - <?= htmlspecialchars($p['product_name']); ?> (Stock: <?= $p['available_qty']; ?>)
                                                </option>
                                            <?php } ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control bg-light available" readonly>
                                    </td>
                                    <td>
                                        <input type="number" name="qty[]" class="form-control item-qty" min="1" required>
                                    </td>
                                    <td class="text-center">
                                        <button type="button" class="btn btn-success addRow"><i class="fa-solid fa-plus"></i></button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between">
                        <a href="index.php" class="btn btn-outline-secondary px-3"><i class="fa-solid fa-arrow-left me-1"></i> Back</a>
                        <button type="submit" class="btn btn-success px-4"><i class="fa-solid fa-floppy-disk me-1"></i> Save Sales Order</button>
                    </div>

                </form>
            </div>
        </div>

    </div>
</div>

<?php include "../../../includes/footer.php"; ?>

<script>
document.addEventListener("change", function(e) {
    if (e.target.classList.contains("product")) {
        let option = e.target.options[e.target.selectedIndex];
        let row = e.target.closest("tr");
        let qtyInput = row.querySelector(".available");
        qtyInput.value = option.getAttribute("data-qty") || "";
    }
});

document.addEventListener("input", function(e) {
    if (e.target.classList.contains("item-qty")) {
        let row = e.target.closest("tr");
        let available = parseInt(row.querySelector(".available").value) || 0;
        let requested = parseInt(e.target.value) || 0;

        if (requested > available) {
            alert("Ordered quantity cannot exceed available stock (" + available + ").");
            e.target.value = available;
        }
    }
});

document.addEventListener("click", function(e) {
    if (e.target.closest(".addRow")) {
        let tbody = document.querySelector("#itemTable tbody");
        let firstRow = tbody.rows[0];
        let newRow = firstRow.cloneNode(true);

        newRow.querySelector(".product").selectedIndex = 0;
        newRow.querySelector(".available").value = "";
        newRow.querySelector(".item-qty").value = "";

        let btn = newRow.querySelector(".addRow");
        btn.classList.remove("btn-success", "addRow");
        btn.classList.add("btn-danger", "removeRow");
        btn.innerHTML = '<i class="fa-solid fa-minus"></i>';

        tbody.appendChild(newRow);
    }

    if (e.target.closest(".removeRow")) {
        e.target.closest("tr").remove();
    }
});
</script>