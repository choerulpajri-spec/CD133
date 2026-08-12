<?php
$path = $_GET['__path'] ?? '';
$path = ltrim($path, '/');

// Whitelist ketat: cuma huruf, angka, underscore, slash, dan harus diakhiri .php
if (!preg_match('#^[a-zA-Z0-9_\/]+\.php$#', $path)) {
    http_response_code(404);
    exit('Not found');
}

$root   = realpath(__DIR__ . '/..');
$target = realpath($root . '/' . $path);

// Pastikan hasil resolusi path tetap di dalam folder project (anti path traversal)
if ($target === false || strpos($target, $root) !== 0 || !file_exists($target)) {
    http_response_code(404);
    exit('Not found');
}

require $target;
