<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$baseDir = __DIR__;

if (!isset($_SESSION['employee_id'])) {
    header("Location: login.php");
    exit();
}

require_once $baseDir . "/config/database.php";
include $baseDir . "/includes/header.php";

// Access Control: Strict Sidebar Customizer access for Super Admin & Admin only
allowRoles(['Super Admin', 'Admin']);

// 1. Auto-create sidebar customization table if not exists
@mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `sidebar_customization` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `menu_key` VARCHAR(100) NOT NULL UNIQUE,
        `menu_name` VARCHAR(150) NOT NULL,
        `icon` VARCHAR(100) DEFAULT 'fa-folder',
        `url` VARCHAR(255) NOT NULL,
        `is_active` TINYINT(1) DEFAULT 1,
        `sort_order` INT DEFAULT 0
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Insert default menus if table is empty
$chkCount = mysqli_fetch_array(mysqli_query($conn, "SELECT COUNT(*) FROM sidebar_customization"))[0];
if ($chkCount == 0) {$defaults = [
        ['dashboard', 'Dashboard', 'fa-solid fa-house', 'index.php', 1, 1],
        ['inventory', 'Inventory Management', 'fa-solid fa-boxes-stacked', 'modules/inventory/index.php', 1, 2],
        ['adjustments', 'Stock Adjustments', 'fa-solid fa-sliders', 'modules/inventory/adjustment/index.php', 1, 3],
        ['notifications', 'Notifications Center', 'fa-solid fa-bell', 'notifications/index.php', 1, 4],
        ['file_editor', 'Code Editor', 'fa-solid fa-code', 'file_editor.php', 1, 5]
    ];
    foreach ($defaults as$d) {
        mysqli_query($conn, "INSERT INTO sidebar_customization (menu_key, menu_name, icon, url, is_active, sort_order) VALUES ('{$d[0]}', '{$d[1]}', '{$d[2]}', '{$d[3]}', {$d[4]}, {$d[5]})");
    }
}

// Handle Status Toggle (Enable/Disable Menu)
if (isset($_GET['toggle']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $status = intval($_GET['status']);
    mysqli_query($conn, "UPDATE sidebar_customization SET is_active = $status WHERE id =$id");
    $_SESSION['success'] = "Sidebar menu status updated successfully!";
    header("Location: sidebar_customizer.php");
    exit();
}

// Handle Form Submission for Editing/Adding Menus
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_menu'])) {$menuId   = intval($_POST['menu_id'] ?? 0);$menuName = mysqli_real_escape_string($conn,$_POST['menu_name']);
    $icon     = mysqli_real_escape_string($conn, $_POST['icon']);$url      = mysqli_real_escape_string($conn,$_POST['url']);
    $sort     = intval($_POST['sort_order']);

    if ($menuId > 0) {
        mysqli_query($conn, "UPDATE sidebar_customization SET menu_name='$menuName', icon='$icon', url='$url', sort_order=$sort WHERE id=$menuId");
        $_SESSION['success'] = "Sidebar menu updated successfully!";
    } else {
        $key = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '_',$menuName)));
        mysqli_query($conn, "INSERT INTO sidebar_customization (menu_key, menu_name, icon, url, is_active, sort_order) VALUES ('$key', '$menuName', '$icon', '$url', 1,$sort)");
        $_SESSION['success'] = "New sidebar menu added successfully!";
    }
    header("Location: sidebar_customizer.php");
    exit();
}

$menus = mysqli_query($conn, "SELECT * FROM sidebar_customization ORDER BY sort_order ASC");
?>

<div class="container-fluid py-4">

    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-bars-staggered text-warning me-2"></i>Sidebar Navigation Customizer
            </h2>
            <p class="text-muted mb-0">Manage visible menu links, rearrange priorities, and customize dashboard navigation</p>
        </div>
        <a href="index.php" class="btn btn-secondary fw-bold rounded-pill px-3 shadow-sm">
            <i class="fa-solid fa-arrow-left me-1"></i> Back to Dashboard
        </a>
    </div>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4">
            <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
            <button class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="row g-4">
        <!-- Left Side: Add / Edit Form -->
        <div class="col-lg-4">
            <div class="card shadow-sm border-0 rounded-4 bg-white">
                <div class="card-header bg-light border-0 py-3 px-4 rounded-top-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-plus-circle me-2 text-primary"></i>Add / Edit Menu Item</h5>
                </div>
                <div class="card-body p-4">
                    <form method="POST">
                        <input type="hidden" name="menu_id" id="menu_id" value="0">
                        
                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Menu Title *</label>
                            <input type="text" name="menu_name" id="menu_name" class="form-control border-2 fw-semibold" placeholder="e.g. Stock Reports" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">FontAwesome Icon Class *</label>
                            <input type="text" name="icon" id="icon" class="form-control border-2 font-monospace" placeholder="fa-solid fa-box" value="fa-solid fa-folder" required>
                            <small class="text-muted" style="font-size: 11px;">Use FontAwesome 6 classes (e.g. <code>fa-solid fa-truck</code>)</small>
                        </div>

                        <div class="mb-3">
                            <label class="form-label small fw-bold text-muted">Target URL Link *</label>
                            <input type="text" name="url" id="url" class="form-control border-2 font-monospace" placeholder="modules/reports/index.php" required>
                        </div>

                        <div class="mb-4">
                            <label class="form-label small fw-bold text-muted">Sort Order Priority</label>
                            <input type="number" name="sort_order" id="sort_order" class="form-control border-2 fw-bold" value="10" required>
                        </div>

                        <div class="d-grid gap-2">
                            <button type="submit" name="save_menu" class="btn btn-success fw-bold rounded-pill shadow-sm py-2">
                                <i class="fa-solid fa-floppy-disk me-1"></i> Save Menu Item
                            </button>
                            <button type="button" onclick="resetForm()" class="btn btn-light fw-bold rounded-pill text-muted">Cancel / Reset</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Right Side: Active Sidebar Items Table -->
        <div class="col-lg-8">
            <div class="card shadow-sm border-0 rounded-4 bg-white">
                <div class="card-header bg-light border-0 py-3 px-4 rounded-top-4">
                    <h5 class="fw-bold mb-0 text-dark"><i class="fa-solid fa-list-ul me-2 text-success"></i>Current Sidebar Navigation Structure</h5>
                </div>
                <div class="card-body p-4">
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light text-uppercase fs-7">
                                <tr>
                                    <th width="60" class="text-center">Order</th>
                                    <th>Icon & Menu Name</th>
                                    <th>URL Path</th>
                                    <th class="text-center">Status</th>
                                    <th width="125" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($menus && mysqli_num_rows($menus) > 0): ?>
                                    <?php while ($m = mysqli_fetch_assoc($menus)): ?>
                                        <tr>
                                            <td class="text-center fw-bold text-muted font-monospace"><?= $m['sort_order']; ?></td>
                                            <td>
                                                <div class="d-flex align-items-center gap-2">
                                                    <span class="p-2 bg-light rounded-circle text-primary"><i class="<?= htmlspecialchars($m['icon']); ?>"></i></span>
                                                    <span class="fw-bold text-dark"><?= htmlspecialchars($m['menu_name']); ?></span>
                                                </div>
                                            </td>
                                            <td><code class="text-secondary small"><?= htmlspecialchars($m['url']); ?></code></td>
                                            <td class="text-center">
                                                <?php if ($m['is_active'] == 1): ?>
                                                    <span class="badge bg-success-subtle text-success border border-success-subtle px-3 py-1 rounded-pill">Active</span>
                                                <?php else: ?>
                                                    <span class="badge bg-danger-subtle text-danger border border-danger-subtle px-3 py-1 rounded-pill">Hidden</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <div class="d-inline-flex gap-1">
                                                    <?php if ($m['is_active'] == 1): ?>
                                                        <a href="sidebar_customizer.php?toggle=1&id=<?= $m['id']; ?>&status=0" class="btn btn-outline-warning btn-sm rounded-circle" title="Hide Menu"><i class="fa-solid fa-eye-slash"></i></a>
                                                    <?php else: ?>
                                                        <a href="sidebar_customizer.php?toggle=1&id=<?= $m['id']; ?>&status=1" class="btn btn-outline-success btn-sm rounded-circle" title="Show Menu"><i class="fa-solid fa-eye"></i></a>
                                                    <?php endif; ?>
                                                    <button type="button" class="btn btn-outline-primary btn-sm rounded-circle" onclick="editMenu(<?= htmlspecialchars(json_encode($m)); ?>)" title="Edit Menu"><i class="fa-solid fa-pen"></i></button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="5" class="text-center py-4 text-muted">No custom sidebar menus found.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

</div>

<script>
function editMenu(data) {
    document.getElementById('menu_id').value = data.id;
    document.getElementById('menu_name').value = data.menu_name;
    document.getElementById('icon').value = data.icon;
    document.getElementById('url').value = data.url;
    document.getElementById('sort_order').value = data.sort_order;
    window.scrollTo({top: 0, behavior: 'smooth'});
}

function resetForm() {
    document.getElementById('menu_id').value = 0;
    document.getElementById('menu_name').value = '';
    document.getElementById('icon').value = 'fa-solid fa-folder';
    document.getElementById('url').value = '';
    document.getElementById('sort_order').value = 10;
}
</script>

<?php include $baseDir . "/includes/footer.php"; ?>