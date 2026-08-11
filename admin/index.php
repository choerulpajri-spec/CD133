<?php
require_once __DIR__ . '/../config/koneksi.php';

/**
 * ASUMSI PENTING soal skema database (sesuaikan kalau beda):
 *
 * - Tabel `pesanan` punya kolom `status` (VARCHAR bebas, BUKAN enum),
 *   dengan default 'Menunggu Verifikasi'. Berdasarkan data yang ada
 *   sekarang, nilai yang pernah dipakai baru 'Menunggu Verifikasi' dan
 *   'Diserahkan'. Dashboard ini mengasumsikan alur status sebagai berikut
 *   (SESUAIKAN daftar di bawah kalau di aplikasi kamu nama statusnya beda):
 *     - 'Menunggu Verifikasi' -> kartu "Menunggu Verifikasi"
 *     - 'Diproses'            -> kartu "Sedang Diproses"
 *     - 'Selesai' / 'Diserahkan' -> kartu "Pesanan Selesai"
 *   Kalau nanti kamu pakai nama status lain (misal 'Produksi', 'Dikirim',
 *   dll), tinggal ubah nilai di query COUNT(*) masing-masing kartu.
 *
 * - Kartu "Perlu Tindakan" dihitung dari tabel `pembayaran` yang
 *   `status = 'Menunggu Verifikasi'` — artinya ada bukti transfer yang
 *   masuk dan butuh dicek/diverifikasi admin. Ganti definisinya kalau
 *   yang kamu maksud "perlu tindakan" itu beda (misal pesanan yang
 *   terlambat dari estimasi, dsb).
 *
 * - Tidak ada kolom "estimasi selesai" / tanggal target produksi di
 *   tabel `pesanan`, jadi baris itu di panel tracker sengaja dihapus.
 *   Kalau nanti kolomnya ditambahkan, tinggal tampilkan lagi.
 *
 * - Panel "Pesanan Berjalan" menampilkan 1 pesanan TERBARU yang statusnya
 *   BELUM 'Selesai'/'Diserahkan'. Posisi di 5 langkah tracker
 *   (Diterima / DP Diverifikasi / Produksi / QC / Selesai) ditebak dari
 *   kolom `status` & `status_pembayaran` lewat fungsi
 *   langkahTrackerDariStatus() di bawah — sesuaikan logikanya kalau
 *   alur status di aplikasi kamu beda.
 */

/* ---------- STAT: Total Produk ---------- */
$total_produk = 0;
try {
    $total_produk = (int) $koneksi->query("SELECT COUNT(*) FROM produk")->fetchColumn();
} catch (PDOException $e) {
    $total_produk = 0;
}

/* ---------- STAT: Pesanan (total & per status) ---------- */
$total_pesanan       = 0;
$menunggu_verifikasi = 0;
$sedang_diproses     = 0;
$pesanan_selesai     = 0;

try {
    $total_pesanan = (int) $koneksi->query("SELECT COUNT(*) FROM pesanan")->fetchColumn();

    $menunggu_verifikasi = (int) $koneksi->query("
        SELECT COUNT(*) FROM pesanan WHERE status = 'Menunggu Verifikasi'
    ")->fetchColumn();

    $sedang_diproses = (int) $koneksi->query("
        SELECT COUNT(*) FROM pesanan WHERE status = 'Diproses'
    ")->fetchColumn();

    $pesanan_selesai = (int) $koneksi->query("
        SELECT COUNT(*) FROM pesanan WHERE status IN ('Selesai', 'Diserahkan')
    ")->fetchColumn();
} catch (PDOException $e) {
    $total_pesanan = $menunggu_verifikasi = $sedang_diproses = $pesanan_selesai = 0;
}

/* ---------- STAT: Perlu Tindakan (pembayaran menunggu verifikasi) ---------- */
$perlu_tindakan = 0;
try {
    $perlu_tindakan = (int) $koneksi->query("
        SELECT COUNT(*) FROM pembayaran WHERE status = 'Menunggu Verifikasi'
    ")->fetchColumn();
} catch (PDOException $e) {
    $perlu_tindakan = 0;
}

/**
 * Tebak posisi tracker (1-5) dari status pesanan & status pembayaran.
 * 1=Diterima, 2=DP Diverifikasi, 3=Produksi, 4=QC, 5=Selesai
 */
function langkahTrackerDariStatus(?string $status, ?string $status_pembayaran): int
{
    $status = $status ?? '';

    if (in_array($status, ['Selesai', 'Diserahkan'], true)) {
        return 5;
    }
    if ($status === 'QC') {
        return 4;
    }
    if (in_array($status, ['Diproses', 'Produksi'], true)) {
        return 3;
    }
    if (($status_pembayaran ?? 'Belum Dibayar') !== 'Belum Dibayar') {
        return 2;
    }
    return 1;
}

/* ---------- Pesanan Berjalan: pesanan terbaru yang belum selesai ---------- */
$pesanan_berjalan = null;
try {
    $stmt = $koneksi->prepare("
        SELECT * FROM pesanan
        WHERE status NOT IN ('Selesai', 'Diserahkan')
        ORDER BY dibuat_pada DESC
        LIMIT 1
    ");
    $stmt->execute();
    $pesanan_berjalan = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
} catch (PDOException $e) {
    $pesanan_berjalan = null;
}

$langkah_tracker_saat_ini = $pesanan_berjalan
    ? langkahTrackerDariStatus($pesanan_berjalan['status'] ?? null, $pesanan_berjalan['status_pembayaran'] ?? null)
    : 0;

$label_langkah_tracker = ['Diterima', 'DP Diverifikasi', 'Produksi', 'QC', 'Selesai'];

/* ---------- Aktivitas Terbaru: 5 pesanan paling baru masuk ---------- */
$aktivitas_terbaru = [];
try {
    $aktivitas_terbaru = $koneksi->query("
        SELECT id_pesanan, kode_pesanan, nama_pemesan, jenis_produk, jumlah, status
        FROM pesanan
        ORDER BY dibuat_pada DESC
        LIMIT 5
    ")->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $aktivitas_terbaru = [];
}

/**
 * Kelas badge untuk status pesanan. Hanya ada 3 warna badge yang sudah
 * tersedia di CSS (info/warn/done) — status yang tidak dikenali fallback
 * ke "info" supaya tidak error.
 */
function badgeClassStatus(?string $status): string
{
    switch ($status) {
        case 'Menunggu Verifikasi':
            return 'badge--warn';
        case 'Selesai':
        case 'Diserahkan':
            return 'badge--done';
        case 'Diproses':
        case 'Produksi':
        case 'QC':
        default:
            return 'badge--info';
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin — CD 133 Production</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../assets/css/tokens.css">
    <link rel="stylesheet" href="../assets/css/admin_index.css">
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
                <span class="sidebar__icon">▦</span> Dashboard
            </a>
            <a href="produk/index.php" class="sidebar__link">
                <span class="sidebar__icon">◈</span> Kelola Produk
            </a>
            <a href="pembayaran/index.php" class="sidebar__link">
                <span class="sidebar__icon">◇</span> informasi pesanan & verifikasi pembayaran
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
                <p class="topbar__eyebrow">Panel Admin</p>
                <h1>Selamat Datang, Admin</h1>
            </div>

            <div class="topbar__clock" id="clock">Memuat…</div>
        </div>

        <!-- Stat Cards -->
        <section class="stats">
            <div class="stat-card stat-card--info">
                <div class="stat-card__value" data-count="<?= $total_produk ?>">0</div>
                <div class="stat-card__label">Total Produk</div>
            </div>
            <div class="stat-card">
                <div class="stat-card__value" data-count="<?= $total_pesanan ?>">0</div>
                <div class="stat-card__label">Total Pesanan</div>
            </div>
            <div class="stat-card stat-card--warn">
                <div class="stat-card__value" data-count="<?= $menunggu_verifikasi ?>">0</div>
                <div class="stat-card__label">Menunggu Verifikasi</div>
            </div>
            <div class="stat-card stat-card--info">
                <div class="stat-card__value" data-count="<?= $sedang_diproses ?>">0</div>
                <div class="stat-card__label">Sedang Diproses</div>
            </div>
            <div class="stat-card stat-card--done">
                <div class="stat-card__value" data-count="<?= $pesanan_selesai ?>">0</div>
                <div class="stat-card__label">Pesanan Selesai</div>
            </div>
            <div class="stat-card stat-card--danger">
                <div class="stat-card__value" data-count="<?= $perlu_tindakan ?>">0</div>
                <div class="stat-card__label">Perlu Tindakan</div>
            </div>
        </section>

        <!-- Info Box -->
        <div class="info-box">
            <p>
                Selamat datang di Sistem Informasi Penjualan
                <strong>CD 133 Production</strong>.
            </p>
            <p>
                Gunakan menu di sebelah kiri untuk mengelola produk, pesanan,
                pembayaran, status pesanan, dan laporan penjualan.
            </p>
            <div class="info-box__tip">
                💡 <strong>Tip:</strong> Tekan <kbd>Ctrl</kbd> + <kbd>M</kbd> untuk toggle sidebar,
                atau <kbd>Ctrl</kbd> + <kbd>L</kbd> untuk logout cepat.
            </div>
        </div>

        <!-- Panel: Pesanan Berjalan (dari database) -->
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2>Pesanan Berjalan</h2>
                    <p class="panel__hint">Pantau progres produksi terkini.</p>
                </div>
                <a href="pesanan/index.php" class="panel__link">Lihat semua →</a>
            </div>

            <?php if ($pesanan_berjalan): ?>
                <div class="tracker">
                    <div class="tracker__info">
                        <strong>
                            <?= htmlspecialchars($pesanan_berjalan['kode_pesanan']) ?> ·
                            <?= htmlspecialchars($pesanan_berjalan['jenis_produk']) ?> × <?= (int) $pesanan_berjalan['jumlah'] ?>
                        </strong>
                        <span>
                            Pemesan: <?= htmlspecialchars($pesanan_berjalan['nama_pemesan']) ?> ·
                            Masuk <?= date('d F Y', strtotime($pesanan_berjalan['dibuat_pada'])) ?>
                        </span>
                    </div>
                    <ol class="tracker__steps">
                        <?php foreach ($label_langkah_tracker as $i => $label): ?>
                            <?php
                                $nomor_langkah = $i + 1;
                                $kelas_langkah = '';
                                if ($nomor_langkah < $langkah_tracker_saat_ini) {
                                    $kelas_langkah = 'is-done';
                                } elseif ($nomor_langkah === $langkah_tracker_saat_ini) {
                                    $kelas_langkah = 'is-current';
                                }
                            ?>
                            <li class="tracker__step <?= $kelas_langkah ?>">
                                <span class="tracker__dot"></span>
                                <span class="tracker__label"><?= $label ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <p class="empty-state__title">Tidak ada pesanan yang sedang berjalan</p>
                    <p class="empty-state__text">Semua pesanan sudah selesai/diserahkan, atau belum ada pesanan masuk.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- Panel: Aktivitas Terbaru (dari database) -->
        <section class="panel">
            <div class="panel__head">
                <div>
                    <h2>Aktivitas Terbaru</h2>
                    <p class="panel__hint">Pesanan & pembayaran yang baru masuk.</p>
                </div>
                <a href="pesanan/index.php" class="panel__link">Kelola →</a>
            </div>

            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>No. Pesanan</th>
                            <th>Pemesan</th>
                            <th>Produk</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($aktivitas_terbaru)): ?>
                        <tr>
                            <td colspan="5">
                                <div class="empty-state">
                                    <p class="empty-state__title">Belum ada aktivitas</p>
                                    <p class="empty-state__text">Pesanan yang masuk akan muncul di sini.</p>
                                </div>
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($aktivitas_terbaru as $row): ?>
                            <tr>
                                <td><?= htmlspecialchars($row['kode_pesanan']) ?></td>
                                <td><?= htmlspecialchars($row['nama_pemesan']) ?></td>
                                <td><?= htmlspecialchars($row['jenis_produk']) ?> × <?= (int) $row['jumlah'] ?></td>
                                <td><span class="badge <?= badgeClassStatus($row['status']) ?>"><?= htmlspecialchars($row['status'] ?? '-') ?></span></td>
                                <td><a href="pembayaran/index.php?id=<?= (int) $row['id_pesanan'] ?>" class="table__action">Detail</a></td>
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