
<?php

require_once 'config/database.php';
require_once 'includes/functions.php';

$pageTitle = 'DriveEase - Rental Mobil';

$query = $pdo->query("
    SELECT
        m.*,
        k.nama_kategori,

        (
            SELECT g.nama_file
            FROM gambar_mobil g
            WHERE g.id_mobil = m.id_mobil
            ORDER BY g.is_utama DESC, g.id_gambar ASC
            LIMIT 1
        ) AS foto

    FROM mobil m

    LEFT JOIN kategori_mobil k
    ON m.id_kategori = k.id_kategori

    WHERE m.status = 'tersedia'

    ORDER BY m.created_at DESC

    LIMIT 3
");

$mobil = $query->fetchAll();

require 'includes/header.php';

?>

<section class="hero">

    <div class="container hero-content">

        <div class="hero-text">

            <span class="subtitle">
                PERJALANAN DIMULAI DI SINI
            </span>

            <h1>
                Jelajahi Lebih Jauh,
                <span>Tanpa Repot.</span>
            </h1>

            <p>
                Temukan mobil yang sesuai dengan
                kebutuhan perjalanan Anda.
                Pesan kendaraan dengan mudah
                melalui DriveEase.
            </p>

            <a href="mobil.php" class="btn-primary hero-button">
                Temukan Mobil →
            </a>

        </div>

        <div class="hero-image">
            <div class="hero-circle"></div>

            <div class="hero-car">
                🚘
            </div>
        </div>

    </div>

</section>


<section class="search-section container">

    <form action="mobil.php" method="GET"
          class="search-box">

        <div>
            <label>Lokasi Pengambilan</label>

            <select name="lokasi">
                <option value="">Pilih lokasi</option>
                <option value="Jambi">Jambi</option>
            </select>
        </div>

        <div>
            <label>Tanggal Mulai</label>

            <input type="date"
                   name="mulai"
                   id="tanggalMulai"
                   min="<?= date('Y-m-d') ?>">
        </div>

        <div>
            <label>Tanggal Selesai</label>

            <input type="date"
                   name="selesai"
                   id="tanggalSelesai"
                   min="<?= date('Y-m-d') ?>">
        </div>

        <button type="submit" class="btn-primary">
            Cari Mobil →
        </button>

    </form>

</section>


<section class="section container">

    <div class="section-heading">

        <div>
            <span class="subtitle">
                PILIHAN KENDARAAN
            </span>

            <h2>Temukan Mobil yang Tepat</h2>

            <p>
                Pilih kendaraan sesuai kebutuhan
                perjalanan Anda.
            </p>
        </div>

        <a href="mobil.php" class="see-all">
            Lihat Semua →
        </a>

    </div>


    <?php if (count($mobil) > 0): ?>

        <div class="car-grid">

            <?php foreach ($mobil as $item): ?>

                <div class="car-card">

                    <div class="car-image">

                        <?php if (!empty($item['foto'])): ?>

                            <img
                                src="assets/mobil/<?= e($item['foto']) ?>"
                                alt="<?= e($item['nama_mobil']) ?>"
                            >

                        <?php else: ?>

                            <div class="no-image">
                                🚘
                            </div>

                        <?php endif; ?>

                        <span class="available">
                            Tersedia
                        </span>

                    </div>

                    <div class="car-info">

                        <small>
                            <?= e($item['nama_kategori'] ?? 'Kendaraan') ?>
                        </small>

                        <h3>
                            <?= e($item['merk']) ?>
                            <?= e($item['nama_mobil']) ?>
                        </h3>

                        <p>
                            <?= (int) $item['kapasitas'] ?>
                            Penumpang
                            ·
                            <?= e(ucfirst($item['transmisi'])) ?>
                        </p>

                        <div class="car-bottom">

                            <strong>
                                <?= rupiah($item['harga_per_hari']) ?>
                                <small>/hari</small>
                            </strong>

                            <a href="detail-mobil.php?id=<?= (int) $item['id_mobil'] ?>"
                               class="btn-detail">
                                Detail
                            </a>

                        </div>

                    </div>

                </div>

            <?php endforeach; ?>

        </div>

    <?php else: ?>

        <div class="empty-state">

            <div>🚗</div>

            <h3>Armada Sedang Dipersiapkan</h3>

            <p>
                Informasi kendaraan akan muncul
                setelah admin menambahkan data mobil.
            </p>

        </div>

    <?php endif; ?>

</section>


<section class="steps-section" id="cara-sewa">

    <div class="container">

        <div class="section-heading center">

            <span class="subtitle">
                MUDAH DAN PRAKTIS
            </span>

            <h2>Bagaimana Cara Menyewa?</h2>

        </div>

        <div class="steps-grid">

            <div class="step-card">
                <span>01</span>
                <h3>Pilih Mobil</h3>
                <p>
                    Temukan kendaraan sesuai
                    kebutuhan Anda.
                </p>
            </div>

            <div class="step-card">
                <span>02</span>
                <h3>Tentukan Jadwal</h3>
                <p>
                    Tentukan tanggal mulai
                    dan pengembalian.
                </p>
            </div>

            <div class="step-card">
                <span>03</span>
                <h3>Konfirmasi Pesanan</h3>
                <p>
                    Lengkapi data dan lakukan
                    pembayaran.
                </p>
            </div>

        </div>

    </div>

</section>

<?php require 'includes/footer.php'; ?>
