<?php
require_once __DIR__ . '/../config/koneksi.php';

/* =====================================================================
   HELPER
   ===================================================================== */
function rupiah(float|null $angka): string
{
    if ($angka === null) return '-';
    return 'Rp' . number_format($angka, 0, ',', '.');
}

function tglIndo(?string $tgl): string
{
    if (!$tgl) return '-';
    $bulan = [
        1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
        7=>'Jul',8=>'Agt',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des',
    ];
    $ts = strtotime($tgl);
    return date('d', $ts) . ' ' . $bulan[(int)date('n', $ts)] . ' ' . date('Y', $ts);
}

/* =====================================================================
   FILTER TANGGAL
   ===================================================================== */
$tgl_awal  = $_GET['tgl_awal']  ?? '';
$tgl_akhir = $_GET['tgl_akhir'] ?? '';

// Default: bulan berjalan
if ($tgl_awal === '') {
    $tgl_awal = date('Y-m-01');
}
if ($tgl_akhir === '') {
    $tgl_akhir = date('Y-m-t');
}

/* =====================================================================
   QUERY UTAMA: semua pesanan dalam rentang tanggal
   JOIN ke pembayaran untuk tahu status & total yang sudah diterima
   ===================================================================== */
$stmt = $koneksi->prepare("
    SELECT
        p.id_pesanan,
        p.kode_pesanan,
        p.nama_pemesan,
        p.jenis_produk,
        p.jumlah,
        p.total_tagihan,
        p.status,
        p.status_pembayaran,
        p.dibuat_pada,
        -- ambil pembayaran yang sudah diterima (jika ada)
        pb.kode_pembayaran,
        pb.total_pembayaran  AS nominal_diterima,
        pb.tanggal_bayar,
        pb.status            AS status_bayar
    FROM pesanan p
    LEFT JOIN pembayaran pb
        ON pb.id_pesanan = p.id_pesanan
        AND pb.status = 'Diterima'
    WHERE DATE(p.dibuat_pada) BETWEEN :tgl_awal AND :tgl_akhir
    ORDER BY p.dibuat_pada DESC
");
$stmt->execute([':tgl_awal' => $tgl_awal, ':tgl_akhir' => $tgl_akhir]);
$pesanan_list = $stmt->fetchAll();

/* =====================================================================
   RINGKASAN AGREGAT
   ===================================================================== */
$stmt_ring = $koneksi->prepare("
    SELECT
        COUNT(DISTINCT p.id_pesanan)                    AS total_pesanan,
        COALESCE(SUM(p.jumlah), 0)                      AS total_produk,
        COALESCE(SUM(pb.total_pembayaran), 0)           AS total_pendapatan,
        COUNT(DISTINCT CASE WHEN p.status = 'Selesai'       THEN p.id_pesanan END) AS jml_selesai,
        COUNT(DISTINCT CASE WHEN p.status_pembayaran
                            = 'Belum Dibayar'            THEN p.id_pesanan END) AS jml_belum_bayar
    FROM pesanan p
    LEFT JOIN pembayaran pb
        ON pb.id_pesanan = p.id_pesanan AND pb.status = 'Diterima'
    WHERE DATE(p.dibuat_pada) BETWEEN :tgl_awal AND :tgl_akhir
");
$stmt_ring->execute([':tgl_awal' => $tgl_awal, ':tgl_akhir' => $tgl_akhir]);
$ring = $stmt_ring->fetch();

/* =====================================================================
   STATUS BADGE CLASS
   ===================================================================== */
function statusBadge(string $status): string
{
    return match(strtolower($status)) {
        'selesai', 'diserahkan'     => 'badge badge--done',
        'menunggu verifikasi'       => 'badge badge--warn',
        'produksi', 'konfirmasi'    => 'badge badge--info',
        'ditolak', 'batal'          => 'badge badge--danger',
        default                     => 'badge',
    };
}
function bayarBadge(string $status): string
{
    return match($status) {
        'Lunas', 'Diterima'         => 'badge badge--done',
        'Menunggu Verifikasi'       => 'badge badge--warn',
        'Ditolak'                   => 'badge badge--danger',
        default                     => 'badge',
    };
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laporan Penjualan — CD 133 Production</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/tokens.css">
    <link rel="stylesheet" href="../assets/css/admin_index.css">
    <style>
        /* ── tambahan khusus laporan ── */
        .filter-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 18px 22px;
            margin-bottom: 22px;
            box-shadow: var(--shadow);
            display: flex;
            align-items: flex-end;
            gap: 16px;
            flex-wrap: wrap;
        }
        .filter-card .field {
            display: flex;
            flex-direction: column;
            gap: 5px;
        }
        .filter-card label {
            font-size: 12px;
            font-weight: 600;
            color: var(--ink-soft);
            text-transform: uppercase;
            letter-spacing: .04em;
        }
        .filter-card input[type="date"] {
            padding: 9px 12px;
            border: 1px solid var(--border);
            border-radius: var(--radius-sm);
            font-family: var(--font-body);
            font-size: 13.5px;
            color: var(--ink);
            background: var(--bg);
        }
        .filter-card input[type="date"]:focus {
            border-color: var(--denim-light);
            outline: none;
        }
        .btn { display: inline-flex; align-items: center; gap: 6px;
               padding: 10px 18px; border-radius: var(--radius-sm);
               font-size: 13.5px; font-weight: 600; border: none; cursor: pointer; }
        .btn--primary { background: var(--denim); color: #fff; }
        .btn--primary:hover { background: var(--denim-light); }
        .btn--outline { background: var(--surface); color: var(--denim);
                        border: 1px solid var(--denim); }
        .btn--outline:hover { background: var(--denim-soft); }

        .ringkasan-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 14px;
            margin-bottom: 22px;
        }
        .ring-card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 16px 18px;
            box-shadow: var(--shadow);
        }
        .ring-card__val {
            font-family: var(--font-display);
            font-size: 22px;
            font-weight: 700;
            color: var(--ink);
            margin-bottom: 4px;
        }
        .ring-card__lbl { font-size: 12px; color: var(--ink-soft); }
        .ring-card--done { border-top: 3px solid var(--done); }
        .ring-card--warn { border-top: 3px solid var(--warn); }
        .ring-card--info { border-top: 3px solid var(--denim-light); }
        .ring-card--thread { border-top: 3px solid var(--thread); }

        /* badge tambahan */
        .badge { display:inline-block; padding:3px 10px; border-radius:999px;
                 font-size:11.5px; font-weight:600; }
        .badge--done    { background:var(--done-soft);   color:var(--done); }
        .badge--warn    { background:var(--warn-soft);   color:var(--warn); }
        .badge--info    { background:var(--denim-soft);  color:var(--denim); }
        .badge--danger  { background:#fde8e4;            color:#c0392b; }

        .empty-row td {
            text-align: center;
            padding: 40px 0;
            color: var(--ink-soft);
            font-size: 14px;
        }

        /* ── PRINT ── */
        @media print {
            .sidebar, .topbar__menu-btn, .filter-card, .no-print { display: none !important; }
            .main { padding: 0 !important; }
            .app { display: block !important; }
            .table td, .table th { white-space: normal !important; font-size: 11px; }
            .ringkasan-grid { grid-template-columns: repeat(5,1fr) !important; }
        }

        @media (max-width: 1100px) {
            .ringkasan-grid { grid-template-columns: repeat(3,1fr); }
        }
        @media (max-width: 768px) {
            .ringkasan-grid { grid-template-columns: 1fr 1fr; }
        }
    </style>
</head>
<body>

<div class="app">

    <!-- ===== SIDEBAR ===== -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__brand">
            <div class="sidebar__mark">CD</div>
            <div class="sidebar__brand-text">
                <strong>CD 133 Production</strong>
                <span>Panel Internal</span>
            </div>
        </div>

        <nav class="sidebar__nav" aria-label="Menu utama">
            <a href="index.php" class="sidebar__link is-active">
                <span class="sidebar__icon">▥</span> Laporan Penjualan
            </a>
        </nav>

        <a href="../logout.php" class="sidebar__logout">
            <span class="sidebar__icon">⎋</span> Logout
        </a>
    </aside>

    <div class="sidebar__overlay" id="sidebarOverlay"></div>

    <!-- ===== MAIN ===== -->
    <main class="main">

        <!-- Topbar -->
        <div class="topbar">
            <button class="topbar__menu-btn" id="sidebarToggle" aria-label="Buka menu">
                <span></span><span></span><span></span>
            </button>
            <div class="topbar__greeting">
                <p class="topbar__eyebrow">Laporan</p>
                <h1>Laporan Penjualan</h1>
            </div>
            <button class="btn btn--outline no-print" onclick="window.print()">
                🖨 Cetak Laporan
            </button>
        </div>

        <!-- Filter Tanggal -->
        <form method="GET" action="" class="filter-card no-print">
            <div class="field">
                <label for="tgl_awal">Dari Tanggal</label>
                <input type="date" id="tgl_awal" name="tgl_awal"
                       value="<?= htmlspecialchars($tgl_awal) ?>">
            </div>
            <div class="field">
                <label for="tgl_akhir">Sampai Tanggal</label>
                <input type="date" id="tgl_akhir" name="tgl_akhir"
                       value="<?= htmlspecialchars($tgl_akhir) ?>">
            </div>
            <button type="submit" class="btn btn--primary">Tampilkan</button>
            <a href="index.php" class="btn btn--outline">Reset</a>
        </form>

        <!-- Ringkasan -->
        <div class="ringkasan-grid">
            <div class="ring-card ring-card--info">
                <div class="ring-card__val"><?= number_format($ring['total_pesanan']) ?></div>
                <div class="ring-card__lbl">Total Pesanan</div>
            </div>
            <div class="ring-card ring-card--info">
                <div class="ring-card__val"><?= number_format($ring['total_produk']) ?></div>
                <div class="ring-card__lbl">Total Produk Terjual</div>
            </div>
            <div class="ring-card ring-card--thread">
                <div class="ring-card__val" style="font-size:15px;"><?= rupiah($ring['total_pendapatan']) ?></div>
                <div class="ring-card__lbl">Total Pendapatan Diterima</div>
            </div>
            <div class="ring-card ring-card--done">
                <div class="ring-card__val"><?= number_format($ring['jml_selesai']) ?></div>
                <div class="ring-card__lbl">Pesanan Selesai</div>
            </div>
            <div class="ring-card ring-card--warn">
                <div class="ring-card__val"><?= number_format($ring['jml_belum_bayar']) ?></div>
                <div class="ring-card__lbl">Belum Dibayar</div>
            </div>
        </div>

        <!-- Tabel Laporan -->
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2>Detail Pesanan</h2>
                    <p class="panel__hint">
                        Periode: <?= tglIndo($tgl_awal) ?> — <?= tglIndo($tgl_akhir) ?>
                        &nbsp;·&nbsp; <?= count($pesanan_list) ?> data
                    </p>
                </div>
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
                            <th>Jml</th>
                            <th>Total Tagihan</th>
                            <th>Pembayaran</th>
                            <th>Status Pesanan</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($pesanan_list)): ?>
                            <tr class="empty-row">
                                <td colspan="9">Tidak ada data pesanan pada periode ini.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($pesanan_list as $i => $p): ?>
                            <tr>
                                <td><?= $i + 1 ?></td>
                                <td style="font-weight:600; font-family:monospace; font-size:12px;">
                                    <?= htmlspecialchars($p['kode_pesanan']) ?>
                                </td>
                                <td><?= tglIndo($p['dibuat_pada']) ?></td>
                                <td><?= htmlspecialchars($p['nama_pemesan']) ?></td>
                                <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $p['jenis_produk']))) ?></td>
                                <td><?= number_format($p['jumlah']) ?> pcs</td>
                                <td><?= rupiah($p['total_tagihan']) ?></td>
                                <td>
                                    <?php
                                        // Tampilkan status bayar dari tabel pembayaran
                                        // jika ada record diterima, atau fallback ke status_pembayaran di pesanan
                                        $statusBayar = $p['status_bayar']
                                            ? $p['status_bayar']
                                            : $p['status_pembayaran'];
                                        $nominalBayar = $p['nominal_diterima']
                                            ? rupiah($p['nominal_diterima'])
                                            : '';
                                    ?>
                                    <span class="<?= bayarBadge($statusBayar) ?>">
                                        <?= htmlspecialchars($statusBayar) ?>
                                    </span>
                                    <?php if ($nominalBayar): ?>
                                        <div style="font-size:11px;color:var(--ink-soft);margin-top:2px;">
                                            <?= $nominalBayar ?>
                                        </div>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="<?= statusBadge($p['status']) ?>">
                                        <?= htmlspecialchars($p['status']) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                    <?php if (!empty($pesanan_list)): ?>
                    <tfoot>
                        <tr style="border-top: 2px solid var(--border);">
                            <td colspan="6" style="text-align:right; font-weight:700; padding:13px 12px;">
                                Total Pendapatan Diterima:
                            </td>
                            <td colspan="3" style="font-weight:700; color:var(--done); padding:13px 12px;">
                                <?= rupiah($ring['total_pendapatan']) ?>
                            </td>
                        </tr>
                    </tfoot>
                    <?php endif; ?>
                </table>
            </div>
        </section>

    </main>
</div>

<script src="../assets/js/admin_index.js"></script>
</body>
</html>