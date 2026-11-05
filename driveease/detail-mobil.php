
<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Validasi ID mobil
$idMobil = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idMobil || $idMobil < 1) {
    http_response_code(404);
    exit('Mobil tidak ditemukan.');
}

// Ambil informasi mobil
$stmt = $pdo->prepare("
    SELECT
        m.*,
        k.nama_kategori
    FROM mobil m
    LEFT JOIN kategori_mobil k
        ON m.id_kategori = k.id_kategori
    WHERE m.id_mobil = :id
    LIMIT 1
");

$stmt->execute(['id' => $idMobil]);
$mobil = $stmt->fetch();

if (!$mobil) {
    http_response_code(404);
    exit('Mobil tidak ditemukan.');
}

// Ambil seluruh foto mobil
$stmtGambar = $pdo->prepare("
    SELECT nama_file, is_utama
    FROM gambar_mobil
    WHERE id_mobil = :id
    ORDER BY is_utama DESC, id_gambar ASC
");

$stmtGambar->execute(['id' => $idMobil]);
$daftarGambar = $stmtGambar->fetchAll();

$tersedia = $mobil['status'] === 'tersedia';

require_once 'includes/header.php';
?>

<section class="detail-page">

    <a href="mobil.php" class="back-link">
        <i class="fa-solid fa-arrow-left"></i>
        Kembali ke katalog
    </a>

    <div class="detail-layout">

        <div class="detail-gallery">

            <?php if (count($daftarGambar) > 0): ?>

                <div class="main-car-image">
                    <img
                        id="mainCarImage"
                        src="assets/mobil/<?= e(basename($daftarGambar[0]['nama_file'])) ?>"
                        alt="<?= e($mobil['nama_mobil']) ?>"
                    >
                </div>

                <?php if (count($daftarGambar) > 1): ?>

                    <div class="thumbnail-list">

                        <?php foreach ($daftarGambar as $index => $gambar): ?>

                            <button
                                type="button"
                                class="thumbnail-button <?= $index === 0 ? 'active' : '' ?>"
                                onclick="changeCarImage(this)"
                                data-image="assets/mobil/<?= e(basename($gambar['nama_file'])) ?>"
                                aria-label="Lihat foto <?= $index + 1 ?>"
                            >
                                <img
                                    src="assets/mobil/<?= e(basename($gambar['nama_file'])) ?>"
                                    alt="Foto <?= $index + 1 ?> <?= e($mobil['nama_mobil']) ?>"
                                >
                            </button>

                        <?php endforeach; ?>

                    </div>

                <?php endif; ?>

            <?php else: ?>

                <div class="detail-image-placeholder">
                    <i class="fa-solid fa-car-side"></i>
                    <p>Foto mobil belum tersedia</p>
                </div>

            <?php endif; ?>

        </div>

        <div class="detail-information">

            <span class="car-category">
                <?= e($mobil['nama_kategori'] ?? 'Umum') ?>
            </span>

            <h1><?= e($mobil['nama_mobil']) ?></h1>

            <p class="detail-brand">
                <?= e($mobil['merk']) ?>
                <?= !empty($mobil['tahun']) ? ' · ' . e($mobil['tahun']) : '' ?>
            </p>

            <div class="detail-status <?= $tersedia ? 'status-available' : 'status-unavailable' ?>">
                <i class="fa-solid fa-circle"></i>
                <?= $tersedia ? 'Tersedia untuk disewa' : 'Tidak tersedia untuk disewa' ?>
            </div>

            <div class="detail-price">
                <span>Harga sewa</span>
                <h2><?= rupiah($mobil['harga_per_hari']) ?></h2>
                <small>per hari</small>
            </div>

            <div class="detail-specification">

                <h3>Spesifikasi Mobil</h3>

                <div class="specification-grid">

                    <div class="specification-item">
                        <i class="fa-solid fa-calendar"></i>
                        <div>
                            <small>Tahun</small>
                            <strong><?= e($mobil['tahun'] ?: '-') ?></strong>
                        </div>
                    </div>

                    <div class="specification-item">
                        <i class="fa-solid fa-gear"></i>
                        <div>
                            <small>Transmisi</small>
                            <strong><?= e(ucfirst($mobil['transmisi'])) ?></strong>
                        </div>
                    </div>

                    <div class="specification-item">
                        <i class="fa-solid fa-users"></i>
                        <div>
                            <small>Kapasitas</small>
                            <strong><?= e($mobil['kapasitas']) ?> Kursi</strong>
                        </div>
                    </div>

                    <div class="specification-item">
                        <i class="fa-solid fa-gas-pump"></i>
                        <div>
                            <small>Bahan Bakar</small>
                            <strong><?= e($mobil['bahan_bakar']) ?></strong>
                        </div>
                    </div>

                </div>

            </div>

            <div class="detail-description">
                <h3>Deskripsi Mobil</h3>

                <p>
                    <?= !empty($mobil['deskripsi'])
                        ? nl2br(e($mobil['deskripsi']))
                        : 'Belum ada deskripsi untuk mobil ini.' ?>
                </p>
            </div>

<?php if ($tersedia): ?>

    <a
        href="pemesanan.php?id=<?= (int) $mobil['id_mobil'] ?>"
        class="btn-booking"
    >
        <i class="fa-solid fa-calendar-check"></i>
        Lanjutkan Pemesanan
    </a>

<?php else: ?>

    <button type="button" class="btn-booking" disabled>
        Mobil Tidak Tersedia
    </button>

<?php endif; ?>

        </div>

    </div>

</section>

<script>
function changeCarImage(button) {
    const mainImage = document.getElementById('mainCarImage');

    if (!mainImage) return;

    mainImage.src = button.dataset.image;

    document.querySelectorAll('.thumbnail-button').forEach(item => {
        item.classList.remove('active');
    });

    button.classList.add('active');
}
</script>

<?php require_once 'includes/footer.php'; ?>
