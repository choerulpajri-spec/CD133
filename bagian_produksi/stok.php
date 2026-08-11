<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

$pesan = $_SESSION['pesan'] ?? '';
$pesan_type = $_SESSION['pesan_type'] ?? '';
unset($_SESSION['pesan'], $_SESSION['pesan_type']);

/* PROSES UPDATE STOK */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_produk = (int)($_POST['id_produk'] ?? 0);
    $jumlah    = (int)($_POST['jumlah'] ?? 0);
    $tipe      = $_POST['tipe'] ?? 'tambah'; // tambah / kurangi

    if ($id_produk > 0 && $jumlah > 0) {
        if ($tipe === 'tambah') {
            $stmt = $koneksi->prepare("UPDATE produk SET stok = stok + :jumlah WHERE id_produk = :id");
        } else {
            /* Pastikan stok tidak minus */
            $stmt = $koneksi->prepare("UPDATE produk SET stok = GREATEST(0, stok - :jumlah) WHERE id_produk = :id");
        }
        $stmt->execute([':jumlah' => $jumlah, ':id' => $id_produk]);

        /* Catat di tabel log_stok kalau ada, kalau tidak ada skip */
        try {
            $log = $koneksi->prepare("
                INSERT INTO log_stok (id_produk, tipe, jumlah, keterangan, dibuat_pada)
                VALUES (?, ?, ?, ?, NOW())
            ");
            $log->execute([$id_produk, $tipe, $jumlah, $_POST['keterangan'] ?? '']);
        } catch (Exception $e) { /* tabel log_stok opsional */ }

        $_SESSION['pesan'] = 'Stok berhasil ' . ($tipe === 'tambah' ? 'ditambahkan' : 'dikurangi') . '.';
        $_SESSION['pesan_type'] = 'ok';
        header('Location: stok.php');
        exit;
    }
}

/* Ambil semua produk */
$produk_list = $koneksi->query("
    SELECT * FROM produk ORDER BY nama_produk ASC
")->fetchAll();

/* Batas ambang stok rendah */
const BATAS_STOK_RENDAH = 20;

/*
 * Kumpulkan bahan baku yang perlu dibeli.
 * Kolom `jenis` pada tabel produk menyimpan nama bahan baku (mis. "cotton combed 24s"),
 * jadi kita kelompokkan produk yang stoknya rendah/habis berdasarkan kolom itu.
 */
$bahan_perlu_dibeli = [];
foreach ($produk_list as $p) {
    $stok_p = (int)$p['stok'];
    if ($stok_p <= BATAS_STOK_RENDAH) {
        $jenis_bahan = trim((string)($p['jenis'] ?? ''));
        if ($jenis_bahan === '') $jenis_bahan = 'Tidak diketahui';

        if (!isset($bahan_perlu_dibeli[$jenis_bahan])) {
            $bahan_perlu_dibeli[$jenis_bahan] = [
                'produk' => [],
                'habis'  => 0,
                'rendah' => 0,
            ];
        }
        $status = $stok_p === 0 ? 'habis' : 'rendah';
        $bahan_perlu_dibeli[$jenis_bahan]['produk'][] = [
            'nama'   => $p['nama_produk'],
            'stok'   => $stok_p,
            'status' => $status,
        ];
        $bahan_perlu_dibeli[$jenis_bahan][$status]++;
    }
}
/* Urutkan: bahan dengan produk paling kritis (habis) tampil duluan */
uasort($bahan_perlu_dibeli, fn($a, $b) => $b['habis'] <=> $a['habis']);
?>
<!DOCTYPE html>
<html lang="id">
<head>
<link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Stok — Produksi CD 133</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/admin_index.css">
<style>
.stok-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(280px,1fr)); gap:16px; }
.stok-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
             padding:18px; box-shadow:var(--shadow); }
.stok-card__nama { font-family:var(--font-display); font-size:15px; font-weight:600; margin-bottom:4px; }
.stok-card__kat  { font-size:12px; color:var(--ink-soft); margin-bottom:2px; }
.stok-card__bahan{ font-size:12px; color:var(--ink-soft); margin-bottom:14px; }
.stok-card__val  { font-size:28px; font-weight:700; font-family:var(--font-display); }
.stok-card__lbl  { font-size:12px; color:var(--ink-soft); margin-bottom:16px; }
.stok-card--ok   { border-top:3px solid var(--done); }
.stok-card--low  { border-top:3px solid var(--thread); }
.stok-card--empty{ border-top:3px solid var(--warn); }
.stok-form { display:flex; gap:8px; align-items:center; flex-wrap:wrap; }
.stok-input { width:80px; padding:8px 10px; border:1px solid var(--border); border-radius:var(--radius-sm);
              font-size:14px; font-family:var(--font-body); }
.stok-input:focus { border-color:var(--denim-light); outline:none; }
.stok-select { padding:8px 10px; border:1px solid var(--border); border-radius:var(--radius-sm);
               font-size:13px; font-family:var(--font-body); background:var(--surface); }
.btn-stok { padding:8px 16px; border:none; border-radius:var(--radius-sm); font-weight:600;
            font-size:13px; cursor:pointer; }
.btn-stok--tambah { background:var(--done); color:#fff; }
.btn-stok--tambah:hover { opacity:.88; }
.stok-ket { width:100%; padding:7px 10px; border:1px solid var(--border); border-radius:var(--radius-sm);
            font-size:13px; font-family:var(--font-body); }
.progress-bar { height:6px; background:var(--border); border-radius:3px; margin-bottom:16px; overflow:hidden; }
.progress-bar__fill { height:100%; border-radius:3px; }
.toast { position:fixed; top:20px; right:20px; z-index:999; padding:12px 20px; border-radius:var(--radius-sm);
         background:var(--done); color:#fff; font-weight:600; font-size:14px;
         opacity:0; transform:translateY(-10px); transition:.3s; }
.toast.is-visible { opacity:1; transform:translateY(0); }

/* Tag "perlu dibeli" pada kartu produk */
.tag-beli { display:inline-block; margin-top:8px; padding:3px 9px; border-radius:20px;
            font-size:11px; font-weight:600; }
.tag-beli--habis  { background:color-mix(in srgb, var(--warn) 15%, transparent); color:var(--warn); }
.tag-beli--rendah { background:color-mix(in srgb, var(--thread) 15%, transparent); color:var(--thread); }

/* Panel Bahan Baku Perlu Dibeli */
.bahan-alert { background:var(--surface); border:1px solid var(--border); border-left:4px solid var(--warn);
               border-radius:var(--radius); padding:18px 20px; margin-bottom:22px; box-shadow:var(--shadow); }
.bahan-alert__head { display:flex; align-items:center; gap:8px; margin-bottom:4px; }
.bahan-alert__head h2 { font-family:var(--font-display); font-size:16px; font-weight:700; margin:0; }
.bahan-alert__icon { font-size:18px; }
.bahan-alert__sub { font-size:13px; color:var(--ink-soft); margin:0 0 14px; }
.bahan-alert__grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(240px,1fr)); gap:12px; }
.bahan-item { background:var(--bg, #fafafa); border:1px solid var(--border); border-radius:var(--radius-sm);
              padding:12px 14px; }
.bahan-item__nama { font-weight:700; font-size:13px; margin-bottom:8px; display:flex; align-items:center; gap:6px; }
.bahan-item__nama::before { content:"🧵"; font-size:13px; }
.bahan-item__produk { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:6px; }
.bahan-item__produk li { display:flex; align-items:center; justify-content:space-between; gap:8px;
                          font-size:12.5px; }
.badge { padding:2px 8px; border-radius:20px; font-size:11px; font-weight:600; white-space:nowrap; }
.badge--habis  { background:var(--warn); color:#fff; }
.badge--rendah { background:var(--thread); color:#fff; }
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
            <a href="desain.php" class="sidebar__link"><span class="sidebar__icon">🎨</span> Desain Customer</a>
            <a href="stok.php" class="sidebar__link is-active"><span class="sidebar__icon">📦</span> Update Stok</a>
            <a href="tracking.php" class="sidebar__link"><span class="sidebar__icon">↻</span> Update Pengerjaan</a>
        </nav>
        <a href="../logout.php" class="sidebar__logout"><span class="sidebar__icon">⎋</span> Logout</a>
    </aside>
    <div class="sidebar__overlay" id="sidebarOverlay"></div>

    <main class="main">

        <?php if ($pesan): ?>
        <div class="toast is-visible" id="toast"><?= htmlspecialchars($pesan) ?></div>
        <script>setTimeout(()=>document.getElementById('toast').classList.remove('is-visible'),3000);</script>
        <?php endif; ?>

        <div class="topbar">
            <button class="topbar__menu-btn" id="sidebarToggle"><span></span><span></span><span></span></button>
            <div class="topbar__greeting">
                <p class="topbar__eyebrow">Bagian Produksi</p>
                <h1>Update Stok Produk</h1>
            </div>
        </div>

        <!-- Ringkasan -->
        <?php
        $stok_ok     = count(array_filter($produk_list, fn($p) => (int)$p['stok'] > BATAS_STOK_RENDAH));
        $stok_low    = count(array_filter($produk_list, fn($p) => (int)$p['stok'] > 0 && (int)$p['stok'] <= BATAS_STOK_RENDAH));
        $stok_habis  = count(array_filter($produk_list, fn($p) => (int)$p['stok'] === 0));
        ?>
        <div style="display:grid;grid-template-columns:repeat(3,1fr);gap:14px;margin-bottom:22px;">
            <div class="stok-card stok-card--ok">
                <div class="stok-card__val"><?= $stok_ok ?></div>
                <div class="stok-card__lbl">Produk Stok Aman</div>
            </div>
            <div class="stok-card stok-card--low">
                <div class="stok-card__val"><?= $stok_low ?></div>
                <div class="stok-card__lbl">Stok Rendah (≤<?= BATAS_STOK_RENDAH ?> pcs)</div>
            </div>
            <div class="stok-card stok-card--empty">
                <div class="stok-card__val"><?= $stok_habis ?></div>
                <div class="stok-card__lbl">Stok Habis</div>
            </div>
        </div>

        <!-- Peringatan Bahan Baku Perlu Dibeli -->
        <?php if (!empty($bahan_perlu_dibeli)): ?>
        <div class="bahan-alert">
            <div class="bahan-alert__head">
                <span class="bahan-alert__icon">⚠️</span>
                <h2>Bahan Baku Perlu Dibeli</h2>
            </div>
            <p class="bahan-alert__sub">
                Stok produk di bawah ini menipis atau habis — segera pesan bahan baku terkait ke supplier.
            </p>
            <div class="bahan-alert__grid">
                <?php foreach ($bahan_perlu_dibeli as $jenis => $data): ?>
                <div class="bahan-item">
                    <div class="bahan-item__nama"><?= htmlspecialchars($jenis) ?></div>
                    <ul class="bahan-item__produk">
                        <?php foreach ($data['produk'] as $dp): ?>
                        <li>
                            <span><?= htmlspecialchars($dp['nama']) ?></span>
                            <span class="badge badge--<?= $dp['status'] ?>">
                                <?= $dp['status'] === 'habis' ? 'Habis' : $dp['stok'] . ' pcs' ?>
                            </span>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if (empty($produk_list)): ?>
            <div style="text-align:center;padding:60px;color:var(--ink-soft);">
                <p>Belum ada produk terdaftar. Minta Admin untuk menambahkan produk terlebih dahulu.</p>
            </div>
        <?php else: ?>
        <div class="stok-grid">
        <?php foreach ($produk_list as $p):
            $stok = (int)$p['stok'];
            $cardClass = $stok === 0 ? 'stok-card--empty' : ($stok <= BATAS_STOK_RENDAH ? 'stok-card--low' : 'stok-card--ok');
            $valColor  = $stok === 0 ? 'color:var(--warn)' : ($stok <= BATAS_STOK_RENDAH ? 'color:var(--thread)' : 'color:var(--done)');
            $maxStok   = max($stok, 100);
            $fillPct   = min(100, round($stok / $maxStok * 100));
            $fillColor = $stok === 0 ? 'var(--warn)' : ($stok <= BATAS_STOK_RENDAH ? 'var(--thread)' : 'var(--done)');
            $perluBeli = $stok <= BATAS_STOK_RENDAH;
        ?>
        <div class="stok-card <?= $cardClass ?>">
            <div class="stok-card__nama"><?= htmlspecialchars($p['nama_produk']) ?></div>
            <div class="stok-card__kat"><?= htmlspecialchars($p['kategori'] ?? '-') ?></div>
            <div class="stok-card__bahan">Bahan: <?= htmlspecialchars($p['jenis'] ?? '-') ?></div>
            <div class="stok-card__val" style="<?= $valColor ?>"><?= number_format($stok) ?></div>
            <div class="stok-card__lbl">pcs kapasitas tersedia</div>
            <div class="progress-bar">
                <div class="progress-bar__fill" style="width:<?= $fillPct ?>%;background:<?= $fillColor ?>;"></div>
            </div>
            <?php if ($perluBeli): ?>
            <div class="tag-beli <?= $stok === 0 ? 'tag-beli--habis' : 'tag-beli--rendah' ?>">
                🛒 Bahan "<?= htmlspecialchars($p['jenis'] ?? '-') ?>" perlu dibeli
            </div>
            <?php endif; ?>
            <form method="post" action="stok.php" style="margin-top:12px;">
                <input type="hidden" name="id_produk" value="<?= $p['id_produk'] ?>">
                <div class="stok-form">
                    <select name="tipe" class="stok-select">
                        <option value="tambah">+ Tambah</option>
                        <option value="kurangi">- Kurangi</option>
                    </select>
                    <input type="number" name="jumlah" class="stok-input" min="1" placeholder="pcs" required>
                    <button type="submit" class="btn-stok btn-stok--tambah">Simpan</button>
                </div>
                <input type="text" name="keterangan" class="stok-ket" style="margin-top:8px;"
                       placeholder="Keterangan (opsional, contoh: bahan baru datang)">
            </form>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</div>
<script src="../assets/js/admin_index.js"></script>
</body>
</html>