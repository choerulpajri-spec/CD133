<?php
require_once __DIR__ . '/../../config/koneksi.php';

/* =========================================================
   HELPER
   ========================================================= */
function formatRupiah($angka)
{
    return 'Rp' . number_format((float) $angka, 0, ',', '.');
}

function formatTanggalIndo($tanggal)
{
    if (!$tanggal) return '-';
    $bulan = [
        1 => 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
    ];
    $ts = strtotime($tanggal);
    return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ' ' . date('Y', $ts);
}

function formatWaktuSingkat($tanggal)
{
    if (!$tanggal) return '-';
    $bulan = [
        1 => 'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
        'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'
    ];
    $ts = strtotime($tanggal);
    return date('d', $ts) . ' ' . $bulan[(int) date('n', $ts)] . ', ' . date('H:i', $ts);
}

function resolveBuktiUrls(?string $namaFile): array
{
    if (!$namaFile) return ['', ''];
    $encoded = rawurlencode($namaFile);
    if (strpos($namaFile, 'bukti_') === 0) {
        return [
            '../../uploads/bukti/' . $encoded,
            '../../uploads/pembayaran/' . $encoded,
        ];
    }
    return [
        '../../uploads/pembayaran/' . $encoded,
        '../../uploads/bukti/' . $encoded,
    ];
}

function statusToClass($status)
{
    switch ($status) {
        case 'Diterima': return 'diterima';
        case 'Ditolak':  return 'ditolak';
        default:         return 'menunggu';
    }
}

function labelJenisPembayaran(int $urutan): string
{
    if ($urutan <= 1) return 'Pembayaran Awal';
    if ($urutan === 2) return 'Pembayaran Pelunasan';
    return 'Pelunasan ke-' . ($urutan - 1);
}

function gabungkanStatusPesanan(string $statusPesanan, float $totalTagihan, float $sisaTagihan): array
{
    $sudahDikonfirmasi = !in_array($statusPesanan, ['Menunggu Verifikasi', 'Dibatalkan'], true);

    if (!$sudahDikonfirmasi) {
        return [
            'label' => $statusPesanan,
            'class' => $statusPesanan === 'Dibatalkan' ? 'ditolak' : 'menunggu',
        ];
    }

    $lunas = ($totalTagihan > 0 && $sisaTagihan <= 0);

    return [
        'label' => $lunas ? 'Dikonfirmasi - Lunas' : 'Dikonfirmasi - Belum Lunas',
        'class' => $lunas ? 'lunas' : 'belum_lunas',
    ];
}

function infoKategori(string $kategori): array
{
    switch ($kategori) {
        case 'baru':
            return [
                'label' => 'Pesanan Baru',
                'icon'  => '🆕',
                'class' => 'menunggu',
                'desc'  => 'Menunggu verifikasi pembayaran awal (DP)',
            ];
        case 'pelunasan_menunggu_verifikasi':
            return [
                'label' => 'Pelunasan · Perlu Verifikasi',
                'icon'  => '🔍',
                'class' => 'menunggu',
                'desc'  => 'Customer sudah upload bukti pelunasan, cek & verifikasi',
            ];
        case 'pelunasan_menunggu_bayar':
            return [
                'label' => 'Pelunasan · Menunggu Customer',
                'icon'  => '⏳',
                'class' => 'menunggu_bayar',
                'desc'  => 'DP sudah diterima, customer belum melunasi',
            ];
        case 'lunas':
            return [
                'label' => 'Lunas',
                'icon'  => '✅',
                'class' => 'lunas',
                'desc'  => 'Seluruh tagihan sudah diterima',
            ];
        case 'dibatalkan':
            return [
                'label' => 'Dibatalkan',
                'icon'  => '✕',
                'class' => 'ditolak',
                'desc'  => 'Pesanan dibatalkan / pembayaran awal ditolak',
            ];
        default:
            return ['label' => $kategori, 'icon' => '', 'class' => 'menunggu', 'desc' => ''];
    }
}

$aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';
$id   = isset($_GET['id'])   ? (int) $_GET['id'] : 0;

if ($aksi === 'terima' && $id > 0) {
    try {
        $koneksi->beginTransaction();

        $stmtCari = $koneksi->prepare("SELECT id_pesanan FROM pembayaran WHERE id_pembayaran = :id");
        $stmtCari->execute([':id' => $id]);
        $rowBayar = $stmtCari->fetch(PDO::FETCH_ASSOC);

        if ($rowBayar) {
            $id_pesanan_terkait = (int) $rowBayar['id_pesanan'];

            $stmt = $koneksi->prepare("
                UPDATE pembayaran
                SET status = 'Diterima', diverifikasi_pada = NOW()
                WHERE id_pembayaran = :id
            ");
            $stmt->execute([':id' => $id]);

            $stmtTotal = $koneksi->prepare("
                SELECT COALESCE(SUM(total_pembayaran), 0) AS total_diterima
                FROM pembayaran
                WHERE id_pesanan = :id_pesanan AND status = 'Diterima'
            ");
            $stmtTotal->execute([':id_pesanan' => $id_pesanan_terkait]);
            $total_diterima = (float) $stmtTotal->fetch(PDO::FETCH_ASSOC)['total_diterima'];

            $stmtPs = $koneksi->prepare("SELECT total_tagihan, status FROM pesanan WHERE id_pesanan = :id_pesanan");
            $stmtPs->execute([':id_pesanan' => $id_pesanan_terkait]);
            $psRow = $stmtPs->fetch(PDO::FETCH_ASSOC);
            $totalTagihan = (float) ($psRow['total_tagihan'] ?? 0);

            $statusPembayaranBaru = ($totalTagihan > 0 && $total_diterima >= $totalTagihan)
                ? 'Lunas'
                : 'Belum Lunas';

            if ($psRow && $psRow['status'] === 'Menunggu Verifikasi') {
                $stmt2 = $koneksi->prepare("
                    UPDATE pesanan
                    SET status = 'Dikonfirmasi', status_pembayaran = :status_pembayaran
                    WHERE id_pesanan = :id_pesanan
                ");
            } else {
                $stmt2 = $koneksi->prepare("
                    UPDATE pesanan
                    SET status_pembayaran = :status_pembayaran
                    WHERE id_pesanan = :id_pesanan
                ");
            }
            $stmt2->execute([
                ':status_pembayaran' => $statusPembayaranBaru,
                ':id_pesanan'        => $id_pesanan_terkait,
            ]);
        }

        $koneksi->commit();
    } catch (PDOException $e) {
        if ($koneksi->inTransaction()) {
            $koneksi->rollBack();
        }
    }
    header('Location: index.php?aksi=detail&id=' . $id);
    exit;
}

if ($aksi === 'tolak' && $id > 0) {
    $alasan = trim($_GET['alasan'] ?? $_POST['alasan'] ?? '');
    try {
        $koneksi->beginTransaction();

        $stmtCari = $koneksi->prepare("SELECT id_pesanan FROM pembayaran WHERE id_pembayaran = :id");
        $stmtCari->execute([':id' => $id]);
        $rowBayar = $stmtCari->fetch(PDO::FETCH_ASSOC);

        if ($rowBayar) {
            $id_pesanan_terkait = (int) $rowBayar['id_pesanan'];

            $stmt = $koneksi->prepare("
                UPDATE pembayaran
                SET status = 'Ditolak', alasan_penolakan = :alasan, diverifikasi_pada = NOW()
                WHERE id_pembayaran = :id
            ");
            $stmt->execute([':id' => $id, ':alasan' => $alasan]);

            $stmtPs = $koneksi->prepare("SELECT status FROM pesanan WHERE id_pesanan = :id_pesanan");
            $stmtPs->execute([':id_pesanan' => $id_pesanan_terkait]);
            $psRow = $stmtPs->fetch(PDO::FETCH_ASSOC);

            if ($psRow && $psRow['status'] === 'Menunggu Verifikasi') {
                $stmt2 = $koneksi->prepare("
                    UPDATE pesanan
                    SET status = 'Dibatalkan'
                    WHERE id_pesanan = :id_pesanan
                ");
                $stmt2->execute([':id_pesanan' => $id_pesanan_terkait]);
            }
        }

        $koneksi->commit();
    } catch (PDOException $e) {
        if ($koneksi->inTransaction()) {
            $koneksi->rollBack();
        }
    }
    header('Location: index.php?aksi=detail&id=' . $id);
    exit;
}

$detail                     = null;
$daftar_pembayaran_pesanan  = [];

if ($aksi === 'detail' && $id > 0) {
    $stmt0 = $koneksi->prepare("
        SELECT ps.*
        FROM pembayaran pb
        JOIN pesanan ps ON ps.id_pesanan = pb.id_pesanan
        WHERE pb.id_pembayaran = :id
        LIMIT 1
    ");
    $stmt0->execute([':id' => $id]);
    $pesananInfo = $stmt0->fetch(PDO::FETCH_ASSOC);

    if (!$pesananInfo) {
        header('Location: index.php');
        exit;
    }

    $stmt1 = $koneksi->prepare("
        SELECT
            pb.*,
            ROW_NUMBER() OVER (ORDER BY pb.dibuat_pada ASC) AS urutan_bayar
        FROM pembayaran pb
        WHERE pb.id_pesanan = :id_pesanan
        ORDER BY pb.dibuat_pada ASC
    ");
    $stmt1->execute([':id_pesanan' => $pesananInfo['id_pesanan']]);
    $daftar_pembayaran_pesanan = $stmt1->fetchAll(PDO::FETCH_ASSOC);

    $total_diterima = 0.0;
    foreach ($daftar_pembayaran_pesanan as $pb) {
        if ($pb['status'] === 'Diterima') {
            $total_diterima += (float) $pb['total_pembayaran'];
        }
    }
    $total_tagihan = (float) ($pesananInfo['total_tagihan'] ?? 0);
    $sisa_tagihan  = max(0, $total_tagihan - $total_diterima);

    $status_gabungan = gabungkanStatusPesanan($pesananInfo['status'], $total_tagihan, $sisa_tagihan);

    $detail = [
        'id_pesanan'          => $pesananInfo['id_pesanan'],
        'kode_pesanan'        => $pesananInfo['kode_pesanan'],
        'nama_pemesan'        => $pesananInfo['nama_pemesan'],
        'status_pesanan'      => $pesananInfo['status'],
        'status_pembayaran'   => $pesananInfo['status_pembayaran'] ?: 'Belum Dibayar',
        'status_gabungan'     => $status_gabungan,
        'pesanan_dibuat_pada' => $pesananInfo['dibuat_pada'],
        'total_tagihan'       => $total_tagihan,
        'total_diterima'      => $total_diterima,
        'sisa_tagihan'        => $sisa_tagihan,
    ];
}

$daftar_pesanan   = [];
$hitung_kategori  = [
    'baru'                          => 0,
    'pelunasan_menunggu_verifikasi' => 0,
    'pelunasan_menunggu_bayar'      => 0,
    'lunas'                         => 0,
    'dibatalkan'                    => 0,
];

if ($aksi !== 'detail') {
    $stmt = $koneksi->query("
        SELECT
            pb.id_pembayaran,
            pb.kode_pembayaran,
            pb.total_pembayaran,
            pb.tanggal_bayar,
            pb.status AS status_item,
            pb.dibuat_pada,
            ps.id_pesanan,
            ps.kode_pesanan,
            ps.nama_pemesan,
            ps.status AS status_pesanan,
            ps.total_tagihan,
            ROW_NUMBER() OVER (PARTITION BY pb.id_pesanan ORDER BY pb.dibuat_pada ASC)  AS urutan_bayar,
            ROW_NUMBER() OVER (PARTITION BY pb.id_pesanan ORDER BY pb.dibuat_pada DESC) AS urutan_terbaru,
            COUNT(*) OVER (PARTITION BY pb.id_pesanan) AS jumlah_pembayaran,
            SUM(CASE WHEN pb.status = 'Diterima' THEN pb.total_pembayaran ELSE 0 END)
                OVER (PARTITION BY pb.id_pesanan) AS total_diterima_pesanan,
            SUM(CASE WHEN pb.status = 'Menunggu Verifikasi' THEN 1 ELSE 0 END)
                OVER (PARTITION BY pb.id_pesanan) AS jumlah_pending
        FROM pembayaran pb
        JOIN pesanan ps ON ps.id_pesanan = pb.id_pesanan
        ORDER BY ps.kode_pesanan ASC, pb.dibuat_pada ASC
    ");
    $semuaBaris = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $perPesanan = [];
    foreach ($semuaBaris as $row) {
        $idPesanan = $row['id_pesanan'];
        if (!isset($perPesanan[$idPesanan])) {
            $perPesanan[$idPesanan] = $row;
            continue;
        }
        if ($row['status_item'] === 'Menunggu Verifikasi') {
            $perPesanan[$idPesanan] = $row;
        } elseif ($perPesanan[$idPesanan]['status_item'] !== 'Menunggu Verifikasi'
                  && (int) $row['urutan_terbaru'] === 1) {
            $perPesanan[$idPesanan] = $row;
        }
    }

    foreach ($perPesanan as $row) {
        $totalTagihan  = (float) ($row['total_tagihan'] ?? 0);
        $totalDiterima = (float) ($row['total_diterima_pesanan'] ?? 0);
        $sisaTagihan   = max(0, $totalTagihan - $totalDiterima);
        $adaPending    = ((int) $row['jumlah_pending']) > 0;
        $statusPesanan = $row['status_pesanan'];

        if ($statusPesanan === 'Dibatalkan') {
            $kategori = 'dibatalkan';
        } elseif ($statusPesanan === 'Menunggu Verifikasi') {
            $kategori = 'baru';
        } elseif ($totalTagihan > 0 && $sisaTagihan <= 0) {
            $kategori = 'lunas';
        } elseif ($adaPending) {
            $kategori = 'pelunasan_menunggu_verifikasi';
        } else {
            $kategori = 'pelunasan_menunggu_bayar';
        }

        $row['kategori']       = $kategori;
        $row['sisa_tagihan']   = $sisaTagihan;
        $row['total_diterima'] = $totalDiterima;

        $row['urutan_tahap'] = ($kategori === 'pelunasan_menunggu_bayar')
            ? ((int) $row['jumlah_pembayaran'] + 1)
            : (int) $row['urutan_bayar'];

        $hitung_kategori[$kategori]++;
        $daftar_pesanan[] = $row;
    }

    $prioritasKategori = [
        'baru'                          => 1,
        'pelunasan_menunggu_verifikasi' => 2,
        'pelunasan_menunggu_bayar'      => 3,
        'lunas'                         => 4,
        'dibatalkan'                    => 5,
    ];
    usort($daftar_pesanan, function ($a, $b) use ($prioritasKategori) {
        $pa = $prioritasKategori[$a['kategori']] ?? 99;
        $pb2 = $prioritasKategori[$b['kategori']] ?? 99;
        if ($pa !== $pb2) return $pa <=> $pb2;
        return strcmp($a['kode_pesanan'], $b['kode_pesanan']);
    });
}

$total_semua_pesanan = count($daftar_pesanan);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <link rel="icon" type="image/svg+xml" href="../../assets/img/logo.svg">
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verifikasi Pembayaran — CD 133 Production</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../assets/css/tokens.css">
    <link rel="stylesheet" href="../../assets/css/admin_pembayaran.css">
    <link rel="stylesheet" href="../../assets/css/admin_index.css">
    <style>
    /* =========================================================
       CLEAN DESIGN SYSTEM — Linear/Vercel-inspired
       Fokus: rapi, ringan, tidak lag
       ========================================================= */
    :root {
        --bg: #f8fafc;
        --surface: #ffffff;
        --surface-soft: #f1f5f9;
        --border: #e2e8f0;
        --border-strong: #cbd5e1;
        --ink: #0f172a;
        --ink-2: #334155;
        --ink-soft: #64748b;
        --ink-muted: #94a3b8;
        --brand: #4f46e5;
        --brand-soft: #eef2ff;
        --brand-dark: #4338ca;
        --ok: #16a34a;
        --ok-soft: #dcfce7;
        --warn: #d97706;
        --warn-soft: #fef3c7;
        --danger: #dc2626;
        --danger-soft: #fee2e2;
        --info: #0284c7;
        --info-soft: #e0f2fe;
        --purple: #7c3aed;
        --purple-soft: #ede9fe;
        --shadow-xs: 0 1px 2px rgba(15, 23, 42, 0.04);
        --shadow-sm: 0 1px 3px rgba(15, 23, 42, 0.06), 0 1px 2px rgba(15, 23, 42, 0.04);
        --shadow-md: 0 4px 6px -1px rgba(15, 23, 42, 0.06), 0 2px 4px -2px rgba(15, 23, 42, 0.04);
        --radius-sm: 6px;
        --radius: 10px;
        --radius-lg: 14px;
    }

    * { box-sizing: border-box; }

    body {
        font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        background: var(--bg);
        color: var(--ink);
        -webkit-font-smoothing: antialiased;
        -moz-osx-font-smoothing: grayscale;
    }

    /* ===== PAGE HEADER ===== */
    .page-header {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 16px;
        margin-bottom: 28px;
        flex-wrap: wrap;
    }

    .page-header__title {
        font-size: 26px;
        font-weight: 800;
        color: var(--ink);
        margin: 0 0 6px 0;
        letter-spacing: -0.02em;
    }

    .page-header__breadcrumb {
        display: flex;
        align-items: center;
        gap: 6px;
        font-size: 13px;
        color: var(--ink-muted);
    }

    .page-header__breadcrumb a {
        color: var(--ink-soft);
        text-decoration: none;
        font-weight: 500;
        transition: color 0.15s;
    }

    .page-header__breadcrumb a:hover { color: var(--brand); }
    .page-header__breadcrumb > span { color: var(--ink-muted); }

    /* ===== BUTTONS ===== */
    .action-btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 9px 16px;
        border-radius: var(--radius-sm);
        font-size: 13px;
        font-weight: 600;
        border: 1px solid var(--border);
        background: var(--surface);
        color: var(--ink-2);
        cursor: pointer;
        text-decoration: none;
        line-height: 1;
        transition: background 0.12s, border-color 0.12s, box-shadow 0.12s;
        white-space: nowrap;
    }

    .action-btn:hover {
        background: var(--surface-soft);
        border-color: var(--border-strong);
    }

    .action-btn--success {
        background: var(--ok);
        border-color: var(--ok);
        color: #fff;
    }
    .action-btn--success:hover {
        background: #15803d;
        border-color: #15803d;
    }

    .action-btn--danger {
        background: var(--surface);
        border-color: var(--danger);
        color: var(--danger);
    }
    .action-btn--danger:hover {
        background: var(--danger-soft);
    }

    .action-btn--primary {
        background: var(--brand);
        border-color: var(--brand);
        color: #fff;
    }
    .action-btn--primary:hover {
        background: var(--brand-dark);
        border-color: var(--brand-dark);
    }

    /* ===== STATUS BADGES ===== */
    .status-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        border-radius: 999px;
        font-size: 11.5px;
        font-weight: 600;
        letter-spacing: 0.01em;
        line-height: 1.4;
    }

    .status-badge--menunggu {
        background: var(--warn-soft);
        color: var(--warn);
    }

    .status-badge--diterima {
        background: var(--ok-soft);
        color: var(--ok);
    }

    .status-badge--ditolak,
    .status-badge--dibatalkan {
        background: var(--danger-soft);
        color: var(--danger);
    }

    .status-badge--lunas {
        background: var(--ok-soft);
        color: var(--ok);
    }

    .status-badge--belum_lunas {
        background: var(--warn-soft);
        color: var(--warn);
    }

    .status-badge--menunggu_bayar {
        background: var(--purple-soft);
        color: var(--purple);
    }

    /* ===== JENIS BADGE ===== */
    .jenis-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 6px;
        background: var(--brand-soft);
        color: var(--brand);
        font-size: 11.5px;
        font-weight: 700;
        letter-spacing: 0.02em;
    }

    /* ===== RINGKASAN PESANAN ===== */
    .ringkasan-pesanan {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 24px;
        margin-bottom: 28px;
        display: flex;
        flex-wrap: wrap;
        gap: 24px;
        align-items: center;
        justify-content: space-between;
        box-shadow: var(--shadow-sm);
    }

    .ringkasan-pesanan__badges {
        flex: 1;
        min-width: 240px;
    }

    .ringkasan-pesanan__meta {
        font-size: 13px;
        color: var(--ink-soft);
        margin-bottom: 10px;
        font-weight: 500;
    }

    .ringkasan-pesanan__meta strong {
        color: var(--ink);
        font-weight: 700;
    }

    .ringkasan-pesanan__stats {
        display: flex;
        gap: 32px;
        flex-wrap: wrap;
    }

    .ringkasan-pesanan__stat {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 120px;
    }

    .ringkasan-pesanan__stat-label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--ink-muted);
        font-weight: 600;
    }

    .ringkasan-pesanan__stat-val {
        font-size: 18px;
        font-weight: 700;
        color: var(--ink);
        letter-spacing: -0.01em;
        font-variant-numeric: tabular-nums;
    }

    .ringkasan-pesanan__stat-val--warn { color: var(--warn); }
    .ringkasan-pesanan__stat-val--ok { color: var(--ok); }

    /* ===== PEMBAYARAN ITEM (TIMELINE) ===== */
    .pembayaran-item {
        margin-bottom: 24px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-xs);
    }

    .pembayaran-item__title {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 18px 24px;
        border-bottom: 1px solid var(--border);
        background: var(--surface-soft);
        flex-wrap: wrap;
    }

    .pembayaran-item__title h3 {
        margin: 0;
        font-size: 15px;
        font-weight: 700;
        color: var(--ink);
    }

    /* ===== DETAIL LAYOUT ===== */
    .detail-layout {
        display: grid;
        grid-template-columns: 1.1fr 1fr;
        gap: 0;
    }

    @media (max-width: 900px) {
        .detail-layout { grid-template-columns: 1fr; }
    }

    .detail-panel,
    .bukti-panel {
        padding: 24px;
    }

    .detail-panel {
        border-right: 1px solid var(--border);
    }

    @media (max-width: 900px) {
        .detail-panel {
            border-right: none;
            border-bottom: 1px solid var(--border);
        }
    }

    .detail-panel__head,
    .bukti-panel__head {
        margin-bottom: 20px;
    }

    .detail-panel__title,
    .bukti-panel__title {
        margin: 0 0 4px 0;
        font-size: 15px;
        font-weight: 700;
        color: var(--ink);
    }

    .detail-panel__subtitle {
        margin: 0;
        font-size: 13px;
        color: var(--ink-muted);
    }

    .detail-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 16px;
    }

    @media (max-width: 520px) {
        .detail-grid { grid-template-columns: 1fr; }
    }

    .detail-item {
        display: flex;
        flex-direction: column;
        gap: 6px;
    }

    .detail-item--full { grid-column: 1 / -1; }

    .detail-item__label {
        font-size: 11px;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--ink-muted);
        font-weight: 600;
    }

    .detail-item__value {
        font-size: 14px;
        font-weight: 500;
        color: var(--ink);
        word-break: break-word;
    }

    .detail-item__value--mono {
        font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace;
        font-size: 13px;
        background: var(--surface-soft);
        padding: 6px 10px;
        border-radius: var(--radius-sm);
        display: inline-block;
    }

    .detail-item__value--mono a {
        color: var(--brand);
        text-decoration: none;
        font-weight: 600;
    }

    .detail-item__value--mono a:hover { text-decoration: underline; }

    .detail-item__value--amount {
        font-size: 22px;
        font-weight: 800;
        color: var(--brand);
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }

    .rekening-info {
        margin-top: 20px;
        padding: 14px 16px;
        background: var(--surface-soft);
        border-radius: var(--radius);
        border: 1px solid var(--border);
    }

    .rekening-info__row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 6px 0;
        font-size: 13px;
        color: var(--ink-soft);
    }

    .rekening-info__row strong {
        font-family: 'SFMono-Regular', Consolas, monospace;
        font-size: 13px;
        color: var(--ink);
        font-weight: 600;
    }

    /* ===== BUKTI ===== */
    .bukti-preview {
        position: relative;
        border-radius: var(--radius);
        overflow: hidden;
        cursor: pointer;
        border: 1px solid var(--border);
        background: var(--surface-soft);
    }

    .bukti-preview img {
        width: 100%;
        height: auto;
        display: block;
    }

    .bukti-preview__zoom {
        position: absolute;
        top: 12px;
        right: 12px;
        width: 36px;
        height: 36px;
        background: rgba(15, 23, 42, 0.8);
        color: #fff;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
        opacity: 0;
        transition: opacity 0.15s;
    }

    .bukti-preview:hover .bukti-preview__zoom { opacity: 1; }

    .bukti-actions {
        display: flex;
        gap: 8px;
        margin-top: 14px;
    }

    .bukti-actions .action-btn {
        flex: 1;
    }

    .bukti-placeholder {
        padding: 40px 20px;
        text-align: center;
        background: var(--surface-soft);
        border-radius: var(--radius);
        border: 1px dashed var(--border-strong);
        color: var(--ink-muted);
        font-size: 13px;
    }

    .bukti-placeholder__icon {
        font-size: 36px;
        margin-bottom: 8px;
        opacity: 0.5;
    }

    /* ===== ACTION BAR ===== */
    .action-bar {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        padding: 18px 24px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 20px;
        margin-top: 4px;
        margin-bottom: 24px;
        flex-wrap: wrap;
    }

    .action-bar__info {
        flex: 1;
        min-width: 200px;
    }

    .action-bar__info strong {
        display: block;
        font-size: 14px;
        font-weight: 700;
        margin-bottom: 2px;
        color: var(--ink);
    }

    .action-bar__info span {
        font-size: 12.5px;
        color: var(--ink-soft);
    }

    .action-bar__buttons {
        display: flex;
        gap: 8px;
    }

    /* ===== LIST VIEW ===== */
    .legenda-box {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        padding: 14px 18px;
        margin-bottom: 20px;
        display: flex;
        flex-wrap: wrap;
        gap: 14px 24px;
        font-size: 12.5px;
        color: var(--ink-soft);
    }

    .legenda-box strong {
        color: var(--ink);
        font-weight: 700;
    }

    .kategori-tabs {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        margin-bottom: 16px;
        padding: 4px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius);
        overflow-x: auto;
    }

    .kategori-tab {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        border: none;
        background: transparent;
        border-radius: 6px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        color: var(--ink-soft);
        cursor: pointer;
        transition: background 0.12s, color 0.12s;
        white-space: nowrap;
        flex-shrink: 0;
    }

    .kategori-tab:hover {
        background: var(--surface-soft);
        color: var(--ink);
    }

    .kategori-tab.is-active {
        background: var(--brand);
        color: #fff;
    }

    .kategori-tab__count {
        background: rgba(0,0,0,0.08);
        border-radius: 999px;
        padding: 1px 8px;
        font-size: 11px;
        font-weight: 700;
        min-width: 20px;
        text-align: center;
    }

    .kategori-tab.is-active .kategori-tab__count {
        background: rgba(255,255,255,0.25);
    }

    .filter-bar {
        display: flex;
        gap: 10px;
        align-items: center;
        margin-bottom: 18px;
    }

    .filter-bar__search {
        flex: 1;
        padding: 10px 14px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        font-size: 13px;
        background: var(--surface);
        color: var(--ink);
        font-family: inherit;
        transition: border-color 0.12s, box-shadow 0.12s;
    }

    .filter-bar__search::placeholder { color: var(--ink-muted); }

    .filter-bar__search:focus {
        outline: none;
        border-color: var(--brand);
        box-shadow: 0 0 0 3px var(--brand-soft);
    }

    .filter-bar__count {
        padding: 10px 14px;
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-sm);
        font-size: 12.5px;
        font-weight: 600;
        color: var(--ink-soft);
        white-space: nowrap;
    }

    /* ===== TABLE ===== */
    .pembayaran-table {
        background: var(--surface);
        border: 1px solid var(--border);
        border-radius: var(--radius-lg);
        overflow: hidden;
        box-shadow: var(--shadow-sm);
    }

    .pembayaran-table__wrap { overflow-x: auto; }

    .pembayaran-table table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13.5px;
    }

    .pembayaran-table thead {
        background: var(--surface-soft);
    }

    .pembayaran-table th {
        padding: 12px 16px;
        text-align: left;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.06em;
        color: var(--ink-soft);
        border-bottom: 1px solid var(--border);
        white-space: nowrap;
    }

    .pembayaran-table td {
        padding: 14px 16px;
        border-bottom: 1px solid var(--border);
        vertical-align: middle;
    }

    .pembayaran-table tbody tr {
        transition: background 0.1s;
    }

    .pembayaran-table tbody tr:hover {
        background: var(--surface-soft);
    }

    .pembayaran-table tbody tr:last-child td {
        border-bottom: none;
    }

    .pembayaran-table__id {
        font-family: 'SFMono-Regular', Consolas, monospace;
        font-size: 13px;
        font-weight: 600;
        color: var(--brand);
    }

    .pembayaran-table__amount {
        font-family: 'SFMono-Regular', Consolas, monospace;
        font-size: 13.5px;
        font-weight: 700;
        color: var(--ink);
        font-variant-numeric: tabular-nums;
    }

    .pembayaran-table__date {
        font-size: 12.5px;
        color: var(--ink-soft);
        white-space: nowrap;
    }

    .pesanan-cell {
        display: flex;
        flex-direction: column;
        gap: 2px;
        min-width: 140px;
    }

    .pesanan-cell__nama {
        color: var(--ink-soft);
        font-size: 12px;
        font-weight: 500;
    }

    .kategori-badge-row {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 160px;
    }

    .kategori-badge-row__desc {
        font-size: 11px;
        color: var(--ink-muted);
        font-weight: 500;
        line-height: 1.3;
    }

    .nominal-sisa-tag {
        font-size: 10.5px;
        color: var(--ink-muted);
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.04em;
        margin-top: 2px;
    }

    /* ===== MODAL ===== */
    .modal-overlay {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.5);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 1000;
        padding: 20px;
    }

    .modal-overlay.is-open {
        display: flex;
    }

    .modal {
        background: var(--surface);
        border-radius: var(--radius-lg);
        padding: 32px;
        max-width: 440px;
        width: 100%;
        box-shadow: 0 20px 40px rgba(15, 23, 42, 0.15);
    }

    .modal__icon {
        width: 56px;
        height: 56px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 28px;
        margin: 0 auto 16px;
    }

    .modal__icon--success {
        background: var(--ok-soft);
        color: var(--ok);
    }

    .modal__icon--danger {
        background: var(--danger-soft);
        color: var(--danger);
    }

    .modal__title {
        margin: 0 0 8px 0;
        font-size: 18px;
        font-weight: 700;
        text-align: center;
        color: var(--ink);
    }

    .modal__message {
        margin: 0 0 20px 0;
        font-size: 13.5px;
        color: var(--ink-soft);
        text-align: center;
        line-height: 1.5;
    }

    .modal__form {
        margin-bottom: 20px;
    }

    .modal__label {
        display: block;
        font-size: 12px;
        font-weight: 600;
        color: var(--ink-2);
        margin-bottom: 6px;
    }

    .modal__textarea {
        width: 100%;
        padding: 10px 12px;
        border-radius: var(--radius-sm);
        border: 1px solid var(--border);
        font-size: 13.5px;
        font-family: inherit;
        resize: vertical;
        min-height: 100px;
        background: var(--surface);
        color: var(--ink);
        transition: border-color 0.12s, box-shadow 0.12s;
    }

    .modal__textarea:focus {
        outline: none;
        border-color: var(--brand);
        box-shadow: 0 0 0 3px var(--brand-soft);
    }

    .modal__actions {
        display: flex;
        gap: 8px;
    }

    .modal__actions .action-btn {
        flex: 1;
        padding: 10px 16px;
    }

    /* ===== LIGHTBOX ===== */
    .lightbox {
        position: fixed;
        inset: 0;
        background: rgba(15, 23, 42, 0.92);
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 2000;
        padding: 40px;
    }

    .lightbox.is-open { display: flex; }

    .lightbox img {
        max-width: 100%;
        max-height: 100%;
        border-radius: var(--radius);
    }

    .lightbox__close {
        position: absolute;
        top: 20px;
        right: 20px;
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: rgba(255,255,255,0.15);
        border: none;
        color: #fff;
        font-size: 20px;
        cursor: pointer;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: background 0.15s;
    }

    .lightbox__close:hover {
        background: rgba(255,255,255,0.25);
    }

    .lightbox__download {
        position: absolute;
        bottom: 24px;
        left: 50%;
        transform: translateX(-50%);
        padding: 10px 20px;
        background: #fff;
        color: var(--ink);
        text-decoration: none;
        border-radius: var(--radius-sm);
        font-weight: 600;
        font-size: 13px;
        display: inline-flex;
        align-items: center;
        gap: 6px;
        transition: background 0.15s;
    }

    .lightbox__download:hover {
        background: var(--surface-soft);
    }

    /* ===== RESPONSIVE ===== */
    @media (max-width: 768px) {
        .ringkasan-pesanan { padding: 20px; }
        .ringkasan-pesanan__stats { gap: 20px; }
        .action-bar { flex-direction: column; align-items: stretch; }
        .action-bar__buttons { width: 100%; }
        .action-bar__buttons .action-btn { flex: 1; }
        .pembayaran-item__title { padding: 14px 18px; }
        .detail-panel, .bukti-panel { padding: 18px; }
        .detail-grid { gap: 14px; }
    }

    @media (max-width: 640px) {
        .page-header__title { font-size: 22px; }
        .ringkasan-pesanan__stat-val { font-size: 16px; }
    }
    </style>
</head>
<body>

<div class="app">

    <aside class="sidebar" id="sidebar">
        <div class="sidebar__brand">
            <div class="sidebar__mark">CD</div>
            <div class="sidebar__brand-text">
                <strong>CD 133 Production</strong>
                <span>Panel Internal</span>
            </div>
        </div>

        <nav class="sidebar__nav" aria-label="Menu utama">
            <a href="../index.php" class="sidebar__link">
                <span class="sidebar__icon">▦</span> Dashboard
            </a>
            <a href="../produk/index.php" class="sidebar__link">
                <span class="sidebar__icon">◈</span> Kelola Produk
            </a>
            <a href="index.php" class="sidebar__link is-active">
                <span class="sidebar__icon">◇</span> informasi pesanan & verifikasi pembayaran
            </a>
        </nav>

        <a href="../logout.php" class="sidebar__logout">
            <span class="sidebar__icon">⎋</span> Logout
        </a>
    </aside>

    <div class="sidebar__overlay" id="sidebarOverlay"></div>

    <main class="main">

        <?php if ($aksi === "detail" && $detail): ?>

        <div class="page-header">
            <div>
                <h1 class="page-header__title">Detail Pembayaran</h1>
                <div class="page-header__breadcrumb">
                    <a href="index.php">Dashboard</a>
                    <span>/</span>
                    <a href="index.php">Verifikasi Pembayaran</a>
                    <span>/</span>
                    <span><?= htmlspecialchars($detail['kode_pesanan']) ?></span>
                </div>
            </div>
            <a href="index.php" class="action-btn">← Kembali</a>
        </div>

        <div class="ringkasan-pesanan">
            <div class="ringkasan-pesanan__badges">
                <div class="ringkasan-pesanan__meta">
                    <strong><?= htmlspecialchars($detail['kode_pesanan']) ?></strong> · <?= htmlspecialchars($detail['nama_pemesan']) ?>
                </div>
                <div style="display:flex;gap:8px;flex-wrap:wrap;">
                    <span class="status-badge status-badge--<?= $detail['status_gabungan']['class'] ?>">
                        <?= htmlspecialchars($detail['status_gabungan']['label']) ?>
                    </span>
                </div>
            </div>
            <div class="ringkasan-pesanan__stats">
                <div class="ringkasan-pesanan__stat">
                    <span class="ringkasan-pesanan__stat-label">Total Tagihan</span>
                    <span class="ringkasan-pesanan__stat-val"><?= formatRupiah($detail['total_tagihan']) ?></span>
                </div>
                <div class="ringkasan-pesanan__stat">
                    <span class="ringkasan-pesanan__stat-label">Sudah Diterima</span>
                    <span class="ringkasan-pesanan__stat-val ringkasan-pesanan__stat-val--ok"><?= formatRupiah($detail['total_diterima']) ?></span>
                </div>
                <div class="ringkasan-pesanan__stat">
                    <span class="ringkasan-pesanan__stat-label">Sisa Tagihan</span>
                    <span class="ringkasan-pesanan__stat-val <?= $detail['sisa_tagihan'] > 0 ? 'ringkasan-pesanan__stat-val--warn' : 'ringkasan-pesanan__stat-val--ok' ?>">
                        <?= formatRupiah($detail['sisa_tagihan']) ?>
                    </span>
                </div>
            </div>
        </div>

        <?php if (empty($daftar_pembayaran_pesanan)): ?>
            <div class="pembayaran-item">
                <div style="padding: 32px 24px; text-align: center; color: var(--ink-soft);">
                    Belum ada pembayaran untuk pesanan ini. Menunggu customer melakukan pembayaran pelunasan.
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($daftar_pembayaran_pesanan as $pb): ?>
        <?php $urutan = (int) $pb['urutan_bayar']; ?>
        <div class="pembayaran-item">

            <div class="pembayaran-item__title">
                <h3><?= labelJenisPembayaran($urutan) ?></h3>
                <span class="jenis-badge"><?= htmlspecialchars($pb['kode_pembayaran']) ?></span>
                <span class="status-badge status-badge--<?= statusToClass($pb['status']) ?>">
                    <?= htmlspecialchars($pb['status']) ?>
                </span>
            </div>

            <div class="detail-layout">

                <div class="detail-panel">
                    <div class="detail-panel__head">
                        <h2 class="detail-panel__title">Informasi Pembayaran</h2>
                        <p class="detail-panel__subtitle">Data transaksi dari customer.</p>
                    </div>

                    <div class="detail-grid">
                        <div class="detail-item">
                            <div class="detail-item__label">ID Pembayaran</div>
                            <div class="detail-item__value detail-item__value--mono"><?= htmlspecialchars($pb['kode_pembayaran']) ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-item__label">ID Pesanan</div>
                            <div class="detail-item__value detail-item__value--mono">
                                <a href="../pesanan/index.php?aksi=detail&id=<?= (int) $detail['id_pesanan'] ?>">
                                    <?= htmlspecialchars($detail['kode_pesanan']) ?>
                                </a>
                            </div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-item__label">Nama Customer</div>
                            <div class="detail-item__value"><?= htmlspecialchars($detail['nama_pemesan']) ?></div>
                        </div>

                        <div class="detail-item">
                            <div class="detail-item__label">Tanggal Bayar</div>
                            <div class="detail-item__value"><?= formatTanggalIndo($pb['tanggal_bayar']) ?></div>
                        </div>

                        <div class="detail-item detail-item--full">
                            <div class="detail-item__label">Nominal Pembayaran</div>
                            <div class="detail-item__value detail-item__value--amount"><?= formatRupiah($pb['total_pembayaran']) ?></div>
                        </div>

                        <div class="detail-item detail-item--full">
                            <div class="detail-item__label">Status</div>
                            <div class="detail-item__value">
                                <span class="status-badge status-badge--<?= statusToClass($pb['status']) ?>">
                                    <?= htmlspecialchars($pb['status']) ?>
                                </span>
                            </div>
                        </div>

                        <?php if ($pb['status'] === 'Ditolak' && $pb['alasan_penolakan']): ?>
                        <div class="detail-item detail-item--full">
                            <div class="detail-item__label">Alasan Penolakan</div>
                            <div class="detail-item__value"><?= htmlspecialchars($pb['alasan_penolakan']) ?></div>
                        </div>
                        <?php endif; ?>
                    </div>

                    <div class="rekening-info">
                        <div class="rekening-info__row">
                            <span>Transfer ke:</span>
                            <strong>BCA — 1234567890</strong>
                        </div>
                        <div class="rekening-info__row">
                            <span>Atas nama:</span>
                            <strong>CD 133 Production</strong>
                        </div>
                    </div>
                </div>

                <div class="bukti-panel">
                    <div class="bukti-panel__head">
                        <h2 class="bukti-panel__title">Bukti Pembayaran</h2>
                        <p class="detail-panel__subtitle">Klik gambar untuk memperbesar.</p>
                    </div>

                    <?php [$bukti_url, $bukti_url_fallback] = resolveBuktiUrls($pb['bukti_transfer']); ?>

                    <?php if ($pb['bukti_transfer']): ?>
                        <div class="bukti-preview" title="Klik untuk zoom">
                            <img src="<?= htmlspecialchars($bukti_url) ?>" alt="Bukti Transfer <?= htmlspecialchars($pb['kode_pembayaran']) ?>"
                                 data-fallback-src="<?= htmlspecialchars($bukti_url_fallback) ?>"
                                 onerror="if(!this.dataset.fallbackTried){this.dataset.fallbackTried='1';this.src=this.dataset.fallbackSrc;}else{this.parentElement.outerHTML='<div class=\'bukti-placeholder\'><div class=\'bukti-placeholder__icon\'>📄</div><div>Bukti transfer belum diupload</div></div>'}">
                            <div class="bukti-preview__zoom">🔍</div>
                        </div>

                        <div class="bukti-actions">
                            <a href="<?= htmlspecialchars($bukti_url) ?>" download class="action-btn">⬇ Download</a>
                            <button type="button" class="action-btn" onclick="this.closest('.detail-layout').querySelector('.bukti-preview').click()">🔍 Zoom</button>
                        </div>
                    <?php else: ?>
                        <div class="bukti-placeholder">
                            <div class="bukti-placeholder__icon">📄</div>
                            <div>Bukti transfer belum diupload</div>
                        </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <?php if ($pb['status'] === 'Menunggu Verifikasi'): ?>
        <div class="action-bar">
            <div class="action-bar__info">
                <strong>Aksi Verifikasi — <?= labelJenisPembayaran($urutan) ?></strong>
                <span>Periksa bukti transfer sebelum mengambil tindakan.</span>
            </div>
            <div class="action-bar__buttons">
                <button
                    type="button"
                    class="action-btn action-btn--success"
                    data-terima-url="index.php?aksi=terima&id=<?= (int) $pb['id_pembayaran'] ?>"
                    data-customer-name="<?= htmlspecialchars($detail['nama_pemesan']) ?>"
                    data-amount="<?= htmlspecialchars(formatRupiah($pb['total_pembayaran'])) ?>"
                >
                    ✓ Terima Pembayaran
                </button>
                <button
                    type="button"
                    class="action-btn action-btn--danger"
                    data-tolak-url="index.php?aksi=tolak&id=<?= (int) $pb['id_pembayaran'] ?>"
                    data-customer-name="<?= htmlspecialchars($detail['nama_pemesan']) ?>"
                >
                    ✕ Tolak Pembayaran
                </button>
            </div>
        </div>
        <?php endif; ?>

        <?php endforeach; ?>

        <?php else: ?>

        <div class="page-header">
            <div>
                <h1 class="page-header__title">Verifikasi Pembayaran</h1>
                <div class="page-header__breadcrumb">
                    <a href="dashboard.php">Dashboard</a>
                    <span>/</span>
                    <span>Verifikasi Pembayaran</span>
                </div>
            </div>
        </div>

        <div class="legenda-box">
            <span>🆕 <strong>Pesanan Baru</strong> — DP baru masuk, perlu diverifikasi</span>
            <span>🔍 <strong>Pelunasan · Perlu Verifikasi</strong> — bukti pelunasan sudah diupload</span>
            <span>⏳ <strong>Pelunasan · Menunggu Customer</strong> — DP diterima, pelunasan belum dibayar</span>
            <span>✅ <strong>Lunas</strong> — tagihan sudah selesai</span>
            <span>✕ <strong>Dibatalkan</strong></span>
        </div>

        <div class="kategori-tabs" id="kategoriTabs">
            <button type="button" class="kategori-tab is-active" data-kategori="all">
                Semua <span class="kategori-tab__count"><?= $total_semua_pesanan ?></span>
            </button>
            <?php foreach (['baru', 'pelunasan_menunggu_verifikasi', 'pelunasan_menunggu_bayar', 'lunas', 'dibatalkan'] as $kat): ?>
                <?php $info = infoKategori($kat); ?>
                <button type="button" class="kategori-tab" data-kategori="<?= $kat ?>">
                    <?= $info['icon'] ?> <?= htmlspecialchars($info['label']) ?>
                    <span class="kategori-tab__count"><?= $hitung_kategori[$kat] ?></span>
                </button>
            <?php endforeach; ?>
        </div>

        <div class="filter-bar">
            <input
                type="text"
                id="searchInput"
                class="filter-bar__search"
                placeholder="Cari kode pesanan, nama customer..."
            >
            <span class="filter-bar__count" id="visibleCount"><?= $total_semua_pesanan ?> pesanan</span>
        </div>

        <div class="pembayaran-table">
            <div class="pembayaran-table__wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>Pesanan</th>
                            <th>Tahap Pembayaran</th>
                            <th>Nominal</th>
                            <th>Tanggal</th>
                            <th>Status</th>
                            <th style="width: 160px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="tabelBody">
                        <?php if (empty($daftar_pesanan)): ?>
                        <tr>
                            <td colspan="7" style="text-align:center; padding:40px 20px; color:var(--ink-muted);">
                                Belum ada data pesanan/pembayaran.
                            </td>
                        </tr>
                        <?php else: ?>
                            <?php foreach ($daftar_pesanan as $no => $row): ?>
                            <?php
                                $info        = infoKategori($row['kategori']);
                                $butuhAksi   = in_array($row['kategori'], ['baru', 'pelunasan_menunggu_verifikasi'], true);
                                $tahapLabel  = labelJenisPembayaran((int) $row['urutan_tahap']);
                                $teksCari    = strtolower($row['kode_pesanan'] . ' ' . $row['nama_pemesan'] . ' ' . $row['kode_pembayaran']);
                            ?>
                            <tr data-kategori="<?= $row['kategori'] ?>" data-cari="<?= htmlspecialchars($teksCari) ?>">
                                <td style="color: var(--ink-muted); font-weight: 600;"><?= $no + 1 ?></td>
                                <td>
                                    <div class="pesanan-cell">
                                        <span class="pembayaran-table__id"><?= htmlspecialchars($row['kode_pesanan']) ?></span>
                                        <span class="pesanan-cell__nama"><?= htmlspecialchars($row['nama_pemesan']) ?></span>
                                    </div>
                                </td>
                                <td><span class="jenis-badge"><?= $tahapLabel ?></span></td>
                                <td class="pembayaran-table__amount">
                                    <?php if ($row['kategori'] === 'pelunasan_menunggu_bayar'): ?>
                                        <?= formatRupiah($row['sisa_tagihan']) ?>
                                        <div class="nominal-sisa-tag">sisa tagihan</div>
                                    <?php elseif ($row['kategori'] === 'lunas'): ?>
                                        <?= formatRupiah($row['total_diterima']) ?>
                                        <div class="nominal-sisa-tag">total diterima</div>
                                    <?php else: ?>
                                        <?= formatRupiah($row['total_pembayaran']) ?>
                                    <?php endif; ?>
                                </td>
                                <td class="pembayaran-table__date">
                                    <?= $row['kategori'] === 'pelunasan_menunggu_bayar' ? '-' : formatTanggalIndo($row['tanggal_bayar']) ?>
                                </td>
                                <td>
                                    <div class="kategori-badge-row">
                                        <span class="status-badge status-badge--<?= $info['class'] ?>">
                                            <?= $info['icon'] ?> <?= htmlspecialchars($info['label']) ?>
                                        </span>
                                        <span class="kategori-badge-row__desc"><?= htmlspecialchars($info['desc']) ?></span>
                                    </div>
                                </td>
                                <td>
                                    <div style="display:flex; gap:6px; flex-wrap:wrap;">
                                        <a href="index.php?aksi=detail&id=<?= (int) $row['id_pembayaran'] ?>" class="action-btn">Detail</a>
                                        <?php if ($butuhAksi): ?>
                                            <button
                                                type="button"
                                                class="action-btn action-btn--success"
                                                title="Terima Pembayaran"
                                                data-terima-url="index.php?aksi=terima&id=<?= (int) $row['id_pembayaran'] ?>"
                                                data-customer-name="<?= htmlspecialchars($row['nama_pemesan']) ?>"
                                                data-amount="<?= htmlspecialchars(formatRupiah($row['total_pembayaran'])) ?>"
                                            >✓</button>
                                            <button
                                                type="button"
                                                class="action-btn action-btn--danger"
                                                title="Tolak Pembayaran"
                                                data-tolak-url="index.php?aksi=tolak&id=<?= (int) $row['id_pembayaran'] ?>"
                                                data-customer-name="<?= htmlspecialchars($row['nama_pemesan']) ?>"
                                            >✕</button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <?php endif; ?>

        <div class="modal-overlay" id="terimaModal">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="modal__icon modal__icon--success">✓</div>
                <h3 class="modal__title">Terima Pembayaran?</h3>
                <p class="modal__message">
                    Pembayaran akan diterima dan pesanan diproses lebih lanjut.
                </p>
                <div class="modal__actions">
                    <button type="button" class="action-btn" data-cancel-terima>Batal</button>
                    <button type="button" class="action-btn action-btn--success" data-confirm-terima>
                        Ya, Terima
                    </button>
                </div>
            </div>
        </div>

        <div class="modal-overlay" id="tolakModal">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="modal__icon modal__icon--danger">✕</div>
                <h3 class="modal__title">Tolak Pembayaran?</h3>
                <p class="modal__message">
                    Pembayaran akan ditolak. Sertakan alasan untuk pemberitahuan ke customer.
                </p>
                <div class="modal__form">
                    <label class="modal__label" for="alasanTolak">
                        Alasan Penolakan <span style="color: var(--danger);">*</span>
                    </label>
                    <textarea
                        id="alasanTolak"
                        class="modal__textarea"
                        placeholder="Contoh: Nominal tidak sesuai, bukti tidak jelas, dll."
                        required
                    ></textarea>
                </div>
                <div class="modal__actions">
                    <button type="button" class="action-btn" data-cancel-tolak>Batal</button>
                    <button type="button" class="action-btn action-btn--danger" data-confirm-tolak>
                        Ya, Tolak
                    </button>
                </div>
            </div>
        </div>

    </main>
</div>

<div class="lightbox" id="lightbox">
    <button class="lightbox__close" aria-label="Tutup">✕</button>
    <img src="" alt="Preview">
    <a href="#" class="lightbox__download" download>⬇ Download</a>
</div>

<script src="../../assets/js/admin_index.js"></script>
<script src="../../assets/js/admin_pembayaran.js"></script>
<script>
(function () {
    var tabs      = document.querySelectorAll('#kategoriTabs .kategori-tab');
    var search    = document.getElementById('searchInput');
    var rows      = document.querySelectorAll('#tabelBody tr[data-kategori]');
    var countEl   = document.getElementById('visibleCount');
    if (!tabs.length && !search) return;

    var kategoriAktif = 'all';

    function terapkanFilter() {
        var kataKunci = (search && search.value ? search.value.toLowerCase().trim() : '');
        var jumlahTampil = 0;

        rows.forEach(function (tr) {
            var cocokKategori = (kategoriAktif === 'all') || (tr.getAttribute('data-kategori') === kategoriAktif);
            var cocokCari = !kataKunci || (tr.getAttribute('data-cari') || '').indexOf(kataKunci) !== -1;
            var tampil = cocokKategori && cocokCari;
            tr.style.display = tampil ? '' : 'none';
            if (tampil) jumlahTampil++;
        });

        if (countEl) countEl.textContent = jumlahTampil + ' pesanan';
    }

    tabs.forEach(function (tab) {
        tab.addEventListener('click', function () {
            tabs.forEach(function (t) { t.classList.remove('is-active'); });
            tab.classList.add('is-active');
            kategoriAktif = tab.getAttribute('data-kategori');
            terapkanFilter();
        });
    });

    if (search) {
        search.addEventListener('input', terapkanFilter);
    }
})();
</script>
</body>
</html>