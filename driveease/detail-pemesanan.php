
<?php
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Pastikan pengguna sudah login
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Validasi ID booking
$idBooking = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idBooking) {
    header('Location: riwayat-pemesanan.php');
    exit;
}

// Ambil detail pemesanan dan informasi pelanggan
$stmt = $pdo->prepare("
    SELECT
        p.id_booking,
        p.kode_booking,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.jumlah_hari,
        p.harga_per_hari,
        p.total_harga,
        p.status,
        p.created_at,

        m.id_mobil,
        m.nama_mobil,
        m.merk,
        m.nomor_plat,
        m.tahun,
        m.transmisi,
        m.kapasitas,
        m.bahan_bakar,

        u.nama_lengkap,
        u.no_hp,
        u.no_sim,
        u.alamat,

        (
            SELECT gm.nama_file
            FROM gambar_mobil gm
            WHERE gm.id_mobil = m.id_mobil
            ORDER BY gm.is_utama DESC, gm.id_gambar ASC
            LIMIT 1
        ) AS gambar

    FROM pemesanan p
    INNER JOIN mobil m ON p.id_mobil = m.id_mobil
    INNER JOIN users u ON p.id_user = u.id_user
    WHERE p.id_booking = :id_booking
      AND p.id_user = :id_user
    LIMIT 1
");

$stmt->execute([
    'id_booking' => $idBooking,
    'id_user' => $_SESSION['user_id']
]);

$booking = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$booking) {
    header('Location: riwayat-pemesanan.php');
    exit;
}

// Ambil data pengembalian jika sudah diajukan
$stmt = $pdo->prepare("
    SELECT
        id_pengembalian,
        tanggal_pengembalian,
        jumlah_hari_aktual,
        kondisi_mobil,
        biaya_tambahan,
        total_biaya_akhir,
        catatan,
        status_pengembalian
    FROM pengembalian
    WHERE id_booking = :id_booking
    LIMIT 1
");

$stmt->execute(['id_booking' => $idBooking]);
$pengembalian = $stmt->fetch(PDO::FETCH_ASSOC);

// Informasi status pemesanan
$statusBooking = [
    'menunggu_pembayaran' => 'Menunggu Pembayaran',
    'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
    'dikonfirmasi' => 'Dikonfirmasi',
    'berlangsung' => 'Sedang Berlangsung',
    'selesai' => 'Selesai',
    'dibatalkan' => 'Dibatalkan'
];

$statusPengembalian = [
    'menunggu_konfirmasi' => 'Menunggu Konfirmasi Petugas',
    'disetujui' => 'Disetujui',
    'ditolak' => 'Ditolak'
];

$status = $statusBooking[$booking['status']] ?? $booking['status'];

$statusClass = str_replace('_', '-', $booking['status']);

$gambar = !empty($booking['gambar'])
    ? 'assets/mobil/' . rawurlencode($booking['gambar'])
    : 'assets/images/no-image.png';

require_once 'includes/header.php';
?>

<main class="detail-booking-page">

    <div class="detail-booking-container">

        <div class="detail-booking-heading">
            <a href="riwayat-pemesanan.php" class="detail-back">
                <i class="fa-solid fa-arrow-left"></i>
                Kembali ke Riwayat
            </a>

            <h1>Detail Pemesanan</h1>
            <p>Informasi lengkap penyewaan mobil Anda.</p>
        </div>

        <!-- Informasi status -->
        <div class="detail-booking-status">

            <div>
                <span>Status Pemesanan</span>
                <h2><?= e($status) ?></h2>
            </div>

            <span class="status-booking status-<?= e($statusClass) ?>">
                <?= e($status) ?>
            </span>

        </div>

        <!-- Kode booking -->
        <section class="detail-booking-card">

            <div class="detail-card-heading">
                <h3>Informasi Booking</h3>
            </div>

            <div class="detail-code">
                <span>Kode Booking</span>
                <strong><?= e($booking['kode_booking']) ?></strong>
            </div>

            <div class="detail-info-grid">

                <div>
                    <span>Tanggal Pemesanan</span>
                    <strong>
                        <?= date('d-m-Y H:i', strtotime($booking['created_at'])) ?>
                    </strong>
                </div>

                <div>
                    <span>Tanggal Mulai</span>
                    <strong>
                        <?= date('d-m-Y', strtotime($booking['tanggal_mulai'])) ?>
                    </strong>
                </div>

                <div>
                    <span>Tanggal Selesai</span>
                    <strong>
                        <?= date('d-m-Y', strtotime($booking['tanggal_selesai'])) ?>
                    </strong>
                </div>

                <div>
                    <span>Durasi Sewa</span>
                    <strong><?= (int) $booking['jumlah_hari'] ?> Hari</strong>
                </div>

            </div>

        </section>

        <!-- Informasi mobil -->
        <section class="detail-booking-card">

            <div class="detail-card-heading">
                <h3>Informasi Mobil</h3>
            </div>

            <div class="detail-booking-car">

                <img
                    src="<?= e($gambar) ?>"
                    alt="<?= e($booking['nama_mobil']) ?>"
                    onerror="this.onerror=null;this.src='assets/images/no-image.png';"
                >

                <div>
                    <h3><?= e($booking['merk'] . ' ' . $booking['nama_mobil']) ?></h3>
                    <p><?= e($booking['tahun'] ?? '-') ?></p>
                    <strong><?= e($booking['nomor_plat'] ?? '-') ?></strong>
                </div>

            </div>

            <div class="detail-info-grid">

                <div>
                    <span>Transmisi</span>
                    <strong><?= e(ucfirst($booking['transmisi'])) ?></strong>
                </div>

                <div>
                    <span>Kapasitas</span>
                    <strong><?= (int) $booking['kapasitas'] ?> Orang</strong>
                </div>

                <div>
                    <span>Bahan Bakar</span>
                    <strong><?= e($booking['bahan_bakar']) ?></strong>
                </div>

            </div>

        </section>

        <!-- Informasi pelanggan -->
        <section class="detail-booking-card">

            <div class="detail-card-heading">
                <h3>Informasi Penyewa</h3>
            </div>

            <div class="detail-info-grid">

                <div>
                    <span>Nama Lengkap</span>
                    <strong><?= e($booking['nama_lengkap']) ?></strong>
                </div>

                <div>
                    <span>Nomor Telepon</span>
                    <strong><?= e($booking['no_hp']) ?></strong>
                </div>

                <div>
                    <span>Nomor SIM</span>
                    <strong><?= e($booking['no_sim']) ?></strong>
                </div>

                <div>
                    <span>Alamat</span>
                    <strong><?= e($booking['alamat']) ?></strong>
                </div>

            </div>

        </section>

        <!-- Rincian biaya -->
        <section class="detail-booking-card">

            <div class="detail-card-heading">
                <h3>Rincian Biaya Pemesanan</h3>
            </div>

            <div class="detail-cost-row">
                <span>Harga Sewa per Hari</span>
                <strong><?= rupiah($booking['harga_per_hari']) ?></strong>
            </div>

            <div class="detail-cost-row">
                <span>Durasi Sewa</span>
                <strong><?= (int) $booking['jumlah_hari'] ?> Hari</strong>
            </div>

            <div class="detail-cost-total">
                <span>Total Biaya Pemesanan</span>
                <strong><?= rupiah($booking['total_harga']) ?></strong>
            </div>

        </section>

        <!-- Informasi pengembalian -->
        <?php if ($pengembalian): ?>

            <section class="detail-booking-card">

                <div class="detail-card-heading">
                    <h3>Informasi Pengembalian</h3>
                </div>

                <div class="detail-info-grid">

                    <div>
                        <span>Tanggal Pengajuan</span>
                        <strong>
                            <?= $pengembalian['tanggal_pengembalian']
                                ? date('d-m-Y H:i', strtotime($pengembalian['tanggal_pengembalian']))
                                : '-' ?>
                        </strong>
                    </div>

                    <div>
                        <span>Durasi Aktual</span>
                        <strong>
                            <?= (int) $pengembalian['jumlah_hari_aktual'] ?> Hari
                        </strong>
                    </div>

                    <div>
                        <span>Kondisi Mobil</span>
                        <strong>
                            <?= $pengembalian['kondisi_mobil'] === 'baik'
                                ? 'Baik'
                                : 'Ada Kerusakan' ?>
                        </strong>
                    </div>

                    <div>
                        <span>Status Pengembalian</span>
                        <strong>
                            <?= e(
                                $statusPengembalian[$pengembalian['status_pengembalian']]
                                ?? $pengembalian['status_pengembalian']
                            ) ?>
                        </strong>
                    </div>

                </div>

                <div class="detail-cost-row">
                    <span>Biaya Sewa Aktual</span>
                    <strong>
                        <?= rupiah(
                            $pengembalian['jumlah_hari_aktual'] * $booking['harga_per_hari']
                        ) ?>
                    </strong>
                </div>

                <div class="detail-cost-row">
                    <span>Biaya Tambahan</span>
                    <strong><?= rupiah($pengembalian['biaya_tambahan']) ?></strong>
                </div>

                <div class="detail-cost-total">
                    <span>Total Biaya Akhir</span>
                    <strong><?= rupiah($pengembalian['total_biaya_akhir']) ?></strong>
                </div>

                <?php if (!empty($pengembalian['catatan'])): ?>
                    <div class="detail-return-note">
                        <span>Catatan Pengembalian</span>
                        <p><?= nl2br(e($pengembalian['catatan'])) ?></p>
                    </div>
                <?php endif; ?>

            </section>

        <?php endif; ?>

        <!-- Tombol aksi -->
        <div class="detail-booking-actions">

            <?php if ($booking['status'] === 'berlangsung' && !$pengembalian): ?>

                <a
                    href="ajukan-pengembalian.php?id=<?= (int) $booking['id_booking'] ?>"
                    class="detail-action-primary"
                >
                    <i class="fa-solid fa-car-side"></i>
                    Kembalikan Mobil
                </a>

            <?php elseif ($booking['status'] === 'menunggu_pembayaran'): ?>

                <div class="detail-action-notice">
                    Silakan lanjutkan proses pembayaran sesuai instruksi petugas.
                </div>

            <?php elseif ($pengembalian && $pengembalian['status_pengembalian'] === 'menunggu_konfirmasi'): ?>

                <div class="detail-action-notice">
                    Pengajuan pengembalian sedang diperiksa oleh petugas.
                </div>

            <?php elseif ($pengembalian && $pengembalian['status_pengembalian'] === 'ditolak'): ?>

                <div class="detail-action-notice">
                    Pengajuan pengembalian ditolak. Silakan hubungi petugas untuk informasi lebih lanjut.
                </div>

            <?php endif; ?>

            <a href="riwayat-pemesanan.php" class="detail-action-secondary">
                Kembali ke Riwayat Pemesanan
            </a>

        </div>

    </div>

</main>

<?php require_once 'includes/footer.php'; ?>
