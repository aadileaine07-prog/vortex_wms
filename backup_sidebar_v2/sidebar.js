/* =====================================
   VORTEX WMS SIDEBAR V2
===================================== */

document.addEventListener("DOMContentLoaded", function () {

    /* ===============================
       DROPDOWN
    =============================== */

    const dropdowns = document.querySelectorAll(".dropdown");

    dropdowns.forEach(dropdown => {

        const button = dropdown.querySelector(".dropdown-btn");

        button.addEventListener("click", function (e) {

            e.preventDefault();

            // Close all others
            dropdowns.forEach(item => {

                if (item !== dropdown) {

                    item.classList.remove("open");

                }

            });

            dropdown.classList.toggle("open");

        });

    });

    /* ===============================
       ACTIVE MENU
    =============================== */

    const current = window.location.pathname;

    document.querySelectorAll(".dropdown-menu a").forEach(link => {

        if (current.includes(link.getAttribute("href"))) {

            link.classList.add("active");

            const parent = link.closest(".dropdown");

            if (parent) {

                parent.classList.add("open");

            }

        }

    });

});

/* ===============================
   MOBILE SIDEBAR
=============================== */

function openSidebar() {

    document.querySelector(".sidebar").classList.add("show");

}

function closeSidebar() {

    document.querySelector(".sidebar").classList.remove("show");

}

/* ===============================
   CLICK OUTSIDE
=============================== */

document.addEventListener("click", function (e) {

    const sidebar = document.querySelector(".sidebar");

    const toggle = document.querySelector(".menu-toggle");

    if (window.innerWidth <= 992) {

        if (
            sidebar &&
            !sidebar.contains(e.target) &&
            toggle &&
            !toggle.contains(e.target)
        ) {

            sidebar.classList.remove("show");

        }

    }

});

/* ===============================
   ESC KEY
=============================== */

document.addEventListener("keydown", function (e) {

    if (e.key === "Escape") {

        closeSidebar();

    }

});