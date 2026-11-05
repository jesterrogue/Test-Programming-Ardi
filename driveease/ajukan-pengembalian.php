
<?php
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$idBooking = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idBooking) {
    header('Location: riwayat-pemesanan.php');
    exit;
}

// ==========================================
// AMBIL BOOKING MILIK PENGGUNA
// ==========================================

$stmt = $pdo->prepare("
    SELECT
        p.id_booking,
        p.kode_booking,
        p.id_user,
        p.id_mobil,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.jumlah_hari,
        p.harga_per_hari,
        p.total_harga,
        p.status,
        m.nama_mobil,
        m.merk,
        m.nomor_plat

    FROM pemesanan p

    INNER JOIN mobil m
        ON p.id_mobil = m.id_mobil

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

// Hanya booking berlangsung yang dapat dikembalikan
if ($booking['status'] !== 'berlangsung') {
    header('Location: riwayat-pemesanan.php');
    exit;
}

// ==========================================
// CEK PENGAJUAN PENGEMBALIAN SEBELUMNYA
// ==========================================

$stmt = $pdo->prepare("
    SELECT id_pengembalian
    FROM pengembalian
    WHERE id_booking = :id_booking
    LIMIT 1
");

$stmt->execute([
    'id_booking' => $idBooking
]);

if ($stmt->fetch()) {
    header('Location: riwayat-pemesanan.php');
    exit;
}

// ==========================================
// CSRF TOKEN
// ==========================================

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $token = $_POST['csrf_token'] ?? '';

    $nomorPlatInput = trim($_POST['nomor_plat'] ?? '');

    $kondisiMobil = $_POST['kondisi_mobil'] ?? '';

    $catatan = trim($_POST['catatan'] ?? '');

    // ======================================
    // VALIDASI FORM
    // ======================================

    if (
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {

        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';

    } elseif ($nomorPlatInput === '') {

        $error = 'Nomor plat mobil wajib diisi.';

    } elseif (!in_array(
        $kondisiMobil,
        ['baik', 'ada_kerusakan'],
        true
    )) {

        $error = 'Silakan pilih kondisi mobil.';

    } elseif (strlen($catatan) > 5000) {

        $error = 'Catatan terlalu panjang.';

    } else {

        try {

            // Normalisasi nomor plat
            // Contoh: BH 1234 XX menjadi BH1234XX
            $nomorPlatBersih = preg_replace(
                '/[\s-]+/',
                '',
                strtoupper($nomorPlatInput)
            );

            $pdo->beginTransaction();

            // ======================================
            // KUNCI DAN VERIFIKASI BOOKING
            // ======================================

            $stmt = $pdo->prepare("
                SELECT
                    p.id_booking,
                    p.tanggal_mulai,
                    p.harga_per_hari,
                    p.status,
                    m.nomor_plat

                FROM pemesanan p

                INNER JOIN mobil m
                    ON p.id_mobil = m.id_mobil

                WHERE p.id_booking = :id_booking
                  AND p.id_user = :id_user

                FOR UPDATE
            ");

            $stmt->execute([
                'id_booking' => $idBooking,
                'id_user' => $_SESSION['user_id']
            ]);

            $bookingTerkunci = $stmt->fetch(PDO::FETCH_ASSOC);

            if (
                !$bookingTerkunci ||
                $bookingTerkunci['status'] !== 'berlangsung'
            ) {

                throw new Exception(
                    'Pemesanan tidak dapat diproses untuk pengembalian.'
                );
            }

            // ======================================
            // VERIFIKASI NOMOR PLAT
            // ======================================

            $nomorPlatDatabase = preg_replace(
                '/[\s-]+/',
                '',
                strtoupper($bookingTerkunci['nomor_plat'])
            );

            if ($nomorPlatBersih !== $nomorPlatDatabase) {

                throw new Exception(
                    'Nomor plat tidak sesuai dengan mobil yang Anda sewa.'
                );
            }

            // ======================================
            // CEK PENGEMBALIAN SEBELUMNYA
            // ======================================

            $stmt = $pdo->prepare("
                SELECT id_pengembalian
                FROM pengembalian
                WHERE id_booking = :id_booking
                LIMIT 1
            ");

            $stmt->execute([
                'id_booking' => $idBooking
            ]);

            if ($stmt->fetch()) {

                throw new Exception(
                    'Pengembalian untuk pemesanan ini sudah pernah diajukan.'
                );
            }

            // ======================================
            // HITUNG DURASI AKTUAL
            // ======================================

            $tanggalMulai = new DateTimeImmutable(
                $bookingTerkunci['tanggal_mulai']
            );

            $tanggalHariIni = new DateTimeImmutable('today');

            if ($tanggalHariIni < $tanggalMulai) {

                throw new Exception(
                    'Tanggal mulai penyewaan belum tiba.'
                );
            }

            $jumlahHariAktual = max(
                1,
                (int) $tanggalMulai->diff($tanggalHariIni)->days
            );

            // ======================================
            // HITUNG BIAYA
            // ======================================

            $hargaPerHari = (float) $bookingTerkunci['harga_per_hari'];

            // Biaya tambahan ditentukan admin
            $biayaTambahan = 0;

            $totalBiayaAkhir =
                ($jumlahHariAktual * $hargaPerHari) + $biayaTambahan;

            // ======================================
            // SIMPAN PENGEMBALIAN
            // ======================================

            $stmt = $pdo->prepare("
                INSERT INTO pengembalian (
                    id_booking,
                    tanggal_pengembalian,
                    jumlah_hari_aktual,
                    kondisi_mobil,
                    biaya_tambahan,
                    total_biaya_akhir,
                    catatan,
                    status_pengembalian
                ) VALUES (
                    :id_booking,
                    NOW(),
                    :jumlah_hari_aktual,
                    :kondisi_mobil,
                    :biaya_tambahan,
                    :total_biaya_akhir,
                    :catatan,
                    'menunggu_konfirmasi'
                )
            ");

            $stmt->execute([
                'id_booking' => $idBooking,
                'jumlah_hari_aktual' => $jumlahHariAktual,
                'kondisi_mobil' => $kondisiMobil,
                'biaya_tambahan' => $biayaTambahan,
                'total_biaya_akhir' => $totalBiayaAkhir,
                'catatan' => $catatan !== '' ? $catatan : null
            ]);

            $pdo->commit();

            header('Location: riwayat-pemesanan.php?return=success');
            exit;

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = 'Terjadi kesalahan database. Silakan coba kembali.';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $error = $e->getMessage();
        }
    }
}

require_once 'includes/header.php';
?>

<main class="return-page">

    <div class="return-container">

        <div class="return-heading">

            <h1>Pengembalian Mobil</h1>

            <p>
                Masukkan nomor plat dan lengkapi informasi kondisi mobil.
            </p>

        </div>

        <?php if ($error !== ''): ?>

            <div class="return-alert">

                <i class="fa-solid fa-circle-exclamation"></i>

                <?= e($error) ?>

            </div>

        <?php endif; ?>

        <div class="return-card">

            <h3>Informasi Pemesanan</h3>

            <div class="return-info">

                <div>
                    <span>Kode Booking</span>
                    <strong><?= e($booking['kode_booking']) ?></strong>
                </div>

                <div>
                    <span>Mobil</span>
                    <strong>
                        <?= e($booking['merk'] . ' ' . $booking['nama_mobil']) ?>
                    </strong>
                </div>

                <div>
                    <span>Nomor Plat Terdaftar</span>
                    <strong><?= e($booking['nomor_plat']) ?></strong>
                </div>

                <div>
                    <span>Tanggal Mulai</span>
                    <strong>
                        <?= date('d-m-Y', strtotime($booking['tanggal_mulai'])) ?>
                    </strong>
                </div>

                <div>
                    <span>Rencana Selesai</span>
                    <strong>
                        <?= date('d-m-Y', strtotime($booking['tanggal_selesai'])) ?>
                    </strong>
                </div>

                <div>
                    <span>Harga per Hari</span>
                    <strong>
                        <?= rupiah($booking['harga_per_hari']) ?>
                    </strong>
                </div>

            </div>

            <div class="return-notice">

                <i class="fa-solid fa-circle-info"></i>

                Masukkan nomor plat mobil sesuai kendaraan yang Anda sewa.
                Biaya tambahan, jika ada, akan ditentukan petugas setelah pemeriksaan.

            </div>

            <form method="POST">

                <input
                    type="hidden"
                    name="csrf_token"
                    value="<?= e($_SESSION['csrf_token']) ?>"
                >

                <!-- INPUT NOMOR PLAT -->

                <div class="return-form-group">

                    <label for="nomor_plat">
                        Nomor Plat Mobil
                    </label>

                    <input
                        type="text"
                        name="nomor_plat"
                        id="nomor_plat"
                        placeholder="Contoh: BH 1234 XX"
                        value="<?= e($_POST['nomor_plat'] ?? '') ?>"
                        maxlength="20"
                        autocomplete="off"
                        required
                    >

                </div>

                <!-- KONDISI MOBIL -->

                <div class="return-form-group">

                    <label for="kondisi_mobil">
                        Kondisi Mobil
                    </label>

                    <select
                        name="kondisi_mobil"
                        id="kondisi_mobil"
                        required
                    >

                        <option value="">
                            -- Pilih Kondisi Mobil --
                        </option>

                        <option
                            value="baik"
                            <?= (($_POST['kondisi_mobil'] ?? '') === 'baik') ? 'selected' : '' ?>
                        >
                            Baik
                        </option>

                        <option
                            value="ada_kerusakan"
                            <?= (($_POST['kondisi_mobil'] ?? '') === 'ada_kerusakan') ? 'selected' : '' ?>
                        >
                            Ada Kerusakan
                        </option>

                    </select>

                </div>

                <!-- CATATAN -->

                <div class="return-form-group">

                    <label for="catatan">
                        Catatan Pengembalian (Opsional)
                    </label>

                    <textarea
                        name="catatan"
                        id="catatan"
                        rows="4"
                        maxlength="5000"
                        placeholder="Tuliskan catatan kondisi mobil..."
                    ><?= e($_POST['catatan'] ?? '') ?></textarea>

                </div>

                <button
                    type="submit"
                    class="return-submit-button"
                >

                    <i class="fa-solid fa-car-side"></i>

                    Ajukan Pengembalian

                </button>

                <a
                    href="riwayat-pemesanan.php"
                    class="return-cancel-button"
                >
                    Kembali ke Riwayat
                </a>

            </form>

        </div>

    </div>

</main>

<?php require_once 'includes/footer.php'; ?>
