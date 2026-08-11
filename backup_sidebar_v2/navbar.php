<nav class="top-navbar">

<div class="left-section">

<button class="sidebar-toggle" onclick="openSidebar()">

<i class="fa-solid fa-bars"></i>

</button>

<h4 class="page-title">

Warehouse Management System

</h4>

</div>

<div class="right-section">

<!-- Search -->

<div class="search-box">

<input
type="text"
placeholder="Search...">

<i class="fa-solid fa-magnifying-glass"></i>

</div>

<!-- Notification -->

<div class="nav-icon">

<i class="fa-regular fa-bell"></i>

<span class="badge bg-danger">

3

</span>

</div>

<!-- Messages -->

<div class="nav-icon">

<i class="fa-regular fa-envelope"></i>

<span class="badge bg-success">

5

</span>

</div>

<!-- User -->

<div class="profile-menu">

<img
src="/vortex_wms/assets/images/default-user.png"
class="profile-photo">

<div>

<strong>

<?= $_SESSION['full_name'] ?? "Administrator"; ?>

</strong>

<br>

<small>

<?= $_SESSION['role'] ?? "Admin"; ?>

</small>

</div>

</div>

</div>

</nav>