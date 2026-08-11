<?php
session_start();
require_once __DIR__ . '/../config/koneksi.php';

/* =========================================================
   Direktori penyimpanan bukti transfer.
   Sesuaikan path ini dengan struktur folder upload kamu
   yang sudah ada (mengikuti pola nama file "bukti_xxx.png"
   yang terlihat di tabel `pembayaran`).
   ========================================================= */
define('BUKTI_UPLOAD_DIR', __DIR__ . '/../uploads/bukti/');
define('BUKTI_UPLOAD_URL_PREFIX', 'bukti_'); // prefix nama file, mengikuti data yang sudah ada

$pesan_upload      = $_SESSION['pesan_upload'] ?? '';
$pesan_upload_type = $_SESSION['pesan_upload_type'] ?? '';
unset($_SESSION['pesan_upload'], $_SESSION['pesan_upload_type']);

$kode_pesanan = trim($_GET['kode_pesanan'] ?? '');
$no_hp        = trim($_GET['no_hp'] ?? '');
$sudah_cari   = $kode_pesanan !== '' && $no_hp !== '';

/* =========================================================
   PROSES UPLOAD BUKTI PEMBAYARAN (langsung di halaman ini)
   ========================================================= */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['aksi']) && $_POST['aksi'] === 'upload_bukti') {

    $id_pesanan_post   = (int) ($_POST['id_pesanan'] ?? 0);
    $kode_pesanan_post = trim($_POST['kode_pesanan'] ?? '');
    $no_hp_post        = trim($_POST['no_hp'] ?? '');
    $nominal_post      = (float) preg_replace('/[^0-9.]/', '', $_POST['nominal'] ?? '0');

    // Validasi ulang kepemilikan pesanan (kode_pesanan + no_hp harus cocok dengan id_pesanan)
    $stmtCek = $koneksi->prepare("
        SELECT id_pesanan FROM pesanan
        WHERE id_pesanan = ? AND kode_pesanan = ? AND no_hp = ?
        LIMIT 1
    ");
    $stmtCek->execute([$id_pesanan_post, $kode_pesanan_post, $no_hp_post]);
    $valid = $stmtCek->fetch(PDO::FETCH_ASSOC);

    if (!$valid) {
        $_SESSION['pesan_upload']      = 'Data pesanan tidak valid. Coba cari ulang pesanan kamu.';
        $_SESSION['pesan_upload_type'] = 'err';
    } elseif ($nominal_post <= 0) {
        $_SESSION['pesan_upload']      = 'Nominal pembayaran tidak valid.';
        $_SESSION['pesan_upload_type'] = 'err';
    } elseif (!isset($_FILES['bukti_transfer']) || $_FILES['bukti_transfer']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['pesan_upload']      = 'Bukti transfer belum dipilih atau gagal diunggah.';
        $_SESSION['pesan_upload_type'] = 'err';
    } else {
        $file     = $_FILES['bukti_transfer'];
        $ext      = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed  = ['jpg', 'jpeg', 'png', 'webp'];
        $maxBytes = 3 * 1024 * 1024; // 3MB

        if (!in_array($ext, $allowed, true)) {
            $_SESSION['pesan_upload']      = 'Format file harus JPG, PNG, atau WEBP.';
            $_SESSION['pesan_upload_type'] = 'err';
        } elseif ($file['size'] > $maxBytes) {
            $_SESSION['pesan_upload']      = 'Ukuran file maksimal 3MB.';
            $_SESSION['pesan_upload_type'] = 'err';
        } else {
            if (!is_dir(BUKTI_UPLOAD_DIR)) {
                mkdir(BUKTI_UPLOAD_DIR, 0755, true);
            }

            $namaFile = BUKTI_UPLOAD_URL_PREFIX . uniqid() . '.' . $ext;
            $tujuan   = BUKTI_UPLOAD_DIR . $namaFile;

            if (move_uploaded_file($file['tmp_name'], $tujuan)) {
                $kodePembayaran = 'BYR' . date('YmdHis');

                $stmtInsert = $koneksi->prepare("
                    INSERT INTO pembayaran
                        (kode_pembayaran, id_pesanan, total_pembayaran, bukti_transfer, tanggal_bayar, status, dibuat_pada)
                    VALUES
                        (?, ?, ?, ?, NOW(), 'Menunggu Verifikasi', NOW())
                ");
                $stmtInsert->execute([$kodePembayaran, $id_pesanan_post, $nominal_post, $namaFile]);

                $_SESSION['pesan_upload']      = 'Bukti pembayaran berhasil dikirim. Admin akan segera memverifikasi.';
                $_SESSION['pesan_upload_type'] = 'ok';
            } else {
                $_SESSION['pesan_upload']      = 'Gagal menyimpan file. Coba lagi.';
                $_SESSION['pesan_upload_type'] = 'err';
            }
        }
    }

    // Redirect balik ke pencarian yang sama (PRG pattern)
    header('Location: lacak_pesanan.php?kode_pesanan=' . urlencode($kode_pesanan_post) . '&no_hp=' . urlencode($no_hp_post));
    exit;
}

$pesanan          = null;
$item_pesanan     = [];
$tidak_ditemukan  = false;
$sisa_tagihan     = 0;
$total_tagihan_db = 0;
$total_dibayar    = 0;

if ($sudah_cari) {

    /* Cari pesanan berdasarkan kode pesanan + no HP */
    $stmt = $koneksi->prepare("
        SELECT * FROM pesanan
        WHERE kode_pesanan = ? AND no_hp = ?
        LIMIT 1
    ");
    $stmt->execute([$kode_pesanan, $no_hp]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        /* Ambil nominal pembayaran terakhir untuk pesanan ini (kalau ada) */
        $stmtBayar = $koneksi->prepare("
            SELECT total_pembayaran, status
            FROM pembayaran
            WHERE id_pesanan = ?
            ORDER BY dibuat_pada DESC
            LIMIT 1
        ");
        $stmtBayar->execute([$row['id_pesanan']]);
        $bayar = $stmtBayar->fetch(PDO::FETCH_ASSOC);

        $totalBayar = $bayar['total_pembayaran'] ?? null;

        /* Total seluruh pembayaran yang sudah DITERIMA untuk pesanan ini,
           supaya sisa tagihan tetap akurat kalau ada skema DP/cicilan
           (lebih dari satu baris pembayaran per pesanan). */
        $stmtTotalBayar = $koneksi->prepare("
            SELECT COALESCE(SUM(total_pembayaran), 0) AS total_dibayar
            FROM pembayaran
            WHERE id_pesanan = ? AND status = 'Diterima'
        ");
        $stmtTotalBayar->execute([$row['id_pesanan']]);
        $total_dibayar = (float) ($stmtTotalBayar->fetch(PDO::FETCH_ASSOC)['total_dibayar'] ?? 0);

        $total_tagihan_db = (float) ($row['total_tagihan'] ?? 0);

        $pesanan = [
            'id_pesanan'        => $row['id_pesanan'],
            'kode_pesanan'      => $row['kode_pesanan'],
            'nama_pemesan'      => $row['nama_pemesan'],
            'tanggal'           => $row['dibuat_pada'],
            'status'            => $row['status'],
            'status_pembayaran' => $row['status_pembayaran'] ?? ($bayar['status'] ?? 'Belum Lunas'),
            'estimasi'          => $row['estimasi'] ?? null,
        ];

        /* PENTING: acuan harga/total HARUS dari total_tagihan pesanan
           (harga penuh), bukan dari nominal pembayaran terakhir.
           Kalau dipakai dari pembayaran terakhir, saat customer bayar
           bertahap (DP dulu, lalu pelunasan), Total yang tampil akan
           ikut-ikutan angka pembayaran terakhir itu — bukan total
           tagihan sebenarnya. */
        $acuan_harga_satuan = $total_tagihan_db > 0 ? $total_tagihan_db : (float) ($totalBayar ?? 0);

        $item_pesanan = [
            [
                'produk' => $row['jenis_produk'],
                'qty'    => (int) $row['jumlah'],
                'harga'  => $acuan_harga_satuan && $row['jumlah'] > 0
                    ? round($acuan_harga_satuan / $row['jumlah'])
                    : 0,
            ],
        ];

        $totalItem    = array_sum(array_map(fn($i) => $i['qty'] * $i['harga'], $item_pesanan));
        $acuanTagihan = $total_tagihan_db > 0 ? $total_tagihan_db : $totalItem;
        $sisa_tagihan = max(0, $acuanTagihan - $total_dibayar);
    } else {
        $tidak_ditemukan = true;
    }
}

$tahapan = [
    'Menunggu Verifikasi' => 'Menunggu Verifikasi',
    'Dikonfirmasi'        => 'Dikonfirmasi',
    'Cutting'             => 'Cutting',
    'Sablon/Bordir'       => 'Sablon/Bordir',
    'Sewing/Dijahit'      => 'Sewing/Dijahit',
    'Finishing'           => 'Finishing',
    'Packing'             => 'Packing',
    'Selesai'             => 'Selesai',
    'Diserahkan'          => 'Diserahkan',
];
$urutan_tahapan = array_keys($tahapan);

function status_pembayaran_badge(?string $status): array
{
    $lunas = $status !== null && strtolower($status) === 'lunas';
    return $lunas
        ? ['badge badge--selesai', 'Lunas']
        : ['badge badge--menunggu', 'Belum Lunas'];
}

function tglIndoSingkat(?string $t): string
{
    if (!$t) return '-';
    $b = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',
          7=>'Jul',8=>'Agt',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
    $ts = strtotime($t);
    return date('d', $ts) . ' ' . $b[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
<link rel="icon" type="image/svg+xml" href="../assets/img/logo.svg">
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Lacak Pesanan — CD 133 Production</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Sora:wght@500;600;700&family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/css/tokens.css">
<link rel="stylesheet" href="../assets/css/public.css">
<style>
/* ============================================================
   PREMIUM DESIGN ENHANCEMENTS
   Tidak mengubah fungsi — hanya memperindah tampilan
   ============================================================ */

/* Page entrance animation */
.public-wrap {
    animation: fadeInUp 0.6s ease-out;
}

@keyframes fadeInUp {
    from { opacity: 0; transform: translateY(20px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Search card enhancement */
.search-card {
    position: relative;
    overflow: hidden;
    border-radius: 20px !important;
    box-shadow: 0 10px 40px rgba(31, 58, 95, 0.08) !important;
    transition: box-shadow 0.3s ease;
}

.search-card::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    height: 4px;
    background: linear-gradient(90deg, #1F3A5F, #C77700, #1F3A5F);
    background-size: 200% 100%;
    animation: shimmer 3s ease-in-out infinite;
}

@keyframes shimmer {
    0%, 100% { background-position: 0% 50%; }
    50% { background-position: 100% 50%; }
}

.search-form .field label {
    font-weight: 600;
    color: var(--ink, #222);
    font-size: 13px;
    letter-spacing: 0.02em;
}

.search-form input[type="text"] {
    padding: 12px 16px !important;
    border-radius: 12px !important;
    border: 2px solid transparent !important;
    background: #F7F9FC !important;
    transition: all 0.3s ease !important;
    font-size: 15px !important;
}

.search-form input[type="text"]:focus {
    background: #fff !important;
    border-color: var(--denim, #1F3A5F) !important;
    box-shadow: 0 0 0 4px rgba(31, 58, 95, 0.1) !important;
    outline: none !important;
}

.search-form .btn--accent {
    padding: 14px 28px !important;
    border-radius: 12px !important;
    font-weight: 600 !important;
    font-size: 15px !important;
    background: linear-gradient(135deg, #1F3A5F 0%, #2C4E7A 100%) !important;
    box-shadow: 0 4px 14px rgba(31, 58, 95, 0.25) !important;
    transition: all 0.3s ease !important;
}

.search-form .btn--accent:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 20px rgba(31, 58, 95, 0.35) !important;
}

.search-form .btn--accent:active {
    transform: translateY(0) !important;
}

/* Result card */
.result-card {
    animation: fadeInUp 0.6s ease-out 0.1s both;
    border-radius: 20px !important;
    box-shadow: 0 10px 40px rgba(31, 58, 95, 0.08) !important;
    border: 1px solid rgba(31, 58, 95, 0.06) !important;
}

.result-card__head {
    padding: 24px 28px !important;
    background: linear-gradient(135deg, #F7F9FC 0%, #FFFFFF 100%);
    border-bottom: 1px solid rgba(31, 58, 95, 0.08);
}

.result-card__head h2 {
    font-family: 'Sora', sans-serif !important;
    font-weight: 700 !important;
    color: var(--denim, #1F3A5F) !important;
    font-size: 22px !important;
    margin-bottom: 4px !important;
    letter-spacing: -0.01em;
}

.result-card__head span {
    font-size: 13px;
    color: #6B7280;
    font-weight: 500;
}

/* Badge enhancement */
.badge {
    padding: 8px 16px !important;
    border-radius: 999px !important;
    font-weight: 600 !important;
    font-size: 12.5px !important;
    letter-spacing: 0.03em;
    text-transform: uppercase;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}

.badge--selesai {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%) !important;
    color: #fff !important;
    box-shadow: 0 2px 10px rgba(16, 185, 129, 0.3) !important;
}

.badge--menunggu {
    background: linear-gradient(135deg, #F59E0B 0%, #D97706 100%) !important;
    color: #fff !important;
    box-shadow: 0 2px 10px rgba(245, 158, 11, 0.3) !important;
}

/* ============================================================
   PREMIUM TRACKER
   ============================================================ */
.tracker__steps {
    display: flex !important;
    align-items: flex-start !important;
    justify-content: space-between !important;
    padding: 32px 28px !important;
    gap: 8px;
    position: relative;
    overflow-x: auto;
}

.tracker__step {
    position: relative !important;
    display: flex !important;
    flex-direction: column !important;
    align-items: center !important;
    gap: 10px !important;
    flex: 1 !important;
    min-width: 80px;
    list-style: none !important;
}

.tracker__step:not(:last-child)::before {
    content: '';
    position: absolute;
    top: 16px;
    left: calc(50% + 18px);
    right: calc(-50% + 18px);
    height: 3px;
    background: #E5E7EB;
    border-radius: 2px;
    transition: background 0.3s ease;
    z-index: 0;
}

.tracker__step.is-done:not(:last-child)::before {
    background: linear-gradient(90deg, #10B981 0%, #059669 100%);
}

.tracker__dot {
    position: relative !important;
    width: 32px !important;
    height: 32px !important;
    border-radius: 50% !important;
    background: #fff !important;
    border: 3px solid #E5E7EB !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    z-index: 1 !important;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.tracker__dot::after {
    content: '';
    display: none;
    width: 14px;
    height: 14px;
    background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='white'%3E%3Cpath d='M20.285 6.708l-11.241 11.241-5.03-5.03 1.414-1.414 3.616 3.616 9.827-9.827z'/%3E%3C/svg%3E");
    background-size: contain;
}

.tracker__step.is-done .tracker__dot {
    background: linear-gradient(135deg, #10B981 0%, #059669 100%) !important;
    border-color: #10B981 !important;
    box-shadow: 0 4px 12px rgba(16, 185, 129, 0.35);
}

.tracker__step.is-done .tracker__dot::after {
    display: block;
}

.tracker__step.is-current .tracker__dot {
    background: linear-gradient(135deg, #1F3A5F 0%, #2C4E7A 100%) !important;
    border-color: #1F3A5F !important;
    box-shadow: 0 4px 14px rgba(31, 58, 95, 0.4);
    animation: pulse 2s ease-in-out infinite;
}

@keyframes pulse {
    0%, 100% { box-shadow: 0 4px 14px rgba(31, 58, 95, 0.4); }
    50% { box-shadow: 0 4px 20px rgba(31, 58, 95, 0.6); }
}

.tracker__step.is-current .tracker__dot::before {
    content: '';
    position: absolute;
    inset: -6px;
    border-radius: 50%;
    border: 2px solid #1F3A5F;
    opacity: 0.3;
    animation: ripple 2s ease-in-out infinite;
}

@keyframes ripple {
    0% { transform: scale(1); opacity: 0.3; }
    50% { transform: scale(1.3); opacity: 0; }
    100% { transform: scale(1); opacity: 0; }
}

.tracker__label {
    font-size: 12px !important;
    font-weight: 600 !important;
    color: #9CA3AF !important;
    text-align: center !important;
    line-height: 1.3 !important;
    transition: color 0.3s ease;
}

.tracker__step.is-done .tracker__label {
    color: #10B981 !important;
}

.tracker__step.is-current .tracker__label {
    color: #1F3A5F !important;
    font-weight: 700 !important;
}

.tracker__eta {
    display: inline-block;
    margin: 0 28px 20px !important;
    padding: 8px 14px;
    background: rgba(31, 58, 95, 0.05);
    border-radius: 8px;
    font-size: 13px;
    font-weight: 600;
    color: #1F3A5F;
}

/* Detail table */
.detail-table {
    margin: 0 28px 24px !important;
    border-radius: 12px !important;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.04);
}

.detail-table thead {
    background: linear-gradient(135deg, #1F3A5F 0%, #2C4E7A 100%);
}

.detail-table thead th {
    color: #fff !important;
    padding: 14px 16px !important;
    font-size: 12.5px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.05em !important;
    font-weight: 600 !important;
}

.detail-table tbody tr {
    transition: background 0.2s ease;
}

.detail-table tbody tr:hover {
    background: #F9FAFB;
}

.detail-table tbody td {
    padding: 14px 16px !important;
    font-size: 14px !important;
    color: #374151 !important;
    border-bottom: 1px solid #F3F4F6 !important;
}

.detail-table tfoot td {
    padding: 16px !important;
    font-weight: 700 !important;
    font-size: 15px !important;
    color: #1F3A5F !important;
    background: #F7F9FC !important;
    border-bottom: none !important;
}

/* ============================================================
   TAGIHAN BOX & UPLOAD FORM
   ============================================================ */
.tagihan-box {
    margin: 0 28px 28px !important;
    padding: 24px !important;
    border-radius: 16px !important;
    background: linear-gradient(135deg, #FFF7ED 0%, #FFEDD5 100%) !important;
    border: 2px solid #FED7AA !important;
    box-shadow: 0 4px 16px rgba(217, 119, 6, 0.08);
    position: relative;
    overflow: hidden;
}

.tagihan-box::before {
    content: '';
    position: absolute;
    top: -50%;
    right: -20%;
    width: 200px;
    height: 200px;
    background: radial-gradient(circle, rgba(251, 146, 60, 0.15) 0%, transparent 70%);
    border-radius: 50%;
    pointer-events: none;
}

.tagihan-box__top {
    display: flex !important;
    align-items: center !important;
    justify-content: space-between !important;
    flex-wrap: wrap;
    gap: 16px;
    position: relative;
    z-index: 1;
}

.tagihan-box__info {
    display: flex !important;
    flex-direction: column !important;
    gap: 6px;
}

.tagihan-box__label {
    font-size: 11.5px !important;
    text-transform: uppercase !important;
    letter-spacing: 0.08em !important;
    color: #92400E !important;
    font-weight: 700 !important;
}

.tagihan-box__val {
    font-size: 24px !important;
    font-weight: 700 !important;
    color: #C2410C !important;
    font-family: 'Sora', sans-serif !important;
    letter-spacing: -0.01em;
}

.btn-toggle-upload {
    padding: 12px 22px !important;
    background: linear-gradient(135deg, #EA580C 0%, #C2410C 100%) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 12px !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    cursor: pointer;
    box-shadow: 0 4px 12px rgba(234, 88, 12, 0.3) !important;
    transition: all 0.3s ease !important;
    white-space: nowrap;
}

.btn-toggle-upload:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 18px rgba(234, 88, 12, 0.4) !important;
}

.btn-toggle-upload:active {
    transform: translateY(0) !important;
}

.upload-form {
    max-height: 0;
    overflow: hidden;
    opacity: 0;
    margin-top: 0;
    padding-top: 0;
    transition: max-height 0.4s ease, opacity 0.3s ease, margin-top 0.4s ease, padding-top 0.4s ease;
    border-top: 0px dashed #FED7AA;
}

.upload-form.is-open {
    max-height: 600px;
    opacity: 1;
    margin-top: 24px !important;
    padding-top: 24px !important;
    border-top: 2px dashed #FED7AA;
}

.upload-form .field {
    margin-bottom: 16px !important;
    display: flex !important;
    flex-direction: column !important;
    gap: 8px;
}

.upload-form label {
    font-size: 13px !important;
    font-weight: 600 !important;
    color: #374151 !important;
    letter-spacing: 0.01em;
}

.upload-form input[type="number"],
.upload-form input[type="file"] {
    padding: 12px 16px !important;
    border: 2px solid #E5E7EB !important;
    border-radius: 12px !important;
    font-size: 14px !important;
    font-family: inherit !important;
    background: #fff !important;
    transition: all 0.3s ease !important;
    width: 100%;
    box-sizing: border-box;
}

.upload-form input[type="number"]:focus {
    border-color: #EA580C !important;
    box-shadow: 0 0 0 4px rgba(234, 88, 12, 0.1) !important;
    outline: none !important;
}

.upload-form input[type="file"] {
    padding: 10px !important;
    background: #F9FAFB !important;
    cursor: pointer;
}

.upload-form input[type="file"]:hover {
    background: #F3F4F6 !important;
}

.upload-form__submit {
    padding: 13px 24px !important;
    background: linear-gradient(135deg, #1F3A5F 0%, #2C4E7A 100%) !important;
    color: #fff !important;
    border: none !important;
    border-radius: 12px !important;
    font-size: 14.5px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
    box-shadow: 0 4px 12px rgba(31, 58, 95, 0.25) !important;
    transition: all 0.3s ease !important;
    margin-top: 8px;
    width: 100%;
}

.upload-form__submit:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 6px 18px rgba(31, 58, 95, 0.35) !important;
}

.upload-form__submit:active {
    transform: translateY(0) !important;
}

.upload-form__hint {
    font-size: 12px !important;
    color: #6B7280 !important;
    margin-top: -4px !important;
    font-style: italic;
}

/* Alerts */
.alert--ok {
    background: linear-gradient(135deg, #D1FAE5 0%, #A7F3D0 100%) !important;
    color: #065F46 !important;
    border: 1.5px solid #6EE7B7 !important;
    padding: 14px 18px !important;
    border-radius: 12px !important;
    margin-top: 16px !important;
    font-size: 13.5px !important;
    font-weight: 500 !important;
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    animation: slideInDown 0.4s ease-out;
}

.alert--ok::before {
    content: '✓';
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    background: #065F46;
    color: #fff;
    border-radius: 50%;
    font-size: 14px;
    font-weight: 700;
    flex-shrink: 0;
}

.alert--err {
    background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%) !important;
    color: #991B1B !important;
    border: 1.5px solid #FCA5A5 !important;
    padding: 14px 18px !important;
    border-radius: 12px !important;
    margin-top: 16px !important;
    font-size: 13.5px !important;
    font-weight: 500 !important;
    display: flex;
    align-items: center;
    gap: 10px;
    position: relative;
    animation: slideInDown 0.4s ease-out;
}

.alert--err::before {
    content: '!';
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 24px;
    height: 24px;
    background: #991B1B;
    color: #fff;
    border-radius: 50%;
    font-size: 14px;
    font-weight: 700;
    flex-shrink: 0;
}

@keyframes slideInDown {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

.alert {
    background: linear-gradient(135deg, #FEF3C7 0%, #FDE68A 100%);
    color: #92400E;
    padding: 14px 18px;
    border-radius: 12px;
    font-size: 13.5px;
    margin-top: 12px;
    border: 1.5px solid #FCD34D;
    font-weight: 500;
    animation: slideInDown 0.4s ease-out;
}

/* Responsive refinements */
@media (max-width: 768px) {
    .tracker__steps {
        padding: 24px 16px !important;
        overflow-x: auto;
        -webkit-overflow-scrolling: touch;
        scrollbar-width: none;
    }
    .tracker__steps::-webkit-scrollbar { display: none; }

    .tracker__step {
        min-width: 70px !important;
    }

    .tracker__label {
        font-size: 10.5px !important;
    }

    .result-card__head { padding: 20px !important; }
    .result-card__head h2 { font-size: 19px !important; }

    .detail-table { margin: 0 16px 20px !important; }
    .tagihan-box { margin: 0 16px 20px !important; padding: 20px !important; }
}

@media (max-width: 520px) {
    .tagihan-box__top {
        flex-direction: column !important;
        align-items: stretch !important;
    }
    .btn-toggle-upload { width: 100% !important; text-align: center; }

    .detail-table thead th,
    .detail-table tbody td {
        padding: 10px 12px !important;
        font-size: 12.5px !important;
    }

    .detail-table tfoot td {
        padding: 12px !important;
        font-size: 14px !important;
    }
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
        <a href="pesan.php">Pesan Sekarang</a>
        <a href="lacak_pesanan.php" class="is-active">Lacak Pesanan</a>
    </div>
</nav>

<div class="public-wrap">

    <div class="public-hero">
        <p class="public-hero__eyebrow">Cek Status</p>
        <h1>Lacak Pesanan Kamu</h1>
        <p>Masukkan kode pesanan dan nomor HP yang dipakai saat memesan.</p>
    </div>

    <div class="search-card">
        <form class="search-form" method="get" action="lacak_pesanan.php">
            <div class="field">
                <label for="kode_pesanan">Kode Pesanan</label>
                <input type="text" id="kode_pesanan" name="kode_pesanan"
                       placeholder="Contoh: ORD-0231"
                       value="<?= htmlspecialchars($kode_pesanan) ?>" required>
            </div>
            <div class="field">
                <label for="no_hp">Nomor HP</label>
                <input type="text" id="no_hp" name="no_hp"
                       placeholder="Contoh: 081234567890"
                       value="<?= htmlspecialchars($no_hp) ?>" required>
            </div>
            <button type="submit" class="btn btn--accent">Cek Status</button>
        </form>

        <?php if ($tidak_ditemukan): ?>
            <p class="alert">
                ⚠️ Kode pesanan atau nomor HP tidak cocok. Periksa kembali, atau hubungi admin kalau masih bermasalah.
            </p>
        <?php endif; ?>
    </div>

    <?php if ($pesanan): ?>
        <?php
            $posisi_sekarang = array_search($pesanan['status'], $urutan_tahapan);
            $total           = array_sum(array_map(fn($i) => $i['qty'] * $i['harga'], $item_pesanan));

            $status_pembayaran_efektif = $sisa_tagihan <= 0 ? 'Lunas' : $pesanan['status_pembayaran'];
            [$badge_class, $badge_label] = status_pembayaran_badge($status_pembayaran_efektif);
            $tampil_tagihan = strtolower($status_pembayaran_efektif) !== 'lunas' && $sisa_tagihan > 0;
        ?>
        <div class="result-card">
            <div class="result-card__head">
                <div>
                    <h2><?= htmlspecialchars($pesanan['kode_pesanan']) ?></h2>
                    <span>Atas nama <?= htmlspecialchars($pesanan['nama_pemesan']) ?> · Dipesan <?= tglIndoSingkat($pesanan['tanggal']) ?></span>
                </div>
                <span class="<?= $badge_class ?>"><?= $badge_label ?></span>
            </div>

            <ol class="tracker__steps">
                <?php foreach ($urutan_tahapan as $i => $key): ?>
                    <?php
                        $state = $posisi_sekarang === false
                            ? ''
                            : ($i < $posisi_sekarang ? 'is-done'
                               : ($i === $posisi_sekarang ? 'is-current' : ''));
                    ?>
                    <li class="tracker__step <?= $state ?>">
                        <span class="tracker__dot"></span>
                        <span class="tracker__label"><?= $tahapan[$key] ?></span>
                    </li>
                <?php endforeach; ?>
            </ol>
            <?php if (!empty($pesanan['estimasi'])): ?>
            <span class="tracker__eta">📅 Estimasi selesai: <?= htmlspecialchars($pesanan['estimasi']) ?></span>
            <?php endif; ?>

            <table class="detail-table">
                <thead>
                    <tr>
                        <th>Produk</th>
                        <th class="text-right">Qty</th>
                        <th class="text-right">Harga</th>
                        <th class="text-right">Subtotal</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($item_pesanan as $item): ?>
                    <tr>
                        <td><?= htmlspecialchars($item['produk']) ?></td>
                        <td class="text-right"><?= $item['qty'] ?></td>
                        <td class="text-right">Rp<?= number_format($item['harga'], 0, ',', '.') ?></td>
                        <td class="text-right">Rp<?= number_format($item['qty'] * $item['harga'], 0, ',', '.') ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr>
                        <td colspan="3" class="text-right">Total</td>
                        <td class="text-right">Rp<?= number_format($total, 0, ',', '.') ?></td>
                    </tr>
                </tfoot>
            </table>

            <?php if ($tampil_tagihan): ?>
            <div class="tagihan-box">
                <div class="tagihan-box__top">
                    <div class="tagihan-box__info">
                        <span class="tagihan-box__label">Sisa Tagihan</span>
                        <span class="tagihan-box__val">Rp<?= number_format($sisa_tagihan, 0, ',', '.') ?></span>
                    </div>
                    <button type="button" class="btn-toggle-upload" onclick="toggleUploadForm()">
                        💳 Lanjut Pembayaran Pelunasan
                    </button>
                </div>

                <?php if ($pesan_upload): ?>
                    <div class="alert--<?= $pesan_upload_type === 'ok' ? 'ok' : 'err' ?>">
                        <?= htmlspecialchars($pesan_upload) ?>
                    </div>
                <?php endif; ?>

                <form class="upload-form <?= $pesan_upload ? 'is-open' : '' ?>" id="uploadForm"
                      method="post" action="lacak_pesanan.php" enctype="multipart/form-data">
                    <input type="hidden" name="aksi" value="upload_bukti">
                    <input type="hidden" name="id_pesanan" value="<?= (int) $pesanan['id_pesanan'] ?>">
                    <input type="hidden" name="kode_pesanan" value="<?= htmlspecialchars($pesanan['kode_pesanan']) ?>">
                    <input type="hidden" name="no_hp" value="<?= htmlspecialchars($no_hp) ?>">

                    <div class="field">
                        <label for="nominal">Nominal Pembayaran</label>
                        <input type="number" id="nominal" name="nominal" min="1" step="1"
                               value="<?= (int) $sisa_tagihan ?>" required>
                        <span class="upload-form__hint">💡 Bisa dibayar penuh atau bertahap (cicilan).</span>
                    </div>

                    <div class="field">
                        <label for="bukti_transfer">Bukti Transfer (JPG/PNG/WEBP, maks 3MB)</label>
                        <input type="file" id="bukti_transfer" name="bukti_transfer" accept="image/*" required>
                    </div>

                    <button type="submit" class="upload-form__submit">Kirim Bukti Pembayaran</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>

</div>

<script>
function toggleUploadForm() {
    document.getElementById('uploadForm').classList.toggle('is-open');
}
</script>

</body>
</html>