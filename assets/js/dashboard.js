/**
 * CD 133 PRODUCTION — Dashboard Customer
 * Interaksi: buka/tutup sidebar di tampilan mobile.
 */
document.addEventListener('DOMContentLoaded', function () {
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    const menuBtn = document.getElementById('menuBtn');

    if (!sidebar || !overlay || !menuBtn) return;

    function openSidebar() {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-visible');
    }

    function closeSidebar() {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-visible');
    }

    menuBtn.addEventListener('click', openSidebar);
    overlay.addEventListener('click', closeSidebar);

    // Tutup otomatis kalau ukuran layar dibesarkan ke tampilan desktop
    window.addEventListener('resize', function () {
        if (window.innerWidth > 768) closeSidebar();
    });
});