<?php
// logout.php (letakkan di root project, sejajar dengan folder admin/, config/, dll)
session_start();

// Kosongkan semua data session
$_SESSION = [];

// Hapus cookie session (kalau pakai cookie session)
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

// Hancurkan session sepenuhnya
session_destroy();

// Arahkan kembali ke halaman login admin
header('Location: login.php');
exit;