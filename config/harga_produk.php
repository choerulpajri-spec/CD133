<?php
/**
 * Harga Flat per Kategori Produk - CD 133 Production
 *
 * Satu sumber harga dipakai bersama oleh pesan.php (hitung total_tagihan otomatis)
 * dan halaman admin (kalau nanti mau ditampilkan/diedit).
 *
 * Kalau ada kategori dengan harga `null`, artinya harga custom / butuh quote manual
 * dari admin (misal kategori "lainnya"), jadi total_tagihan pesanan itu akan
 * disimpan NULL dan admin harus set manual lewat database untuk sementara.
 */

return [
    'kaos'          => 65000,
    'kaos_polo'     => 85000,
    'jaket_hoodie'  => 150000,
    'kemeja_kerja'  => 120000,
    'seragam'       => 110000,
    'lainnya'       => null, // custom, perlu quote manual dari admin
];