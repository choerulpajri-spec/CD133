/* ===================================================================
   CD 133 PRODUCTION — KELOLA PRODUK JS
   Search, filter, preview gambar, validasi form, modal konfirmasi.
=================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    initSearchFilter();
    initCategoryFilter();
    initImageUpload();
    initFormValidation();
    initDeleteModal();
    initPriceFormat();
});

/* ---------- SEARCH FILTER ---------- */
function initSearchFilter() {
    const input = document.getElementById('searchInput');
    if (!input) return;

    const tbody = document.querySelector('.produk-table tbody');
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

/* ---------- CATEGORY FILTER ---------- */
function initCategoryFilter() {
    const select = document.getElementById('categoryFilter');
    if (!select) return;

    const tbody = document.querySelector('.produk-table tbody');
    if (!tbody) return;

    select.addEventListener('change', () => {
        const cat = select.value;
        let visible = 0;

        tbody.querySelectorAll('tr').forEach(row => {
            const rowCat = row.getAttribute('data-category') || '';
            const match = cat === 'all' || rowCat === cat;
            row.style.display = match ? '' : 'none';
            if (match) visible++;
        });

        updateCount(visible);
    });
}

function updateCount(n) {
    const el = document.getElementById('visibleCount');
    if (el) el.textContent = `${n} produk`;
}

/* ---------- IMAGE UPLOAD + PREVIEW ---------- */
function initImageUpload() {
    const input = document.getElementById('gambarInput');
    const preview = document.getElementById('gambarPreview');
    const placeholder = document.getElementById('gambarPlaceholder');
    const dropzone = document.getElementById('gambarDropzone');
    const removeBtn = document.getElementById('gambarRemove');

    if (!input || !dropzone) return;

    // Klik dropzone → buka file dialog
    dropzone.addEventListener('click', () => input.click());

    // Drag & drop
    ['dragenter', 'dragover'].forEach(ev => {
        dropzone.addEventListener(ev, (e) => {
            e.preventDefault();
            dropzone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach(ev => {
        dropzone.addEventListener(ev, (e) => {
            e.preventDefault();
            dropzone.classList.remove('is-dragover');
        });
    });

    dropzone.addEventListener('drop', (e) => {
        const files = e.dataTransfer.files;
        if (files.length) {
            input.files = files;
            handleFile(files[0]);
        }
    });

    input.addEventListener('change', () => {
        if (input.files.length) handleFile(input.files[0]);
    });

    if (removeBtn) {
        removeBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            input.value = '';
            if (preview) {
                preview.src = '';
                preview.classList.remove('is-visible');
            }
            if (placeholder) placeholder.style.display = 'flex';
            removeBtn.classList.remove('is-visible');
        });
    }

    function handleFile(file) {
        // Validasi tipe
        if (!file.type.startsWith('image/')) {
            showToast('File harus berupa gambar.', 'danger');
            input.value = '';
            return;
        }
        // Validasi ukuran (max 2MB)
        if (file.size > 2 * 1024 * 1024) {
            showToast('Ukuran gambar maksimal 2MB.', 'danger');
            input.value = '';
            return;
        }

        const reader = new FileReader();
        reader.onload = (e) => {
            if (preview) {
                preview.src = e.target.result;
                preview.classList.add('is-visible');
            }
            if (placeholder) placeholder.style.display = 'none';
            if (removeBtn) removeBtn.classList.add('is-visible');
        };
        reader.readAsDataURL(file);
    }
}

/* ---------- FORM VALIDATION ---------- */
function initFormValidation() {
    const form = document.getElementById('produkForm');
    if (!form) return;

    form.addEventListener('submit', (e) => {
        let valid = true;

        // Required fields
        const required = form.querySelectorAll('[required]');
        required.forEach(field => {
            const err = field.parentElement.querySelector('.form-error');
            if (!field.value.trim()) {
                field.classList.add('form-control--error');
                if (err) err.classList.add('is-visible');
                valid = false;
            } else {
                field.classList.remove('form-control--error');
                if (err) err.classList.remove('is-visible');
            }
        });

        // Harga & stok harus positif
        const harga = form.querySelector('[name="harga"]');
        if (harga && parseInt(harga.value) <= 0) {
            harga.classList.add('form-control--error');
            const err = harga.parentElement.querySelector('.form-error');
            if (err) {
                err.textContent = 'Harga harus lebih dari 0.';
                err.classList.add('is-visible');
            }
            valid = false;
        }

        const stok = form.querySelector('[name="stok"]');
        if (stok && parseInt(stok.value) < 0) {
            stok.classList.add('form-control--error');
            const err = stok.parentElement.querySelector('.form-error');
            if (err) {
                err.textContent = 'Stok tidak boleh negatif.';
                err.classList.add('is-visible');
            }
            valid = false;
        }

        if (!valid) {
            e.preventDefault();
            showToast('Lengkapi semua field yang wajib diisi.', 'warn');
        }
    });

    // Clear error saat user mengetik
    form.querySelectorAll('.form-control').forEach(field => {
        field.addEventListener('input', () => {
            field.classList.remove('form-control--error');
            const err = field.parentElement.querySelector('.form-error');
            if (err) err.classList.remove('is-visible');
        });
    });
}

/* ---------- DELETE MODAL (pengganti confirm browser) ---------- */
function initDeleteModal() {
    const modal = document.getElementById('deleteModal');
    if (!modal) return;

    const confirmBtn = modal.querySelector('[data-confirm-delete]');
    const cancelBtn = modal.querySelector('[data-cancel-delete]');
    const message = modal.querySelector('.modal__message');
    let deleteUrl = '';

    document.querySelectorAll('[data-delete-url]').forEach(link => {
        link.addEventListener('click', (e) => {
            e.preventDefault();
            deleteUrl = link.getAttribute('data-delete-url');
            const productName = link.getAttribute('data-product-name') || 'produk ini';
            if (message) {
                message.innerHTML = `Produk <strong>"${productName}"</strong> akan dihapus permanen. Tindakan ini tidak dapat dibatalkan.`;
            }
            modal.classList.add('is-visible');
        });
    });

    if (cancelBtn) {
        cancelBtn.addEventListener('click', () => {
            modal.classList.remove('is-visible');
            deleteUrl = '';
        });
    }

    if (confirmBtn) {
        confirmBtn.addEventListener('click', () => {
            if (deleteUrl) window.location.href = deleteUrl;
        });
    }

    // Tutup saat klik overlay
    modal.addEventListener('click', (e) => {
        if (e.target === modal) modal.classList.remove('is-visible');
    });

    // Tutup dengan ESC
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.classList.contains('is-visible')) {
            modal.classList.remove('is-visible');
        }
    });
}

/* ---------- FORMAT HARGA OTOMATIS ---------- */
function initPriceFormat() {
    const hargaInput = document.querySelector('input[name="harga"]');
    if (!hargaInput) return;

    // Saat blur, format jadi rupiah display di samping (opsional)
    hargaInput.addEventListener('blur', () => {
        const val = parseInt(hargaInput.value);
        if (!isNaN(val) && val > 0) {
            // Simpan angka asli, tampilkan format di helper
            let helper = hargaInput.parentElement.querySelector('.price-helper');
            if (!helper) {
                helper = document.createElement('span');
                helper.className = 'form-label__hint price-helper';
                helper.style.color = 'var(--denim)';
                helper.style.fontWeight = '600';
                hargaInput.parentElement.appendChild(helper);
            }
            helper.textContent = '≈ Rp' + val.toLocaleString('id-ID');
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