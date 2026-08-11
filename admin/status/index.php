<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
<link rel="icon" type="image/svg+xml" href="../../assets/img/logo.svg">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Update Status Pesanan — CD 133 Production</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- PERBAIKAN PATH CSS -->
    <link rel="stylesheet" href="../../assets/css/tokens.css">
    <link rel="stylesheet" href="../../assets/css/admin_status.css">
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
            <a href="../dashboard.php" class="sidebar__link">
                <span class="sidebar__icon">▦</span> Dashboard
            </a>
            <a href="../produk/index.php" class="sidebar__link">
                <span class="sidebar__icon">◈</span> Kelola Produk
            </a>
            <a href="../pesanan/index.php" class="sidebar__link">
                <span class="sidebar__icon">▤</span> Kelola Pesanan
            </a>
            <a href="../pembayaran/index.php" class="sidebar__link">
                <span class="sidebar__icon">◇</span> Verifikasi Pembayaran
            </a>
            <a href="index.php" class="sidebar__link is-active">
                <span class="sidebar__icon">↻</span> Update Status Pesanan
            </a>
            <a href="../laporan/index.php" class="sidebar__link">
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

        <?php
        $aksi = isset($_GET['aksi']) ? $_GET['aksi'] : '';
        $id   = isset($_GET['id'])   ? (int)$_GET['id'] : 0;
        ?>

        <?php if($aksi == "update"){ ?>

        <!-- ===== VIEW: FORM UPDATE ===== -->
        
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Update Status Pesanan</h1>
                <div class="page-header__breadcrumb">
                    <a href="../dashboard.php">Dashboard</a>
                    <span>/</span>
                    <a href="index.php">Update Status</a>
                    <span>/</span>
                    <span>PSN001</span>
                </div>
            </div>
            <a href="index.php" class="action-btn">← Kembali</a>
        </div>

        <form id="statusForm" action="" method="post">

            <!-- Info Pesanan -->
            <div class="panel">
                <div class="panel__head">
                    <div>
                        <h2 class="panel__title">Informasi Pesanan</h2>
                        <p class="panel__subtitle">Data pesanan yang akan diubah statusnya.</p>
                    </div>
                    <span class="status-badge status-badge--diproses">Diproses</span>
                </div>

                <div class="order-summary">
                    <div class="order-summary__item">
                        <span class="order-summary__label">ID Pesanan</span>
                        <span class="order-summary__value order-summary__value--mono">PSN001</span>
                    </div>
                    <div class="order-summary__item">
                        <span class="order-summary__label">Customer</span>
                        <span class="order-summary__value">Ahmad</span>
                    </div>
                    <div class="order-summary__item">
                        <span class="order-summary__label">Produk</span>
                        <span class="order-summary__value">Kaos Custom</span>
                    </div>
                    <div class="order-summary__item">
                        <span class="order-summary__label">Jumlah</span>
                        <span class="order-summary__value">50 pcs</span>
                    </div>
                </div>
            </div>

            <!-- Stepper: Pilih Status Baru -->
            <div class="panel">
                <div class="panel__head">
                    <div>
                        <h2 class="panel__title">Pilih Status Baru</h2>
                        <p class="panel__subtitle">Klik tahap produksi yang sesuai dengan kondisi saat ini.</p>
                    </div>
                </div>

                <div class="stepper" id="statusStepper" data-current="Diproses">
                    <div class="stepper__step is-done" data-status="Menunggu Verifikasi">
                        <span class="stepper__dot"></span>
                        <span class="stepper__label">Menunggu Verifikasi</span>
                        <span class="stepper__hint">Cek pembayaran</span>
                    </div>
                    <div class="stepper__step is-current" data-status="Diproses">
                        <span class="stepper__dot"></span>
                        <span class="stepper__label">Diproses</span>
                        <span class="stepper__hint">Sedang produksi</span>
                    </div>
                    <div class="stepper__step" data-status="Selesai">
                        <span class="stepper__dot"></span>
                        <span class="stepper__label">Selesai</span>
                        <span class="stepper__hint">Siap diambil</span>
                    </div>
                    <div class="stepper__step" data-status="Diambil">
                        <span class="stepper__dot"></span>
                        <span class="stepper__label">Diambil</span>
                        <span class="stepper__hint">Transaksi selesai</span>
                    </div>
                </div>

                <input type="hidden" name="status" id="statusBaru" value="">

                <!-- Preview Perubahan -->
                <div class="change-preview" id="changePreview">
                    <h3 class="change-preview__title">📋 Ringkasan Perubahan</h3>
                    <div class="change-preview__flow">
                        <span class="change-preview__from">
                            <span class="status-badge status-badge--diproses">Diproses</span>
                        </span>
                        <span class="change-preview__arrow">→</span>
                        <span class="change-preview__to">—</span>
                    </div>
                    <div class="change-preview__warning"></div>
                </div>
            </div>

            <!-- History Log -->
            <div class="panel">
                <div class="panel__head">
                    <div>
                        <h2 class="panel__title">Riwayat Perubahan</h2>
                        <p class="panel__subtitle">Log perubahan status pesanan ini.</p>
                    </div>
                </div>

                <ul class="history-log__list">
                    <li class="history-log__item">
                        <span class="history-log__dot"></span>
                        <div class="history-log__content">
                            <strong>Diproses</strong> oleh Admin · 11 Juli 2026, 14:30<br>
                            <span>Produksi kaos custom 50 pcs dimulai.</span>
                        </div>
                    </li>
                    <li class="history-log__item">
                        <span class="history-log__dot"></span>
                        <div class="history-log__content">
                            <strong>Menunggu Verifikasi</strong> → <strong>Diproses</strong><br>
                            <span>Pembayaran DP telah diverifikasi oleh Admin.</span>
                        </div>
                    </li>
                    <li class="history-log__item">
                        <span class="history-log__dot"></span>
                        <div class="history-log__content">
                            <strong>Pesanan Dibuat</strong> · 10 Juli 2026, 09:15<br>
                            <span>Pesanan masuk dari customer Ahmad.</span>
                        </div>
                    </li>
                </ul>
            </div>

            <!-- Actions -->
            <div class="form-actions">
                <button type="submit" name="simpan" class="btn btn--primary">
                    💾 Simpan Perubahan
                </button>
                <a href="index.php" class="btn btn--ghost">Batal</a>
            </div>

        </form>

        <!-- Modal Konfirmasi -->
        <div class="modal-overlay" id="confirmModal">
            <div class="modal" role="dialog" aria-modal="true">
                <div class="modal__icon modal__icon--warn">↻</div>
                <h3 class="modal__title">Konfirmasi Perubahan Status</h3>
                <p class="modal__message">
                    Status pesanan akan diubah. Lanjutkan?
                </p>
                <div class="modal__actions">
                    <button type="button" class="btn btn--ghost" data-cancel-save>Batal</button>
                    <button type="button" class="btn btn--primary" data-confirm-save>Ya, Simpan</button>
                </div>
            </div>
        </div>

        <?php }else{ ?>

        <!-- ===== VIEW: LIST ===== -->
        
        <div class="page-header">
            <div>
                <h1 class="page-header__title">Update Status Pesanan</h1>
                <div class="page-header__breadcrumb">
                    <a href="../dashboard.php">Dashboard</a>
                    <span>/</span>
                    <span>Update Status</span>
                </div>
            </div>
        </div>

        <!-- Filter -->
        <div class="filter-bar">
            <input 
                type="text" 
                id="searchInput" 
                class="filter-bar__search" 
                placeholder="Cari ID pesanan, nama customer, produk..."
            >
            <select id="statusFilter" class="filter-bar__select">
                <option value="all">Semua Status</option>
                <option value="Menunggu Verifikasi">Menunggu Verifikasi</option>
                <option value="Diproses">Diproses</option>
                <option value="Selesai">Selesai</option>
                <option value="Diambil">Diambil</option>
            </select>
            <span class="filter-bar__count" id="visibleCount">3 pesanan</span>
        </div>

        <!-- Table -->
        <div class="data-table">
            <div class="data-table__wrap">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px;">No</th>
                            <th>ID Pesanan</th>
                            <th>Customer</th>
                            <th>Produk</th>
                            <th>Status</th>
                            <th style="width: 150px;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr data-status="Diproses">
                            <td>1</td>
                            <td style="font-family: 'Courier New', monospace; font-weight: 600; color: var(--denim);">PSN001</td>
                            <td>Ahmad</td>
                            <td>Kaos Custom</td>
                            <td>
                                <div class="status-cell">
                                    <span class="status-badge status-badge--diproses">Diproses</span>
                                    <div class="status-cell__progress">
                                        <div class="status-cell__bar" style="width: 50%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="?aksi=update&id=1" class="action-btn action-btn--primary">↻ Update</a>
                            </td>
                        </tr>

                        <tr data-status="Selesai">
                            <td>2</td>
                            <td style="font-family: 'Courier New', monospace; font-weight: 600; color: var(--denim);">PSN002</td>
                            <td>Budi</td>
                            <td>Hoodie</td>
                            <td>
                                <div class="status-cell">
                                    <span class="status-badge status-badge--selesai">Selesai</span>
                                    <div class="status-cell__progress">
                                        <div class="status-cell__bar" style="width: 75%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="?aksi=update&id=2" class="action-btn action-btn--primary">↻ Update</a>
                            </td>
                        </tr>

                        <tr data-status="Menunggu Verifikasi">
                            <td>3</td>
                            <td style="font-family: 'Courier New', monospace; font-weight: 600; color: var(--denim);">PSN003</td>
                            <td>Siti</td>
                            <td>Jaket</td>
                            <td>
                                <div class="status-cell">
                                    <span class="status-badge status-badge--menunggu">Menunggu Verifikasi</span>
                                    <div class="status-cell__progress">
                                        <div class="status-cell__bar" style="width: 25%;"></div>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <a href="?aksi=update&id=3" class="action-btn action-btn--primary">↻ Update</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php } ?>

    </main>
</div>

<script src="../dashboard.js"></script>
<script src="status.js"></script>
</body>
</html>