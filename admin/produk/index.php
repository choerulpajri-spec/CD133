<?php
require_once __DIR__ . '/../../config/koneksi.php';

$aksi    = $_GET['aksi'] ?? '';
$id      = (int)($_GET['id'] ?? 0);
$pesan   = $_SESSION['pesan'] ?? '';
$pesan_type = $_SESSION['pesan_type'] ?? '';
unset($_SESSION['pesan'], $_SESSION['pesan_type']);

/* =====================================================================
   GENERATOR KODE PRODUK OTOMATIS
   Format: [3 huruf awal KATEGORI][C jika custom]-[nomor urut]
   Contoh: kategori "Kaos" produk jadi   -> KAO-001
           kategori "Kaos" produk custom -> KAOC-001
   Nomor urut dihitung berdasarkan kode dengan awalan yang sama yang
   sudah ada di database, supaya tetap unik & terpisah antara jadi/custom.
   ===================================================================== */
function buatKodeProduk(PDO $koneksi, string $kategori, string $jenis_produk): string {
    $bersihkan = function (string $s): string {
        // Ambil huruf & angka saja, buang spasi/simbol
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $s));
    };

    $kat = substr($bersihkan($kategori), 0, 3);
    if ($kat === '') $kat = 'UMM'; // fallback kalau kategori kosong/simbol semua

    $penanda = ($jenis_produk === 'custom') ? 'C' : ''; // penanda produk custom
    $prefix  = $kat . $penanda . '-';

    // Cari kode terakhir dengan prefix yang sama untuk menentukan nomor urut berikutnya
    $stmt = $koneksi->prepare("SELECT kode_produk FROM produk WHERE kode_produk LIKE ? ORDER BY kode_produk DESC LIMIT 1");
    $stmt->execute([$prefix . '%']);
    $terakhir = $stmt->fetchColumn();

    $nomor = 1;
    if ($terakhir) {
        $angka = (int) substr($terakhir, strlen($prefix));
        $nomor = $angka + 1;
    }

    return $prefix . str_pad((string)$nomor, 3, '0', STR_PAD_LEFT);
}

/* =====================================================================
   PROSES POST: SIMPAN / UPDATE / HAPUS
   ===================================================================== */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama_produk = trim($_POST['nama_produk'] ?? '');
    $kategori    = trim($_POST['kategori'] ?? '');
    $harga       = (int)($_POST['harga'] ?? 0);
    $stok        = (int)($_POST['stok'] ?? 0);
    $min_order   = (int)($_POST['min_order'] ?? 1);
    $deskripsi   = trim($_POST['deskripsi'] ?? '');
    $status      = $_POST['status'] ?? 'aktif';
    $bahan       = trim($_POST['bahan'] ?? '');   // disimpan ke kolom `jenis`
    $ukuran      = trim($_POST['ukuran'] ?? '');
    $jenis_produk = ($_POST['jenis_penjualan'] ?? 'jadi') === 'custom' ? 'custom' : 'jadi';

    /* Upload gambar
       Ekstensi yang diterima: jpg, jpeg, png, webp, dan jfif (format JPEG
       yang biasanya muncul kalau gambar disimpan dari browser/Bing/Google
       Images). Secara teknis file .jfif isinya JPEG biasa, jadi cukup
       ditambahkan ke daftar ekstensi yang diizinkan — tidak perlu proses
       konversi khusus. */
    $nama_gambar = $_POST['gambar_lama'] ?? null;
    if (!empty($_FILES['gambar']['name'])) {
        $ext_ok = ['jpg','jpeg','png','webp','jfif'];
        $ext = strtolower(pathinfo($_FILES['gambar']['name'], PATHINFO_EXTENSION));
        if (in_array($ext, $ext_ok) && $_FILES['gambar']['size'] <= 2 * 1024 * 1024) {
            $folder = __DIR__ . '/../../uploads/produk/';
            if (!is_dir($folder)) mkdir($folder, 0777, true);
            $nama_gambar = uniqid('produk_') . '.' . $ext;
            move_uploaded_file($_FILES['gambar']['tmp_name'], $folder . $nama_gambar);
        }
    }

    if (isset($_POST['simpan'])) {
        /* --- TAMBAH BARU: kode produk dibuat otomatis dari kategori --- */
        $stmt = $koneksi->prepare("
            INSERT INTO produk (kode_produk, nama_produk, kategori, harga_dasar, stok, min_order, deskripsi, gambar, status, jenis, ukuran, jenis_produk)
            VALUES (:kode, :nama, :kat, :harga, :stok, :min, :desk, :gambar, :status, :jenis, :ukuran, :jenis_produk)
        ");

        $maxCoba = 3; // jaga-jaga kalau ada tabrakan kode saat request bersamaan
        for ($coba = 1; $coba <= $maxCoba; $coba++) {
            $kode_produk = buatKodeProduk($koneksi, $kategori, $jenis_produk);
            try {
                $stmt->execute([
                    ':kode'   => $kode_produk,
                    ':nama'   => $nama_produk, ':kat'    => $kategori,
                    ':harga'  => $harga,       ':stok'   => $stok,
                    ':min'    => $min_order,   ':desk'   => $deskripsi,
                    ':gambar' => $nama_gambar, ':status' => $status,
                    ':jenis'  => $bahan,       ':ukuran' => $ukuran,
                    ':jenis_produk' => $jenis_produk,
                ]);
                $_SESSION['pesan'] = "Produk berhasil ditambahkan dengan kode {$kode_produk}.";
                $_SESSION['pesan_type'] = 'ok';
                break;
            } catch (PDOException $e) {
                $duplikatKode = strpos($e->getMessage(), 'kode_produk') !== false;
                if ($duplikatKode && $coba < $maxCoba) {
                    continue; // coba generate ulang kode
                }
                $_SESSION['pesan'] = $duplikatKode
                    ? 'Gagal membuat kode produk unik, silakan coba simpan ulang.'
                    : 'Produk gagal disimpan, silakan coba lagi.';
                $_SESSION['pesan_type'] = 'error';
                header('Location: index.php?aksi=tambah');
                exit;
            }
        }

    } elseif (isset($_POST['update'])) {
        /* --- UPDATE ---
           Kode produk tidak diketik manual. Kalau produk lama sudah punya
           kode, kode itu dipertahankan (hidden field kode_produk_lama).
           Kalau kosong (data lama sebelum fitur ini ada), baru dibuatkan
           kode otomatis dari kategori. */
        $kode_produk = trim($_POST['kode_produk_lama'] ?? '');
        if ($kode_produk === '') {
            $kode_produk = buatKodeProduk($koneksi, $kategori, $jenis_produk);
        }

        try {
            $stmt = $koneksi->prepare("
                UPDATE produk SET
                    kode_produk = :kode, nama_produk = :nama, kategori = :kat, harga_dasar = :harga,
                    stok = :stok, min_order = :min, deskripsi = :desk,
                    gambar = :gambar, status = :status,
                    jenis = :jenis, ukuran = :ukuran, jenis_produk = :jenis_produk
                WHERE id_produk = :id
            ");
            $stmt->execute([
                ':kode'   => $kode_produk,
                ':nama'   => $nama_produk, ':kat'    => $kategori,
                ':harga'  => $harga,       ':stok'   => $stok,
                ':min'    => $min_order,   ':desk'   => $deskripsi,
                ':gambar' => $nama_gambar, ':status' => $status,
                ':jenis'  => $bahan,       ':ukuran' => $ukuran,
                ':jenis_produk' => $jenis_produk,
                ':id'     => (int)$_POST['id_produk'],
            ]);
            $_SESSION['pesan'] = 'Produk berhasil diperbarui.';
            $_SESSION['pesan_type'] = 'ok';
        } catch (PDOException $e) {
            $_SESSION['pesan'] = (strpos($e->getMessage(), 'kode_produk') !== false)
                ? 'Kode produk bentrok, silakan simpan ulang.'
                : 'Produk gagal diperbarui, silakan coba lagi.';
            $_SESSION['pesan_type'] = 'error';
            header('Location: index.php?aksi=edit&id=' . (int)$_POST['id_produk']);
            exit;
        }
    }

    header('Location: index.php');
    exit;
}

/* HAPUS */
if ($aksi === 'hapus' && $id > 0) {
    $koneksi->prepare("DELETE FROM produk WHERE id_produk = ?")->execute([$id]);
    $_SESSION['pesan'] = 'Produk berhasil dihapus.';
    $_SESSION['pesan_type'] = 'ok';
    header('Location: index.php');
    exit;
}

/* TOGGLE STATUS */
if ($aksi === 'toggle' && $id > 0) {
    $r = $koneksi->prepare("SELECT status FROM produk WHERE id_produk = ?")->execute([$id]);
    $row = $koneksi->query("SELECT status FROM produk WHERE id_produk = $id")->fetch();
    $newStatus = ($row['status'] === 'aktif') ? 'nonaktif' : 'aktif';
    $koneksi->prepare("UPDATE produk SET status = ? WHERE id_produk = ?")->execute([$newStatus, $id]);
    $_SESSION['pesan'] = 'Status produk diperbarui.';
    $_SESSION['pesan_type'] = 'ok';
    header('Location: index.php');
    exit;
}

/* Data untuk form edit */
$produk_edit = null;
if ($aksi === 'edit' && $id > 0) {
    $stmt = $koneksi->prepare("SELECT * FROM produk WHERE id_produk = ?");
    $stmt->execute([$id]);
    $produk_edit = $stmt->fetch();
}

/* Data list produk */
$produk_list = $koneksi->query("SELECT * FROM produk ORDER BY nama_produk ASC")->fetchAll();

function rupiah(int $n): string { return 'Rp' . number_format($n, 0, ',', '.'); }
function stokClass(int $s): string {
    if ($s === 0) return 'produk-table__stock--empty';
    if ($s <= 20) return 'produk-table__stock--low';
    return 'produk-table__stock--ok';
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<link rel="icon" type="image/svg+xml" href="../../assets/img/logo.svg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Kelola Produk — CD 133 Production</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../../assets/css/tokens.css">
<link rel="stylesheet" href="../../assets/css/admin_produk.css">
<link rel="stylesheet" href="../../assets/css/admin_index.css">
<style>
    .jenis-penjualan {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 14px;
        margin-bottom: 28px;
    }
    .jenis-penjualan__card {
        display: flex;
        gap: 12px;
        align-items: flex-start;
        border: 1.5px solid var(--border, #e2e2e2);
        border-radius: 12px;
        padding: 16px;
        cursor: pointer;
        transition: border-color .15s ease, background .15s ease;
    }
    .jenis-penjualan__card:hover {
        border-color: var(--denim, #1f2a44);
    }
    .jenis-penjualan__card input {
        margin-top: 3px;
    }
    .jenis-penjualan__card.is-selected {
        border-color: var(--denim, #1f2a44);
        background: rgba(31, 42, 68, 0.04);
    }
    .jenis-penjualan__card strong {
        display: block;
        font-family: 'Sora', sans-serif;
        font-size: 14px;
        margin-bottom: 3px;
    }
    .jenis-penjualan__card p {
        margin: 0;
        font-size: 12.5px;
        color: var(--ink-soft, #6b7280);
        line-height: 1.4;
    }
</style>
</head>
<body>
<div class="app">

    <!-- SIDEBAR -->
    <aside class="sidebar" id="sidebar">
        <div class="sidebar__brand">
            <div class="sidebar__mark">CD</div>
            <div class="sidebar__brand-text">
                <strong>CD 133 Production</strong>
                <span>Panel Internal</span>
            </div>
        </div>
        <nav class="sidebar__nav">
            <a href="../index.php" class="sidebar__link"><span class="sidebar__icon">▦</span> Dashboard</a>
            <a href="index.php" class="sidebar__link"><span class="sidebar__icon">▤</span> Kelola Produk</a>
            <a href="../pembayaran/index.php" class="sidebar__link"><span class="sidebar__icon">◇</span> Verifikasi Pembayaran</a>
        </nav>
        <a href="../../logout.php" class="sidebar__logout"><span class="sidebar__icon">⎋</span> Logout</a>
    </aside>
    <div class="sidebar__overlay" id="sidebarOverlay"></div>

    <main class="main">

        <!-- Toast notifikasi -->
        <?php if ($pesan): ?>
        <div class="toast <?= $pesan_type === 'ok' ? '' : 'toast--danger' ?> is-visible" id="toast">
            <?= htmlspecialchars($pesan) ?>
        </div>
        <script>setTimeout(() => document.getElementById('toast').classList.remove('is-visible'), 3000);</script>
        <?php endif; ?>

        <?php if ($aksi === 'tambah' || $aksi === 'edit'): ?>
        <!-- ======================== FORM TAMBAH/EDIT ======================== -->
        <div class="page-header">
            <div>
                <h1 class="page-header__title"><?= $aksi === 'edit' ? 'Edit Produk' : 'Tambah Produk Baru' ?></h1>
                <div class="page-header__breadcrumb">
                    <a href="index.php">Kelola Produk</a>
                    <span>/</span>
                    <span><?= $aksi === 'edit' ? 'Edit' : 'Tambah' ?></span>
                </div>
            </div>
        </div>

        <?php
            // Preselect pilihan "Produk Jadi" / "Produk Custom" saat edit, dari kolom `jenis_produk`.
            $jenis_penjualan_terpilih = ($produk_edit['jenis_produk'] ?? 'jadi') === 'custom' ? 'custom' : 'jadi';
        ?>

        <form class="form-panel" method="post" action="index.php" enctype="multipart/form-data">
            <?php if ($produk_edit): ?>
                <input type="hidden" name="id_produk" value="<?= $produk_edit['id_produk'] ?>">
                <input type="hidden" name="gambar_lama" value="<?= htmlspecialchars($produk_edit['gambar'] ?? '') ?>">
                <input type="hidden" name="kode_produk_lama" value="<?= htmlspecialchars($produk_edit['kode_produk'] ?? '') ?>">
            <?php endif; ?>

            <div class="form-panel__head">
                <h2 class="form-panel__title">Jenis Penjualan</h2>
                <span class="form-panel__hint">Pilih dulu sebelum mengisi detail produk di bawah.</span>
            </div>

            <div class="jenis-penjualan" id="jenisPenjualan">
                <label class="jenis-penjualan__card" data-value="jadi">
                    <input type="radio" name="jenis_penjualan" value="jadi"
                           <?= $jenis_penjualan_terpilih === 'jadi' ? 'checked' : '' ?>>
                    <div>
                        <strong>Produk Jadi</strong>
                        <p>Sudah tersedia stoknya, siap dikirim/diambil langsung saat dipesan.</p>
                    </div>
                </label>
                <label class="jenis-penjualan__card" data-value="custom">
                    <input type="radio" name="jenis_penjualan" value="custom"
                           <?= $jenis_penjualan_terpilih === 'custom' ? 'checked' : '' ?>>
                    <div>
                        <strong>Produk Custom</strong>
                        <p>Dibuat sesuai desain & spesifikasi pesanan customer, tanpa stok tetap.</p>
                    </div>
                </label>
            </div>

            <div class="form-panel__head">
                <h2 class="form-panel__title">Informasi Produk</h2>
                <span class="form-panel__hint">Field bertanda <span style="color:red">*</span> wajib diisi.</span>
            </div>

            <div class="form-grid">

                <div class="form-group">
                    <label class="form-label">Nama Produk <span class="form-label__required">*</span></label>
                    <input type="text" name="nama_produk" class="form-control" required
                           placeholder="Contoh: Kaos Custom" value="<?= htmlspecialchars($produk_edit['nama_produk'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Kode Produk
                        <span class="form-label__hint">Dibuat otomatis dari kategori produk.</span>
                    </label>
                    <input type="text" class="form-control" disabled
                           placeholder="Akan dibuat otomatis setelah disimpan"
                           value="<?= htmlspecialchars($produk_edit['kode_produk'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Kategori <span class="form-label__required">*</span></label>
                    <input type="text" name="kategori" class="form-control" required
                           placeholder="Contoh: Kaos, Hoodie, Kemeja" value="<?= htmlspecialchars($produk_edit['kategori'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Harga Dasar (Rp/pcs) <span class="form-label__required">*</span>
                        <span class="form-label__hint">Harga awal, bisa berubah sesuai qty.</span>
                    </label>
                    <input type="number" name="harga" class="form-control" min="0" required
                           placeholder="65000" value="<?= $produk_edit['harga_dasar'] ?? '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Bahan
                        <span class="form-label__hint">Contoh: Cotton Combed 24s, Denim, Drill.</span>
                    </label>
                    <input type="text" name="bahan" class="form-control"
                           placeholder="Contoh: Cotton Combed 24s" value="<?= htmlspecialchars($produk_edit['jenis'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Ukuran
                        <span class="form-label__hint">Contoh: S, M, L, XL atau "Sesuai request".</span>
                    </label>
                    <input type="text" name="ukuran" class="form-control"
                           placeholder="Contoh: S, M, L, XL" value="<?= htmlspecialchars($produk_edit['ukuran'] ?? '') ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Stok Kapasitas (pcs) <span class="form-label__required">*</span>
                        <span class="form-label__hint" id="stokHint">Berkurang otomatis saat pesanan masuk.</span>
                    </label>
                    <input type="number" id="stokInput" name="stok" class="form-control" min="0" required
                           placeholder="100" value="<?= $produk_edit['stok'] ?? '' ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Minimum Order (pcs)
                        <span class="form-label__hint">Minimal pesanan per transaksi.</span>
                    </label>
                    <input type="number" name="min_order" class="form-control" min="1"
                           placeholder="12" value="<?= $produk_edit['min_order'] ?? 12 ?>">
                </div>

                <div class="form-group">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-control">
                        <option value="aktif" <?= ($produk_edit['status'] ?? 'aktif') === 'aktif' ? 'selected' : '' ?>>Aktif</option>
                        <option value="nonaktif" <?= ($produk_edit['status'] ?? '') === 'nonaktif' ? 'selected' : '' ?>>Nonaktif</option>
                    </select>
                </div>

                <div class="form-group form-group--full">
                    <label class="form-label">Deskripsi
                        <span class="form-label__hint">Bahan, detail, keterangan tambahan (opsional).</span>
                    </label>
                    <textarea name="deskripsi" class="form-control" rows="3"
                              placeholder="Contoh: Bahan Cotton Combed 24s, tersedia sablon dan bordir."><?= htmlspecialchars($produk_edit['deskripsi'] ?? '') ?></textarea>
                </div>

                <div class="form-group form-group--full">
                    <label class="form-label">Foto Produk
                        <span class="form-label__hint">JPG/JPEG/PNG/WEBP/JFIF, maks 2MB. Kosongkan jika tidak ingin mengganti foto.</span>
                    </label>
                    <div class="file-upload">
                        <label class="file-upload__dropzone" id="gambarDropzone">
                            <div class="file-upload__placeholder" id="gambarPlaceholder">📷</div>
                            <img class="file-upload__preview" id="gambarPreview" alt="Preview">
                            <div class="file-upload__info">
                                <strong>Klik atau seret gambar ke sini</strong>
                                <span>Format JPG, JPEG, PNG, WEBP, JFIF — maksimal 2MB</span>
                            </div>
                            <button type="button" class="file-upload__remove" id="gambarRemove">Hapus</button>
                        </label>
                        <input type="file" id="gambarInput" name="gambar" class="file-upload__input"
                               accept="image/*,.jfif">
                    </div>
                    <?php if (!empty($produk_edit['gambar'])): ?>
                        <p style="font-size:12px;color:var(--ink-soft);margin-top:6px;">
                            Foto saat ini: <?= htmlspecialchars($produk_edit['gambar']) ?> — upload baru untuk mengganti.
                        </p>
                    <?php endif; ?>
                </div>

            </div>

            <div class="form-actions">
                <button type="submit" name="<?= $aksi === 'edit' ? 'update' : 'simpan' ?>" class="action-btn action-btn--primary">
                    💾 <?= $aksi === 'edit' ? 'Update Produk' : 'Simpan Produk' ?>
                </button>
                <a href="index.php" class="action-btn">Batal</a>
            </div>
        </form>

        <script>
        (function () {
            var kartuJenis = document.querySelectorAll('#jenisPenjualan .jenis-penjualan__card');
            var stokHint   = document.getElementById('stokHint');

            function perbaruiTampilan() {
                kartuJenis.forEach(function (kartu) {
                    var radio = kartu.querySelector('input[type="radio"]');
                    kartu.classList.toggle('is-selected', radio.checked);

                    if (radio.checked && stokHint) {
                        stokHint.textContent = radio.value === 'custom'
                            ? 'Produk custom dibuat sesuai pesanan — stok boleh diisi 0.'
                            : 'Berkurang otomatis saat pesanan masuk.';
                    }
                });
            }

            kartuJenis.forEach(function (kartu) {
                kartu.querySelector('input[type="radio"]').addEventListener('change', perbaruiTampilan);
            });

            perbaruiTampilan();
        })();
        </script>

        <?php else: ?>
        <!-- ======================== LIST PRODUK ======================== -->
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Kelola Produk</h1>
                <p style="color:var(--ink-soft);font-size:13px;margin:4px 0 0;">
                    <?= count($produk_list) ?> produk terdaftar
                </p>
            </div>
            <a href="index.php?aksi=tambah" class="action-btn action-btn--thread">+ Tambah Produk</a>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <input type="text" id="searchInput" class="filter-bar__search" placeholder="Cari nama produk atau kategori...">
            <select id="statusFilter" class="filter-bar__select">
                <option value="all">Semua Status</option>
                <option value="aktif">Aktif</option>
                <option value="nonaktif">Nonaktif</option>
            </select>
            <span class="filter-bar__count" id="visibleCount"><?= count($produk_list) ?> produk</span>
        </div>

        <div class="produk-table">
            <div class="produk-table__wrap">
                <table id="tblProduk">
                    <thead>
                        <tr>
                            <th style="width:50px">No</th>
                            <th>Produk</th>
                            <th>Harga/pcs</th>
                            <th>Min Order</th>
                            <th>Stok Kapasitas</th>
                            <th>Status</th>
                            <th style="width:200px">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($produk_list)): ?>
                        <tr><td colspan="7">
                            <div class="empty-state">
                                <div class="empty-state__icon">📦</div>
                                <p class="empty-state__title">Belum ada produk</p>
                                <p class="empty-state__text">Tambahkan produk pertama untuk ditampilkan di katalog customer.</p>
                                <a href="index.php?aksi=tambah" class="action-btn action-btn--thread">+ Tambah Produk</a>
                            </div>
                        </td></tr>
                        <?php else: ?>
                        <?php foreach ($produk_list as $i => $p): ?>
                        <tr data-nama="<?= strtolower($p['nama_produk'] . ' ' . ($p['kategori'] ?? '')) ?>"
                            data-status="<?= $p['status'] ?>">
                            <td><?= $i + 1 ?></td>
                            <td>
                                <div class="produk-cell">
                                    <?php if (!empty($p['gambar']) && file_exists("../../uploads/produk/{$p['gambar']}")): ?>
                                        <img class="produk-cell__thumb" src="../../uploads/produk/<?= htmlspecialchars($p['gambar']) ?>" alt="">
                                    <?php else: ?>
                                        <div class="produk-cell__thumb produk-cell__thumb--placeholder">👕</div>
                                    <?php endif; ?>
                                    <div>
                                        <div class="produk-cell__name"><?= htmlspecialchars($p['nama_produk']) ?></div>
                                        <?php if (!empty($p['kode_produk'])): ?>
                                            <span style="font-size:11px;color:var(--ink-soft);">Kode: <?= htmlspecialchars($p['kode_produk']) ?></span><br>
                                        <?php endif; ?>
                                        <?php if (!empty($p['kategori'])): ?>
                                            <span class="produk-cell__category"><?= htmlspecialchars($p['kategori']) ?></span>
                                        <?php endif; ?>
                                        <?php if (!empty($p['deskripsi'])): ?>
                                            <div style="font-size:11.5px;color:var(--ink-soft);margin-top:2px;max-width:260px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                                                <?= htmlspecialchars($p['deskripsi']) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td class="produk-table__price"><?= rupiah((int)$p['harga_dasar']) ?></td>
                            <td><?= (int)($p['min_order'] ?? 1) ?> pcs</td>
                            <td>
                                <?php $stok = (int)$p['stok']; ?>
                                <span class="produk-table__stock <?= stokClass($stok) ?>">
                                    <?= $stok ?> pcs
                                </span>
                                <?php if ($stok <= 20): ?>
                                    <div style="font-size:11px;color:var(--warn);margin-top:2px;">
                                        <?= $stok === 0 ? '⚠ Stok habis' : '⚠ Stok rendah' ?>
                                    </div>
                                <?php endif; ?>
                            </td>
                            <td>
                                <a href="index.php?aksi=toggle&id=<?= $p['id_produk'] ?>"
                                   style="text-decoration:none;">
                                    <?php if ($p['status'] === 'aktif'): ?>
                                        <span style="background:var(--done-soft);color:var(--done);padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;">Aktif</span>
                                    <?php else: ?>
                                        <span style="background:var(--border);color:var(--ink-soft);padding:3px 10px;border-radius:999px;font-size:12px;font-weight:600;">Nonaktif</span>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td>
                                <div class="action-group">
                                    <a href="index.php?aksi=edit&id=<?= $p['id_produk'] ?>" class="action-btn">✎ Edit</a>
                                    <a href="#" class="action-btn action-btn--danger"
                                       data-delete-url="index.php?aksi=hapus&id=<?= $p['id_produk'] ?>"
                                       data-product-name="<?= htmlspecialchars($p['nama_produk']) ?>">
                                       🗑 Hapus
                                    </a>
                                </div>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal hapus -->
        <div class="modal-overlay" id="deleteModal">
            <div class="modal">
                <div class="modal__icon">⚠</div>
                <h3 class="modal__title">Hapus Produk?</h3>
                <p class="modal__message">Produk "<span id="modalProdukNama"></span>" akan dihapus permanen.</p>
                <div class="modal__actions">
                    <button class="action-btn" data-cancel-delete>Batal</button>
                    <button class="action-btn action-btn--danger" data-confirm-delete>Ya, Hapus</button>
                </div>
            </div>
        </div>

        <script>
        // Filter pencarian
        const rows = document.querySelectorAll('#tblProduk tbody tr[data-nama]');
        const countEl = document.getElementById('visibleCount');
        function filterTable() {
            const q = document.getElementById('searchInput').value.toLowerCase();
            const s = document.getElementById('statusFilter').value;
            let visible = 0;
            rows.forEach(r => {
                const matchQ = r.dataset.nama.includes(q);
                const matchS = s === 'all' || r.dataset.status === s;
                r.style.display = matchQ && matchS ? '' : 'none';
                if (matchQ && matchS) visible++;
            });
            countEl.textContent = visible + ' produk';
        }
        document.getElementById('searchInput').addEventListener('input', filterTable);
        document.getElementById('statusFilter').addEventListener('change', filterTable);

        // Modal hapus
        let deleteUrl = '';
        document.querySelectorAll('[data-delete-url]').forEach(btn => {
            btn.addEventListener('click', e => {
                e.preventDefault();
                deleteUrl = btn.dataset.deleteUrl;
                document.getElementById('modalProdukNama').textContent = btn.dataset.productName;
                document.getElementById('deleteModal').classList.add('is-visible');
            });
        });
        document.querySelector('[data-cancel-delete]').addEventListener('click', () => {
            document.getElementById('deleteModal').classList.remove('is-visible');
        });
        document.querySelector('[data-confirm-delete]').addEventListener('click', () => {
            window.location.href = deleteUrl;
        });
        </script>
        <?php endif; ?>

    </main>
</div>

<script src="../../assets/js/admin_index.js"></script>
<script src="../../assets/js/admin_produk.js"></script>
</body>
</html>