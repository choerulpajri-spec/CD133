<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

/* Ambil pesanan yang masih aktif (belum selesai/diserahkan).
   CATATAN PERBAIKAN: sebelumnya query ini LEFT JOIN ke tabel `pembayaran`
   (status = 'Diterima'). Karena 1 pesanan bisa punya LEBIH DARI SATU
   pembayaran diterima (DP + pelunasan), JOIN itu menggandakan baris
   pesanan (1 pesanan -> tampil 2x). Kolom hasil JOIN itu (total_pembayaran,
   status_bayar) juga tidak dipakai di tabel manapun di halaman ini, jadi
   JOIN-nya dihapus sepenuhnya -> 1 pesanan = 1 baris, seberapa pun jumlah
   pembayarannya. */
$pesanan = $koneksi->query("
    SELECT p.*
    FROM pesanan p
    WHERE p.status NOT IN ('Selesai','Diserahkan','Dibatalkan')
    ORDER BY p.dibuat_pada DESC
")->fetchAll();

function tglIndo(string $t): string {
    $b = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
          7=>'Jul',8=>'Agt',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
    $ts = strtotime($t);
    return date('d',$ts).' '.$b[(int)date('n',$ts)].' '.date('Y',$ts);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Data Pesanan — Produksi CD 133</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/admin_index.css">
<style>
.badge { display:inline-block; padding:3px 10px; border-radius:999px; font-size:12px; font-weight:600; }
.badge--produksi { background:var(--thread-soft); color:#8A4E14; }
.badge--konfirmasi { background:var(--denim-soft); color:var(--denim); }
.badge--menunggu { background:var(--warn-soft); color:var(--warn); }
.empty-row td { text-align:center; padding:40px; color:var(--ink-soft); }
</style>
</head>
<body>
<div class="app">
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__brand">
            <div class="sidebar__mark">CD</div>
            <div class="sidebar__brand-text"><strong>CD 133 Production</strong><span>Bagian Produksi</span></div>
        </div>
        <nav class="sidebar__nav">
            <a href="dashboard.php" class="sidebar__link"><span class="sidebar__icon">▦</span> Dashboard</a>
            <a href="pesanan.php" class="sidebar__link is-active"><span class="sidebar__icon">▤</span> Data Pesanan</a>
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
                <h1>Data Pesanan Aktif</h1>
            </div>
        </div>

        <section class="panel">
            <div class="panel__head">
                <h2>Pesanan yang Perlu Diproduksi</h2>
                <span class="panel__hint"><?= count($pesanan) ?> pesanan aktif</span>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No</th>
                            <th>Kode Pesanan</th>
                            <th>Tanggal</th>
                            <th>Pemesan</th>
                            <th>Produk</th>
                            <th>Jumlah</th>
                            <th>Catatan Desain</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (empty($pesanan)): ?>
                        <tr class="empty-row"><td colspan="9">Tidak ada pesanan aktif saat ini.</td></tr>
                    <?php else: ?>
                    <?php foreach ($pesanan as $i => $p): ?>
                        <tr>
                            <td><?= $i+1 ?></td>
                            <td style="font-family:monospace;font-weight:600;font-size:12px;"><?= htmlspecialchars($p['kode_pesanan']) ?></td>
                            <td><?= tglIndo($p['dibuat_pada']) ?></td>
                            <td>
                                <?= htmlspecialchars($p['nama_pemesan']) ?>
                                <div style="font-size:11.5px;color:var(--ink-soft)"><?= htmlspecialchars($p['no_hp']) ?></div>
                            </td>
                            <td><?= htmlspecialchars(ucwords(str_replace('_',' ',$p['jenis_produk']))) ?></td>
                            <td><strong><?= number_format($p['jumlah']) ?> pcs</strong></td>
                            <td style="max-width:180px;font-size:12.5px;">
                                <?= $p['catatan'] ? htmlspecialchars($p['catatan']) : '<span style="color:var(--ink-soft)">-</span>' ?>
                            </td>
                            <td>
                                <?php
                                $statusClass = match(strtolower($p['status'])) {
                                    'produksi' => 'badge--produksi',
                                    'dikonfirmasi','konfirmasi' => 'badge--konfirmasi',
                                    default => 'badge--menunggu'
                                };
                                ?>
                                <span class="badge <?= $statusClass ?>"><?= htmlspecialchars($p['status']) ?></span>
                            </td>
                            <td>
                                <?php if (!empty($p['file_desain'])): ?>
                                    <a href="desain.php?id=<?= $p['id_pesanan'] ?>"
                                       style="font-size:12.5px;font-weight:600;color:var(--denim-light);">
                                        🎨 Lihat Desain
                                    </a>
                                <?php else: ?>
                                    <span style="font-size:12px;color:var(--ink-soft)">Tidak ada desain</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>
</div>
<script src="../assets/js/admin_index.js"></script>
</body>
</html>