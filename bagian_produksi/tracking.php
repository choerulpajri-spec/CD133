<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

$pesan = $_SESSION['pesan'] ?? '';
$pesan_type = $_SESSION['pesan_type'] ?? '';
unset($_SESSION['pesan'], $_SESSION['pesan_type']);

/* =========================================================
   TAHAPAN PROSES PRODUKSI
   ========================================================= */
$urutan = [
    'Menunggu Verifikasi',
    'Dikonfirmasi',
    'Cutting',
    'Sablon/Bordir',
    'Sewing/Dijahit',
    'Finishing',
    'Packing',
    'Selesai',
    'Diserahkan',
];

$tahapan = [
    'Menunggu Verifikasi' => ['icon' => '📩'],
    'Dikonfirmasi'        => ['icon' => '✅'],
    'Cutting'             => ['icon' => '✂️'],
    'Sablon/Bordir'       => ['icon' => '🖨️'],
    'Sewing/Dijahit'      => ['icon' => '🪡'],
    'Finishing'           => ['icon' => '✨'],
    'Packing'             => ['icon' => '📦'],
    'Selesai'             => ['icon' => '🎉'],
    'Diserahkan'          => ['icon' => '🚚'],
];

/* PROSES UPDATE STATUS */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $id_pesanan  = (int)($_POST['id_pesanan'] ?? 0);
    $status_baru = trim($_POST['status_baru'] ?? '');

    if ($id_pesanan > 0 && in_array($status_baru, $urutan, true)) {
        $stmt = $koneksi->prepare("UPDATE pesanan SET status = ? WHERE id_pesanan = ?");
        $stmt->execute([$status_baru, $id_pesanan]);
        $_SESSION['pesan'] = 'Status pesanan berhasil diperbarui menjadi "' . $status_baru . '".';
        $_SESSION['pesan_type'] = 'ok';
    } else {
        $_SESSION['pesan'] = 'Data tidak valid, coba lagi.';
        $_SESSION['pesan_type'] = 'err';
    }
    header('Location: tracking.php');
    exit;
}

/* Ambil pesanan aktif (belum diserahkan / dibatalkan) */
$focus = (int)($_GET['focus'] ?? 0);

$pesanan_list = $koneksi->query("
    SELECT * FROM pesanan
    WHERE status NOT IN ('Diserahkan','Dibatalkan')
    ORDER BY
        CASE status
            WHEN 'Packing'             THEN 1
            WHEN 'Finishing'           THEN 2
            WHEN 'Sewing/Dijahit'      THEN 3
            WHEN 'Sablon/Bordir'       THEN 4
            WHEN 'Cutting'             THEN 5
            WHEN 'Dikonfirmasi'        THEN 6
            WHEN 'Menunggu Verifikasi' THEN 7
            WHEN 'Selesai'             THEN 8
            ELSE 9
        END,
        dibuat_pada ASC
")->fetchAll();

function statusBg(string $s): string {
    return match($s) {
        'Menunggu Verifikasi' => 'background:var(--warn-soft);color:var(--warn);',
        'Dikonfirmasi'        => 'background:var(--denim-soft);color:var(--denim);',
        'Cutting'             => 'background:#F1E9FE;color:#5B3B9E;',
        'Sablon/Bordir'       => 'background:#FDE8E8;color:#B23A3A;',
        'Sewing/Dijahit'      => 'background:#E6F4EA;color:#1F7A3D;',
        'Finishing'           => 'background:#E3F2FD;color:#1565C0;',
        'Packing'             => 'background:#FFF3E0;color:#8A4E14;',
        'Selesai'             => 'background:var(--done-soft);color:var(--done);',
        'Diserahkan'          => 'background:#ECEFF1;color:#455A64;',
        default               => 'background:var(--border);color:var(--ink-soft);',
    };
}

function tglIndo(string $t): string {
    $b=[1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
        7=>'Jul',8=>'Agt',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
    $ts=strtotime($t);
    return date('d',$ts).' '.$b[(int)date('n',$ts)].' '.date('Y',$ts).' '.date('H:i',$ts);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Update Pengerjaan — Produksi CD 133</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/admin_index.css">
<style>
.tracking-list { display:flex; flex-direction:column; gap:14px; }

.trk-card { background:var(--surface); border:1px solid var(--border); border-radius:var(--radius);
            box-shadow:var(--shadow); overflow:hidden; transition:border-color .15s; }
.trk-card--focus { border-color:var(--thread); box-shadow:0 0 0 3px var(--thread-soft); }
.trk-card--produksi { border-left:4px solid var(--thread); }
.trk-card--selesai  { border-left:4px solid var(--done); }

.trk-header { display:flex; align-items:center; gap:14px; padding:16px 20px; cursor:pointer; }
.trk-header__kode { font-family:monospace; font-weight:700; font-size:13px; color:var(--denim); min-width:130px; }
.trk-header__info { flex:1; }
.trk-header__produk { font-weight:600; font-size:14px; }
.trk-header__meta { font-size:12px; color:var(--ink-soft); margin-top:2px; }
.trk-header__status { padding:4px 12px; border-radius:999px; font-size:12px; font-weight:600; white-space:nowrap; }
.trk-header__arrow { font-size:14px; color:var(--ink-soft); transition:transform .2s; }
.trk-header__arrow.open { transform:rotate(180deg); }

.trk-body { border-top:1px dashed var(--border); padding:18px 20px; display:none; }
.trk-body.is-open { display:block; }

/* Tracker progress */
.tracker-steps { display:flex; margin-bottom:22px; overflow-x:auto; padding-bottom:4px; }
.tracker-step { flex:1; min-width:78px; display:flex; flex-direction:column; align-items:center; gap:8px; position:relative; }
.tracker-step::before { content:''; position:absolute; top:12px; left:-50%; width:100%;
                         border-top:2px dashed var(--border); z-index:0; }
.tracker-step:first-child::before { display:none; }
.tracker-step.done::before, .tracker-step.current::before { border-top-color:var(--thread); }
.tracker-step__dot { width:24px; height:24px; border-radius:50%; background:var(--surface);
                     border:2px solid var(--border); z-index:1; display:flex; align-items:center;
                     justify-content:center; font-size:11px; flex-shrink:0; }
.tracker-step.done .tracker-step__dot { background:var(--thread); border-color:var(--thread); color:#fff; }
.tracker-step.current .tracker-step__dot { border-color:var(--thread); box-shadow:0 0 0 4px var(--thread-soft); }
.tracker-step__lbl { font-size:10.5px; text-align:center; color:var(--ink-soft); line-height:1.2; }
.tracker-step.done .tracker-step__lbl, .tracker-step.current .tracker-step__lbl { color:var(--ink); font-weight:600; }

/* Update form */
.trk-update { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin-top:4px; padding-top:16px; border-top:1px dashed var(--border); }
.trk-select { padding:9px 14px; border:1px solid var(--border); border-radius:var(--radius-sm);
              font-size:14px; font-family:var(--font-body); min-width:200px; }
.trk-select:focus { border-color:var(--denim-light); outline:none; }
.btn-update { padding:9px 20px; background:var(--denim); color:#fff; border:none; border-radius:var(--radius-sm);
              font-size:14px; font-weight:600; cursor:pointer; }
.btn-update:hover { background:var(--denim-light); }

/* Detail baris */
.trk-detail { display:grid; grid-template-columns:1fr 1fr; gap:8px 20px; margin-bottom:16px; }
.trk-detail__item { display:flex; flex-direction:column; gap:2px; }
.trk-detail__label { font-size:11px; text-transform:uppercase; letter-spacing:.04em; color:var(--ink-soft); font-weight:600; }
.trk-detail__val { font-size:13.5px; }

.toast { position:fixed; top:20px; right:20px; z-index:999; padding:12px 20px; border-radius:var(--radius-sm);
         font-weight:600; font-size:14px; opacity:0; transform:translateY(-10px); transition:.3s; }
.toast--ok { background:var(--done); color:#fff; }
.toast--err { background:var(--warn); color:#fff; }
.toast.is-visible { opacity:1; transform:translateY(0); }

@media(max-width:600px) {
    .trk-detail { grid-template-columns:1fr; }
    .tracker-step__lbl { display:none; }
}
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
            <a href="stok.php" class="sidebar__link"><span class="sidebar__icon">📦</span> Update Stok</a>
            <a href="tracking.php" class="sidebar__link is-active"><span class="sidebar__icon">↻</span> Update Pengerjaan</a>
        </nav>
        <a href="../logout.php" class="sidebar__logout"><span class="sidebar__icon">⎋</span> Logout</a>
    </aside>
    <div class="sidebar__overlay" id="sidebarOverlay"></div>

    <main class="main">

        <?php if ($pesan): ?>
        <div class="toast toast--<?= $pesan_type === 'ok' ? 'ok' : 'err' ?> is-visible" id="toast">
            <?= htmlspecialchars($pesan) ?>
        </div>
        <script>setTimeout(()=>document.getElementById('toast').classList.remove('is-visible'),3500);</script>
        <?php endif; ?>

        <div class="topbar">
            <button class="topbar__menu-btn" id="sidebarToggle"><span></span><span></span><span></span></button>
            <div class="topbar__greeting">
                <p class="topbar__eyebrow">Bagian Produksi</p>
                <h1>Update Pengerjaan Pesanan</h1>
            </div>
        </div>

        <?php if (empty($pesanan_list)): ?>
            <div style="text-align:center;padding:60px;color:var(--ink-soft);">
                <div style="font-size:48px;margin-bottom:12px;">🎉</div>
                <p style="font-size:15px;font-weight:600;margin:0 0 6px;">Semua pesanan sudah diserahkan!</p>
                <p style="font-size:13px;margin:0;">Tidak ada pesanan aktif yang perlu diupdate saat ini.</p>
            </div>
        <?php else: ?>

        <p style="font-size:13px;color:var(--ink-soft);margin:0 0 16px;">
            <?= count($pesanan_list) ?> pesanan aktif — klik kartu untuk membuka detail & update status.
        </p>

        <div class="tracking-list">
        <?php
        $tahapProduksi = ['Cutting','Sablon/Bordir','Sewing/Dijahit','Finishing','Packing'];
        foreach ($pesanan_list as $p):
            $statusNow = $p['status'];
            $posisi    = array_search($statusNow, $urutan);
            $isFocus   = ($focus === (int)$p['id_pesanan']);
            $cardClass = '';
            if (in_array($statusNow, $tahapProduksi, true)) $cardClass = 'trk-card--produksi';
            if ($statusNow === 'Selesai')  $cardClass = 'trk-card--selesai';
        ?>
        <div class="trk-card <?= $cardClass ?> <?= $isFocus ? 'trk-card--focus' : '' ?>"
             id="card-<?= $p['id_pesanan'] ?>">

            <!-- Header (klik untuk expand) -->
            <div class="trk-header" onclick="toggleCard(<?= $p['id_pesanan'] ?>)">
                <div class="trk-header__kode"><?= htmlspecialchars($p['kode_pesanan']) ?></div>
                <div class="trk-header__info">
                    <div class="trk-header__produk">
                        <?= htmlspecialchars(ucwords(str_replace('_',' ',$p['jenis_produk']))) ?>
                        — <?= number_format($p['jumlah']) ?> pcs
                    </div>
                    <div class="trk-header__meta">
                        <?= htmlspecialchars($p['nama_pemesan']) ?>
                        &nbsp;·&nbsp; <?= tglIndo($p['dibuat_pada']) ?>
                    </div>
                </div>
                <span class="trk-header__status" style="<?= statusBg($statusNow) ?>">
                    <?= $tahapan[$statusNow]['icon'] ?? '' ?> <?= htmlspecialchars($statusNow) ?>
                </span>
                <span class="trk-header__arrow <?= $isFocus ? 'open' : '' ?>" id="arrow-<?= $p['id_pesanan'] ?>">▼</span>
            </div>

            <!-- Body (detail + update) -->
            <div class="trk-body <?= $isFocus ? 'is-open' : '' ?>" id="body-<?= $p['id_pesanan'] ?>">

                <!-- Tracker progress -->
                <ol class="tracker-steps">
                <?php foreach ($urutan as $i => $step): ?>
                    <?php
                    if ($step === 'Diserahkan') continue; // ditampilkan lewat status badge saja, bukan di tracker produksi
                    $stepClass = '';
                    if ($posisi !== false && $i < $posisi) $stepClass = 'done';
                    elseif ($i === $posisi) $stepClass = 'current';
                    ?>
                    <li class="tracker-step <?= $stepClass ?>">
                        <div class="tracker-step__dot">
                            <?= ($posisi !== false && $i < $posisi) ? '✓' : ($i === $posisi ? '●' : '') ?>
                        </div>
                        <span class="tracker-step__lbl"><?= htmlspecialchars($step) ?></span>
                    </li>
                <?php endforeach; ?>
                </ol>

                <!-- Detail pesanan -->
                <div class="trk-detail">
                    <div class="trk-detail__item">
                        <span class="trk-detail__label">Pemesan</span>
                        <span class="trk-detail__val"><?= htmlspecialchars($p['nama_pemesan']) ?></span>
                    </div>
                    <div class="trk-detail__item">
                        <span class="trk-detail__label">No. HP</span>
                        <span class="trk-detail__val"><?= htmlspecialchars($p['no_hp']) ?></span>
                    </div>
                    <div class="trk-detail__item">
                        <span class="trk-detail__label">Metode Ambil</span>
                        <span class="trk-detail__val"><?= htmlspecialchars(ucwords(str_replace('_',' ',$p['metode_ambil']))) ?></span>
                    </div>
                    <div class="trk-detail__item">
                        <span class="trk-detail__label">Status Pembayaran</span>
                        <span class="trk-detail__val"><?= htmlspecialchars($p['status_pembayaran']) ?></span>
                    </div>
                    <?php if ($p['catatan']): ?>
                    <div class="trk-detail__item" style="grid-column:1/-1;">
                        <span class="trk-detail__label">Catatan / Desain</span>
                        <span class="trk-detail__val"><?= htmlspecialchars($p['catatan']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($p['file_desain'])): ?>
                    <div class="trk-detail__item" style="grid-column:1/-1;">
                        <span class="trk-detail__label">File Desain</span>
                        <a href="desain.php?id=<?= $p['id_pesanan'] ?>"
                           style="font-size:13.5px;font-weight:600;color:var(--denim-light);text-decoration:none;">
                            🎨 Buka Desain Customer →
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Form Update Status -->
                <form method="post" action="tracking.php" class="trk-update">
                    <input type="hidden" name="id_pesanan" value="<?= $p['id_pesanan'] ?>">
                    <select name="status_baru" class="trk-select">
                        <?php foreach ($urutan as $opt): ?>
                            <option value="<?= $opt ?>" <?= $opt === $statusNow ? 'selected' : '' ?>>
                                <?= $tahapan[$opt]['icon'] ?? '' ?> <?= htmlspecialchars($opt) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-update">Simpan Status</button>
                </form>

            </div>
        </div>
        <?php endforeach; ?>
        </div>
        <?php endif; ?>

    </main>
</div>

<script src="../assets/js/admin_index.js"></script>
<script>
function toggleCard(id) {
    const body  = document.getElementById('body-' + id);
    const arrow = document.getElementById('arrow-' + id);
    body.classList.toggle('is-open');
    arrow.classList.toggle('open');
}
/* Auto buka kalau ada ?focus */
<?php if ($focus > 0): ?>
document.addEventListener('DOMContentLoaded', function() {
    const el = document.getElementById('card-<?= $focus ?>');
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
});
<?php endif; ?>
</script>
</body>
</html>