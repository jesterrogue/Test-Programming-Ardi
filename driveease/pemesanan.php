
<?php
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

// Pastikan pelanggan sudah login
if (!isset($_SESSION['user_id'])) {
    $id = (int) ($_GET['id'] ?? 0);

    header('Location: login.php?redirect=' . urlencode('pemesanan.php?id=' . $id));
    exit;
}

// Ambil ID mobil
$idMobil = (int) ($_GET['id'] ?? $_POST['id_mobil'] ?? 0);

if ($idMobil < 1) {
    header('Location: mobil.php');
    exit;
}

// Siapkan CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Ambil data mobil
$stmt = $pdo->prepare("
    SELECT
        id_mobil,
        nama_mobil,
        merk,
        tahun,
        transmisi,
        kapasitas,
        bahan_bakar,
        harga_per_hari,
        status
    FROM mobil
    WHERE id_mobil = :id
    LIMIT 1
");

$stmt->execute(['id' => $idMobil]);
$mobil = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mobil) {
    http_response_code(404);
    exit('Mobil tidak ditemukan.');
}

$error = '';
$tanggalMulai = '';
$tanggalSelesai = '';

// Proses pemesanan
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $tanggalMulai = trim($_POST['tanggal_mulai'] ?? '');
    $tanggalSelesai = trim($_POST['tanggal_selesai'] ?? '');
    $csrf = $_POST['csrf_token'] ?? '';

    if (!hash_equals($_SESSION['csrf_token'], $csrf)) {
        $error = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    } else {

        // Validasi format tanggal
        $mulai = DateTime::createFromFormat('!Y-m-d', $tanggalMulai);
        $selesai = DateTime::createFromFormat('!Y-m-d', $tanggalSelesai);

        $validMulai = $mulai &&
            $mulai->format('Y-m-d') === $tanggalMulai;

        $validSelesai = $selesai &&
            $selesai->format('Y-m-d') === $tanggalSelesai;

        if (!$validMulai || !$validSelesai) {
            $error = 'Format tanggal tidak valid.';

        } elseif ($tanggalMulai < date('Y-m-d')) {
            $error = 'Tanggal mulai tidak boleh sebelum hari ini.';

        } elseif ($tanggalSelesai <= $tanggalMulai) {
            $error = 'Tanggal pengembalian harus setelah tanggal mulai.';

        } else {

            $jumlahHari = (int) $mulai->diff($selesai)->days;

            try {
                $pdo->beginTransaction();

                // Kunci data mobil selama proses booking
                $lockMobil = $pdo->prepare("
                    SELECT harga_per_hari, status
                    FROM mobil
                    WHERE id_mobil = :id
                    FOR UPDATE
                ");

                $lockMobil->execute(['id' => $idMobil]);
                $mobilTerkunci = $lockMobil->fetch(PDO::FETCH_ASSOC);

                if (
                    !$mobilTerkunci ||
                    $mobilTerkunci['status'] !== 'tersedia'
                ) {
                    throw new Exception(
                        'Mobil sedang tidak tersedia untuk disewa.'
                    );
                }

                // Periksa bentrok jadwal
                $cekBooking = $pdo->prepare("
                    SELECT id_booking
                    FROM pemesanan
                    WHERE id_mobil = :id_mobil
                    AND status IN (
                        'menunggu_pembayaran',
                        'menunggu_konfirmasi',
                        'dikonfirmasi',
                        'berlangsung'
                    )
                    AND tanggal_mulai < :tanggal_selesai
                    AND tanggal_selesai > :tanggal_mulai
                    LIMIT 1
                ");

                $cekBooking->execute([
                    'id_mobil' => $idMobil,
                    'tanggal_mulai' => $tanggalMulai,
                    'tanggal_selesai' => $tanggalSelesai
                ]);

                if ($cekBooking->fetch()) {
                    throw new Exception(
                        'Mobil sudah dipesan pada tanggal tersebut. Silakan pilih tanggal lain.'
                    );
                }

                // Hitung harga berdasarkan database
                $hargaPerHari = (float) $mobilTerkunci['harga_per_hari'];
                $totalHarga = $hargaPerHari * $jumlahHari;

                // Buat kode booking unik
                $kodeBooking = 'DE-' . strtoupper(bin2hex(random_bytes(5)));

                // Simpan pemesanan
                $insert = $pdo->prepare("
                    INSERT INTO pemesanan (
                        kode_booking,
                        id_user,
                        id_mobil,
                        tanggal_mulai,
                        tanggal_selesai,
                        jumlah_hari,
                        harga_per_hari,
                        total_harga,
                        status
                    ) VALUES (
                        :kode_booking,
                        :id_user,
                        :id_mobil,
                        :tanggal_mulai,
                        :tanggal_selesai,
                        :jumlah_hari,
                        :harga_per_hari,
                        :total_harga,
                        'menunggu_pembayaran'
                    )
                ");

                $insert->execute([
                    'kode_booking' => $kodeBooking,
                    'id_user' => $_SESSION['user_id'],
                    'id_mobil' => $idMobil,
                    'tanggal_mulai' => $tanggalMulai,
                    'tanggal_selesai' => $tanggalSelesai,
                    'jumlah_hari' => $jumlahHari,
                    'harga_per_hari' => $hargaPerHari,
                    'total_harga' => $totalHarga
                ]);

                // Simpan transaksi
                $pdo->commit();

                // Arahkan ke riwayat pemesanan
                header('Location: riwayat-pemesanan.php?booking=success');
                exit;

            } catch (PDOException $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = 'Terjadi kesalahan database saat menyimpan pemesanan.';

            } catch (Exception $e) {

                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                $error = $e->getMessage();
            }
        }
    }
}

// Tampilkan halaman
require_once 'includes/header.php';
?>

<main class="booking-page">

    <div class="booking-container">

        <a href="detail-mobil.php?id=<?= (int) $mobil['id_mobil'] ?>"
           class="back-link">
            <i class="fas fa-arrow-left"></i>
            Kembali ke Detail Mobil
        </a>

        <div class="booking-heading">
            <h1>Form Pemesanan</h1>
            <p>Lengkapi tanggal penyewaan mobil pilihan Anda.</p>
        </div>

        <div class="booking-card">

            <!-- Informasi Mobil -->
            <div class="booking-car-info">

                <div class="booking-car-icon">
                    <i class="fa-solid fa-car-side"></i>
                </div>

                <div>
                    <h2>
                        <?= e($mobil['merk'] . ' ' . $mobil['nama_mobil']) ?>
                    </h2>

                    <p>
                        <?= e($mobil['tahun'] ?? '-') ?> ·
                        <?= e(ucfirst($mobil['transmisi'])) ?> ·
                        <?= (int) $mobil['kapasitas'] ?> Kursi
                    </p>

                    <strong>
                        <?= rupiah($mobil['harga_per_hari']) ?> / hari
                    </strong>
                </div>

            </div>

            <!-- Pesan Error -->
            <?php if ($error): ?>
                <div class="booking-alert">
                    <i class="fas fa-exclamation-circle"></i>
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <?php if ($mobil['status'] !== 'tersedia'): ?>

                <div class="booking-unavailable">
                    <i class="fas fa-car-side"></i>
                    <h3>Mobil Tidak Tersedia</h3>
                    <p>Mobil ini sedang tidak dapat dipesan.</p>

                    <a href="mobil.php" class="booking-button">
                        Pilih Mobil Lain
                    </a>
                </div>

            <?php else: ?>

                <form
                    method="POST"
                    action="pemesanan.php?id=<?= (int) $mobil['id_mobil'] ?>"
                    id="bookingForm"
                >

                    <input
                        type="hidden"
                        name="id_mobil"
                        value="<?= (int) $mobil['id_mobil'] ?>"
                    >

                    <input
                        type="hidden"
                        name="csrf_token"
                        value="<?= e($_SESSION['csrf_token']) ?>"
                    >

                    <!-- Tanggal Mulai -->
                    <div class="booking-form-group">

                        <label for="tanggal_mulai">
                            Tanggal Mulai Sewa
                        </label>

                        <input
                            type="date"
                            id="tanggal_mulai"
                            name="tanggal_mulai"
                            min="<?= date('Y-m-d') ?>"
                            value="<?= e($tanggalMulai) ?>"
                            required
                        >

                    </div>

                    <!-- Tanggal Selesai -->
                    <div class="booking-form-group">

                        <label for="tanggal_selesai">
                            Tanggal Pengembalian
                        </label>

                        <input
                            type="date"
                            id="tanggal_selesai"
                            name="tanggal_selesai"
                            min="<?= date('Y-m-d', strtotime('+1 day')) ?>"
                            value="<?= e($tanggalSelesai) ?>"
                            required
                        >

                    </div>

                    <!-- Ringkasan Harga -->
                    <div class="booking-summary">

                        <div>
                            <span>Durasi Sewa</span>
                            <strong id="jumlahHari">-</strong>
                        </div>

                        <div>
                            <span>Harga per Hari</span>
                            <strong id="hargaHarian">
                                <?= rupiah($mobil['harga_per_hari']) ?>
                            </strong>
                        </div>

                        <div class="booking-total">
                            <span>Total Pembayaran</span>
                            <strong id="totalHarga">-</strong>
                        </div>

                    </div>

                    <p class="booking-note">
                        <i class="fas fa-info-circle"></i>
                        Tanggal pengembalian tidak dikenakan biaya sewa tambahan. Pemesanan akan berstatus
                        menunggu pembayaran setelah dikirim.
                    </p>

                    <button type="submit" class="booking-button">
                        <i class="fas fa-calendar-check"></i>
                        Konfirmasi Pemesanan
                    </button>

                </form>

            <?php endif; ?>

        </div>

    </div>

</main>

<script>
const tanggalMulai = document.getElementById('tanggal_mulai');
const tanggalSelesai = document.getElementById('tanggal_selesai');

const hargaPerHari = <?= (float) $mobil['harga_per_hari'] ?>;

const jumlahHariEl = document.getElementById('jumlahHari');
const totalHargaEl = document.getElementById('totalHarga');

function formatRupiah(angka) {
    return new Intl.NumberFormat('id-ID', {
        style: 'currency',
        currency: 'IDR',
        maximumFractionDigits: 0
    }).format(angka);
}

function hitungBooking() {

    if (!tanggalMulai || !tanggalSelesai) {
        return;
    }

    const mulai = tanggalMulai.value;
    const selesai = tanggalSelesai.value;

    // Atur tanggal minimum pengembalian
    if (mulai) {

        const minSelesai = new Date(mulai + 'T00:00:00');

        minSelesai.setDate(minSelesai.getDate() + 1);

        const tahun = minSelesai.getFullYear();
        const bulan = String(minSelesai.getMonth() + 1).padStart(2, '0');
        const hari = String(minSelesai.getDate()).padStart(2, '0');

        tanggalSelesai.min = `${tahun}-${bulan}-${hari}`;
    }

    if (!mulai || !selesai || selesai <= mulai) {

        jumlahHariEl.textContent = '-';
        totalHargaEl.textContent = '-';

        return;
    }

    const tanggal1 = new Date(mulai + 'T00:00:00');
    const tanggal2 = new Date(selesai + 'T00:00:00');

    const selisih = Math.round(
        (tanggal2 - tanggal1) / (1000 * 60 * 60 * 24)
    );

    const total = selisih * hargaPerHari;

    jumlahHariEl.textContent = selisih + ' Hari';
    totalHargaEl.textContent = formatRupiah(total);
}

tanggalMulai?.addEventListener('change', hitungBooking);
tanggalSelesai?.addEventListener('change', hitungBooking);

hitungBooking();
</script>

<?php require_once 'includes/footer.php'; ?>
