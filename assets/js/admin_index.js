/* ===================================================================
   CD 133 PRODUCTION — DASHBOARD JS
   Sidebar toggle, clock, counter animation, toast, logout confirm.
=================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    initSidebar();
    initClock();
    initActiveMenu();
    initCounters();
    initLogoutConfirm();
    showWelcomeToast();
});

/* ---------- WELCOME TOAST (role-aware) ---------- */
function showWelcomeToast() {
    // role & nama dikirim dari PHP lewat data-attribute di <body>,
    // contoh: <body data-role="produksi" data-nama="Budi">
    const role = (document.body.dataset.role || 'admin').toLowerCase();
    const nama = document.body.dataset.nama || '';

    const labelRole = {
        admin:    'Admin',
        produksi: 'Produksi',
        owner:    'Owner',
    }[role] || 'Admin';

    const pesan = nama
        ? `Selamat datang, ${labelRole} (${nama}).`
        : `Selamat datang, ${labelRole}.`;

    showToast(pesan, 'success');
}

/* ---------- SIDEBAR TOGGLE (mobile) ---------- */
function initSidebar() {
    const btn     = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!btn || !sidebar) return;

    const open  = () => {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-visible');
    };
    const close = () => {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-visible');
    };

    btn.addEventListener('click', () => {
        sidebar.classList.contains('is-open') ? close() : open();
    });
    overlay.addEventListener('click', close);

    // Tutup sidebar saat klik link (mobile)
    sidebar.querySelectorAll('.sidebar__link').forEach(link => {
        link.addEventListener('click', () => {
            if (window.innerWidth <= 768) close();
        });
    });
}

/* ---------- CLOCK (Bahasa Indonesia) ---------- */
function initClock() {
    const el = document.getElementById('clock');
    if (!el) return;

    const update = () => {
        const now = new Date();
        const opts = {
            weekday: 'long',
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            hour12: false
        };
        el.textContent = now.toLocaleString('id-ID', opts);
    };
    update();
    setInterval(update, 1000);
}

/* ---------- ACTIVE MENU ---------- */
function initActiveMenu() {
    const path = window.location.pathname;
    const links = document.querySelectorAll('.sidebar__link');

    links.forEach(link => {
        const href = link.getAttribute('href') || '';
        if (path.endsWith(href) || (href === 'dashboard.php' && path.endsWith('dashboard.php'))) {
            link.classList.add('is-active');
        }
    });
}

/* ---------- COUNTER ANIMATION ---------- */
function initCounters() {
    const values = document.querySelectorAll('[data-count]');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                animateCount(entry.target);
                observer.unobserve(entry.target);
            }
        });
    }, { threshold: 0.3 });

    values.forEach(el => observer.observe(el));
}

function animateCount(el) {
    const target = parseInt(el.dataset.count, 10) || 0;
    const duration = 1200;
    const start = performance.now();

    const tick = (now) => {
        const progress = Math.min((now - start) / duration, 1);
        // easing out-quad
        const eased = 1 - (1 - progress) * (1 - progress);
        el.textContent = Math.floor(eased * target).toLocaleString('id-ID');
        if (progress < 1) requestAnimationFrame(tick);
        else el.textContent = target.toLocaleString('id-ID');
    };
    requestAnimationFrame(tick);
}

/* ---------- LOGOUT CONFIRM ---------- */
function initLogoutConfirm() {
    document.querySelectorAll('a[href*="logout"]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            const href = link.getAttribute('href');
            if (confirm('Apakah Anda yakin ingin keluar dari sistem?')) {
                window.location.href = href;
            }
        });
    });
}

/* ---------- TOAST ---------- */
function showToast(message, type = '', duration = 3000) {
    const existing = document.querySelector('.toast');
    if (existing) existing.remove();

    const toast = document.createElement('div');
    toast.className = 'toast' + (type ? ` toast--${type}` : '');
    toast.textContent = message;
    document.body.appendChild(toast);

    requestAnimationFrame(() => toast.classList.add('is-visible'));

    setTimeout(() => {
        toast.classList.remove('is-visible');
        setTimeout(() => toast.remove(), 400);
    }, duration);
}

/* ---------- KEYBOARD SHORTCUTS ---------- */
document.addEventListener('keydown', (e) => {
    // Ctrl + M → toggle sidebar
    if (e.ctrlKey && e.key.toLowerCase() === 'm') {
        e.preventDefault();
        const sidebar = document.getElementById('sidebar');
        if (sidebar) sidebar.classList.toggle('is-open');
    }
    // Ctrl + L → logout
    if (e.ctrlKey && e.key.toLowerCase() === 'l') {
        e.preventDefault();
        if (confirm('Logout dari sistem?')) {
            window.location.href = '../logout.php';
        }
    }
});