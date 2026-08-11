/* ===================================================================
   CD 133 PRODUCTION — KELOLA PESANAN JS
   Filter, search, konfirmasi aksi, dan UX enhancement.
=================================================================== */

document.addEventListener('DOMContentLoaded', () => {
    initSearchFilter();
    initStatusFilter();
    initActionConfirm();
    initTableSort();
});

/* ---------- SEARCH FILTER ---------- */
function initSearchFilter() {
    const searchInput = document.getElementById('searchInput');
    if (!searchInput) return;

    const table = document.querySelector('.pesanan-table tbody');
    if (!table) return;

    searchInput.addEventListener('input', (e) => {
        const query = e.target.value.toLowerCase().trim();
        const rows = table.querySelectorAll('tr');

        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            const match = text.includes(query);
            row.style.display = match ? '' : 'none';
        });

        // Update counter jika ada
        updateVisibleCount();
    });
}

/* ---------- STATUS FILTER ---------- */
function initStatusFilter() {
    const statusSelect = document.getElementById('statusFilter');
    if (!statusSelect) return;

    const table = document.querySelector('.pesanan-table tbody');
    if (!table) return;

    statusSelect.addEventListener('change', (e) => {
        const selectedStatus = e.target.value;
        const rows = table.querySelectorAll('tr');

        rows.forEach(row => {
            const statusBadge = row.querySelector('.status-badge');
            if (!statusBadge) return;

            const statusText = statusBadge.textContent.toLowerCase();
            const match = selectedStatus === 'all' || statusText.includes(selectedStatus);
            row.style.display = match ? '' : 'none';
        });

        updateVisibleCount();
    });
}

/* ---------- UPDATE VISIBLE COUNT ---------- */
function updateVisibleCount() {
    const counter = document.getElementById('visibleCount');
    if (!counter) return;

    const rows = document.querySelectorAll('.pesanan-table tbody tr');
    let visible = 0;

    rows.forEach(row => {
        if (row.style.display !== 'none') visible++;
    });

    counter.textContent = `${visible} pesanan`;
}

/* ---------- ACTION CONFIRM ---------- */
function initActionConfirm() {
    // Konfirmasi untuk aksi kritis (contoh: hapus, batal)
    document.querySelectorAll('[data-confirm]').forEach(btn => {
        btn.addEventListener('click', (e) => {
            const message = btn.getAttribute('data-confirm') || 'Apakah Anda yakin?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

/* ---------- TABLE SORT (opsional) ---------- */
function initTableSort() {
    const headers = document.querySelectorAll('.pesanan-table th[data-sort]');
    
    headers.forEach(header => {
        header.style.cursor = 'pointer';
        header.addEventListener('click', () => {
            const column = header.getAttribute('data-sort');
            sortTable(column, header);
        });
    });
}

function sortTable(column, header) {
    const table = document.querySelector('.pesanan-table table');
    const tbody = table.querySelector('tbody');
    const rows = Array.from(tbody.querySelectorAll('tr'));

    // Toggle sort direction
    const currentDir = header.getAttribute('data-dir') || 'asc';
    const newDir = currentDir === 'asc' ? 'desc' : 'asc';

    // Reset semua header
    document.querySelectorAll('.pesanan-table th[data-sort]').forEach(th => {
        th.removeAttribute('data-dir');
    });
    header.setAttribute('data-dir', newDir);

    // Sort rows
    rows.sort((a, b) => {
        const cellA = a.querySelector(`[data-column="${column}"]`);
        const cellB = b.querySelector(`[data-column="${column}"]`);

        if (!cellA || !cellB) return 0;

        let valA = cellA.textContent.trim();
        let valB = cellB.textContent.trim();

        // Coba parse sebagai angka (untuk kolom total)
        const numA = parseFloat(valA.replace(/[^\d.-]/g, ''));
        const numB = parseFloat(valB.replace(/[^\d.-]/g, ''));

        if (!isNaN(numA) && !isNaN(numB)) {
            return newDir === 'asc' ? numA - numB : numB - numA;
        }

        // Sort sebagai string
        return newDir === 'asc' 
            ? valA.localeCompare(valB, 'id')
            : valB.localeCompare(valA, 'id');
    });

    // Re-append rows
    rows.forEach(row => tbody.appendChild(row));
}

/* ---------- HELPER: Format Rupiah ---------- */
function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        minimumFractionDigits: 0
    }).format(angka);
}

/* ---------- HELPER: Format Tanggal ---------- */
function formatTanggal(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('id-ID', {
        day: '2-digit',
        month: 'long',
        year: 'numeric'
    });
}

/* ---------- EXPORT (jika perlu dipakai di halaman lain) ---------- */
window.CD133 = window.CD133 || {};
window.CD133.formatRupiah = formatRupiah;
window.CD133.formatTanggal = formatTanggal;