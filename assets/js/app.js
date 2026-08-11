/* ==========================================
   VORTEX WMS Enterprise UI
========================================== */

document.addEventListener("DOMContentLoaded", function () {

    /* ===========================
       Sidebar Toggle
    =========================== */

    const sidebar = document.querySelector(".sidebar");
    const toggle = document.getElementById("sidebarToggle");

    if (toggle && sidebar) {

        toggle.addEventListener("click", function () {

            if (window.innerWidth <= 992) {

                sidebar.classList.toggle("show");

            } else {

                sidebar.classList.toggle("collapsed");

                document.body.classList.toggle("sidebar-collapse");

            }

        });

    }

    /* ===========================
       Active Menu
    =========================== */

    const currentURL = window.location.pathname;

    document.querySelectorAll(".menu a").forEach(function (link) {

        if (currentURL === link.getAttribute("href")) {

            link.classList.add("active");

        }

    });

    /* ===========================
       Dark Mode
    =========================== */

    const themeBtn = document.getElementById("themeToggle");

    if (localStorage.getItem("theme") === "dark") {

        document.body.classList.add("dark-mode");

    }

    if (themeBtn) {

        themeBtn.addEventListener("click", function () {

            document.body.classList.toggle("dark-mode");

            if (document.body.classList.contains("dark-mode")) {

                localStorage.setItem("theme", "dark");

            } else {

                localStorage.setItem("theme", "light");

            }

        });

    }

    /* ===========================
       Auto Close Sidebar (Mobile)
    =========================== */

    document.querySelectorAll(".menu a").forEach(function (link) {

        link.addEventListener("click", function () {

            if (window.innerWidth <= 992) {

                sidebar.classList.remove("show");

            }

        });

    });

});