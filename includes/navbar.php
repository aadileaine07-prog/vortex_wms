<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$fullName = $_SESSION['full_name'] ?? 'Aaadil Raine';
$role     = $_SESSION['role'] ?? 'Super Admin';

// Database Connection Requirement
require_once __DIR__ . "/../config/database.php";

// Fetch Dynamic Notifications Count & Latest 3 Unread Alerts
$unreadCountQuery = mysqli_query($conn, "SELECT COUNT(*) as unread_count FROM notifications WHERE is_read = 0");
$unreadCountRes   = mysqli_fetch_assoc($unreadCountQuery);
$unreadCount      = $unreadCountRes['unread_count'] ?? 0;

$latestNotifsQuery = mysqli_query($conn, "
    SELECT * 
    FROM notifications 
    ORDER BY id DESC 
    LIMIT 3
");

?>

<nav class="top-navbar">

    <!-- LEFT -->
    <div class="navbar-left">
        <button
            type="button"
            class="menu-toggle"
            onclick="openSidebar()"
            aria-label="Open Sidebar">
            <i class="fa-solid fa-bars"></i>
        </button>

        <span class="navbar-title">
            Warehouse Management System
        </span>
    </div>

    <!-- RIGHT -->
    <div class="navbar-right">

        <!-- NOTIFICATION DROPDOWN -->
        <div class="navbar-notification">

            <button
                type="button"
                class="notification-button"
                onclick="toggleNotificationMenu(event)"
                aria-label="Notifications">

                <i class="fa-regular fa-bell"></i>

                <?php if ($unreadCount > 0): ?>
                    <span class="notification-dot">
                        <?= $unreadCount; ?>
                    </span>
                <?php endif; ?>

            </button>

            <div class="notification-dropdown" id="notificationDropdown">

                <div class="notification-header">
                    <strong>Notifications</strong>
                    <span><?= $unreadCount; ?> New</span>
                </div>

                <!-- Dynamic Notifications Items -->
                <?php if ($latestNotifsQuery && mysqli_num_rows($latestNotifsQuery) > 0): ?>
                    <?php while ($notif = mysqli_fetch_assoc($latestNotifsQuery)): ?>
                        <?php
                        // Color styling based on notification type
                        $iconBg = 'blue';
                        $iconClass = 'fa-circle-info';

                        if ($notif['type'] == 'warning') {
                            $iconBg = 'orange';
                            $iconClass = 'fa-triangle-exclamation';
                        } elseif ($notif['type'] == 'danger') {
                            $iconBg = 'red';
                            $iconClass = 'fa-circle-xmark';
                        } elseif ($notif['type'] == 'success') {
                            $iconBg = 'green';
                            $iconClass = 'fa-circle-check';
                        }
                        ?>
                        
                        <div class="notification-item <?= ($notif['is_read'] == 0) ? 'unread-item' : ''; ?>">
                            <div class="notification-icon <?= $iconBg; ?>">
                                <i class="fa-solid <?= $iconClass; ?>"></i>
                            </div>
                            <div>
                                <strong><?= htmlspecialchars($notif['title']); ?></strong>
                                <p><?= htmlspecialchars($notif['message']); ?></p>
                                <small><?= date("d M, h:i A", strtotime($notif['created_at'])); ?></small>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="p-3 text-center text-muted">
                        <small>No new notifications</small>
                    </div>
                <?php endif; ?>

                <div class="notification-footer">
                    <a href="/vortex_wms/modules/notifications/index.php">
                        View all notifications
                        <i class="fa-solid fa-arrow-right"></i>
                    </a>
                </div>

            </div>

        </div>

        <!-- PROFILE DROPDOWN -->
        <div class="navbar-profile">

            <button
                type="button"
                class="profile-button"
                onclick="toggleProfileMenu(event)">

                <img
                    src="/vortex_wms/assets/images/profile.png"
                    alt="Profile"
                    class="navbar-avatar"
                    width="30"
                    height="30"
                    loading="eager"
                    decoding="async"
                >

                <div class="navbar-user-info">
                    <strong><?= htmlspecialchars($fullName); ?></strong>
                    <span><?= htmlspecialchars($role); ?></span>
                </div>

                <i class="fa-solid fa-chevron-down navbar-arrow"></i>

            </button>

            <!-- PROFILE MENU -->
            <div class="profile-dropdown" id="profileDropdown">

                <div class="profile-dropdown-header">
                    <img src="/vortex_wms/assets/images/profile.png" alt="Profile">
                    <div>
                        <strong><?= htmlspecialchars($fullName); ?></strong>
                        <small><?= htmlspecialchars($role); ?></small>
                    </div>
                </div>

                <div class="profile-divider"></div>

                <a href="/vortex_wms/modules/profile/index.php">
                    <i class="fa-regular fa-user"></i>
                    <span>My Profile</span>
                </a>

                <a href="/vortex_wms/modules/settings/index.php">
                    <i class="fa-solid fa-gear"></i>
                    <span>Settings</span>
                </a>

                <div class="profile-divider"></div>

                <a href="/vortex_wms/logout.php" class="profile-logout">
                    <i class="fa-solid fa-right-from-bracket"></i>
                    <span>Logout</span>
                </a>

            </div>

        </div>

    </div>

</nav>