<?php
require_once __DIR__ . '/../config/koneksi.php';

$kode_pesanan = trim($_GET['kode_pesanan'] ?? $_POST['kode_pesanan'] ?? '');
$no_hp        = trim($_GET['no_hp'] ?? $_POST['no_hp'] ?? '');

$errors    = [];
$berhasil  = false;
$kode_pembayaran_baru = null;

/* ---------- REKENING TUJUAN (statis) ---------- */
$rekening = [
    'bank'         => 'BCA',
    'nomor'        => '1234567890',
    'atas_nama'    => 'CD 133 Production',
];

/* ---------- CARI PESANAN BERDASARKAN KODE + NO HP ---------- */
$pesanan = null;
if ($kode_pesanan !== '' && $no_hp !== '') {
    $stmt = $koneksi->prepare("
        SELECT id_pesanan, kode_pesanan, nama_pemesan, no_hp, jenis_produk, jumlah, total_tagihan, status
        FROM pesanan
        WHERE kode_pesanan = :kode_pesanan AND no_hp = :no_hp
        LIMIT 1
    ");
    $stmt->execute([
        ':kode_pesanan' => $kode_pesanan,
        ':no_hp'        => $no_hp,
    ]);
    $pesanan = $stmt->fetch(PDO::FETCH_ASSOC);
}

/* ---------- CEK RIWAYAT PEMBAYARAN UNTUK PESANAN INI ---------- */
$pembayaran_terakhir = null;
if ($pesanan) {
    $stmt = $koneksi->prepare("
        SELECT kode_pembayaran, total_pembayaran, status, alasan_penolakan, dibuat_pada
        FROM pembayaran
        WHERE id_pesanan = :id_pesanan
        ORDER BY dibuat_pada DESC
        LIMIT 1
    ");
    $stmt->execute([':id_pesanan' => $pesanan['id_pesanan']]);
    $pembayaran_terakhir = $stmt->fetch(PDO::FETCH_ASSOC);
}

$boleh_upload = $pesanan
    && $pesanan['total_tagihan'] !== null
    && (!$pembayaran_terakhir || $pembayaran_terakhir['status'] === 'Ditolak');

/* ---------- PROSES UPLOAD BUKTI TRANSFER ---------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $boleh_upload) {

    $nominal_transfer = trim($_POST['nominal_transfer'] ?? '');

    if ($nominal_transfer === '' || (float) $nominal_transfer <= 0) {
        $errors['nominal_transfer'] = 'Nominal transfer wajib diisi dan lebih dari 0.';
    }

    $nama_file_bukti = null;
    if (empty($_FILES['bukti_transfer']['name'])) {
        $errors['bukti_transfer'] = 'Bukti transfer wajib diupload.';
    } else {
        $ekstensi_diizinkan = ['jpg', 'jpeg', 'png', 'pdf'];
        $ekstensi = strtolower(pathinfo($_FILES['bukti_transfer']['name'], PATHINFO_EXTENSION));

        if (!in_array($ekstensi, $ekstensi_diizinkan)) {
            $errors['bukti_transfer'] = 'File harus JPG, PNG, atau PDF.';
        } elseif ($_FILES['bukti_transfer']['size'] > 5 * 1024 * 1024) {
            $errors['bukti_transfer'] = 'Ukuran file maksimal 5MB.';
        } else {
            $folder = "../uploads/pembayaran/";

            if (!is_dir($folder)) {
                mkdir($folder, 0777, true);
            }

            $nama_file_bukti = uniqid('bukti_') . "." . $ekstensi;

            move_uploaded_file(
                $_FILES['bukti_transfer']['tmp_name'],
                $folder . $nama_file_bukti
            );
        }
    }

    if (empty($errors)) {
        $kode_pembayaran_baru = "BYR" . date("YmdHis");

        try {
            $stmt = $koneksi->prepare("
                INSERT INTO pembayaran
                (
                    kode_pembayaran,
                    id_pesanan,
                    total_pembayaran,
                    bukti_transfer,
                    tanggal_bayar,
                    status,
                    dibuat_pada
                )
                VALUES
                (
                    :kode_pembayaran,
                    :id_pesanan,
                    :total_pembayaran,
                    :bukti_transfer,
                    NOW(),
                    'Menunggu Verifikasi',
                    NOW()
                )
            ");

            $stmt->execute([
                ':kode_pembayaran'  => $kode_pembayaran_baru,
                ':id_pesanan'       => $pesanan['id_pesanan'],
                ':total_pembayaran' => (float) $nominal_transfer,
                ':bukti_transfer'   => $nama_file_bukti,
            ]);

            $berhasil = true;

        } catch (PDOException $e) {
            $errors[] = "Bukti pembayaran gagal disimpan: " . $e->getMessage();
        }
    }
}

$total_lunas = $pesanan['total_tagihan'] ?? null;
$total_dp    = $total_lunas !== null ? $total_lunas / 2 : null;
?>
<!DOCTYPE html>
<html lang="id">
<head>
<link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pembayaran — CD 133 Production</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/public.css">
</head>
<body>

<nav class="public-nav">
    <div class="public-nav__brand">
        <span class="public-nav__mark">133</span>
        CD 133 Production
    </div>
    <div class="public-nav__links">
        <a href="dashboard.php">Beranda</a>
        <a href="pesan.php">Pesan Sekarang</a>
        <a href="lacak_pesanan.php">Lacak Pesanan</a>
    </div>
</nav>

<div class="public-wrap">

    <?php if (!$pesanan): ?>

        <div class="page-head">
            <p class="page-head__eyebrow">Pembayaran</p>
            <h1>Pesanan Tidak Ditemukan</h1>
            <p>Kode pesanan dan nomor HP tidak cocok dengan data yang ada. Cek kembali kode pesanan kamu.</p>
        </div>
        <a href="lacak_pesanan.php" class="btn btn--accent">Lacak Pesanan</a>

    <?php elseif ($berhasil): ?>

        <div class="success-card">
            <div class="success-card__icon">✓</div>
            <h2>Bukti Pembayaran Terkirim!</h2>
            <p>Admin akan memverifikasi pembayaran kamu dalam waktu 1x24 jam.</p>
            <div class="success-card__code"><?= htmlspecialchars($kode_pembayaran_baru) ?></div>
            <div class="success-card__actions">
                <a href="lacak_pesanan.php?kode_pesanan=<?= urlencode($kode_pesanan) ?>&no_hp=<?= urlencode($no_hp) ?>" class="btn btn--accent">Lacak Status Pesanan</a>
                <a href="dashboard.php" class="btn btn--ghost" style="color:var(--denim); border-color:var(--border);">Kembali ke Beranda</a>
            </div>
        </div>

    <?php elseif ($pesanan['total_tagihan'] === null): ?>

        <div class="page-head">
            <p class="page-head__eyebrow">Pembayaran</p>
            <h1>Menunggu Penentuan Harga</h1>
            <p>Pesanan kategori "Lainnya" perlu dicek dulu oleh admin sebelum total tagihan ditentukan. Admin akan menghubungi kamu lewat WhatsApp ke nomor <strong><?= htmlspecialchars($pesanan['no_hp']) ?></strong>. Silakan kembali ke halaman ini setelah dikonfirmasi.</p>
        </div>

    <?php elseif ($pembayaran_terakhir && $pembayaran_terakhir['status'] !== 'Ditolak'): ?>

        <div class="page-head">
            <p class="page-head__eyebrow">Pembayaran</p>
            <h1>Bukti Pembayaran Sudah Dikirim</h1>
        </div>

        <div class="success-card" style="text-align:left;">
            <p>Kode Pembayaran: <strong><?= htmlspecialchars($pembayaran_terakhir['kode_pembayaran']) ?></strong></p>
            <p>Nominal: <strong>Rp<?= number_format($pembayaran_terakhir['total_pembayaran'], 0, ',', '.') ?></strong></p>
            <p>Status: <strong><?= htmlspecialchars($pembayaran_terakhir['status']) ?></strong></p>
            <?php if ($pembayaran_terakhir['status'] === 'Menunggu Verifikasi'): ?>
                <p>Mohon tunggu admin memverifikasi pembayaran kamu.</p>
            <?php endif; ?>
            <div class="success-card__actions">
                <a href="lacak_pesanan.php?kode_pesanan=<?= urlencode($kode_pesanan) ?>&no_hp=<?= urlencode($no_hp) ?>" class="btn btn--accent">Lacak Status Pesanan</a>
            </div>
        </div>

    <?php else: ?>

        <?php if (!empty($errors) && isset($errors[0])): ?>
            <div class="alert alert--error" style="margin-bottom:16px; padding:12px 16px; border:1px solid #e33; background:#fee; border-radius:8px; color:#a00;">
                <?php foreach ($errors as $key => $err): ?>
                    <?php if (is_int($key)): ?>
                        <p style="margin:0;"><?= htmlspecialchars($err) ?></p>
                    <?php endif; ?>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <?php if ($pembayaran_terakhir && $pembayaran_terakhir['status'] === 'Ditolak'): ?>
            <div class="alert alert--error" style="margin-bottom:16px; padding:12px 16px; border:1px solid #e33; background:#fee; border-radius:8px; color:#a00;">
                <p style="margin:0;"><strong>Pembayaran sebelumnya ditolak:</strong> <?= htmlspecialchars($pembayaran_terakhir['alasan_penolakan'] ?? '-') ?></p>
                <p style="margin:4px 0 0;">Silakan upload ulang bukti transfer yang benar.</p>
            </div>
        <?php endif; ?>

        <div class="page-head">
            <p class="page-head__eyebrow">Pembayaran</p>
            <h1>Pembayaran Pesanan <?= htmlspecialchars($pesanan['kode_pesanan']) ?></h1>
            <p>Transfer sesuai nominal pilihan kamu, lalu upload bukti transfer di bawah.</p>
        </div>

        <form class="form-card" method="post" action="pembayaran.php" enctype="multipart/form-data" novalidate>
            <input type="hidden" name="kode_pesanan" value="<?= htmlspecialchars($kode_pesanan) ?>">
            <input type="hidden" name="no_hp" value="<?= htmlspecialchars($no_hp) ?>">

            <div class="form-section">
                <h2 class="form-section__title">Informasi Tagihan</h2>
                <div class="form-grid">
                    <div class="field">
                        <label>Total Tagihan (Lunas)</label>
                        <input type="text" value="Rp<?= number_format($total_lunas, 0, ',', '.') ?>" disabled>
                    </div>
                    <div class="field">
                        <label>DP 50%</label>
                        <input type="text" value="Rp<?= number_format($total_dp, 0, ',', '.') ?>" disabled>
                    </div>

                    <div class="field field--full">
                        <label>Transfer ke</label>
                        <input type="text" value="<?= htmlspecialchars($rekening['bank']) ?> — <?= htmlspecialchars($rekening['nomor']) ?> a.n. <?= htmlspecialchars($rekening['atas_nama']) ?>" disabled>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <h2 class="form-section__title">Detail Pembayaran</h2>
                <div class="form-grid">

                    <div class="field field--full">
                        <label>Pilih Jenis Pembayaran</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="jenis_bayar" value="lunas" data-nominal="<?= (int) $total_lunas ?>" checked>
                                Bayar Lunas (Rp<?= number_format($total_lunas, 0, ',', '.') ?>)
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="jenis_bayar" value="dp" data-nominal="<?= (int) $total_dp ?>">
                                Bayar DP 50% (Rp<?= number_format($total_dp, 0, ',', '.') ?>)
                            </label>
                        </div>
                        <span class="field-hint">Pilihan ini hanya bantu isi nominal otomatis, kamu tetap bisa ubah manual sesuai nominal yang benar-benar ditransfer.</span>
                    </div>

                    <div class="field field--full">
                        <label for="nominal_transfer">Nominal yang Ditransfer (Rp)</label>
                        <input type="number" id="nominal_transfer" name="nominal_transfer" min="1" step="1"
                               class="<?= isset($errors['nominal_transfer']) ? 'has-error' : '' ?>"
                               value="<?= (int) $total_lunas ?>">
                        <?php if (isset($errors['nominal_transfer'])): ?><span class="field-error"><?= $errors['nominal_transfer'] ?></span><?php endif; ?>
                    </div>

                    <div class="field field--full">
                        <label for="bukti_transfer">Upload Bukti Transfer</label>
                        <div class="file-drop">
                            <input type="file" id="bukti_transfer" name="bukti_transfer" accept=".jpg,.jpeg,.png,.pdf">
                        </div>
                        <?php if (isset($errors['bukti_transfer'])): ?>
                            <span class="field-error"><?= $errors['bukti_transfer'] ?></span>
                        <?php else: ?>
                            <span class="field-hint">Format JPG, PNG, atau PDF, maksimal 5MB.</span>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--accent">Kirim Bukti Pembayaran</button>
            </div>

        </form>

        <script>
            document.querySelectorAll('input[name="jenis_bayar"]').forEach(function (radio) {
                radio.addEventListener('change', function () {
                    document.getElementById('nominal_transfer').value = this.dataset.nominal;
                });
            });
        </script>

    <?php endif; ?>

</div>

<footer class="public-footer">
    © 2026 CD 133 Production — Konveksi Custom. Semua hak cipta dilindungi.
</footer>

</body>
</html>