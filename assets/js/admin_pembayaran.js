/* ===================================================================
   CD 133 PRODUCTION — VERIFIKASI PEMBAYARAN JS
   Search, filter, lightbox, modal terima/tolak, toast.
=================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    initSearchFilter();
    initStatusFilter();
    initLightbox();
    initTerimaModal();
    initTolakModal();
});

/* ---------- SEARCH FILTER ---------- */
function initSearchFilter() {
    const input = document.getElementById('searchInput');
    if (!input) return;

    const tbody = document.querySelector('.pembayaran-table tbody');
    if (!tbody) return;

    input.addEventListener('input', () => {
        const q = input.value.toLowerCase().trim();
        let visible = 0;

        tbody.querySelectorAll('tr').forEach(row => {
            const match = row.textContent.toLowerCase().includes(q);
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        updateCount(visible);
    });
}

/* ---------- STATUS FILTER ---------- */
function initStatusFilter() {
    const select = document.getElementById('statusFilter');
    if (!select) return;

    const tbody = document.querySelector('.pembayaran-table tbody');
    if (!tbody) return;

    select.addEventListener('change', () => {
        const status = select.value;
        let visible = 0;

        tbody.querySelectorAll('tr').forEach(row => {
            const rowStatus = row.getAttribute('data-status') || '';
            const match = status === 'all' || rowStatus === status;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        updateCount(visible);
    });
}

function updateCount(n) {
    const el = document.getElementById('visibleCount');
    if (el) el.textContent = `${n} pembayaran`;
}

/* ---------- LIGHTBOX (preview bukti transfer) ---------- */
function initLightbox() {
    const lightbox = document.getElementById('lightbox');
    if (!lightbox) return;

    const lightboxImg = lightbox.querySelector('img');
    const downloadBtn = lightbox.querySelector('.lightbox__download');
    const closeBtn = lightbox.querySelector('.lightbox__close');

    // Buka lightbox saat klik preview
    document.querySelectorAll('.bukti-preview').forEach(preview => {
        preview.addEventListener('click', () => {
            const img = preview.querySelector('img');
            if (!img) return;

            lightboxImg.src = img.src;
            lightboxImg.alt = img.alt || 'Bukti Pembayaran';
            if (downloadBtn) {
                downloadBtn.href = img.src;
                downloadBtn.download = img.alt || 'bukti-transfer.jpg';
            }
            lightbox.classList.add('is-visible');
            document.body.style.overflow = 'hidden';
        });
    });

    // Tutup lightbox
    const closeLightbox = () => {
        lightbox.classList.remove('is-visible');
        document.body.style.overflow = '';
    };

    if (closeBtn) closeBtn.addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', (e) => {
        if (e.target === lightbox) closeLightbox();
    });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && lightbox.classList.contains('is-visible')) {
            closeLightbox();
        }
    });
}

/* ---------- MODAL TERIMA ---------- */
function initTerimaModal() {
    const modal = document.getElementById('terimaModal');
    if (!modal) return;

    const confirmBtn = modal.querySelector('[data-confirm-terima]');
    const cancelBtn = modal.querySelector('[data-cancel-terima]');
    const message = modal.querySelector('.modal__message');
    let actionUrl = '';

    document.querySelectorAll('[data-terima-url]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            actionUrl = btn.getAttribute('data-terima-url');
            const customerName = btn.getAttribute('data-customer-name') || 'customer';
            const amount = btn.getAttribute('data-amount') || '';
            if (message) {
                message.innerHTML = `Pembayaran dari <strong>${customerName}</strong> sebesar <strong>${amount}</strong> akan diterima dan pesanan diproses lebih lanjut.`;
            }
            modal.classList.add('is-visible');
        });
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            modal.classList.remove('is-visible');
            actionUrl = '';
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            if (actionUrl) {
                showToast('Pembayaran berhasil diterima.', 'success');
                setTimeout(() => {
                    window.location.href = actionUrl;
                }, 800);
            }
        });
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('is-visible');
    });
}

/* ---------- MODAL TOLAK ---------- */
function initTolakModal() {
    const modal = document.getElementById('tolakModal');
    if (!modal) return;

    const confirmBtn = modal.querySelector('[data-confirm-tolak]');
    const cancelBtn = modal.querySelector('[data-cancel-tolak]');
    const textarea = modal.querySelector('#alasanTolak');
    const message = modal.querySelector('.modal__message');
    let actionUrl = '';

    document.querySelectorAll('[data-tolak-url]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            actionUrl = btn.getAttribute('data-tolak-url');
            const customerName = btn.getAttribute('data-customer-name') || 'customer';
            if (message) {
                message.innerHTML = `Pembayaran dari <strong>${customerName}</strong> akan ditolak. Sertakan alasan untuk pemberitahuan ke customer.`;
            }
            if (textarea) textarea.value = '';
            modal.classList.add('is-visible');
        });
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            modal.classList.remove('is-visible');
            actionUrl = '';
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            const alasan = textarea ? textarea.value.trim() : '';
            if (!alasan) {
                textarea.classList.add('form-control--error');
                showToast('Alasan penolakan wajib diisi.', 'warn');
                return;
            }
            if (actionUrl) {
                // Kirim alasan via form atau append ke URL
                const form = document.createElement('form');
                form.method = 'POST';
                form.action = actionUrl;
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'alasan';
                input.value = alasan;
                form.appendChild(input);
                document.body.appendChild(form);
                showToast('Pembayaran ditolak.', 'danger');
                setTimeout(() => form.submit(), 800);
            }
        });
    }

    // Clear error saat mengetik
    if (textarea) {
        textarea.addEventListener('input', () => {
            textarea.classList.remove('form-control--error');
        });
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('is-visible');
    });

    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('is-visible')) {
            modal.classList.remove('is-visible');
        }
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