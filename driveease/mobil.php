
<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

// Ambil daftar kategori
$stmtKategori = $pdo->query("
    SELECT id_kategori, nama_kategori
    FROM kategori_mobil
    ORDER BY nama_kategori ASC
");
$kategoriList = $stmtKategori->fetchAll();

// Ambil filter dari URL
$keyword = trim($_GET['q'] ?? '');
$kategori = $_GET['kategori'] ?? '';
$transmisi = $_GET['transmisi'] ?? '';
$minHarga = $_GET['min_harga'] ?? '';
$maxHarga = $_GET['max_harga'] ?? '';

$tanggalMulai = $_GET['tanggal_mulai'] ?? '';
$tanggalSelesai = $_GET['tanggal_selesai'] ?? '';

// Validasi tanggal
$errorTanggal = '';
$filterTanggalAktif = ($tanggalMulai !== '' || $tanggalSelesai !== '');
$tanggalValid = true;

if ($filterTanggalAktif) {

    // Kedua tanggal wajib diisi
    if ($tanggalMulai === '' || $tanggalSelesai === '') {
        $errorTanggal = 'Tanggal mulai dan tanggal selesai wajib diisi.';
        $tanggalValid = false;

    } else {

        $mulai = DateTime::createFromFormat('!Y-m-d', $tanggalMulai);
        $selesai = DateTime::createFromFormat('!Y-m-d', $tanggalSelesai);

        $errorMulai = DateTime::getLastErrors();

        $validMulai = $mulai !== false
            && $mulai->format('Y-m-d') === $tanggalMulai
            && ($errorMulai === false || (
                $errorMulai['warning_count'] === 0
                && $errorMulai['error_count'] === 0
            ));

        $errorSelesai = DateTime::getLastErrors();

        $validSelesai = $selesai !== false
            && $selesai->format('Y-m-d') === $tanggalSelesai
            && ($errorSelesai === false || (
                $errorSelesai['warning_count'] === 0
                && $errorSelesai['error_count'] === 0
            ));

        if (!$validMulai || !$validSelesai) {
            $errorTanggal = 'Format tanggal tidak valid.';
            $tanggalValid = false;

        } elseif ($tanggalMulai < date('Y-m-d')) {
            $errorTanggal = 'Tanggal mulai tidak boleh sebelum hari ini.';
            $tanggalValid = false;

        } elseif ($tanggalSelesai <= $tanggalMulai) {
            $errorTanggal = 'Tanggal selesai harus setelah tanggal mulai.';
            $tanggalValid = false;
        }
    }
}

// Susun query secara dinamis
$where = ["m.status = 'tersedia'"];
$params = [];

// Pencarian nama atau merek
if ($keyword !== '') {
    $where[] = "(m.nama_mobil LIKE :keyword1
                 OR m.merk LIKE :keyword2)";

    $params['keyword1'] = "%$keyword%";
    $params['keyword2'] = "%$keyword%";
}

// Filter kategori
if ($kategori !== '' && ctype_digit($kategori)) {
    $where[] = "m.id_kategori = :kategori";
    $params['kategori'] = (int) $kategori;
}

// Filter transmisi
if (in_array($transmisi, ['manual', 'matic'], true)) {
    $where[] = "m.transmisi = :transmisi";
    $params['transmisi'] = $transmisi;
}

// Filter harga minimum
if ($minHarga !== '' && is_numeric($minHarga) && $minHarga >= 0) {
    $where[] = "m.harga_per_hari >= :min_harga";
    $params['min_harga'] = (float) $minHarga;
}

// Filter harga maksimum
if ($maxHarga !== '' && is_numeric($maxHarga) && $maxHarga >= 0) {
    $where[] = "m.harga_per_hari <= :max_harga";
    $params['max_harga'] = (float) $maxHarga;
}

// Filter ketersediaan berdasarkan tanggal sewa
if ($filterTanggalAktif && $tanggalValid) {

    $where[] = "
        NOT EXISTS (
            SELECT 1
            FROM pemesanan p
            WHERE p.id_mobil = m.id_mobil

            AND p.status IN (
                'menunggu_pembayaran',
                'menunggu_konfirmasi',
                'dikonfirmasi',
                'berlangsung'
            )

            AND p.tanggal_mulai < :tanggal_selesai
            AND p.tanggal_selesai > :tanggal_mulai
        )
    ";

    $params['tanggal_selesai'] = $tanggalSelesai;
    $params['tanggal_mulai'] = $tanggalMulai;
}

// Ambil daftar mobil
$daftarMobil = [];

if ($tanggalValid) {

    $sql = "
        SELECT
            m.*,
            k.nama_kategori,
            (
                SELECT gm.nama_file
                FROM gambar_mobil gm
                WHERE gm.id_mobil = m.id_mobil
                ORDER BY gm.is_utama DESC, gm.id_gambar ASC
                LIMIT 1
            ) AS gambar_utama

        FROM mobil m

        LEFT JOIN kategori_mobil k
            ON m.id_kategori = k.id_kategori

        WHERE " . implode(' AND ', $where) . "

        ORDER BY m.created_at DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);

    $daftarMobil = $stmt->fetchAll();
}

require_once 'includes/header.php';
?>

<section class="catalog-page">

    <div class="catalog-header">
        <span class="section-label">KOLEKSI DRIVE EASE</span>
        <h1>Pilihan Mobil</h1>
        <p>
            Temukan mobil yang sesuai dengan kebutuhan perjalanan Anda.
        </p>
    </div>

    <form action="mobil.php" method="GET" class="filter-box">

        <div class="filter-group search-filter">
            <label for="q">Cari mobil</label>
            <input
                type="text"
                id="q"
                name="q"
                placeholder="Nama atau merek mobil..."
                value="<?= e($keyword) ?>"
            >
        </div>

        <div class="filter-group">
            <label for="kategori">Kategori</label>
            <select name="kategori" id="kategori">
                <option value="">Semua kategori</option>

                <?php foreach ($kategoriList as $item): ?>
                    <option
                        value="<?= (int) $item['id_kategori'] ?>"
                        <?= $kategori == $item['id_kategori'] ? 'selected' : '' ?>
                    >
                        <?= e($item['nama_kategori']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="filter-group">
            <label for="transmisi">Transmisi</label>
            <select name="transmisi" id="transmisi">
                <option value="">Semua transmisi</option>

                <option value="manual"
                    <?= $transmisi === 'manual' ? 'selected' : '' ?>>
                    Manual
                </option>

                <option value="matic"
                    <?= $transmisi === 'matic' ? 'selected' : '' ?>>
                    Matic
                </option>
            </select>
        </div>

        <div class="filter-group">
            <label for="min_harga">Harga minimum / hari</label>
            <input
                type="number"
                id="min_harga"
                name="min_harga"
                min="0"
                placeholder="Contoh: 100000"
                value="<?= e($minHarga) ?>"
            >
        </div>

        <div class="filter-group">
            <label for="max_harga">Harga maksimum / hari</label>
            <input
                type="number"
                id="max_harga"
                name="max_harga"
                min="0"
                placeholder="Contoh: 500000"
                value="<?= e($maxHarga) ?>"
            >
        </div>

        <!-- Filter tanggal sewa -->
        <div class="filter-group availability-search">
            <label for="tanggal_mulai">
                <i class="fa-regular fa-calendar"></i>
                Tanggal Mulai Sewa
            </label>

            <input
                type="date"
                id="tanggal_mulai"
                name="tanggal_mulai"
                min="<?= date('Y-m-d') ?>"
                value="<?= e($tanggalMulai) ?>"
            >
        </div>

        <div class="filter-group availability-search">
            <label for="tanggal_selesai">
                <i class="fa-regular fa-calendar-check"></i>
                Tanggal Selesai Sewa
            </label>

            <input
                type="date"
                id="tanggal_selesai"
                name="tanggal_selesai"
                min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                value="<?= e($tanggalSelesai) ?>"
            >
        </div>

        <div class="filter-actions">
            <button type="submit" class="btn-filter">
                <i class="fa-solid fa-magnifying-glass"></i>
                Cari Mobil
            </button>

            <a href="mobil.php" class="btn-reset">
                Reset
            </a>
        </div>

    </form>

    <!-- Pesan validasi tanggal -->
    <?php if ($errorTanggal !== ''): ?>

        <div class="empty-catalog">
            <i class="fa-solid fa-triangle-exclamation"></i>
            <h3>Tanggal Tidak Valid</h3>
            <p><?= e($errorTanggal) ?></p>
        </div>

    <?php else: ?>

        <div class="catalog-result">
            <div>
                <h2>
                    <?= $filterTanggalAktif
                        ? 'Mobil Tersedia pada Periode Ini'
                        : 'Mobil Tersedia' ?>
                </h2>

                <p>
                    Ditemukan <?= count($daftarMobil) ?> mobil.
                </p>

                <?php if ($filterTanggalAktif): ?>
                    <p>
                        Periode:
                        <?= date('d-m-Y', strtotime($tanggalMulai)) ?>
                        sampai
                        <?= date('d-m-Y', strtotime($tanggalSelesai)) ?>
                    </p>
                <?php endif; ?>
            </div>
        </div>

        <?php if (count($daftarMobil) > 0): ?>

            <div class="car-grid">

                <?php foreach ($daftarMobil as $mobil): ?>

                    <article class="car-card">

                        <?php if (!empty($mobil['gambar_utama'])): ?>

                            <img
                                src="assets/mobil/<?= e(basename($mobil['gambar_utama'])) ?>"
                                alt="<?= e($mobil['nama_mobil']) ?>"
                                class="car-image"
                                loading="lazy"
                            >

                        <?php else: ?>

                            <div class="car-image-placeholder">
                                <span>Foto belum tersedia</span>
                            </div>

                        <?php endif; ?>

                        <div class="car-content">

                            <span class="car-category">
                                <?= e($mobil['nama_kategori'] ?? 'Umum') ?>
                            </span>

                            <h3><?= e($mobil['nama_mobil']) ?></h3>

                            <p class="car-brand">
                                <?= e($mobil['merk']) ?>
                                <?= !empty($mobil['tahun'])
                                    ? ' · ' . e($mobil['tahun'])
                                    : '' ?>
                            </p>

                            <div class="car-specs">

                                <span>
                                    <i class="fa-solid fa-gear"></i>
                                    <?= e(ucfirst($mobil['transmisi'])) ?>
                                </span>

                                <span>
                                    <i class="fa-solid fa-users"></i>
                                    <?= e($mobil['kapasitas']) ?> Kursi
                                </span>

                                <span>
                                    <i class="fa-solid fa-gas-pump"></i>
                                    <?= e($mobil['bahan_bakar']) ?>
                                </span>

                            </div>

                            <div class="car-price">
                                <div>
                                    <strong>
                                        <?= rupiah($mobil['harga_per_hari']) ?>
                                    </strong>
                                    <small>/ hari</small>
                                </div>

                                <a
                                    href="detail-mobil.php?id=<?= (int) $mobil['id_mobil'] ?>"
                                    class="btn-detail"
                                >
                                    Detail
                                </a>
                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="empty-catalog">
                <i class="fa-solid fa-car-side"></i>

                <h3>Mobil tidak ditemukan</h3>

                <p>
                    Belum ada mobil yang sesuai dengan pencarian Anda.
                    Silakan ubah filter atau periode tanggal sewa.
                </p>

                <a href="mobil.php" class="btn-filter">
                    Tampilkan Semua Mobil
                </a>
            </div>

        <?php endif; ?>

    <?php endif; ?>

</section>

<?php require_once 'includes/footer.php'; ?>
