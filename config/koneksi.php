<?php

$db_host = 'localhost';
$db_name = 'cd133_production'; // ganti sesuai nama database yang kamu buat
$db_user = 'root';
$db_pass = '';

try {
    $koneksi = new PDO(
        "mysql:host={$db_host};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Saat development boleh ditampilkan, nanti sebelum upload ke hosting
    // ganti jadi pesan generik supaya detail database tidak terlihat publik.
    die('Koneksi database gagal: ' . $e->getMessage());
}