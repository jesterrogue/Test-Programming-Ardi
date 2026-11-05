
<?php
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

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
        m.nama_mobil,
        m.merk,
        m.tahun,
        (
            SELECT gm.nama_file
            FROM gambar_mobil gm
            WHERE gm.id_mobil = m.id_mobil
            ORDER BY gm.is_utama DESC, gm.id_gambar ASC
            LIMIT 1
        ) AS gambar
    FROM pemesanan p
    INNER JOIN mobil m ON p.id_mobil = m.id_mobil
    WHERE p.id_user = :id_user
    ORDER BY p.created_at DESC
");

$stmt->execute([
    'id_user' => $_SESSION['user_id']
]);

$pemesanan = $stmt->fetchAll(PDO::FETCH_ASSOC);

require_once 'includes/header.php';

function statusBooking($status)
{
    $statusList = [
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'dikonfirmasi' => 'Dikonfirmasi',
        'berlangsung' => 'Sedang Berlangsung',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan'
    ];

    return $statusList[$status] ?? ucfirst($status);
}
?>

<main class="pemesanan-page">
    <div class="pemesanan-container">

        <div class="pemesanan-heading">
            <h1>Riwayat Pemesanan</h1>
            <p>Lihat daftar dan status penyewaan mobil Anda.</p>
        </div>

        <?php if (isset($_GET['booking']) && $_GET['booking'] === 'success'): ?>
            <div class="pemesanan-success">
                <i class="fas fa-check-circle"></i>
                Pemesanan berhasil dibuat. Silakan periksa detail pesanan Anda.
            </div>
        <?php endif; ?>
<?php if (isset($_GET['return']) && $_GET['return'] === 'success'): ?>
    <div class="pemesanan-success">
        <i class="fa-solid fa-circle-check"></i>
        Pengajuan pengembalian berhasil dikirim. Silakan menunggu konfirmasi petugas.
    </div>
<?php endif; ?>

        <?php if (empty($pemesanan)): ?>

            <div class="pemesanan-empty">
                <i class="fas fa-car-side"></i>
                <h3>Belum Ada Pemesanan</h3>
                <p>Anda belum memiliki riwayat penyewaan mobil.</p>
                <a href="mobil.php" class="pemesanan-button">
                    Lihat Pilihan Mobil
                </a>
            </div>

        <?php else: ?>

            <div class="pemesanan-list">

                <?php foreach ($pemesanan as $item): ?>

                    <?php
                    $statusClass = str_replace('_', '-', $item['status']);
                    ?>

                    <div class="pemesanan-card">

                        <div class="pemesanan-card-header">
                            <div>
                                <span class="pemesanan-label">Kode Booking</span>
                                <strong><?= e($item['kode_booking']) ?></strong>
                            </div>

                            <span class="status-booking status-<?= e($statusClass) ?>">
                                <?= e(statusBooking($item['status'])) ?>
                            </span>
                        </div>

                        <div class="pemesanan-car">

                            <div class="pemesanan-image">
                                <?php if (!empty($item['gambar'])): ?>
                                    <img
                                        src="assets/mobil/<?= rawurlencode($item['gambar']) ?>"
                                        alt="<?= e($item['nama_mobil']) ?>"
                                    >
                                <?php else: ?>
                                    <i class="fas fa-car"></i>
                                <?php endif; ?>
                            </div>

                            <div class="pemesanan-car-info">
                                <h3><?= e($item['merk'] . ' ' . $item['nama_mobil']) ?></h3>
                                <p>Tahun <?= e($item['tahun'] ?? '-') ?></p>
                                <strong><?= rupiah($item['total_harga']) ?></strong>
                            </div>

                        </div>

                        <div class="pemesanan-details">

                            <div>
                                <span>Tanggal Mulai</span>
                                <strong><?= date('d-m-Y', strtotime($item['tanggal_mulai'])) ?></strong>
                            </div>

                            <div>
                                <span>Tanggal Selesai</span>
                                <strong><?= date('d-m-Y', strtotime($item['tanggal_selesai'])) ?></strong>
                            </div>

                            <div>
                                <span>Durasi</span>
                                <strong><?= (int) $item['jumlah_hari'] ?> Hari</strong>
                            </div>

                            <div>
                                <span>Harga per Hari</span>
                                <strong><?= rupiah($item['harga_per_hari']) ?></strong>
                            </div>

                        </div>

                        <div class="pemesanan-card-footer">
    <span>
        Dipesan pada
        <?= date('d-m-Y H:i', strtotime($item['created_at'])) ?>
    </span>

    <div class="pemesanan-actions">
        <a href="detail-pemesanan.php?id=<?= (int) $item['id_booking'] ?>">
            Lihat Detail
            <i class="fa-solid fa-arrow-right"></i>
        </a>

        <?php if ($item['status'] === 'berlangsung'): ?>
            <a
                href="ajukan-pengembalian.php?id=<?= (int) $item['id_booking'] ?>"
                class="btn-ajukan-pengembalian"
            >
                <i class="fa-solid fa-car-side"></i>
                Kembalikan Mobil
            </a>
        <?php endif; ?>
    </div>
</div>

                    </div>

                <?php endforeach; ?>

            </div>

        <?php endif; ?>

    </div>
</main>

<?php require_once 'includes/footer.php'; ?>
