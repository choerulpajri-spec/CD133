<?php

$db_host = getenv('MYSQLHOST') ?: 'acela.proxy.rlwy.net';
$db_port = getenv('MYSQLPORT') ?: '18853';
$db_name = getenv('MYSQLDATABASE') ?: 'railway';
$db_user = getenv('MYSQLUSER') ?: 'root';
$db_pass = getenv('MYSQLPASSWORD') ?: 'DqfuJUNLnkDhATDyNZgUAiWvNfXXUtIw';

try {
    $koneksi = new PDO(
        "mysql:host={$db_host};port={$db_port};dbname={$db_name};charset=utf8mb4",
        $db_user,
        $db_pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
} catch (PDOException $e) {
    die('Koneksi database gagal: ' . $e->getMessage());
}
