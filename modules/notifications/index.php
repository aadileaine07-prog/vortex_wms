<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Multi-Level Project Root Detection
$projectRoot = file_exists(__DIR__ . "/../../config/database.php") 
    ? dirname(__DIR__, 2) 
    : (file_exists(__DIR__ . "/../../../config/database.php") 
        ? dirname(__DIR__, 3) 
        : (file_exists(__DIR__ . "/../../../../config/database.php") ? dirname(__DIR__, 4) : dirname(__DIR__, 1)));

if (!isset($_SESSION['employee_id'])) {
    header("Location: /vortex_wms/login.php");
    exit();
}

require_once $projectRoot . "/config/database.php";

/* ==========================================================================
   1. AUTO-SCHEMA REPAIR FOR NOTIFICATIONS TABLE
   ========================================================================== */

@mysqli_query($conn, "
    CREATE TABLE IF NOT EXISTS `notifications` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `user_id` INT NULL,
        `title` VARCHAR(255) NOT NULL,
        `message` TEXT NOT NULL,
        `type` VARCHAR(50) DEFAULT 'info',
        `is_read` TINYINT(1) DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
");

// Helper Function: Relative Time Ago
function timeAgo($datetime) {
    $timestamp = strtotime($datetime);
    $difference = time() - $timestamp;
    
    if ($difference < 60) return "Just now";
    if ($difference < 3600) return floor($difference / 60) . " mins ago";
    if ($difference < 86400) return floor($difference / 3600) . " hours ago";
    if ($difference < 604800) return floor($difference / 86400) . " days ago";
    return date("d M Y", $timestamp);
}

// Mark Single as Read
if (isset($_GET['action']) && $_GET['action'] === 'read' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    mysqli_query($conn, "UPDATE notifications SET is_read = 1 WHERE id = '$id'");
    $_SESSION['success'] = "Notification marked as read.";
    header("Location: index.php");
    exit();
}

// Mark All as Read
if (isset($_POST['mark_all_read'])) {
    mysqli_query($conn, "UPDATE notifications SET is_read = 1");
    $_SESSION['success'] = "All notifications marked as read.";
    header("Location: index.php");
    exit();
}

// Delete Single Notification
if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    mysqli_query($conn, "DELETE FROM notifications WHERE id = '$id'");
    $_SESSION['success'] = "Notification deleted.";
    header("Location: index.php");
    exit();
}

// Clear All Notifications
if (isset($_POST['clear_all'])) {
    mysqli_query($conn, "DELETE FROM notifications");
    $_SESSION['success'] = "All notifications cleared.";
    header("Location: index.php");
    exit();
}

// Search & Filter Logic
$filter     = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$typeFilter = isset($_GET['type']) ? $_GET['type'] : '';
$search     = isset($_GET['search']) ? trim($_GET['search']) : '';

$whereConditions = [];

if ($filter === 'unread') {
    $whereConditions[] = "is_read = 0";
} elseif ($filter === 'read') {
    $whereConditions[] = "is_read = 1";
}

if (!empty($typeFilter)) {
    $escapedType = mysqli_real_escape_string($conn, $typeFilter);
    $whereConditions[] = "type = '$escapedType'";
}

if (!empty($search)) {
    $escapedSearch = mysqli_real_escape_string($conn, $search);
    $whereConditions[] = "(title LIKE '%$escapedSearch%' OR message LIKE '%$escapedSearch%')";
}

$whereClause = "";
if (count($whereConditions) > 0) {
    $whereClause = "WHERE " . implode(" AND ", $whereConditions);
}

$query = "SELECT * FROM notifications $whereClause ORDER BY id DESC LIMIT 100";
$result = mysqli_query($conn, $query);

// Dashboard Counters
$totalCount   = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications"))['cnt'] ?? 0;
$unreadCount  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE is_read = 0"))['cnt'] ?? 0;
$warningCount = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE type = 'warning'"))['cnt'] ?? 0;
$dangerCount  = mysqli_fetch_assoc(mysqli_query($conn, "SELECT COUNT(*) as cnt FROM notifications WHERE type = 'danger'"))['cnt'] ?? 0;

include $projectRoot . "/includes/header.php";
?>

<div class="container-fluid p-0">

    <!-- Top Header -->
    <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
        <div>
            <h2 class="fw-bold text-dark mb-1">
                <i class="fa-solid fa-bell text-primary me-2"></i>Notifications Center
            </h2>
            <p class="text-muted mb-0">System alerts, stock warnings, and real-time operations log</p>
        </div>
        <div class="d-flex gap-2">
            <form method="POST" class="d-inline">
                <button type="submit" name="mark_all_read" class="btn btn-outline-primary rounded-pill px-3 shadow-sm" <?= ($unreadCount == 0) ? 'disabled' : ''; ?>>
                    <i class="fa-solid fa-check-double me-1"></i> Mark All as Read
                </button>
            </form>
            <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to clear all notifications?');">
                <button type="submit" name="clear_all" class="btn btn-outline-danger rounded-pill px-3 shadow-sm" <?= ($totalCount == 0) ? 'disabled' : ''; ?>>
                    <i class="fa-solid fa-trash me-1"></i> Clear All
                </button>
            </form>
        </div>
    </div>

    <!-- Success Alert -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show rounded-4 border-0 shadow-sm mb-4" role="alert">
            <i class="fa-solid fa-circle-check me-2"></i><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <!-- Quick Stats Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-primary h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Total Alerts</small>
                        <h3 class="fw-bold mb-0 text-dark mt-1"><?= $totalCount; ?></h3>
                    </div>
                    <div class="p-3 bg-primary bg-opacity-10 text-primary rounded-4">
                        <i class="fa-solid fa-layer-group fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-info h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Unread</small>
                        <h3 class="fw-bold mb-0 text-info mt-1"><?= $unreadCount; ?></h3>
                    </div>
                    <div class="p-3 bg-info bg-opacity-10 text-info rounded-4">
                        <i class="fa-solid fa-envelope-open-text fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-warning h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Warnings</small>
                        <h3 class="fw-bold mb-0 text-warning mt-1"><?= $warningCount; ?></h3>
                    </div>
                    <div class="p-3 bg-warning bg-opacity-10 text-warning rounded-4">
                        <i class="fa-solid fa-triangle-exclamation fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-3 col-md-6 col-6">
            <div class="card border-0 shadow-sm rounded-4 p-3 bg-white border-start border-4 border-danger h-100">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <small class="text-muted text-uppercase fw-bold" style="font-size: 11px;">Critical</small>
                        <h3 class="fw-bold mb-0 text-danger mt-1"><?= $dangerCount; ?></h3>
                    </div>
                    <div class="p-3 bg-danger bg-opacity-10 text-danger rounded-4">
                        <i class="fa-solid fa-circle-radiation fa-2x"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Filter & Search Controls -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-3">
            <form method="GET" class="row g-2 align-items-center">
                <div class="col-md-4">
                    <div class="btn-group w-100" role="group">
                        <a href="index.php?filter=all<?= !empty($typeFilter) ? '&type='.$typeFilter : ''; ?>" class="btn btn-<?= ($filter == 'all') ? 'primary' : 'outline-secondary'; ?> text-nowrap rounded-start-pill">All</a>
                        <a href="index.php?filter=unread<?= !empty($typeFilter) ? '&type='.$typeFilter : ''; ?>" class="btn btn-<?= ($filter == 'unread') ? 'primary' : 'outline-secondary'; ?> text-nowrap">
                            Unread <span class="badge bg-danger ms-1"><?= $unreadCount; ?></span>
                        </a>
                        <a href="index.php?filter=read<?= !empty($typeFilter) ? '&type='.$typeFilter : ''; ?>" class="btn btn-<?= ($filter == 'read') ? 'primary' : 'outline-secondary'; ?> text-nowrap rounded-end-pill">Read</a>
                    </div>
                </div>

                <div class="col-md-3">
                    <select name="type" class="form-select border-2" onchange="this.form.submit()">
                        <option value="">All Alert Types</option>
                        <option value="info" <?= ($typeFilter == 'info') ? 'selected' : ''; ?>>Info</option>
                        <option value="success" <?= ($typeFilter == 'success') ? 'selected' : ''; ?>>Success</option>
                        <option value="warning" <?= ($typeFilter == 'warning') ? 'selected' : ''; ?>>Warning</option>
                        <option value="danger" <?= ($typeFilter == 'danger') ? 'selected' : ''; ?>>Danger / Critical</option>
                    </select>
                </div>

                <div class="col-md-5">
                    <div class="input-group">
                        <input type="hidden" name="filter" value="<?= htmlspecialchars($filter); ?>">
                        <input type="text" name="search" class="form-control border-2" placeholder="Search notification title or body..." value="<?= htmlspecialchars($search); ?>">
                        <button type="submit" class="btn btn-primary px-3"><i class="fa-solid fa-magnifying-glass"></i></button>
                        <?php if (!empty($search) || !empty($typeFilter)): ?>
                            <a href="index.php" class="btn btn-outline-secondary" title="Reset Filters"><i class="fa-solid fa-xmark"></i></a>
                        <?php endif; ?>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Main Notifications Feed -->
    <div class="card shadow-sm border-0 rounded-4 bg-white mb-4">
        <div class="card-body p-4">

            <?php if ($result && mysqli_num_rows($result) > 0): ?>
                <div class="list-group list-group-flush">
                    <?php while ($row = mysqli_fetch_assoc($result)): ?>
                        <?php
                        $badgeClass = 'bg-info text-white';
                        $iconClass = 'fa-circle-info';
                        $borderLeft = 'border-info';

                        if ($row['type'] == 'warning') {
                            $badgeClass = 'bg-warning text-dark';
                            $iconClass = 'fa-triangle-exclamation';
                            $borderLeft = 'border-warning';
                        } elseif ($row['type'] == 'danger') {
                            $badgeClass = 'bg-danger text-white';
                            $iconClass = 'fa-circle-xmark';
                            $borderLeft = 'border-danger';
                        } elseif ($row['type'] == 'success') {
                            $badgeClass = 'bg-success text-white';
                            $iconClass = 'fa-circle-check';
                            $borderLeft = 'border-success';
                        }
                        ?>

                        <div class="list-group-item p-3 border-bottom border-start border-4 <?= $borderLeft; ?> <?= ($row['is_read'] == 0) ? 'bg-light shadow-2xs' : ''; ?> rounded-3 mb-2">
                            <div class="d-flex justify-content-between align-items-start gap-3">
                                <div class="d-flex gap-3 align-items-start">
                                    <span class="badge <?= $badgeClass; ?> p-2 fs-6 mt-1 rounded-circle">
                                        <i class="fa-solid <?= $iconClass; ?>"></i>
                                    </span>
                                    <div>
                                        <h6 class="mb-1 text-dark fw-bold">
                                            <?= htmlspecialchars($row['title']); ?>
                                            <?php if ($row['is_read'] == 0): ?>
                                                <span class="badge bg-danger ms-1 px-2 py-1" style="font-size: 10px;">NEW</span>
                                            <?php endif; ?>
                                        </h6>
                                        <p class="mb-1 text-secondary fw-normal"><?= htmlspecialchars($row['message']); ?></p>
                                        <small class="text-muted">
                                            <i class="fa-regular fa-clock me-1"></i><?= timeAgo($row['created_at']); ?> &bull; <?= date("d M Y, h:i A", strtotime($row['created_at'])); ?>
                                        </small>
                                    </div>
                                </div>

                                <div class="d-flex gap-2">
                                    <?php if ($row['is_read'] == 0): ?>
                                        <a href="index.php?action=read&id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-success rounded-circle" title="Mark as Read">
                                            <i class="fa-solid fa-check"></i>
                                        </a>
                                    <?php endif; ?>
                                    <a href="index.php?action=delete&id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-danger rounded-circle" title="Delete" onclick="return confirm('Delete this notification?');">
                                        <i class="fa-solid fa-trash"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="text-center py-5">
                    <i class="fa-regular fa-bell-slash fs-1 text-muted mb-3 d-block opacity-50"></i>
                    <h5 class="text-muted fw-bold">No Notifications Found</h5>
                    <p class="text-secondary small mb-3">There are no alerts matching your current filters or search term.</p>
                    <a href="index.php" class="btn btn-sm btn-outline-primary rounded-pill px-3">Reset All Filters</a>
                </div>
            <?php endif; ?>

        </div>
    </div>

</div>

<?php include $projectRoot . "/includes/footer.php"; ?>