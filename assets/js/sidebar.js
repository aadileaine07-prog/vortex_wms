/* =========================================
   VORTEX WMS SIDEBAR
========================================= */

document.addEventListener("DOMContentLoaded", function () {

    /* =====================================
       ACTIVE MENU
    ===================================== */

    const currentURL = window.location.href;

    document.querySelectorAll(".sidebar a").forEach(link => {

        if (link.href === currentURL) {

            link.classList.add("active");

        }

    });

});


/* =========================================
   MOBILE SIDEBAR
========================================= */

function openSidebar() {

    const sidebar = document.querySelector(".sidebar");

    if (sidebar) {

        sidebar.classList.add("show");

    }

}


function closeSidebar() {

    const sidebar = document.querySelector(".sidebar");

    if (sidebar) {

        sidebar.classList.remove("show");

    }

}


/* =========================================
   ESC — CLOSE SIDEBAR
========================================= */

document.addEventListener("keydown", function (event) {

    if (event.key === "Escape") {

        closeSidebar();

    }

});


/* =========================================
   CLICK OUTSIDE — MOBILE SIDEBAR
========================================= */

document.addEventListener("click", function (event) {

    const sidebar =
        document.querySelector(".sidebar");

    const toggle =
        document.querySelector(".menu-toggle");

    if (!sidebar) {
        return;
    }

    if (window.innerWidth < 992) {

        if (
            !sidebar.contains(event.target) &&
            (!toggle || !toggle.contains(event.target))
        ) {

            sidebar.classList.remove("show");

        }

    }

});


/* =========================================
   PROFILE DROPDOWN
========================================= */

function toggleProfileMenu(event) {

    event.stopPropagation();

    const dropdown =
        document.getElementById("profileDropdown");

    if (!dropdown) {
        return;
    }

    dropdown.classList.toggle("show");

}


/* =========================================
   NOTIFICATION DROPDOWN
========================================= */

function toggleNotificationMenu(event) {

    event.stopPropagation();

    const dropdown =
        document.getElementById("notificationDropdown");

    if (!dropdown) {
        return;
    }

    dropdown.classList.toggle("show");

}


/* =========================================
   OUTSIDE CLICK
========================================= */

document.addEventListener("click", function (event) {

    const profileDropdown =
        document.getElementById("profileDropdown");

    const notificationDropdown =
        document.getElementById("notificationDropdown");

    const profile =
        document.querySelector(".navbar-profile");

    const notification =
        document.querySelector(".navbar-notification");


    if (
        profileDropdown &&
        profile &&
        !profile.contains(event.target)
    ) {

        profileDropdown.classList.remove("show");

    }


    if (
        notificationDropdown &&
        notification &&
        !notification.contains(event.target)
    ) {

        notificationDropdown.classList.remove("show");

    }

});


/* =========================================
   ESC — CLOSE DROPDOWNS
========================================= */

document.addEventListener("keydown", function (event) {

    if (event.key !== "Escape") {
        return;
    }

    const profileDropdown =
        document.getElementById("profileDropdown");

    const notificationDropdown =
        document.getElementById("notificationDropdown");


    if (profileDropdown) {

        profileDropdown.classList.remove("show");

    }


    if (notificationDropdown) {

        notificationDropdown.classList.remove("show");

    }

});