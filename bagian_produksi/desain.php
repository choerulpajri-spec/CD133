<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

/* Kalau ada ?id= tampilkan detail satu pesanan */
$id = (int)($_GET['id'] ?? 0);
$pesanan_detail = null;

if ($id > 0) {
    $stmt = $koneksi->prepare("SELECT * FROM pesanan WHERE id_pesanan = ? AND file_desain IS NOT NULL AND file_desain != ''");
    $stmt->execute([$id]);
    $pesanan_detail = $stmt->fetch();
}

/* Daftar semua pesanan yang punya file desain & belum selesai */
$list = $koneksi->query("
    SELECT * FROM pesanan
    WHERE file_desain IS NOT NULL AND file_desain != ''
    AND status NOT IN ('Selesai','Diserahkan','Dibatalkan')
    ORDER BY dibuat_pada DESC
")->fetchAll();

/* FIX: sebelumnya '../../uploads/desain/' (kelebihan 1 level ../)
   sehingga path salah dan gambar tidak tampil. File ini berada
   1 level di bawah root (sama seperti pesanan.php), jadi cukup 1x ../ */
$base_path = '../uploads/desain/';
?>
<!DOCTYPE html>
<html lang="id">
<head>
<link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Desain Customer — Produksi CD 133</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/admin_index.css">
<style>
.desain-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(260px,1fr)); gap:16px; }
.desain-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
               overflow:hidden; box-shadow:var(--shadow); text-decoration:none; color:var(--ink); display:block; }
.desain-card:hover { border-color:var(--thread); }
.desain-card__img { width:100%; height:180px; object-fit:cover; background:var(--denim-soft);
                    display:flex; align-items:center; justify-content:center; font-size:40px; }
.desain-card__img img { width:100%; height:100%; object-fit:cover; }
.desain-card__body { padding:14px 16px; }
.desain-card__kode { font-family:monospace; font-size:12px; font-weight:700; color:var(--denim); }
.desain-card__produk { font-weight:600; margin:4px 0 6px; }
.desain-card__pemesan { font-size:12.5px; color:var(--ink-soft); }
.desain-card__actions { display:flex; gap:8px; padding:10px 16px; border-top:1px dashed var(--border); }
.btn-view { flex:1; text-align:center; padding:8px; border-radius:var(--radius-sm);
            background:var(--denim-soft); color:var(--denim); font-size:13px; font-weight:600; text-decoration:none; }
.btn-download { flex:1; text-align:center; padding:8px; border-radius:var(--radius-sm);
                background:var(--thread); color:#2A1604; font-size:13px; font-weight:600; text-decoration:none; }

/* Modal preview */
.modal-overlay { display:none; position:fixed; inset:0; background:rgba(0,0,0,.7);
                 z-index:100; align-items:center; justify-content:center; }
.modal-overlay.is-visible { display:flex; }
.modal-img { max-width:90vw; max-height:88vh; border-radius:8px; object-fit:contain; }
.modal-close { position:absolute; top:20px; right:24px; font-size:28px; color:#fff; cursor:pointer; background:none; border:none; }
.modal-info { position:absolute; bottom:24px; left:50%; transform:translateX(-50%);
              background:rgba(0,0,0,.6); color:#fff; border-radius:8px; padding:10px 18px; font-size:13px; text-align:center; }

/* Detail view */
.detail-wrap { display:grid; grid-template-columns:1fr 340px; gap:22px; }
.detail-preview { border-radius:var(--radius); overflow:hidden; background:var(--denim-soft);
                  min-height:300px; display:flex; align-items:center; justify-content:center; }
.detail-preview img { width:100%; height:auto; display:block; }
.detail-preview__pdf { padding:40px; text-align:center; color:var(--denim); }
.detail-info { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
               padding:22px; box-shadow:var(--shadow); }
.detail-info__row { display:flex; flex-direction:column; gap:3px; margin-bottom:16px; }
.detail-info__label { font-size:11px; text-transform:uppercase; letter-spacing:.05em; color:var(--ink-soft); font-weight:600; }
.detail-info__val { font-size:14px; font-weight:500; }
@media (max-width:760px) { .detail-wrap { grid-template-columns:1fr; } }
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
            <a href="pesanan.php" class="sidebar__link"><span class="sidebar__icon">▤</span> Data Pesanan</a>
            <a href="desain.php" class="sidebar__link is-active"><span class="sidebar__icon">🎨</span> Desain Customer</a>
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
                <h1>Desain Customer</h1>
            </div>
            <?php if ($pesanan_detail): ?>
                <a href="desain.php" style="font-size:13px;font-weight:600;color:var(--denim-light);">← Semua Desain</a>
            <?php endif; ?>
        </div>

        <?php if ($pesanan_detail): ?>
        <!-- ===== DETAIL SATU PESANAN ===== -->
        <div class="detail-wrap">
            <div>
                <div class="detail-preview">
                    <?php
                    $file = $pesanan_detail['file_desain'];
                    $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                    $path = $base_path . $file;
                    if (in_array($ext, ['jpg','jpeg','png','webp','gif'])): ?>
                        <img src="<?= htmlspecialchars($path) ?>" alt="Desain <?= htmlspecialchars($pesanan_detail['kode_pesanan']) ?>">
                    <?php elseif ($ext === 'pdf'): ?>
                        <div class="detail-preview__pdf">
                            <div style="font-size:48px; margin-bottom:16px;">📄</div>
                            <p style="margin:0 0 14px;font-size:15px;font-weight:600;">File PDF</p>
                            <a href="<?= htmlspecialchars($path) ?>" target="_blank" class="btn-download" style="display:inline-block;text-decoration:none;">
                                Buka PDF ↗
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="detail-preview__pdf">
                            <div style="font-size:48px; margin-bottom:12px;">📎</div>
                            <p>Format tidak bisa dipreview</p>
                        </div>
                    <?php endif; ?>
                </div>

                <div style="margin-top:12px; display:flex; gap:10px;">
                    <?php if (in_array($ext, ['jpg','jpeg','png','webp','gif','pdf'])): ?>
                        <a href="<?= htmlspecialchars($path) ?>" download class="btn-download" style="padding:10px 18px;">
                            ⬇ Unduh File Desain
                        </a>
                        <?php if (in_array($ext, ['jpg','jpeg','png','webp','gif'])): ?>
                            <a href="<?= htmlspecialchars($path) ?>" target="_blank" class="btn-view" style="padding:10px 18px;">
                                ↗ Buka di Tab Baru
                            </a>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

            <div class="detail-info">
                <h3 style="margin:0 0 20px;font-family:var(--font-display);font-size:16px;">Informasi Pesanan</h3>
                <div class="detail-info__row">
                    <span class="detail-info__label">Kode Pesanan</span>
                    <span class="detail-info__val" style="font-family:monospace;color:var(--denim);"><?= htmlspecialchars($pesanan_detail['kode_pesanan']) ?></span>
                </div>
                <div class="detail-info__row">
                    <span class="detail-info__label">Pemesan</span>
                    <span class="detail-info__val"><?= htmlspecialchars($pesanan_detail['nama_pemesan']) ?></span>
                </div>
                <div class="detail-info__row">
                    <span class="detail-info__label">No. HP</span>
                    <span class="detail-info__val"><?= htmlspecialchars($pesanan_detail['no_hp']) ?></span>
                </div>
                <div class="detail-info__row">
                    <span class="detail-info__label">Jenis Produk</span>
                    <span class="detail-info__val"><?= htmlspecialchars(ucwords(str_replace('_',' ',$pesanan_detail['jenis_produk']))) ?></span>
                </div>
                <div class="detail-info__row">
                    <span class="detail-info__label">Jumlah</span>
                    <span class="detail-info__val"><?= number_format($pesanan_detail['jumlah']) ?> pcs</span>
                </div>
                <div class="detail-info__row">
                    <span class="detail-info__label">Catatan Desain</span>
                    <span class="detail-info__val" style="white-space:pre-wrap;">
                        <?= $pesanan_detail['catatan'] ? htmlspecialchars($pesanan_detail['catatan']) : '-' ?>
                    </span>
                </div>
                <div class="detail-info__row">
                    <span class="detail-info__label">Status Pesanan</span>
                    <span class="detail-info__val"><?= htmlspecialchars($pesanan_detail['status']) ?></span>
                </div>
                <div class="detail-info__row">
                    <span class="detail-info__label">Nama File</span>
                    <span class="detail-info__val" style="font-size:12px;word-break:break-all;"><?= htmlspecialchars($file) ?></span>
                </div>
                <a href="tracking.php?focus=<?= $pesanan_detail['id_pesanan'] ?>"
                   style="display:block; margin-top:8px; text-align:center; padding:10px; background:var(--denim); color:#fff; border-radius:var(--radius-sm); font-weight:600; font-size:13.5px; text-decoration:none;">
                    ↻ Update Status Pengerjaan
                </a>
            </div>
        </div>

        <?php else: ?>
        <!-- ===== GRID SEMUA DESAIN ===== -->
        <?php if (empty($list)): ?>
            <div style="text-align:center; padding:60px 0; color:var(--ink-soft);">
                <div style="font-size:48px; margin-bottom:12px;">🎨</div>
                <p style="font-size:15px; font-weight:600; margin:0 0 6px;">Belum ada file desain</p>
                <p style="font-size:13px; margin:0;">Desain customer akan muncul di sini setelah mereka mengunggahnya.</p>
            </div>
        <?php else: ?>
            <div style="margin-bottom:16px; font-size:13px; color:var(--ink-soft);">
                <?= count($list) ?> pesanan aktif dengan file desain
            </div>
            <div class="desain-grid">
            <?php foreach ($list as $p): ?>
                <?php
                $file = $p['file_desain'];
                $ext  = strtolower(pathinfo($file, PATHINFO_EXTENSION));
                $path = $base_path . $file;
                ?>
                <div class="desain-card">
                    <div class="desain-card__img">
                        <?php if (in_array($ext, ['jpg','jpeg','png','webp','gif'])): ?>
                            <img src="<?= htmlspecialchars($path) ?>" alt="">
                        <?php elseif ($ext === 'pdf'): ?>
                            <div style="text-align:center;color:var(--denim);padding:20px;">
                                <div style="font-size:42px;">📄</div>
                                <div style="font-size:12px;margin-top:8px;font-weight:600;">PDF</div>
                            </div>
                        <?php else: ?>
                            <div style="text-align:center;">📎</div>
                        <?php endif; ?>
                    </div>
                    <div class="desain-card__body">
                        <div class="desain-card__kode"><?= htmlspecialchars($p['kode_pesanan']) ?></div>
                        <div class="desain-card__produk"><?= htmlspecialchars(ucwords(str_replace('_',' ',$p['jenis_produk']))) ?> — <?= number_format($p['jumlah']) ?> pcs</div>
                        <div class="desain-card__pemesan"><?= htmlspecialchars($p['nama_pemesan']) ?></div>
                    </div>
                    <div class="desain-card__actions">
                        <a href="desain.php?id=<?= $p['id_pesanan'] ?>" class="btn-view">Detail</a>
                        <a href="<?= htmlspecialchars($path) ?>" download class="btn-download">Unduh</a>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <?php endif; ?>

    </main>
</div>
<script src="../assets/js/admin_index.js"></script>
</body>
</html>