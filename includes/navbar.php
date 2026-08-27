<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$fullName = $_SESSION['full_name'] ?? 'User Admin';
$role     = $_SESSION['role'] ?? 'Super Admin';

// Safe Database Connection
if (!isset($conn)) {
    $db_path = __DIR__ . "/../config/database.php";
    if (file_exists($db_path)) {
        require_once $db_path;
    }
}

$unreadCount = 0;
$latestNotifs = [];

if (isset($conn)) {
    $notifChk = @mysqli_query($conn, "SHOW TABLES LIKE 'notifications'");
    if ($notifChk && mysqli_num_rows($notifChk) > 0) {
        $unreadCountQuery = @mysqli_query($conn, "SELECT COUNT(*) as unread_count FROM notifications WHERE is_read = 0");
        if ($unreadCountQuery && $r = mysqli_fetch_assoc($unreadCountQuery)) {
            $unreadCount = $r['unread_count'] ?? 0;
        }

        $latestNotifsQuery = @mysqli_query($conn, "SELECT * FROM notifications ORDER BY id DESC LIMIT 3");
        if ($latestNotifsQuery) {
            while ($row = mysqli_fetch_assoc($latestNotifsQuery)) {
                $latestNotifs[] = $row;
            }
        }
    }
}
?>

<nav class="top-navbar">
    <div class="navbar-left">
        <button type="button" class="menu-toggle" onclick="toggleSidebarMenu()" aria-label="Toggle Sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>
        <span class="navbar-title fw-bold text-dark fs-5">
            Warehouse Management System
        </span>
    </div>

    <div class="navbar-right d-flex align-items-center gap-3">
        <!-- NOTIFICATION DROPDOWN -->
        <div class="navbar-notification position-relative">
            <button type="button" class="btn btn-light rounded-circle position-relative" onclick="toggleNotificationMenu(event)">
                <i class="fa-regular fa-bell fs-5 text-secondary"></i>
                <?php if ($unreadCount > 0): ?>
                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                        <?= $unreadCount; ?>
                    </span>
                <?php endif; ?>
            </button>

            <div class="notification-dropdown shadow rounded-4" id="notificationDropdown" style="display:none; position:absolute; right:0; top:45px; width:300px; background:#fff; z-index:1060;">
                <div class="p-3 border-bottom d-flex justify-content-between align-items-center">
                    <strong>Notifications</strong>
                    <span class="badge bg-primary rounded-pill"><?= $unreadCount; ?> New</span>
                </div>
                <div class="notification-body">
                    <?php if (!empty($latestNotifs)): ?>
                        <?php foreach ($latestNotifs as $notif): ?>
                            <div class="p-2 border-bottom small">
                                <strong class="d-block text-dark"><?= htmlspecialchars($notif['title'] ?? ''); ?></strong>
                                <span class="text-muted"><?= htmlspecialchars($notif['message'] ?? ''); ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <div class="p-3 text-center text-muted small">No new notifications</div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- PROFILE DROPDOWN -->
        <div class="navbar-profile position-relative">
            <button type="button" class="btn d-flex align-items-center gap-2 p-1 border-0" onclick="toggleProfileMenu(event)">
                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold" style="width:36px; height:36px;">
                    <?= strtoupper(substr($fullName, 0, 1)); ?>
                </div>
                <div class="text-start d-none d-md-block">
                    <strong class="d-block text-dark small leading-tight"><?= htmlspecialchars($fullName); ?></strong>
                    <small class="text-muted" style="font-size:11px;"><?= htmlspecialchars($role); ?></small>
                </div>
                <i class="fa-solid fa-chevron-down text-muted small ms-1"></i>
            </button>

            <div class="profile-dropdown shadow rounded-4 border p-2" id="profileDropdown" style="display:none; position:absolute; right:0; top:48px; width:200px; background:#fff; z-index:1060;">
                <a href="/vortex_wms/modules/system_settings/index.php" class="dropdown-item p-2 rounded-2 small text-dark"><i class="fa-solid fa-gear me-2"></i> Settings</a>
                <div class="dropdown-divider my-1"></div>
                <a href="/vortex_wms/logout.php" class="dropdown-item p-2 rounded-2 small text-danger"><i class="fa-solid fa-right-from-bracket me-2"></i> Logout</a>
            </div>
        </div>
    </div>
</nav>

<script>
function toggleNotificationMenu(e) {
    e.stopPropagation();
    const drop = document.getElementById('notificationDropdown');
    drop.style.display = drop.style.display === 'block' ? 'none' : 'block';
    document.getElementById('profileDropdown').style.display = 'none';
}

function toggleProfileMenu(e) {
    e.stopPropagation();
    const drop = document.getElementById('profileDropdown');
    drop.style.display = drop.style.display === 'block' ? 'none' : 'block';
    document.getElementById('notificationDropdown').style.display = 'none';
}

document.addEventListener('click', function() {
    if (document.getElementById('notificationDropdown')) document.getElementById('notificationDropdown').style.display = 'none';
    if (document.getElementById('profileDropdown')) document.getElementById('profileDropdown').style.display = 'none';
});

function toggleSidebarMenu() {
    const sb = document.getElementById('vortexSidebar');
    if (sb) {
        sb.classList.toggle('show');
    }
}
</script>