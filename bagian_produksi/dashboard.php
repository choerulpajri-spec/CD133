<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

/* Hitung ringkasan untuk card */
$jml_aktif  = $koneksi->query("SELECT COUNT(*) FROM pesanan WHERE status = 'Produksi'")->fetchColumn();
$jml_masuk  = $koneksi->query("SELECT COUNT(*) FROM pesanan WHERE status = 'Menunggu Verifikasi' OR status = 'Dikonfirmasi'")->fetchColumn();
$jml_desain = $koneksi->query("SELECT COUNT(*) FROM pesanan WHERE file_desain IS NOT NULL AND file_desain != '' AND status NOT IN ('Selesai','Diserahkan')")->fetchColumn();
$stok_rendah = $koneksi->query("SELECT COUNT(*) FROM produk WHERE stok <= 20 AND stok > 0")->fetchColumn();
$stok_habis  = $koneksi->query("SELECT COUNT(*) FROM produk WHERE stok = 0")->fetchColumn();

$nama = $_SESSION['nama'] ?? 'Bagian Produksi';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Dashboard Produksi — CD 133 Production</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/admin_index.css">
<style>
.menu-grid { display:grid; grid-template-columns:repeat(2,1fr); gap:18px; margin-top:28px; }
.menu-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
             padding:24px; box-shadow:var(--shadow); text-decoration:none; color:var(--ink);
             display:flex; flex-direction:column; gap:10px; transition:transform .15s,border-color .15s; }
.menu-card:hover { transform:translateY(-2px); border-color:var(--thread); }
.menu-card__icon { font-size:28px; }
.menu-card__title { font-family:var(--font-display); font-size:16px; font-weight:600; }
.menu-card__desc { font-size:13px; color:var(--ink-soft); }
.menu-card__badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; background:var(--thread-soft); color:#8A4E14; width:fit-content; }
.menu-card__badge--warn { background:var(--warn-soft); color:var(--warn); }
.menu-card__badge--ok { background:var(--done-soft); color:var(--done); }
.topbar__greeting h1 { font-size:20px; }
@media (max-width:600px) { .menu-grid { grid-template-columns:1fr; } }
</style>
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__brand">
            <div class="sidebar__mark">CD</div>
            <div class="sidebar__brand-text">
                <strong>CD 133 Production</strong>
                <span>Bagian Produksi</span>
            </div>
        </div>
        <nav class="sidebar__nav">
            <a href="dashboard.php" class="sidebar__link is-active"><span class="sidebar__icon">▦</span> Dashboard</a>
            <a href="pesanan.php" class="sidebar__link"><span class="sidebar__icon">▤</span> Data Pesanan</a>
            <a href="desain.php" class="sidebar__link"><span class="sidebar__icon">🎨</span> Desain Customer</a>
            <a href="stok.php" class="sidebar__link"><span class="sidebar__icon">📦</span> Update Stok</a>
            <a href="tracking.php" class="sidebar__link"><span class="sidebar__icon">↻</span> Update Pengerjaan</a>
        </nav>
        <a href="../logout.php" class="sidebar__logout"><span class="sidebar__icon">⎋</span> Logout</a>
    </aside>
    <div class="sidebar__overlay" id="sidebarOverlay"></div>

    <main class="main">
        <div class="topbar">
            <button class="topbar__menu-btn" id="sidebarToggle"><span></span><span></span><span></span></button>
            <div class="topbar__greeting">
                <p class="topbar__eyebrow">Bagian Produksi</p>
                <h1>Selamat datang, <?= htmlspecialchars($nama) ?></h1>
            </div>
        </div>

        <div class="menu-grid">
            <a href="pesanan.php" class="menu-card">
                <div class="menu-card__icon">▤</div>
                <div class="menu-card__title">Data Pesanan</div>
                <div class="menu-card__desc">Lihat detail semua pesanan aktif yang perlu diproduksi.</div>
                <span class="menu-card__badge <?= $jml_aktif > 0 ? 'menu-card__badge--warn' : 'menu-card__badge--ok' ?>">
                    <?= $jml_aktif ?> pesanan sedang produksi
                </span>
            </a>

            <a href="desain.php" class="menu-card">
                <div class="menu-card__icon">🎨</div>
                <div class="menu-card__title">Desain Customer</div>
                <div class="menu-card__desc">Buka dan unduh file desain yang diunggah customer sebagai acuan pengerjaan.</div>
                <span class="menu-card__badge">
                    <?= $jml_desain ?> pesanan punya file desain
                </span>
            </a>

            <a href="stok.php" class="menu-card">
                <div class="menu-card__icon">📦</div>
                <div class="menu-card__title">Update Stok</div>
                <div class="menu-card__desc">Tambah kapasitas stok produk setelah bahan baku tersedia.</div>
                <?php if ($stok_habis > 0): ?>
                    <span class="menu-card__badge menu-card__badge--warn">⚠ <?= $stok_habis ?> produk stok habis</span>
                <?php elseif ($stok_rendah > 0): ?>
                    <span class="menu-card__badge menu-card__badge--warn"><?= $stok_rendah ?> produk stok rendah</span>
                <?php else: ?>
                    <span class="menu-card__badge menu-card__badge--ok">Semua stok aman</span>
                <?php endif; ?>
            </a>

            <a href="tracking.php" class="menu-card">
                <div class="menu-card__icon">↻</div>
                <div class="menu-card__title">Update Pengerjaan</div>
                <div class="menu-card__desc">Perbarui status pesanan sesuai progres produksi agar customer bisa memantau.</div>
                <span class="menu-card__badge">
                    <?= $jml_aktif ?> pesanan perlu diupdate
                </span>
            </a>
        </div>
    </main>
</div>
<script src="../assets/js/admin_index.js"></script>
</body>
</html>