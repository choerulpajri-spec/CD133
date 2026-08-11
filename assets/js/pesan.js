/**
 * CD 133 PRODUCTION — Form Pemesanan
 * Tampilkan field alamat & wilayah tujuan hanya kalau metode pengambilan = Diantar Kurir.
 */
document.addEventListener('DOMContentLoaded', function () {
    const radios      = document.querySelectorAll('input[name="metode_ambil"]');
    const alamatWrap  = document.getElementById('wrapAlamat');
    const wilayahWrap = document.getElementById('wrapWilayah');

    if (!radios.length || !alamatWrap) return;

    function syncAlamat() {
        const dipilih = document.querySelector('input[name="metode_ambil"]:checked');
        const perluAlamat = dipilih && dipilih.value === 'kurir';

        alamatWrap.style.display = perluAlamat ? 'block' : 'none';
        const textarea = alamatWrap.querySelector('textarea');
        if (textarea) textarea.required = perluAlamat;

        if (wilayahWrap) {
            wilayahWrap.style.display = perluAlamat ? 'block' : 'none';
            const select = wilayahWrap.querySelector('select');
            if (select) select.required = perluAlamat;
        }
    }

    radios.forEach(r => r.addEventListener('change', syncAlamat));
    syncAlamat();
});