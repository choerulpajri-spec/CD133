<?php
require_once __DIR__ . '/../config/koneksi.php';

/**
 * ASUMSI PENTING soal skema database (sesuaikan kalau beda):
 *
 * - Tabel `produk` punya kolom `jenis_produk` (VARCHAR/ENUM) dengan
 *   nilai 'jadi' untuk Produk Jadi dan 'custom' untuk Produk Custom.
 *
 * - Tabel `pesanan` punya kolom `ukuran` dan `bahan` (VARCHAR, boleh NULL).
 *   Kolom `wilayah_tujuan` TIDAK dipakai lagi — wilayah tujuan sekarang
 *   digabung langsung ke dalam kolom `alamat` dengan format:
 *   "(Nama Wilayah, isi alamat)". Kalau metode ambil sendiri (tanpa
 *   wilayah), kolom alamat diisi apa adanya.
 *
 * - Tabel `produk` punya kolom: `ukuran`, `jenis` (alias `bahan`), `harga_dasar`.
 *
 * - Tabel `produk` BELUM punya kolom berat per pcs, jadi perhitungan berat
 *   di bawah ini pakai konstanta rata-rata (BERAT_PER_PCS_KG). Kalau nanti
 *   kolom `berat` sudah ditambahkan ke tabel `produk`, ganti logika di
 *   hitungOngkir() supaya ambil berat aktual per produk, bukan konstanta.
 */

/**
 * Tarif ongkir per zona. Dipakai kalau metode_ambil = 'kurir'.
 * Ubah nilai harga di sini kalau ada penyesuaian tarif.
 */
const TARIF_ONGKIR = [
    'jabar'      => ['label' => 'Jawa Barat',        'harga' => 20000],
    'luar_jabar' => ['label' => 'Luar Jabar (Jawa)', 'harga' => 35000],
    'luar_jawa'  => ['label' => 'Luar Pulau Jawa',    'harga' => 75000],
];

/**
 * Kalau jumlah pesanan (pcs) melebihi BATAS_JUMLAH_BERAT, ongkir tidak lagi
 * cuma tarif flat per zona — ditambah biaya berat (Rp/kg) di atas ambang itu.
 *
 * ASUMSI: rata-rata berat 1 pcs = 0.2 kg. Sesuaikan dengan berat produk
 * kamu yang sebenarnya (idealnya nanti ditarik per-produk dari database).
 */
const BERAT_PER_PCS_KG   = 0.2;
const BATAS_JUMLAH_BERAT = 100;

const TARIF_ONGKIR_PER_KG = [
    'jabar'      => 3000,
    'luar_jabar' => 5000,
    'luar_jawa'  => 8000,
];

function pisahDaftarKoma(?string $mentah): array
{
    if ($mentah === null || trim($mentah) === '') return [];
    $hasil = [];
    foreach (explode(',', $mentah) as $item) {
        $item = trim($item);
        if ($item !== '' && !in_array($item, $hasil, true)) $hasil[] = $item;
    }
    return $hasil;
}

/**
 * Hitung ongkir untuk satu wilayah + jumlah pcs.
 * Kalau jumlah > BATAS_JUMLAH_BERAT, tarif flat ditambah biaya berat
 * (berat_total_kg x tarif_per_kg wilayah tsb).
 */
function hitungOngkir(string $wilayah, int $jumlah): int
{
    if (!array_key_exists($wilayah, TARIF_ONGKIR)) return 0;

    $ongkir = (int) TARIF_ONGKIR[$wilayah]['harga'];

    if ($jumlah > BATAS_JUMLAH_BERAT) {
        $beratTotal = $jumlah * BERAT_PER_PCS_KG;
        $tarifKg    = TARIF_ONGKIR_PER_KG[$wilayah] ?? 0;
        $ongkir    += (int) round($beratTotal * $tarifKg);
    }

    return $ongkir;
}

$daftar_produk_jadi = [];
$harga_produk_jadi  = [];
$produk_info_jadi   = [];

try {
    $stmtProdukJadi = $koneksi->query("
        SELECT nama_produk, harga_dasar, min_order, stok, ukuran, jenis AS bahan
        FROM produk WHERE status = 'aktif' AND jenis_produk = 'jadi'
        ORDER BY nama_produk ASC
    ");
    foreach ($stmtProdukJadi->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $daftar_produk_jadi[$row['nama_produk']] = $row['nama_produk'];
        $harga_produk_jadi[$row['nama_produk']]  = $row['harga_dasar'] !== null ? (float) $row['harga_dasar'] : null;
        $produk_info_jadi[$row['nama_produk']]   = [
            'min_order' => (int) $row['min_order'],
            'stok'      => (int) $row['stok'],
            'ukuran'    => pisahDaftarKoma($row['ukuran'] ?? null),
            'bahan'     => pisahDaftarKoma($row['bahan'] ?? null),
        ];
    }
} catch (PDOException $e) {
    $daftar_produk_jadi = []; $harga_produk_jadi  = []; $produk_info_jadi   = [];
}

$daftar_produk = [];
$harga_produk  = [];
$produk_info   = [];

try {
    $stmtProduk = $koneksi->query("
        SELECT nama_produk, harga_dasar, min_order, stok, ukuran, jenis AS bahan
        FROM produk WHERE status = 'aktif' AND jenis_produk = 'custom'
        ORDER BY nama_produk ASC
    ");
    foreach ($stmtProduk->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $daftar_produk[$row['nama_produk']] = $row['nama_produk'];
        $harga_produk[$row['nama_produk']]  = $row['harga_dasar'] !== null ? (float) $row['harga_dasar'] : null;
        $produk_info[$row['nama_produk']]   = [
            'min_order' => (int) $row['min_order'],
            'stok'      => (int) $row['stok'],
            'ukuran'    => pisahDaftarKoma($row['ukuran'] ?? null),
            'bahan'     => pisahDaftarKoma($row['bahan'] ?? null),
        ];
    }
} catch (PDOException $e) {
    $daftar_produk = []; $harga_produk  = []; $produk_info   = [];
}

$errors  = [];
$berhasil = false;
$kode_pesanan_baru = null;
$total_tagihan = null;
$ongkir = 0;

$data = [
    'nama'             => '',
    'no_hp'            => '',
    'metode_ambil'     => 'ambil_sendiri',
    'alamat'           => '',
    'wilayah_tujuan'   => '',
    'kategori_pesanan' => 'produk_jadi',
    'produk_jadi'      => '',
    'ukuran'           => '',
    'bahan'            => '',
    'jumlah_jadi'      => '',
    'jenis_produk'     => '',
    'ukuran_custom'    => '',
    'bahan_custom'     => '',
    'jumlah'           => '',
    'catatan'          => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data['nama']             = trim($_POST['nama'] ?? '');
    $data['no_hp']            = trim($_POST['no_hp'] ?? '');
    $data['metode_ambil']     = $_POST['metode_ambil'] ?? 'ambil_sendiri';
    $data['alamat']           = trim($_POST['alamat'] ?? '');
    $data['wilayah_tujuan']   = $_POST['wilayah_tujuan'] ?? '';
    $data['kategori_pesanan'] = $_POST['kategori_pesanan'] ?? '';
    $data['produk_jadi']      = trim($_POST['produk_jadi'] ?? '');
    $data['ukuran']           = trim($_POST['ukuran'] ?? '');
    $data['bahan']            = trim($_POST['bahan'] ?? '');
    $data['jumlah_jadi']      = trim($_POST['jumlah_jadi'] ?? '');
    $data['jenis_produk']     = $_POST['jenis_produk'] ?? '';
    $data['ukuran_custom']    = trim($_POST['ukuran_custom'] ?? '');
    $data['bahan_custom']     = trim($_POST['bahan_custom'] ?? '');
    $data['jumlah']           = trim($_POST['jumlah'] ?? '');
    $data['catatan']          = trim($_POST['catatan'] ?? '');

    if ($data['nama'] === '') $errors['nama'] = 'Nama wajib diisi.';
    if ($data['no_hp'] === '') $errors['no_hp'] = 'Nomor HP wajib diisi.';
    elseif (!preg_match('/^[0-9]{9,15}$/', $data['no_hp'])) $errors['no_hp'] = 'Format nomor HP tidak valid (9-15 digit angka).';

    if ($data['metode_ambil'] === 'kurir') {
        if ($data['alamat'] === '') $errors['alamat'] = 'Alamat wajib diisi kalau pesanan diantar kurir.';
        if (!array_key_exists($data['wilayah_tujuan'], TARIF_ONGKIR)) $errors['wilayah_tujuan'] = 'Pilih wilayah tujuan pengiriman.';
    }

    $jenis_produk_final = '';
    $jumlah_final       = '';
    $ukuran_final       = '';
    $bahan_final        = '';

    if ($data['kategori_pesanan'] === '') {
        $errors['kategori_pesanan'] = 'Pilih kategori pesanan dulu.';
    } elseif ($data['kategori_pesanan'] === 'produk_jadi') {
        if (!array_key_exists($data['produk_jadi'], $daftar_produk_jadi)) $errors['produk_jadi'] = 'Pilih produk jadi yang tersedia.';
        if ($data['produk_jadi'] !== '' && isset($produk_info_jadi[$data['produk_jadi']])) {
            if (!in_array($data['ukuran'], $produk_info_jadi[$data['produk_jadi']]['ukuran'], true)) $errors['ukuran'] = 'Pilih ukuran yang tersedia.';
            if (!in_array($data['bahan'], $produk_info_jadi[$data['produk_jadi']]['bahan'], true)) $errors['bahan'] = 'Pilih jenis bahan yang tersedia.';
        } else {
            $errors['ukuran'] = 'Pilih produk dulu.'; $errors['bahan'] = 'Pilih produk dulu.';
        }
        if ($data['jumlah_jadi'] === '' || (int) $data['jumlah_jadi'] < 1) $errors['jumlah_jadi'] = 'Jumlah minimal 1.';
        elseif ($data['produk_jadi'] !== '' && isset($produk_info_jadi[$data['produk_jadi']])) {
            $min = $produk_info_jadi[$data['produk_jadi']]['min_order'];
            $stok = $produk_info_jadi[$data['produk_jadi']]['stok'];
            if ((int) $data['jumlah_jadi'] < $min) $errors['jumlah_jadi'] = "Minimal {$min} pcs.";
            elseif ((int) $data['jumlah_jadi'] > $stok) $errors['jumlah_jadi'] = "Stok tersisa: {$stok} pcs.";
        }
        $jenis_produk_final = $data['produk_jadi'];
        $jumlah_final       = $data['jumlah_jadi'];
        $ukuran_final       = $data['ukuran'];
        $bahan_final        = $data['bahan'];
    } elseif ($data['kategori_pesanan'] === 'produk_custom') {
        if ($data['jenis_produk'] === '' || !array_key_exists($data['jenis_produk'], $daftar_produk)) $errors['jenis_produk'] = 'Pilih jenis produk.';
        if ($data['jenis_produk'] !== '' && isset($produk_info[$data['jenis_produk']])) {
            if (!in_array($data['ukuran_custom'], $produk_info[$data['jenis_produk']]['ukuran'], true)) $errors['ukuran_custom'] = 'Pilih ukuran yang tersedia.';
            if (!in_array($data['bahan_custom'], $produk_info[$data['jenis_produk']]['bahan'], true)) $errors['bahan_custom'] = 'Pilih jenis bahan yang tersedia.';
        } else {
            $errors['ukuran_custom'] = 'Pilih produk dulu.'; $errors['bahan_custom'] = 'Pilih produk dulu.';
        }
        if ($data['jumlah'] === '' || (int) $data['jumlah'] < 1) $errors['jumlah'] = 'Jumlah minimal 1.';
        elseif ($data['jenis_produk'] !== '' && isset($produk_info[$data['jenis_produk']])) {
            $min = $produk_info[$data['jenis_produk']]['min_order'];
            $stok = $produk_info[$data['jenis_produk']]['stok'];
            if ((int) $data['jumlah'] < $min) $errors['jumlah'] = "Minimal {$min} pcs.";
            elseif ((int) $data['jumlah'] > $stok) $errors['jumlah'] = "Stok tersisa: {$stok} pcs.";
        }
        $jenis_produk_final = $data['jenis_produk'];
        $jumlah_final       = $data['jumlah'];
        $ukuran_final       = $data['ukuran_custom'];
        $bahan_final        = $data['bahan_custom'];
    } else {
        $errors['kategori_pesanan'] = 'Kategori pesanan tidak valid.';
    }

    $catatan_final    = '';
    $nama_file_desain = null;

    if ($data['kategori_pesanan'] === 'produk_custom') {
        $catatan_final = $data['catatan'];
        if (!empty($_FILES['file_desain']['name'])) {
            $ekstensi_diizinkan = ['jpg', 'jpeg', 'png', 'pdf'];
            $ekstensi = strtolower(pathinfo($_FILES['file_desain']['name'], PATHINFO_EXTENSION));
            if (!in_array($ekstensi, $ekstensi_diizinkan)) $errors['file_desain'] = 'File harus JPG, PNG, atau PDF.';
            elseif ($_FILES['file_desain']['size'] > 5 * 1024 * 1024) $errors['file_desain'] = 'Ukuran file maksimal 5MB.';
            else {
                $folder = "../uploads/desain/";
                if (!is_dir($folder)) mkdir($folder, 0777, true);
                $nama_file_desain = uniqid('desain_') . "." . $ekstensi;
                move_uploaded_file($_FILES['file_desain']['tmp_name'], $folder . $nama_file_desain);
            }
        }
    }

    $harga_satuan = null;
    if ($data['kategori_pesanan'] === 'produk_jadi' && isset($harga_produk_jadi[$jenis_produk_final])) $harga_satuan = $harga_produk_jadi[$jenis_produk_final];
    elseif ($data['kategori_pesanan'] === 'produk_custom' && isset($harga_produk[$jenis_produk_final])) $harga_satuan = $harga_produk[$jenis_produk_final];

    if ($data['metode_ambil'] === 'kurir' && isset(TARIF_ONGKIR[$data['wilayah_tujuan']])) {
        $ongkir = hitungOngkir($data['wilayah_tujuan'], (int) $jumlah_final);
    }

    if ($harga_satuan !== null && $jumlah_final !== '') {
        $total_tagihan = ($harga_satuan * (int) $jumlah_final) + $ongkir;
    }

    /* Gabungkan wilayah tujuan ke dalam kolom alamat, format "(Wilayah, Alamat)".
       Kalau metode ambil sendiri (tanpa wilayah), alamat disimpan apa adanya.
       Dengan begini tidak perlu kolom wilayah_tujuan terpisah di tabel pesanan. */
    $alamat_final = $data['alamat'];
    if ($data['metode_ambil'] === 'kurir' && isset(TARIF_ONGKIR[$data['wilayah_tujuan']])) {
        $wilayah_label = TARIF_ONGKIR[$data['wilayah_tujuan']]['label'];
        $alamat_final  = "({$wilayah_label}, {$data['alamat']})";
    }

    if (empty($errors)) {
        $kode_pesanan_baru = "ORD" . date("YmdHis");
        try {
            $koneksi->beginTransaction();
            $stmt = $koneksi->prepare("
                INSERT INTO pesanan (
                    kode_pesanan, nama_pemesan, no_hp, metode_ambil, alamat, jenis_produk,
                    ukuran, bahan, jumlah, catatan, total_tagihan, ongkir, file_desain,
                    status, status_pembayaran, dibuat_pada
                ) VALUES (
                    :kode_pesanan, :nama_pemesan, :no_hp, :metode_ambil, :alamat, :jenis_produk,
                    :ukuran, :bahan, :jumlah, :catatan, :total_tagihan, :ongkir, :file_desain,
                    'Menunggu Verifikasi', 'Belum Dibayar', NOW()
                )
            ");
            $stmt->execute([
                ':kode_pesanan'    => $kode_pesanan_baru,
                ':nama_pemesan'    => $data['nama'],
                ':no_hp'           => $data['no_hp'],
                ':metode_ambil'    => $data['metode_ambil'],
                ':alamat'          => $alamat_final,
                ':jenis_produk'    => $jenis_produk_final,
                ':ukuran'          => $ukuran_final,
                ':bahan'           => $bahan_final,
                ':jumlah'          => (int) $jumlah_final,
                ':catatan'         => $catatan_final,
                ':total_tagihan'   => $total_tagihan,
                ':ongkir'          => $ongkir,
                ':file_desain'     => $nama_file_desain,
            ]);

            $kategori_produk_db = $data['kategori_pesanan'] === 'produk_jadi' ? 'jadi' : 'custom';
            $stmtStok = $koneksi->prepare("
                UPDATE produk SET stok = stok - :jumlah
                WHERE nama_produk = :nama_produk AND status = 'aktif'
                  AND jenis_produk = :kategori_produk AND stok >= :jumlah_cek
            ");
            $stmtStok->execute([
                ':jumlah'          => (int) $jumlah_final,
                ':nama_produk'     => $jenis_produk_final,
                ':kategori_produk' => $kategori_produk_db,
                ':jumlah_cek'      => (int) $jumlah_final,
            ]);
            if ($stmtStok->rowCount() === 0) throw new PDOException('Stok produk tidak lagi mencukupi.');

            $koneksi->commit();
            $berhasil = true;
        } catch (PDOException $e) {
            if ($koneksi->inTransaction()) $koneksi->rollBack();
            $errors[] = "Pesanan gagal disimpan: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Pesan Produk — CD 133 Production</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=Space+Grotesk:wght@500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/public.css">

<style>
/* ========= DESIGN SYSTEM ENHANCEMENTS ========= */
:root {
    --denim-950: #0a1220;
    --denim-900: #0f1d33;
    --denim-800: #1a2d4a;
    --denim-700: #243e63;
    --denim-600: #2e4f7c;
    --denim-500: #3d6aa0;
    --denim-400: #5e8ac4;
    --denim-300: #8eb2dd;
    --denim-200: #c1d5ec;
    --denim-100: #e6eef8;
    --denim-50:  #f3f7fc;

    --gold-500: #d4a74c;
    --gold-400: #e6c068;
    --gold-300: #f1d689;

    --ink-900: #0c1424;
    --ink-700: #2a354a;
    --ink-500: #59637a;
    --ink-400: #7d869a;
    --ink-300: #a8afbf;
    --ink-100: #e8ebf1;

    --danger-500: #dc2626;
    --danger-100: #fee2e2;
    --success-500: #16a34a;
    --success-100: #dcfce7;

    --shadow-sm: 0 1px 2px rgba(15, 29, 51, 0.06);
    --shadow-md: 0 4px 16px rgba(15, 29, 51, 0.08);
    --shadow-lg: 0 16px 40px rgba(15, 29, 51, 0.12);
    --shadow-xl: 0 24px 60px rgba(15, 29, 51, 0.18);

    --radius-sm: 8px;
    --radius-md: 14px;
    --radius-lg: 20px;
    --radius-xl: 28px;

    --font-display: 'Plus Jakarta Sans', system-ui, sans-serif;
    --font-mono: 'Space Grotesk', ui-monospace, monospace;
}

* { box-sizing: border-box; }

body {
    font-family: var(--font-display);
    background:
        radial-gradient(circle at 10% -10%, rgba(94, 138, 196, 0.12), transparent 40%),
        radial-gradient(circle at 90% 10%, rgba(212, 167, 76, 0.08), transparent 40%),
        linear-gradient(180deg, #fafbfd 0%, #f3f7fc 100%);
    color: var(--ink-900);
    margin: 0;
    min-height: 100vh;
}

/* ========= NAVBAR ========= */
.public-nav {
    position: sticky;
    top: 0;
    z-index: 100;
    backdrop-filter: saturate(180%) blur(14px);
    -webkit-backdrop-filter: saturate(180%) blur(14px);
    background: rgba(255, 255, 255, 0.75);
    border-bottom: 1px solid rgba(15, 29, 51, 0.08);
    padding: 14px 28px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 20px;
}
.public-nav__brand {
    display: flex;
    align-items: center;
    gap: 12px;
    font-weight: 800;
    font-size: 17px;
    color: var(--denim-900);
    letter-spacing: -0.02em;
}
.public-nav__mark {
    width: 42px;
    height: 42px;
    display: grid;
    place-items: center;
    background: linear-gradient(135deg, var(--denim-800), var(--denim-600));
    color: var(--gold-300);
    border-radius: 12px;
    font-family: var(--font-mono);
    font-weight: 700;
    font-size: 16px;
    box-shadow: 0 4px 14px rgba(36, 62, 99, 0.3), inset 0 1px 0 rgba(255,255,255,0.1);
}
.public-nav__links {
    display: flex;
    gap: 6px;
    align-items: center;
}
.public-nav__links a {
    text-decoration: none;
    color: var(--ink-500);
    font-weight: 600;
    font-size: 14px;
    padding: 9px 16px;
    border-radius: 10px;
    transition: all 0.2s ease;
}
.public-nav__links a:hover { color: var(--denim-700); background: var(--denim-50); }
.public-nav__links a.is-active {
    background: linear-gradient(135deg, var(--denim-800), var(--denim-600));
    color: #fff;
    box-shadow: 0 4px 14px rgba(36, 62, 99, 0.25);
}

/* ========= LAYOUT ========= */
.public-wrap {
    max-width: 920px;
    margin: 0 auto;
    padding: 40px 24px 80px;
}

/* ========= PAGE HEAD (Hero) ========= */
.page-head {
    position: relative;
    padding: 48px 40px 40px;
    margin-bottom: 28px;
    border-radius: var(--radius-xl);
    background:
        linear-gradient(135deg, var(--denim-900) 0%, var(--denim-700) 55%, var(--denim-600) 100%);
    color: #fff;
    overflow: hidden;
    box-shadow: var(--shadow-lg);
}
.page-head::before {
    content: '';
    position: absolute;
    top: -80px; right: -80px;
    width: 300px; height: 300px;
    background: radial-gradient(circle, rgba(212, 167, 76, 0.25), transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.page-head::after {
    content: '';
    position: absolute;
    bottom: -100px; left: -50px;
    width: 240px; height: 240px;
    background: radial-gradient(circle, rgba(142, 178, 221, 0.25), transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}
.page-head__eyebrow {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin: 0 0 14px;
    padding: 6px 14px;
    border-radius: 999px;
    background: rgba(255, 255, 255, 0.1);
    border: 1px solid rgba(255, 255, 255, 0.15);
    color: var(--gold-300);
    font-size: 12px;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    backdrop-filter: blur(6px);
}
.page-head__eyebrow::before {
    content: '';
    width: 6px; height: 6px;
    background: var(--gold-400);
    border-radius: 50%;
    box-shadow: 0 0 8px var(--gold-400);
    animation: pulse 2s ease-in-out infinite;
}
.page-head h1 {
    font-size: clamp(28px, 4vw, 42px);
    font-weight: 800;
    margin: 0 0 12px;
    letter-spacing: -0.03em;
    line-height: 1.1;
    position: relative;
    z-index: 1;
}
.page-head p {
    margin: 0;
    color: rgba(255, 255, 255, 0.8);
    font-size: 15px;
    line-height: 1.6;
    max-width: 560px;
    position: relative;
    z-index: 1;
}
@keyframes pulse {
    0%, 100% { transform: scale(1); opacity: 1; }
    50% { transform: scale(1.3); opacity: 0.7; }
}

/* ========= STEPS PILLS ========= */
.steps {
    display: flex;
    gap: 8px;
    margin-bottom: 24px;
    padding: 8px;
    background: rgba(255, 255, 255, 0.7);
    border: 1px solid var(--ink-100);
    border-radius: 16px;
    backdrop-filter: blur(10px);
    overflow-x: auto;
}
.steps__item {
    flex: 1;
    min-width: 120px;
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 12px 14px;
    border-radius: 12px;
    transition: all 0.3s ease;
}
.steps__item.is-active {
    background: linear-gradient(135deg, var(--denim-800), var(--denim-600));
    color: #fff;
    box-shadow: 0 4px 14px rgba(36, 62, 99, 0.25);
}
.steps__num {
    width: 28px; height: 28px;
    display: grid;
    place-items: center;
    border-radius: 8px;
    background: var(--ink-100);
    color: var(--ink-500);
    font-family: var(--font-mono);
    font-weight: 700;
    font-size: 13px;
}
.steps__item.is-active .steps__num {
    background: var(--gold-400);
    color: var(--denim-900);
}
.steps__label {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-500);
}
.steps__item.is-active .steps__label { color: #fff; }

/* ========= FORM CARD ========= */
.form-card {
    background: #fff;
    border-radius: var(--radius-xl);
    padding: 36px 32px;
    box-shadow: var(--shadow-lg);
    border: 1px solid rgba(15, 29, 51, 0.06);
}
.form-section {
    margin-bottom: 36px;
    padding-bottom: 36px;
    border-bottom: 1px dashed var(--ink-100);
}
.form-section:last-of-type { border-bottom: none; margin-bottom: 0; padding-bottom: 0; }

.form-section__title {
    display: flex;
    align-items: center;
    gap: 12px;
    font-size: 18px;
    font-weight: 700;
    color: var(--denim-900);
    margin: 0 0 24px;
    letter-spacing: -0.01em;
}
.form-section__title::before {
    content: '';
    width: 4px;
    height: 22px;
    background: linear-gradient(180deg, var(--gold-400), var(--gold-500));
    border-radius: 4px;
    box-shadow: 0 2px 8px rgba(212, 167, 76, 0.4);
}

.form-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 18px;
}
.field { display: flex; flex-direction: column; gap: 6px; }
.field--full { grid-column: 1 / -1; }

.field label {
    font-size: 13px;
    font-weight: 600;
    color: var(--ink-700);
    letter-spacing: -0.01em;
}

.field input[type="text"],
.field input[type="number"],
.field select,
.field textarea {
    width: 100%;
    padding: 13px 16px;
    border: 1.5px solid var(--ink-100);
    background: #fff;
    border-radius: var(--radius-md);
    font-family: inherit;
    font-size: 14px;
    color: var(--ink-900);
    transition: all 0.2s ease;
    outline: none;
    appearance: none;
    -webkit-appearance: none;
}
.field select {
    background-image: url("data:image/svg+xml;utf8,<svg xmlns='http://www.w3.org/2000/svg' width='12' height='12' viewBox='0 0 24 24' fill='none' stroke='%2359637a' stroke-width='2.5' stroke-linecap='round' stroke-linejoin='round'><polyline points='6 9 12 15 18 9'/></svg>");
    background-repeat: no-repeat;
    background-position: right 16px center;
    padding-right: 40px;
}
.field input:hover,
.field select:hover,
.field textarea:hover { border-color: var(--denim-300); }
.field input:focus,
.field select:focus,
.field textarea:focus {
    border-color: var(--denim-500);
    box-shadow: 0 0 0 4px rgba(61, 106, 160, 0.12);
}
.field textarea { resize: vertical; min-height: 92px; line-height: 1.55; }

.field input.has-error,
.field select.has-error,
.field textarea.has-error {
    border-color: var(--danger-500);
    background: var(--danger-100);
}
.field input.has-error:focus,
.field select.has-error:focus {
    box-shadow: 0 0 0 4px rgba(220, 38, 38, 0.12);
}

.field-hint {
    font-size: 12px;
    color: var(--ink-400);
    line-height: 1.5;
    margin-top: 2px;
}
.field-error {
    font-size: 12px;
    color: var(--danger-500);
    font-weight: 600;
    display: inline-flex;
    align-items: center;
    gap: 4px;
    margin-top: 2px;
}
.field-error::before {
    content: '';
    width: 14px; height: 14px;
    background: var(--danger-500);
    color: #fff;
    border-radius: 50%;
    display: inline-grid;
    place-items: center;
    font-size: 10px;
    font-weight: 800;
    content: '!';
}

/* ========= RADIO GROUPS ========= */
.radio-group {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 10px;
}
.radio-option {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 16px;
    border: 1.5px solid var(--ink-100);
    border-radius: var(--radius-md);
    background: #fff;
    cursor: pointer;
    transition: all 0.2s ease;
    font-size: 14px;
    font-weight: 600;
    color: var(--ink-700);
    user-select: none;
    margin: 0 !important;
}
.radio-option:hover {
    border-color: var(--denim-300);
    background: var(--denim-50);
}
.radio-option input[type="radio"] {
    appearance: none;
    -webkit-appearance: none;
    width: 18px; height: 18px;
    border: 2px solid var(--ink-300);
    border-radius: 50%;
    margin: 0;
    position: relative;
    transition: all 0.2s ease;
    flex-shrink: 0;
}
.radio-option input[type="radio"]:checked {
    border-color: var(--denim-600);
    background: var(--denim-600);
}
.radio-option input[type="radio"]:checked::after {
    content: '';
    position: absolute;
    top: 50%; left: 50%;
    transform: translate(-50%, -50%);
    width: 6px; height: 6px;
    background: #fff;
    border-radius: 50%;
}
.radio-option:has(input:checked) {
    border-color: var(--denim-500);
    background: linear-gradient(135deg, rgba(94, 138, 196, 0.08), rgba(61, 106, 160, 0.06));
    color: var(--denim-900);
    box-shadow: 0 0 0 1px var(--denim-500) inset;
}

/* ========= KATEGORI BLOCKS ========= */
.kategori-block { display: none; }
.kategori-block.is-visible {
    display: block;
    animation: slideDown 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes slideDown {
    from { opacity: 0; transform: translateY(-8px); }
    to   { opacity: 1; transform: translateY(0); }
}
.kategori-block .form-grid { margin-top: 4px; }

/* ========= HARGA BOX ========= */
.harga-box {
    display: flex;
    justify-content: space-between;
    align-items: center;
    flex-wrap: wrap;
    gap: 12px;
    padding: 18px 20px;
    border-radius: var(--radius-md);
    background: linear-gradient(135deg, #fff, var(--denim-50));
    border: 1px solid var(--denim-200);
    transition: all 0.3s ease;
}
.harga-box__satuan {
    font-size: 13px;
    color: var(--denim-700);
    font-weight: 500;
}
.harga-box__total {
    font-size: 17px;
    font-weight: 800;
    font-family: var(--font-mono);
    color: var(--denim-900);
    letter-spacing: -0.01em;
}
.harga-box.is-kosong {
    background: #fafbfc;
    border-style: dashed;
    border-color: var(--ink-100);
}
.harga-box.is-kosong .harga-box__satuan,
.harga-box.is-kosong .harga-box__total {
    color: var(--ink-400);
    font-weight: 500;
}

/* ========= FILE DROP ========= */
.file-drop {
    position: relative;
    border: 2px dashed var(--denim-200);
    background: var(--denim-50);
    border-radius: var(--radius-md);
    padding: 18px;
    transition: all 0.2s ease;
    cursor: pointer;
}
.file-drop:hover {
    border-color: var(--denim-400);
    background: var(--denim-100);
}
.file-drop::before {
    content: '📎';
    margin-right: 8px;
}
.file-drop input[type="file"] {
    width: 100%;
    font-size: 13px;
    color: var(--ink-700);
    cursor: pointer;
}
.file-drop input[type="file"]::file-selector-button {
    padding: 8px 14px;
    border: 1px solid var(--denim-300);
    background: #fff;
    color: var(--denim-700);
    font-weight: 600;
    font-family: inherit;
    border-radius: 8px;
    cursor: pointer;
    margin-right: 12px;
    transition: all 0.2s ease;
}
.file-drop input[type="file"]::file-selector-button:hover {
    background: var(--denim-800);
    color: #fff;
    border-color: var(--denim-800);
}

/* ========= FORM ACTIONS ========= */
.form-actions {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    padding-top: 20px;
    border-top: 1px solid var(--ink-100);
    margin-top: 8px;
}
.btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    padding: 14px 28px;
    font-family: inherit;
    font-weight: 700;
    font-size: 14px;
    border-radius: var(--radius-md);
    border: 1.5px solid transparent;
    cursor: pointer;
    text-decoration: none;
    transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
    letter-spacing: -0.01em;
}
.btn--accent {
    background: linear-gradient(135deg, var(--denim-800), var(--denim-600));
    color: #fff;
    box-shadow: 0 6px 20px rgba(36, 62, 99, 0.25), inset 0 1px 0 rgba(255,255,255,0.1);
    position: relative;
    overflow: hidden;
}
.btn--accent::after {
    content: '→';
    font-size: 16px;
    transition: transform 0.25s ease;
}
.btn--accent:hover {
    transform: translateY(-2px);
    box-shadow: 0 10px 28px rgba(36, 62, 99, 0.35);
}
.btn--accent:hover::after { transform: translateX(4px); }
.btn--accent:active { transform: translateY(0); }

.btn--ghost {
    background: #fff;
    color: var(--denim-700);
    border-color: var(--ink-100);
}
.btn--ghost:hover {
    border-color: var(--denim-300);
    background: var(--denim-50);
    color: var(--denim-900);
}

/* ========= ALERT ERROR ========= */
.alert--error {
    background: linear-gradient(135deg, #fff, #fef2f2);
    border: 1px solid var(--danger-100);
    border-left: 4px solid var(--danger-500);
    border-radius: var(--radius-md);
    padding: 16px 20px;
    margin-bottom: 24px;
    box-shadow: var(--shadow-md);
}
.alert--error h4 {
    margin: 0 0 10px;
    color: var(--danger-500);
    font-size: 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.alert--error h4::before {
    content: '⚠️';
    font-size: 16px;
}
.alert--error ul { margin: 0; padding-left: 20px; }
.alert--error li {
    font-size: 13px;
    color: var(--ink-700);
    line-height: 1.6;
}

/* ========= SUCCESS CARD ========= */
.success-card {
    background: #fff;
    border-radius: var(--radius-xl);
    padding: 48px 40px;
    text-align: center;
    box-shadow: var(--shadow-xl);
    border: 1px solid rgba(15, 29, 51, 0.06);
    position: relative;
    overflow: hidden;
    animation: successIn 0.6s cubic-bezier(0.16, 1, 0.3, 1);
}
@keyframes successIn {
    from { opacity: 0; transform: translateY(20px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0) scale(1); }
}
.success-card::before {
    content: '';
    position: absolute;
    top: -100px; left: 50%;
    transform: translateX(-50%);
    width: 500px; height: 500px;
    background: radial-gradient(circle, rgba(22, 163, 74, 0.08), transparent 60%);
    pointer-events: none;
}
.success-card__icon {
    width: 88px; height: 88px;
    margin: 0 auto 20px;
    border-radius: 50%;
    background: linear-gradient(135deg, var(--success-500), #22c55e);
    color: #fff;
    display: grid;
    place-items: center;
    font-size: 44px;
    box-shadow: 0 14px 40px rgba(22, 163, 74, 0.35), inset 0 2px 0 rgba(255,255,255,0.25);
    animation: bounceIn 0.8s cubic-bezier(0.16, 1, 0.3, 1) 0.2s both;
    position: relative;
    z-index: 1;
}
@keyframes bounceIn {
    0%   { transform: scale(0) rotate(-180deg); }
    60%  { transform: scale(1.1) rotate(10deg); }
    100% { transform: scale(1) rotate(0deg); }
}
.success-card h2 {
    font-size: 28px;
    font-weight: 800;
    color: var(--denim-900);
    margin: 0 0 12px;
    letter-spacing: -0.02em;
    position: relative; z-index: 1;
}
.success-card > p {
    color: var(--ink-500);
    font-size: 15px;
    line-height: 1.6;
    margin: 0 0 24px;
    max-width: 440px;
    margin-left: auto;
    margin-right: auto;
    position: relative; z-index: 1;
}
.success-card__code {
    display: inline-block;
    padding: 16px 28px;
    margin-bottom: 24px;
    font-family: var(--font-mono);
    font-size: 20px;
    font-weight: 700;
    letter-spacing: 0.08em;
    color: var(--denim-900);
    background: linear-gradient(135deg, var(--denim-50), #fff);
    border: 2px dashed var(--denim-400);
    border-radius: var(--radius-md);
    box-shadow: var(--shadow-sm);
    position: relative; z-index: 1;
}
.success-card__info {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
    gap: 12px;
    max-width: 520px;
    margin: 0 auto 32px;
    position: relative; z-index: 1;
}
.success-card__info-item {
    padding: 16px;
    background: var(--denim-50);
    border-radius: var(--radius-md);
    text-align: left;
}
.success-card__info-item dt {
    font-size: 11px;
    font-weight: 600;
    color: var(--ink-400);
    text-transform: uppercase;
    letter-spacing: 0.06em;
    margin-bottom: 4px;
}
.success-card__info-item dd {
    margin: 0;
    font-size: 14px;
    font-weight: 700;
    color: var(--denim-900);
    font-family: var(--font-mono);
}
.success-card__actions {
    display: flex;
    gap: 10px;
    justify-content: center;
    flex-wrap: wrap;
    position: relative; z-index: 1;
}
.success-card__actions .btn--accent { min-width: 220px; }
.success-card__actions .btn--ghost { min-width: 180px; }

/* ========= FOOTER ========= */
.public-footer {
    text-align: center;
    padding: 32px 20px;
    color: var(--ink-400);
    font-size: 13px;
}

/* ========= RESPONSIVE ========= */
@media (max-width: 720px) {
    .public-nav { padding: 12px 16px; }
    .public-nav__links { gap: 2px; }
    .public-nav__links a { padding: 8px 10px; font-size: 13px; }
    .public-wrap { padding: 24px 16px 60px; }
    .page-head { padding: 36px 24px; border-radius: var(--radius-lg); }
    .form-card { padding: 24px 20px; border-radius: var(--radius-lg); }
    .form-grid { grid-template-columns: 1fr; }
    .radio-group { grid-template-columns: 1fr; }
    .form-actions { flex-direction: column-reverse; }
    .form-actions .btn { width: 100%; }
    .steps { overflow-x: auto; scrollbar-width: none; }
    .steps::-webkit-scrollbar { display: none; }
    .success-card { padding: 36px 24px; }
    .success-card__actions .btn { width: 100%; }
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
        <a href="pesan.php" class="is-active">Pesan</a>
        <a href="lacak_pesanan.php">Lacak</a>
    </div>
</nav>

<div class="public-wrap">

    <?php if ($berhasil): ?>

        <div class="success-card">
            <div class="success-card__icon">✓</div>
            <h2>Pesanan Berhasil Dikirim!</h2>
            <p>Terima kasih, pesanan kamu sudah kami terima. Simpan kode pesanan di bawah untuk memantau status kapan saja.</p>

            <div class="success-card__code"><?= htmlspecialchars($kode_pesanan_baru) ?></div>

            <div class="success-card__info">
                <div class="success-card__info-item">
                    <dt>Kontak WhatsApp</dt>
                    <dd><?= htmlspecialchars($data['no_hp']) ?></dd>
                </div>
                <?php if ($data['metode_ambil'] === 'kurir' && $ongkir > 0): ?>
                <div class="success-card__info-item">
                    <dt>Ongkos Kirim</dt>
                    <dd>Rp<?= number_format($ongkir, 0, ',', '.') ?></dd>
                </div>
                <?php endif; ?>
                <div class="success-card__info-item">
                    <dt>Total Tagihan</dt>
                    <dd>
                        <?php if ($total_tagihan !== null): ?>
                            Rp<?= number_format($total_tagihan, 0, ',', '.') ?>
                        <?php else: ?>
                            Menunggu info admin
                        <?php endif; ?>
                    </dd>
                </div>
            </div>

            <p>Admin akan menghubungi kamu lewat WhatsApp untuk konfirmasi pesanan.</p>

            <div class="success-card__actions">
                <a href="pembayaran.php?kode_pesanan=<?= urlencode($kode_pesanan_baru) ?>&no_hp=<?= urlencode($data['no_hp']) ?>" class="btn btn--accent">Lanjut Bayar</a>
                <a href="lacak_pesanan.php?kode_pesanan=<?= urlencode($kode_pesanan_baru) ?>&no_hp=<?= urlencode($data['no_hp']) ?>" class="btn btn--ghost">Lacak Pesanan</a>
                <a href="dashboard.php" class="btn btn--ghost">Ke Beranda</a>
            </div>
        </div>

    <?php else: ?>

        <?php if (!empty($errors)): ?>
            <div class="alert alert--error" role="alert">
                <h4>Ada beberapa hal yang perlu diperbaiki</h4>
                <ul>
                    <?php foreach ($errors as $err): ?>
                        <li><?= htmlspecialchars(is_array($err) ? implode(', ', $err) : $err) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="page-head">
            <span class="page-head__eyebrow">Form Pemesanan</span>
            <h1>Pesan Produk Custom</h1>
            <p>Isi detail pesanan kamu di bawah ini. Tim kami akan menghubungi lewat WhatsApp untuk konfirmasi final setelah pesanan masuk ke sistem.</p>
        </div>

        <div class="steps" aria-hidden="true">
            <div class="steps__item is-active">
                <span class="steps__num">1</span>
                <span class="steps__label">Data Pemesan</span>
            </div>
            <div class="steps__item is-active">
                <span class="steps__num">2</span>
                <span class="steps__label">Detail Pesanan</span>
            </div>
            <div class="steps__item">
                <span class="steps__num">3</span>
                <span class="steps__label">Konfirmasi & Bayar</span>
            </div>
        </div>

        <form class="form-card" method="post" action="pesan.php" enctype="multipart/form-data" novalidate>

            <div class="form-section">
                <h2 class="form-section__title">Data Pemesan</h2>
                <div class="form-grid">

                    <div class="field">
                        <label for="nama">Nama Lengkap</label>
                        <input type="text" id="nama" name="nama"
                               class="<?= isset($errors['nama']) ? 'has-error' : '' ?>"
                               value="<?= htmlspecialchars($data['nama']) ?>"
                               placeholder="Contoh: Dimas Aulia"
                               autocomplete="name">
                        <?php if (isset($errors['nama'])): ?><span class="field-error"><?= $errors['nama'] ?></span><?php endif; ?>
                    </div>

                    <div class="field">
                        <label for="no_hp">Nomor HP / WhatsApp</label>
                        <input type="text" id="no_hp" name="no_hp"
                               class="<?= isset($errors['no_hp']) ? 'has-error' : '' ?>"
                               value="<?= htmlspecialchars($data['no_hp']) ?>"
                               placeholder="081234567890"
                               inputmode="numeric"
                               autocomplete="tel">
                        <?php if (isset($errors['no_hp'])): ?>
                            <span class="field-error"><?= $errors['no_hp'] ?></span>
                        <?php else: ?>
                            <span class="field-hint">Pakai nomor ini juga untuk melacak pesanan nanti.</span>
                        <?php endif; ?>
                    </div>

                    <div class="field field--full">
                        <label>Metode Pengambilan</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="metode_ambil" value="ambil_sendiri"
                                    <?= $data['metode_ambil'] === 'ambil_sendiri' ? 'checked' : '' ?>>
                                <span>🏠 Ambil Sendiri</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="metode_ambil" value="kurir"
                                    <?= $data['metode_ambil'] === 'kurir' ? 'checked' : '' ?>>
                                <span>🚚 Diantar Kurir</span>
                            </label>
                        </div>
                    </div>

                    <div class="field field--full" id="wrapAlamat">
                        <label for="alamat">Alamat Pengiriman</label>
                        <textarea id="alamat" name="alamat" rows="3"
                                  class="<?= isset($errors['alamat']) ? 'has-error' : '' ?>"
                                  placeholder="Nama jalan, nomor rumah, kecamatan, kota"><?= htmlspecialchars($data['alamat']) ?></textarea>
                        <?php if (isset($errors['alamat'])): ?><span class="field-error"><?= $errors['alamat'] ?></span><?php endif; ?>
                    </div>

                    <div class="field field--full" id="wrapWilayah">
                        <label for="wilayah_tujuan">Wilayah Tujuan</label>
                        <select id="wilayah_tujuan" name="wilayah_tujuan"
                                class="<?= isset($errors['wilayah_tujuan']) ? 'has-error' : '' ?>">
                            <option value="" disabled <?= $data['wilayah_tujuan'] === '' ? 'selected' : '' ?>>— Pilih wilayah —</option>
                            <?php foreach (TARIF_ONGKIR as $kode => $tarif): ?>
                                <option value="<?= htmlspecialchars($kode) ?>"
                                    data-ongkir="<?= (float) $tarif['harga'] ?>"
                                    data-tarifkg="<?= (float) (TARIF_ONGKIR_PER_KG[$kode] ?? 0) ?>"
                                    <?= $data['wilayah_tujuan'] === $kode ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($tarif['label']) ?> — Rp<?= number_format($tarif['harga'], 0, ',', '.') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (isset($errors['wilayah_tujuan'])): ?>
                            <span class="field-error"><?= $errors['wilayah_tujuan'] ?></span>
                        <?php else: ?>
                            <span class="field-hint">Ongkir dihitung per zona. Untuk pesanan di atas <?= (int) BATAS_JUMLAH_BERAT ?> pcs, biaya berat ikut ditambahkan. Wilayah akan digabung otomatis ke depan alamat, contoh: "(Jawa Barat, Jl. Merdeka No. 1)".</span>
                        <?php endif; ?>
                    </div>

                </div>
            </div>

            <div class="form-section">
                <h2 class="form-section__title">Detail Pesanan</h2>
                <div class="form-grid">

                    <div class="field field--full">
                        <label>Kategori Pesanan</label>
                        <div class="radio-group">
                            <label class="radio-option">
                                <input type="radio" name="kategori_pesanan" value="produk_jadi" id="kategoriJadi"
                                    <?= $data['kategori_pesanan'] === 'produk_jadi' ? 'checked' : '' ?>>
                                <span>🎯 Produk Jadi</span>
                            </label>
                            <label class="radio-option">
                                <input type="radio" name="kategori_pesanan" value="produk_custom" id="kategoriCustom"
                                    <?= $data['kategori_pesanan'] === 'produk_custom' ? 'checked' : '' ?>>
                                <span>✨ Produk Custom</span>
                            </label>
                        </div>
                        <?php if (isset($errors['kategori_pesanan'])): ?><span class="field-error"><?= $errors['kategori_pesanan'] ?></span><?php endif; ?>
                    </div>

                    <!-- ===== BLOK: PRODUK JADI ===== -->
                    <div class="field field--full kategori-block" id="blokProdukJadi">
                        <div class="form-grid">
                            <div class="field field--full">
                                <label for="produk_jadi">Pilih Produk</label>
                                <select id="produk_jadi" name="produk_jadi"
                                        class="<?= isset($errors['produk_jadi']) ? 'has-error' : '' ?>">
                                    <option value="" disabled <?= $data['produk_jadi'] === '' ? 'selected' : '' ?>>— Pilih produk jadi —</option>
                                    <?php foreach ($daftar_produk_jadi as $nama_pj => $label_pj): ?>
                                        <option value="<?= htmlspecialchars($nama_pj) ?>"
                                            data-min-order="<?= (int) ($produk_info_jadi[$nama_pj]['min_order'] ?? 1) ?>"
                                            data-stok="<?= (int) ($produk_info_jadi[$nama_pj]['stok'] ?? 0) ?>"
                                            data-ukuran="<?= htmlspecialchars(implode(',', $produk_info_jadi[$nama_pj]['ukuran'] ?? [])) ?>"
                                            data-bahan="<?= htmlspecialchars(implode(',', $produk_info_jadi[$nama_pj]['bahan'] ?? [])) ?>"
                                            data-harga="<?= $harga_produk_jadi[$nama_pj] !== null ? (float) $harga_produk_jadi[$nama_pj] : '' ?>"
                                            <?= $data['produk_jadi'] === $nama_pj ? 'selected' : '' ?>><?= htmlspecialchars($label_pj) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['produk_jadi'])): ?><span class="field-error"><?= $errors['produk_jadi'] ?></span><?php endif; ?>
                            </div>

                            <div class="field">
                                <label for="ukuran">Ukuran</label>
                                <select id="ukuran" name="ukuran"
                                        data-preselect="<?= htmlspecialchars($data['ukuran']) ?>"
                                        class="<?= isset($errors['ukuran']) ? 'has-error' : '' ?>">
                                    <option value="" disabled selected>Pilih produk dulu</option>
                                </select>
                                <?php if (isset($errors['ukuran'])): ?><span class="field-error"><?= $errors['ukuran'] ?></span><?php endif; ?>
                            </div>

                            <div class="field">
                                <label for="bahan">Jenis Bahan</label>
                                <select id="bahan" name="bahan"
                                        data-preselect="<?= htmlspecialchars($data['bahan']) ?>"
                                        class="<?= isset($errors['bahan']) ? 'has-error' : '' ?>">
                                    <option value="" disabled selected>Pilih produk dulu</option>
                                </select>
                                <?php if (isset($errors['bahan'])): ?><span class="field-error"><?= $errors['bahan'] ?></span><?php endif; ?>
                            </div>

                            <div class="field">
                                <label for="jumlah_jadi">Jumlah (pcs)</label>
                                <input type="number" id="jumlah_jadi" name="jumlah_jadi" min="1"
                                       class="<?= isset($errors['jumlah_jadi']) ? 'has-error' : '' ?>"
                                       value="<?= htmlspecialchars($data['jumlah_jadi']) ?>" placeholder="5">
                                <?php if (isset($errors['jumlah_jadi'])): ?>
                                    <span class="field-error"><?= $errors['jumlah_jadi'] ?></span>
                                <?php else: ?>
                                    <span class="field-hint" id="jumlahHintJadi">
                                        <?php if ($data['produk_jadi'] !== '' && isset($produk_info_jadi[$data['produk_jadi']])): ?>
                                            Min. <?= (int) $produk_info_jadi[$data['produk_jadi']]['min_order'] ?> pcs • Stok: <?= (int) $produk_info_jadi[$data['produk_jadi']]['stok'] ?> pcs
                                        <?php else: ?>
                                            Pilih produk dulu untuk melihat batas minimal.
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="field">
                                <label>Estimasi Harga</label>
                                <div class="harga-box is-kosong" id="hargaBoxJadi">
                                    <span class="harga-box__satuan" id="hargaSatuanJadi">Pilih produk untuk melihat harga.</span>
                                    <span class="harga-box__total" id="hargaTotalJadi"></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ===== BLOK: PRODUK CUSTOM ===== -->
                    <div class="field field--full kategori-block" id="blokProdukCustom">
                        <div class="form-grid">
                            <div class="field field--full">
                                <label for="jenis_produk">Jenis Produk Custom</label>
                                <select id="jenis_produk" name="jenis_produk"
                                        class="<?= isset($errors['jenis_produk']) ? 'has-error' : '' ?>">
                                    <option value="" disabled <?= $data['jenis_produk'] === '' ? 'selected' : '' ?>>— Pilih jenis produk —</option>
                                    <?php foreach ($daftar_produk as $value => $label): ?>
                                        <option value="<?= htmlspecialchars($value) ?>"
                                            data-min-order="<?= (int) ($produk_info[$value]['min_order'] ?? 1) ?>"
                                            data-stok="<?= (int) ($produk_info[$value]['stok'] ?? 0) ?>"
                                            data-ukuran="<?= htmlspecialchars(implode(',', $produk_info[$value]['ukuran'] ?? [])) ?>"
                                            data-bahan="<?= htmlspecialchars(implode(',', $produk_info[$value]['bahan'] ?? [])) ?>"
                                            data-harga="<?= $harga_produk[$value] !== null ? (float) $harga_produk[$value] : '' ?>"
                                            <?= $data['jenis_produk'] === $value ? 'selected' : '' ?>><?= htmlspecialchars($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($errors['jenis_produk'])): ?><span class="field-error"><?= $errors['jenis_produk'] ?></span><?php endif; ?>
                            </div>

                            <div class="field">
                                <label for="ukuran_custom">Ukuran</label>
                                <select id="ukuran_custom" name="ukuran_custom"
                                        data-preselect="<?= htmlspecialchars($data['ukuran_custom']) ?>"
                                        class="<?= isset($errors['ukuran_custom']) ? 'has-error' : '' ?>">
                                    <option value="" disabled selected>Pilih produk dulu</option>
                                </select>
                                <?php if (isset($errors['ukuran_custom'])): ?><span class="field-error"><?= $errors['ukuran_custom'] ?></span><?php endif; ?>
                            </div>

                            <div class="field">
                                <label for="bahan_custom">Jenis Bahan</label>
                                <select id="bahan_custom" name="bahan_custom"
                                        data-preselect="<?= htmlspecialchars($data['bahan_custom']) ?>"
                                        class="<?= isset($errors['bahan_custom']) ? 'has-error' : '' ?>">
                                    <option value="" disabled selected>Pilih produk dulu</option>
                                </select>
                                <?php if (isset($errors['bahan_custom'])): ?><span class="field-error"><?= $errors['bahan_custom'] ?></span><?php endif; ?>
                            </div>

                            <div class="field">
                                <label for="jumlah">Jumlah (pcs)</label>
                                <input type="number" id="jumlah" name="jumlah" min="1"
                                       class="<?= isset($errors['jumlah']) ? 'has-error' : '' ?>"
                                       value="<?= htmlspecialchars($data['jumlah']) ?>" placeholder="50">
                                <?php if (isset($errors['jumlah'])): ?>
                                    <span class="field-error"><?= $errors['jumlah'] ?></span>
                                <?php else: ?>
                                    <span class="field-hint" id="jumlahHint">
                                        <?php if ($data['jenis_produk'] !== '' && isset($produk_info[$data['jenis_produk']])): ?>
                                            Min. <?= (int) $produk_info[$data['jenis_produk']]['min_order'] ?> pcs • Stok: <?= (int) $produk_info[$data['jenis_produk']]['stok'] ?> pcs
                                        <?php else: ?>
                                            Pilih produk dulu untuk melihat batas minimal.
                                        <?php endif; ?>
                                    </span>
                                <?php endif; ?>
                            </div>

                            <div class="field">
                                <label>Estimasi Harga</label>
                                <div class="harga-box is-kosong" id="hargaBoxCustom">
                                    <span class="harga-box__satuan" id="hargaSatuanCustom">Pilih produk untuk melihat harga.</span>
                                    <span class="harga-box__total" id="hargaTotalCustom"></span>
                                </div>
                            </div>

                            <div class="field field--full">
                                <label for="catatan">Catatan / Detail Desain</label>
                                <textarea id="catatan" name="catatan" rows="4"
                                          placeholder="Contoh: warna navy, logo di dada kiri, kerah V-neck, dsb."><?= htmlspecialchars($data['catatan']) ?></textarea>
                                <span class="field-hint">Sebutkan warna, posisi desain/logo, dan detail khusus lainnya.</span>
                            </div>

                            <div class="field field--full">
                                <label for="file_desain">Upload Referensi Desain (opsional)</label>
                                <div class="file-drop">
                                    <input type="file" id="file_desain" name="file_desain" accept=".jpg,.jpeg,.png,.pdf">
                                </div>
                                <?php if (isset($errors['file_desain'])): ?>
                                    <span class="field-error"><?= $errors['file_desain'] ?></span>
                                <?php else: ?>
                                    <span class="field-hint">Format JPG, PNG, atau PDF. Maksimal 5MB.</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn--accent">Kirim Pesanan</button>
            </div>

        </form>

    <?php endif; ?>

</div>

<footer class="public-footer">
    © 2026 CD 133 Production — Konveksi Custom. Semua hak cipta dilindungi.
</footer>

<script src="../assets/js/pesan.js"></script>
<script>
(function () {
    var BERAT_PER_PCS_KG   = <?= json_encode(BERAT_PER_PCS_KG) ?>;
    var BATAS_JUMLAH_BERAT = <?= json_encode(BATAS_JUMLAH_BERAT) ?>;

    var radiosKategori   = document.querySelectorAll('input[name="kategori_pesanan"]');
    var blokJadi          = document.getElementById('blokProdukJadi');
    var blokCustom        = document.getElementById('blokProdukCustom');

    function tampilkanBlokKategori() {
        var terpilih = document.querySelector('input[name="kategori_pesanan"]:checked');
        var nilai = terpilih ? terpilih.value : '';
        if (blokJadi)   blokJadi.classList.toggle('is-visible', nilai === 'produk_jadi');
        if (blokCustom) blokCustom.classList.toggle('is-visible', nilai === 'produk_custom');
    }

    radiosKategori.forEach(function (r) {
        r.addEventListener('change', tampilkanBlokKategori);
    });
    tampilkanBlokKategori();

    function formatRupiah(angka) {
        return 'Rp' + Math.round(angka).toString().replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    }

    /**
     * Hitung ongkir: tarif flat zona + (kalau jumlah > BATAS_JUMLAH_BERAT)
     * biaya berat tambahan berdasarkan tarif per kg zona tsb.
     */
    function ambilOngkirTerpilih(jumlah) {
        var metode = document.querySelector('input[name="metode_ambil"]:checked');
        if (!metode || metode.value !== 'kurir') return 0;
        var selWilayah = document.getElementById('wilayah_tujuan');
        var opsiWilayah = selWilayah ? selWilayah.options[selWilayah.selectedIndex] : null;
        if (!opsiWilayah || !opsiWilayah.value) return 0;

        var ongkir = parseFloat(opsiWilayah.getAttribute('data-ongkir')) || 0;
        jumlah = jumlah || 0;

        if (jumlah > BATAS_JUMLAH_BERAT) {
            var tarifKg    = parseFloat(opsiWilayah.getAttribute('data-tarifkg')) || 0;
            var beratTotal = jumlah * BERAT_PER_PCS_KG;
            ongkir += Math.round(beratTotal * tarifKg);
        }

        return ongkir;
    }

    function buatPembaruDropdown(selectProduk, selectTarget, namaAtribut) {
        return function () {
            if (!selectProduk || !selectTarget) return;
            var opsiTerpilih = selectProduk.options[selectProduk.selectedIndex];
            var mentah       = opsiTerpilih ? opsiTerpilih.getAttribute('data-' + namaAtribut) : '';
            var daftar       = mentah ? mentah.split(',').filter(function (v) { return v !== ''; }) : [];
            var preselect    = selectTarget.getAttribute('data-preselect') || '';

            selectTarget.innerHTML = '';

            if (!daftar.length) {
                var kosong = document.createElement('option');
                kosong.value = '';
                kosong.disabled = true;
                kosong.selected = true;
                kosong.textContent = opsiTerpilih && opsiTerpilih.value ? 'Belum ada pilihan untuk produk ini' : 'Pilih produk dulu';
                selectTarget.appendChild(kosong);
                return;
            }

            var placeholder = document.createElement('option');
            placeholder.value = '';
            placeholder.disabled = true;
            placeholder.textContent = namaAtribut === 'ukuran' ? 'Pilih ukuran' : 'Pilih jenis bahan';
            placeholder.selected = (daftar.indexOf(preselect) === -1);
            selectTarget.appendChild(placeholder);

            daftar.forEach(function (nilai) {
                var opt = document.createElement('option');
                opt.value = nilai;
                opt.textContent = nilai;
                if (nilai === preselect) opt.selected = true;
                selectTarget.appendChild(opt);
            });
        };
    }

    function buatPembaruHintJumlah(selectProduk, inputJumlah, elemenHint, teksKosong) {
        return function () {
            if (!selectProduk || !inputJumlah || !elemenHint) return;
            var opsiTerpilih = selectProduk.options[selectProduk.selectedIndex];
            if (!opsiTerpilih || !opsiTerpilih.value) {
                elemenHint.textContent = teksKosong;
                inputJumlah.min = 1;
                return;
            }
            var minOrder = parseInt(opsiTerpilih.getAttribute('data-min-order'), 10) || 1;
            var stok     = parseInt(opsiTerpilih.getAttribute('data-stok'), 10) || 0;
            elemenHint.textContent = 'Min. ' + minOrder + ' pcs • Stok: ' + stok + ' pcs';
            inputJumlah.min = minOrder;
        };
    }

    function buatPembaruHarga(selectProduk, inputJumlah, elBox, elSatuan, elTotal) {
        return function () {
            if (!selectProduk || !elBox || !elSatuan || !elTotal) return;
            var opsiTerpilih = selectProduk.options[selectProduk.selectedIndex];
            if (!opsiTerpilih || !opsiTerpilih.value) {
                elBox.classList.add('is-kosong');
                elSatuan.textContent = 'Pilih produk untuk melihat harga.';
                elTotal.textContent = '';
                return;
            }
            var hargaMentah = opsiTerpilih.getAttribute('data-harga');
            if (hargaMentah === null || hargaMentah === '') {
                elBox.classList.add('is-kosong');
                elSatuan.textContent = 'Harga akan diinfokan admin.';
                elTotal.textContent = '';
                return;
            }
            var harga  = parseFloat(hargaMentah);
            var jumlah = parseInt(inputJumlah && inputJumlah.value ? inputJumlah.value : '0', 10) || 0;
            var ongkir = ambilOngkirTerpilih(jumlah);

            elBox.classList.remove('is-kosong');
            var infoOngkir = '';
            if (ongkir > 0) {
                infoOngkir = ' • Ongkir: ' + formatRupiah(ongkir);
                if (jumlah > BATAS_JUMLAH_BERAT) infoOngkir += ' (termasuk biaya berat)';
            }
            elSatuan.textContent = 'Harga satuan: ' + formatRupiah(harga) + ' / pcs' + infoOngkir;
            elTotal.textContent = jumlah > 0 ? 'Estimasi: ' + formatRupiah((harga * jumlah) + ongkir) : '';
        };
    }

    /* PRODUK JADI */
    var selectProdukJadi = document.getElementById('produk_jadi');
    var selectUkuranJadi = document.getElementById('ukuran');
    var selectBahanJadi  = document.getElementById('bahan');
    var inputJumlahJadi  = document.getElementById('jumlah_jadi');
    var hintJumlahJadi   = document.getElementById('jumlahHintJadi');
    var hargaBoxJadi     = document.getElementById('hargaBoxJadi');
    var hargaSatuanJadi  = document.getElementById('hargaSatuanJadi');
    var hargaTotalJadi   = document.getElementById('hargaTotalJadi');

    var perbaruiUkuranJadi = buatPembaruDropdown(selectProdukJadi, selectUkuranJadi, 'ukuran');
    var perbaruiBahanJadi  = buatPembaruDropdown(selectProdukJadi, selectBahanJadi, 'bahan');
    var perbaruiHintJadi   = buatPembaruHintJumlah(selectProdukJadi, inputJumlahJadi, hintJumlahJadi, 'Pilih produk dulu.');
    var perbaruiHargaJadi  = buatPembaruHarga(selectProdukJadi, inputJumlahJadi, hargaBoxJadi, hargaSatuanJadi, hargaTotalJadi);

    if (selectProdukJadi) {
        selectProdukJadi.addEventListener('change', function () {
            perbaruiUkuranJadi(); perbaruiBahanJadi(); perbaruiHintJadi(); perbaruiHargaJadi();
        });
    }
    if (inputJumlahJadi) inputJumlahJadi.addEventListener('input', perbaruiHargaJadi);
    perbaruiUkuranJadi(); perbaruiBahanJadi(); perbaruiHintJadi(); perbaruiHargaJadi();

    /* PRODUK CUSTOM */
    var selectProdukCustom = document.getElementById('jenis_produk');
    var selectUkuranCustom = document.getElementById('ukuran_custom');
    var selectBahanCustom  = document.getElementById('bahan_custom');
    var inputJumlahCustom  = document.getElementById('jumlah');
    var hintJumlahCustom   = document.getElementById('jumlahHint');
    var hargaBoxCustom     = document.getElementById('hargaBoxCustom');
    var hargaSatuanCustom  = document.getElementById('hargaSatuanCustom');
    var hargaTotalCustom   = document.getElementById('hargaTotalCustom');

    var perbaruiUkuranCustom = buatPembaruDropdown(selectProdukCustom, selectUkuranCustom, 'ukuran');
    var perbaruiBahanCustom  = buatPembaruDropdown(selectProdukCustom, selectBahanCustom, 'bahan');
    var perbaruiHintCustom   = buatPembaruHintJumlah(selectProdukCustom, inputJumlahCustom, hintJumlahCustom, 'Pilih produk dulu.');
    var perbaruiHargaCustom  = buatPembaruHarga(selectProdukCustom, inputJumlahCustom, hargaBoxCustom, hargaSatuanCustom, hargaTotalCustom);

    if (selectProdukCustom) {
        selectProdukCustom.addEventListener('change', function () {
            perbaruiUkuranCustom(); perbaruiBahanCustom(); perbaruiHintCustom(); perbaruiHargaCustom();
        });
    }
    if (inputJumlahCustom) inputJumlahCustom.addEventListener('input', perbaruiHargaCustom);
    perbaruiUkuranCustom(); perbaruiBahanCustom(); perbaruiHintCustom(); perbaruiHargaCustom();

    /* Auto-hide alamat & wilayah tujuan kalau bukan kurir */
    var metodeRadios = document.querySelectorAll('input[name="metode_ambil"]');
    var wrapAlamat = document.getElementById('wrapAlamat');
    var wrapWilayah = document.getElementById('wrapWilayah');
    function toggleAlamat() {
        var selected = document.querySelector('input[name="metode_ambil"]:checked');
        var tampil = selected && selected.value === 'kurir';
        if (wrapAlamat)  wrapAlamat.style.display  = tampil ? '' : 'none';
        if (wrapWilayah) wrapWilayah.style.display = tampil ? '' : 'none';
        perbaruiHargaJadi();
        perbaruiHargaCustom();
    }
    metodeRadios.forEach(function (r) { r.addEventListener('change', toggleAlamat); });
    toggleAlamat();

    /* Update harga saat wilayah tujuan diganti */
    var selectWilayah = document.getElementById('wilayah_tujuan');
    if (selectWilayah) {
        selectWilayah.addEventListener('change', function () {
            perbaruiHargaJadi();
            perbaruiHargaCustom();
        });
    }
})();
</script>
</body>
</html>