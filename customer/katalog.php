<?php
require_once __DIR__ . '/../config/koneksi.php';

/**
 * Katalog Produk — menampilkan seluruh produk dengan status = 'aktif'
 * langsung dari tabel `produk`. Mendukung pencarian sederhana lewat ?cari=
 * dan link "Pesan Sekarang" yang mengarah ke pesan.php dengan produk
 * sudah terisi otomatis (lihat catatan di bagian bawah file).
 */

$kata_kunci = trim($_GET['cari'] ?? '');
$produk_list = [];
$error_query = false;

try {
    if ($kata_kunci !== '') {
        $stmt = $koneksi->prepare("
            SELECT *
            FROM produk
            WHERE status = 'aktif'
              AND nama_produk LIKE :kata_kunci
            ORDER BY nama_produk ASC
        ");
        $stmt->execute([':kata_kunci' => '%' . $kata_kunci . '%']);
    } else {
        $stmt = $koneksi->query("
            SELECT *
            FROM produk
            WHERE status = 'aktif'
            ORDER BY nama_produk ASC
        ");
    }
    $produk_list = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_query = true;
    $produk_list = [];
}

/**
 * Kolom gambar & deskripsi bersifat opsional — kalau tabel `produk`
 * belum punya kolom itu, katalog tetap tampil tanpa error.
 */
function ambilKolom(array $row, string $kolom, $default = null) {
    return array_key_exists($kolom, $row) && $row[$kolom] !== null ? $row[$kolom] : $default;
}

function labelStok(int $stok): array {
    if ($stok <= 0) {
        return ['Habis', 'stock-badge--habis'];
    } elseif ($stok <= 10) {
        return ['Stok Terbatas', 'stock-badge--terbatas'];
    }
    return ['Tersedia', 'stock-badge--tersedia'];
}

/**
 * Pemisah "Produk Jadi" vs "Produk Custom" memakai kolom `jenis_produk`
 * (nilainya 'jadi' atau 'custom', diisi lewat form admin Kelola Produk).
 * Kalau kolom itu kosong/null (misal data lama), fallback ke kategori
 * yang mengandung kata "custom" supaya tidak error.
 */
function isProdukCustom(array $produk): bool {
    $jenisProduk = strtolower((string) ambilKolom($produk, 'jenis_produk', ''));
    if ($jenisProduk !== '') {
        return $jenisProduk === 'custom';
    }
    $kategori = strtolower((string) ambilKolom($produk, 'kategori', ''));
    return strpos($kategori, 'custom') !== false;
}

$produk_jadi   = [];
$produk_custom = [];

foreach ($produk_list as $produk) {
    if (isProdukCustom($produk)) {
        $produk_custom[] = $produk;
    } else {
        $produk_jadi[] = $produk;
    }
}

/** Render satu kartu produk. Dipakai bareng oleh section Produk Jadi & Produk Custom. */
function tampilkanKartuProduk(array $produk): void {
    $nama       = ambilKolom($produk, 'nama_produk', '-');
    $harga      = ambilKolom($produk, 'harga_dasar');
    $stok       = (int) ambilKolom($produk, 'stok', 0);
    $min_order  = (int) ambilKolom($produk, 'min_order', 1);
    $deskripsi  = ambilKolom($produk, 'deskripsi');
    $gambar     = ambilKolom($produk, 'gambar');
    [$label_stok, $kelas_stok] = labelStok($stok);
    ?>
    <div class="product-card">
        <div class="product-card__image">
            <?php if ($gambar): ?>
                <img src="../uploads/produk/<?= htmlspecialchars($gambar) ?>" alt="<?= htmlspecialchars($nama) ?>">
            <?php else: ?>
                <span>Belum ada foto</span>
            <?php endif; ?>
        </div>
        <div class="product-card__body">
            <h3 class="product-card__name"><?= htmlspecialchars($nama) ?></h3>
            <?php if ($deskripsi): ?>
                <p class="product-card__desc"><?= htmlspecialchars($deskripsi) ?></p>
            <?php endif; ?>
            <div class="product-card__meta">
                <span>Min. order: <?= $min_order ?> pcs</span>
                <span class="stock-badge <?= $kelas_stok ?>"><?= $label_stok ?></span>
            </div>
            <div class="product-card__price">
                <?= $harga !== null ? 'Rp' . number_format((float) $harga, 0, ',', '.') : 'Harga cek admin' ?>
                <?php if ($harga !== null): ?><span style="font-size:12px; font-weight:400; color:#6b7280;">/pcs</span><?php endif; ?>
            </div>
        </div>
        <div class="product-card__footer">
            <?php if ($stok > 0): ?>
                <a class="btn btn--accent" href="pesan.php?jenis_produk=<?= urlencode($nama) ?>">Pesan Sekarang</a>
            <?php else: ?>
                <span class="btn is-disabled">Stok Habis</span>
            <?php endif; ?>
        </div>
    </div>
    <?php
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Katalog Produk — CD 133 Production</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/public.css">
<style>
    /* Gaya khusus katalog — mengikuti token warna & spacing yang sudah ada di public.css */
    .catalog-toolbar {
        display: flex;
        gap: 12px;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        margin-bottom: 24px;
    }
    .catalog-search {
        display: flex;
        gap: 8px;
        flex: 1;
        min-width: 240px;
        max-width: 420px;
    }
    .catalog-search input {
        flex: 1;
        padding: 10px 14px;
        border: 1px solid var(--border, #ddd);
        border-radius: 8px;
        font-family: inherit;
        font-size: 14px;
    }
    .catalog-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
        gap: 20px;
    }
    .product-card {
        border: 1px solid var(--border, #e2e2e2);
        border-radius: 14px;
        overflow: hidden;
        background: #fff;
        display: flex;
        flex-direction: column;
        transition: box-shadow .15s ease, transform .15s ease;
    }
    .product-card:hover {
        box-shadow: 0 6px 18px rgba(0,0,0,.08);
        transform: translateY(-2px);
    }
    .product-card__image {
        width: 100%;
        aspect-ratio: 4 / 3;
        background: #f4f5f7;
        display: flex;
        align-items: center;
        justify-content: center;
        color: #b7bcc4;
        font-size: 13px;
        overflow: hidden;
    }
    .product-card__image img {
        width: 100%;
        height: 100%;
        object-fit: cover;
    }
    .product-card__body {
        padding: 16px;
        display: flex;
        flex-direction: column;
        gap: 8px;
        flex: 1;
    }
    .product-card__name {
        font-family: 'Sora', sans-serif;
        font-size: 16px;
        font-weight: 600;
        color: var(--denim, #1f2a44);
        margin: 0;
    }
    .product-card__desc {
        font-size: 13px;
        color: #6b7280;
        margin: 0;
        line-height: 1.5;
    }
    .product-card__price {
        font-size: 17px;
        font-weight: 700;
        color: var(--denim, #1f2a44);
        margin-top: auto;
    }
    .product-card__meta {
        display: flex;
        justify-content: space-between;
        align-items: center;
        font-size: 12px;
        color: #6b7280;
    }
    .stock-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 999px;
        font-size: 11px;
        font-weight: 600;
    }
    .stock-badge--tersedia { background: #e6f7ec; color: #1a7f43; }
    .stock-badge--terbatas { background: #fff4e0; color: #b06a00; }
    .stock-badge--habis    { background: #fdecec; color: #b3261e; }
    .product-card__footer {
        padding: 0 16px 16px;
    }
    .product-card__footer .btn {
        width: 100%;
        text-align: center;
    }
    .btn[disabled], .btn.is-disabled {
        opacity: .5;
        pointer-events: none;
    }
    .catalog-empty {
        text-align: center;
        padding: 60px 20px;
        color: #6b7280;
    }
    .catalog-section {
        margin-bottom: 40px;
    }
    .catalog-section__head {
        margin-bottom: 16px;
    }
    .catalog-section__title {
        font-family: 'Sora', sans-serif;
        font-size: 20px;
        font-weight: 700;
        color: var(--denim, #1f2a44);
        margin: 0 0 4px;
    }
    .catalog-section__desc {
        font-size: 13px;
        color: #6b7280;
        margin: 0;
    }
    .catalog-section__empty {
        border: 1px dashed var(--border, #e2e2e2);
        border-radius: 12px;
        padding: 24px;
        text-align: center;
        color: #6b7280;
        font-size: 13px;
    }
</style>
</head>
<body>

<nav class="public-nav">
    <div class="public-nav__brand">
        <span class="public-nav__mark">133</span>
        CD 133 Production
    </div>
    <div class="public-nav__links">
        <a href="dashboard.php">Beranda</a>
        <a href="katalog.php" class="is-active">Katalog Produk</a>
        <a href="pesan.php">Pesan Sekarang</a>
        <a href="lacak_pesanan.php">Lacak Pesanan</a>
    </div>
</nav>

<div class="public-wrap">

    <div class="page-head">
        <p class="page-head__eyebrow">Katalog</p>
        <h1>Katalog Produk</h1>
        <p>Pilih produk yang kamu butuhkan, cek harga dan stoknya, lalu langsung pesan.</p>
    </div>

    <?php if ($error_query): ?>
        <div class="alert alert--error" style="margin-bottom:16px; padding:12px 16px; border:1px solid #e33; background:#fee; border-radius:8px; color:#a00;">
            <p style="margin:0;">Katalog belum bisa dimuat. Silakan coba lagi beberapa saat lagi.</p>
        </div>
    <?php endif; ?>

    <div class="catalog-toolbar">
        <form class="catalog-search" method="get" action="katalog.php">
            <input type="text" name="cari" placeholder="Cari produk, contoh: Kaos Polos"
                   value="<?= htmlspecialchars($kata_kunci) ?>">
            <button type="submit" class="btn btn--accent">Cari</button>
        </form>
        <?php if ($kata_kunci !== ''): ?>
            <a href="katalog.php" class="btn btn--ghost" style="color:var(--denim); border-color:var(--border);">Reset</a>
        <?php endif; ?>
    </div>

    <?php if (empty($produk_list) && !$error_query): ?>

        <div class="catalog-empty">
            <?php if ($kata_kunci !== ''): ?>
                <p>Produk dengan kata kunci "<?= htmlspecialchars($kata_kunci) ?>" tidak ditemukan.</p>
            <?php else: ?>
                <p>Belum ada produk aktif yang bisa ditampilkan saat ini.</p>
            <?php endif; ?>
        </div>

    <?php elseif (!$error_query): ?>

        <div class="catalog-section">
            <div class="catalog-section__head">
                <h2 class="catalog-section__title">Produk Jadi</h2>
                <p class="catalog-section__desc">Produk siap kirim/ambil dengan stok yang sudah tersedia.</p>
            </div>

            <?php if (empty($produk_jadi)): ?>
                <div class="catalog-section__empty">
                    <?= $kata_kunci !== '' ? 'Tidak ada produk jadi yang cocok dengan pencarian kamu.' : 'Belum ada produk jadi yang tersedia saat ini.' ?>
                </div>
            <?php else: ?>
                <div class="catalog-grid">
                    <?php foreach ($produk_jadi as $produk): ?>
                        <?php tampilkanKartuProduk($produk); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <div class="catalog-section">
            <div class="catalog-section__head">
                <h2 class="catalog-section__title">Produk Custom</h2>
                <p class="catalog-section__desc">Produk yang dibuat sesuai desain dan spesifikasi pesanan kamu.</p>
            </div>

            <?php if (empty($produk_custom)): ?>
                <div class="catalog-section__empty">
                    <?= $kata_kunci !== '' ? 'Tidak ada produk custom yang cocok dengan pencarian kamu.' : 'Belum ada produk custom yang tersedia saat ini.' ?>
                </div>
            <?php else: ?>
                <div class="catalog-grid">
                    <?php foreach ($produk_custom as $produk): ?>
                        <?php tampilkanKartuProduk($produk); ?>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

    <?php endif; ?>

</div>

<footer class="public-footer">
    © 2026 CD 133 Production — Konveksi Custom. Semua hak cipta dilindungi.
</footer>

</body>
</html>