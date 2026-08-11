<?php
session_start();

if (!isset($_SESSION['employee_id'])) {
    header("Location: ../../login.php");
    exit();
}

require_once "../../config/database.php";

// Mark Single as Read
if (isset($_GET['action']) && $_GET['action'] == 'read' && isset($_GET['id'])) {
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

// Delete Notification
if (isset($_GET['action']) && $_GET['action'] == 'delete' && isset($_GET['id'])) {
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

// Fetch Notifications
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'all';
$whereClause = "";

if ($filter === 'unread') {
    $whereClause = "WHERE is_read = 0";
} elseif ($filter === 'read') {
    $whereClause = "WHERE is_read = 1";
}

$query = "SELECT * FROM notifications $whereClause ORDER BY id DESC";
$result = mysqli_query($conn, $query);

// Unread Count
$unreadCountRes = mysqli_query($conn, "SELECT COUNT(*) as unread FROM notifications WHERE is_read = 0");
$unreadCount = mysqli_fetch_assoc($unreadCountRes)['unread'] ?? 0;

include "../../includes/header.php";
include "../../includes/navbar.php";
include "../../includes/sidebar.php";
?>

<div class="content">
    <div class="container-fluid p-4">

        <!-- Header -->
        <div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
            <div>
                <h2 class="fw-bold text-dark mb-1">
                    <i class="fa-regular fa-bell text-primary me-2"></i>Notifications Center
                </h2>
                <p class="text-muted mb-0">System alerts, stock warnings, and activity updates</p>
            </div>
            <div class="d-flex gap-2">
                <form method="POST" class="d-inline">
                    <button type="submit" name="mark_all_read" class="btn btn-outline-primary shadow-sm" <?= ($unreadCount == 0) ? 'disabled' : ''; ?>>
                        <i class="fa-solid fa-check-double me-1"></i> Mark All as Read
                    </button>
                </form>
                <form method="POST" class="d-inline" onsubmit="return confirm('Are you sure you want to delete all notifications?');">
                    <button type="submit" name="clear_all" class="btn btn-outline-danger shadow-sm">
                        <i class="fa-solid fa-trash me-1"></i> Clear All
                    </button>
                </form>
            </div>
        </div>

        <!-- Session Messages -->
        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fa-solid fa-circle-check me-2"></i><?= $_SESSION['success']; unset($_SESSION['success']); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Filters & Badge Row -->
        <div class="card shadow-sm border-0 rounded-3 mb-4">
            <div class="card-body p-3 d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div class="btn-group" role="group">
                    <a href="index.php?filter=all" class="btn btn-<?= ($filter == 'all') ? 'primary' : 'outline-secondary'; ?>">All Notifications</a>
                    <a href="index.php?filter=unread" class="btn btn-<?= ($filter == 'unread') ? 'primary' : 'outline-secondary'; ?>">
                        Unread <span class="badge bg-danger ms-1"><?= $unreadCount; ?></span>
                    </a>
                    <a href="index.php?filter=read" class="btn btn-<?= ($filter == 'read') ? 'primary' : 'outline-secondary'; ?>">Read</a>
                </div>
            </div>
        </div>

        <!-- Notifications List -->
        <div class="card shadow-sm border-0 rounded-3">
            <div class="card-body p-4">

                <?php if ($result && mysqli_num_rows($result) > 0): ?>
                    <div class="list-group list-group-flush">
                        <?php while ($row = mysqli_fetch_assoc($result)): ?>
                            <?php
                            $badgeClass = 'bg-info';
                            $iconClass = 'fa-circle-info';
                            if ($row['type'] == 'warning') {
                                $badgeClass = 'bg-warning text-dark';
                                $iconClass = 'fa-triangle-exclamation';
                            } elseif ($row['type'] == 'danger') {
                                $badgeClass = 'bg-danger';
                                $iconClass = 'fa-circle-xmark';
                            } elseif ($row['type'] == 'success') {
                                $badgeClass = 'bg-success';
                                $iconClass = 'fa-circle-check';
                            }
                            ?>

                            <div class="list-group-item p-3 border-bottom <?= ($row['is_read'] == 0) ? 'bg-light fw-semibold' : ''; ?> rounded-2 mb-2">
                                <div class="d-flex justify-content-between align-items-start gap-3">
                                    <div class="d-flex gap-3 align-items-start">
                                        <span class="badge <?= $badgeClass; ?> p-2 fs-6 mt-1">
                                            <i class="fa-solid <?= $iconClass; ?>"></i>
                                        </span>
                                        <div>
                                            <h6 class="mb-1 text-dark fw-bold">
                                                <?= htmlspecialchars($row['title']); ?>
                                                <?php if ($row['is_read'] == 0): ?>
                                                    <span class="badge bg-danger ms-1">NEW</span>
                                                <?php endif; ?>
                                            </h6>
                                            <p class="mb-1 text-muted fw-normal"><?= htmlspecialchars($row['message']); ?></p>
                                            <small class="text-secondary">
                                                <i class="fa-regular fa-clock me-1"></i><?= date("d M Y, h:i A", strtotime($row['created_at'])); ?>
                                            </small>
                                        </div>
                                    </div>

                                    <div class="d-flex gap-2">
                                        <?php if ($row['is_read'] == 0): ?>
                                            <a href="index.php?action=read&id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-success" title="Mark as Read">
                                                <i class="fa-solid fa-check"></i>
                                            </a>
                                        <?php endif; ?>
                                        <a href="index.php?action=delete&id=<?= $row['id']; ?>" class="btn btn-sm btn-outline-danger" title="Delete Notification" onclick="return confirm('Delete this notification?');">
                                            <i class="fa-solid fa-trash"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-5">
                        <i class="fa-regular fa-bell-slash fs-1 text-muted mb-3 d-block"></i>
                        <h5 class="text-muted">No Notifications Found</h5>
                    </div>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<?php include "../../includes/footer.php"; ?>