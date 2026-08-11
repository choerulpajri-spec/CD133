/* ===================================================================
   CD 133 PRODUCTION — UPDATE STATUS JS
   Stepper interaktif, preview perubahan, konfirmasi simpan.
=================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    initSearchFilter();
    initStatusFilter();
    initStepper();
    initConfirmSave();
});

/* ---------- SEARCH & FILTER (list) ---------- */
function initSearchFilter() {
    const input = document.getElementById('searchInput');
    if (!input) return;
    const tbody = document.querySelector('.data-table tbody');
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

function initStatusFilter() {
    const select = document.getElementById('statusFilter');
    if (!select) return;
    const tbody = document.querySelector('.data-table tbody');
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
    if (el) el.textContent = `${n} pesanan`;
}

/* ---------- STEPPER INTERAKTIF ---------- */
function initStepper() {
    const stepper = document.getElementById('statusStepper');
    if (!stepper) return;

    const steps = stepper.querySelectorAll('.stepper__step');
    const hiddenInput = document.getElementById('statusBaru');
    const preview = document.getElementById('changePreview');
    const previewTo = preview?.querySelector('.change-preview__to');
    const warning = preview?.querySelector('.change-preview__warning');
    const currentStatus = stepper.getAttribute('data-current');

    steps.forEach(step => {
        step.addEventListener('click', () => {
            if (step.classList.contains('is-disabled')) return;

            const newStatus = step.getAttribute('data-status');
            if (!newStatus || newStatus === currentStatus) return;

            // Update visual
            steps.forEach(s => s.classList.remove('is-target'));
            step.classList.add('is-target');

            // Update hidden input
            if (hiddenInput) hiddenInput.value = newStatus;

            // Show preview
            if (preview && previewTo) {
                previewTo.textContent = newStatus;
                preview.classList.add('is-visible');

                // Warning jika mundur (misal dari Selesai ke Diproses)
                const order = ['Menunggu Verifikasi', 'Diproses', 'Selesai', 'Diambil'];
                const curIdx = order.indexOf(currentStatus);
                const newIdx = order.indexOf(newStatus);
                if (warning) {
                    if (newIdx < curIdx && curIdx !== -1 && newIdx !== -1) {
                        warning.textContent = `⚠ Status akan mundur dari "${currentStatus}" ke "${newStatus}". Pastikan perubahan ini disetujui.`;
                        warning.classList.add('is-visible');
                    } else {
                        warning.classList.remove('is-visible');
                    }
                }
            }
        });
    });
}

/* ---------- KONFIRMASI SIMPAN ---------- */
function initConfirmSave() {
    const form = document.getElementById('statusForm');
    const modal = document.getElementById('confirmModal');
    if (!form || !modal) return;

    const confirmBtn = modal.querySelector('[data-confirm-save]');
    const cancelBtn = modal.querySelector('[data-cancel-save]');
    const message = modal.querySelector('.modal__message');

    form.addEventListener('submit', (e) => {
        e.preventDefault();
        const newStatus = document.getElementById('statusBaru')?.value;
        if (!newStatus) {
            showToast('Pilih status baru terlebih dahulu.', 'warn');
            return;
        }
        if (message) {
            message.innerHTML = `Status pesanan akan diubah menjadi <strong>"${newStatus}"</strong>. Lanjutkan?`;
        }
        modal.classList.add('is-visible');
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => modal.classList.remove('is-visible'));
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            modal.classList.remove('is-visible');
            showToast('Status berhasil diperbarui.', 'success');
            setTimeout(() => form.submit(), 600);
        });
    }

    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('is-visible');
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