
<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$pesan = '';
$jenisPesan = '';

// ==========================================
// PROSES AKSI ADMIN
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Validasi CSRF
    adminValidasiCsrf();

    $idBooking = filter_input(
        INPUT_POST,
        'id_booking',
        FILTER_VALIDATE_INT
    );

    $aksi = $_POST['aksi'] ?? '';

    $daftarAksi = [
        'konfirmasi',
        'tolak',
        'mulai_sewa'
    ];

    if (!$idBooking || !in_array($aksi, $daftarAksi, true)) {

        $pesan = 'Data pemesanan tidak valid.';
        $jenisPesan = 'error';

    } else {

        try {

            $pdo->beginTransaction();

            // Ambil pemesanan dan kunci baris
            $stmt = $pdo->prepare("
                SELECT id_booking, status
                FROM pemesanan
                WHERE id_booking = :id_booking
                FOR UPDATE
            ");

            $stmt->execute([
                'id_booking' => $idBooking
            ]);

            $booking = $stmt->fetch();

            if (!$booking) {
                throw new Exception('Pemesanan tidak ditemukan.');
            }

            // ==========================================
            // VALIDASI DAN PERUBAHAN STATUS
            // ==========================================

            if ($aksi === 'mulai_sewa') {

                // Hanya pemesanan yang sudah dikonfirmasi
                // yang dapat dimulai penyewaannya.
                if ($booking['status'] !== 'dikonfirmasi') {

                    throw new Exception(
                        'Pemesanan harus dikonfirmasi terlebih dahulu.'
                    );
                }

                $statusBaru = 'berlangsung';

            } else {

                // Konfirmasi dan penolakan hanya untuk
                // pemesanan yang masih menunggu.
                $statusMenunggu = [
                    'menunggu_pembayaran',
                    'menunggu_konfirmasi'
                ];

                if (!in_array(
                    $booking['status'],
                    $statusMenunggu,
                    true
                )) {

                    throw new Exception(
                        'Pemesanan ini sudah diproses sebelumnya.'
                    );
                }

                if ($aksi === 'konfirmasi') {
                    $statusBaru = 'dikonfirmasi';
                } else {
                    $statusBaru = 'dibatalkan';
                }
            }

            // ==========================================
            // UPDATE STATUS PEMESANAN
            // ==========================================

            $stmtUpdate = $pdo->prepare("
                UPDATE pemesanan
                SET status = :status
                WHERE id_booking = :id_booking
            ");

            $stmtUpdate->execute([
                'status' => $statusBaru,
                'id_booking' => $idBooking
            ]);

            $pdo->commit();

            // Pesan berhasil
            if ($aksi === 'konfirmasi') {

                $pesan = 'Pemesanan berhasil dikonfirmasi.';

            } elseif ($aksi === 'tolak') {

                $pesan = 'Pemesanan berhasil ditolak.';

            } else {

                $pesan = 'Penyewaan mobil berhasil dimulai.';
            }

            $jenisPesan = 'success';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $pesan = $e->getMessage();
            $jenisPesan = 'error';

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $pesan = 'Terjadi kesalahan database saat memproses pemesanan.';
            $jenisPesan = 'error';
        }
    }
}

// ==========================================
// AMBIL SELURUH DATA PEMESANAN
// ==========================================

$stmt = $pdo->query("
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

        u.nama_lengkap,
        u.no_sim,
        u.no_hp,

        m.nama_mobil,
        m.merk,
        m.nomor_plat

    FROM pemesanan p

    INNER JOIN users u
        ON p.id_user = u.id_user

    INNER JOIN mobil m
        ON p.id_mobil = m.id_mobil

    ORDER BY p.created_at DESC
");

$daftarPemesanan = $stmt->fetchAll();

// ==========================================
// HITUNG RINGKASAN STATUS
// ==========================================

$jumlahMenunggu = 0;
$jumlahDikonfirmasi = 0;
$jumlahBerlangsung = 0;
$jumlahSelesai = 0;

foreach ($daftarPemesanan as $item) {

    if (in_array($item['status'], [
        'menunggu_pembayaran',
        'menunggu_konfirmasi'
    ], true)) {

        $jumlahMenunggu++;

    } elseif ($item['status'] === 'dikonfirmasi') {

        $jumlahDikonfirmasi++;

    } elseif ($item['status'] === 'berlangsung') {

        $jumlahBerlangsung++;

    } elseif ($item['status'] === 'selesai') {

        $jumlahSelesai++;
    }
}

// ==========================================
// LABEL STATUS
// ==========================================

function labelStatusPemesanan($status)
{
    $labels = [
        'menunggu_pembayaran' => 'Menunggu Pembayaran',
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'dikonfirmasi' => 'Dikonfirmasi',
        'berlangsung' => 'Sedang Disewa',
        'selesai' => 'Selesai',
        'dibatalkan' => 'Dibatalkan'
    ];

    return $labels[$status] ?? ucfirst($status);
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Manajemen Pemesanan - DriveEase</title>

    <link rel="stylesheet"
          href="../assets/css/admin.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

        .booking-page {
            padding: 25px;
        }

        .booking-heading {
            margin-bottom: 22px;
        }

        .booking-heading h1 {
            margin-bottom: 8px;
        }

        .booking-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .booking-stat {
            background: #fff;
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }

        .booking-stat strong {
            display: block;
            font-size: 25px;
            color: #007bff;
            margin-top: 8px;
        }

        .booking-table-wrapper {
            background: #fff;
            border-radius: 10px;
            padding: 18px;
            overflow-x: auto;
        }

        .booking-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1000px;
        }

        .booking-table th,
        .booking-table td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            text-align: left;
            vertical-align: top;
        }

        .booking-table th {
            background: #f4f8fc;
            white-space: nowrap;
        }

        .booking-table small {
            color: #666;
        }

        /* Label status */

        .status-label {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .status-menunggu {
            background: #fff3cd;
            color: #856404;
        }

        .status-dikonfirmasi {
            background: #d1ecf1;
            color: #0c5460;
        }

        .status-berlangsung {
            background: #cce5ff;
            color: #004085;
        }

        .status-selesai {
            background: #d4edda;
            color: #155724;
        }

        .status-dibatalkan {
            background: #f8d7da;
            color: #721c24;
        }

        /* Tombol aksi */

        .booking-actions {
            display: flex;
            gap: 7px;
            flex-wrap: wrap;
        }

        .booking-actions form {
            margin: 0;
        }

        .booking-actions button {
            border: none;
            border-radius: 6px;
            padding: 8px 11px;
            cursor: pointer;
            font-size: 12px;
            color: white;
            white-space: nowrap;
        }

        .btn-confirm {
            background: #198754;
        }

        .btn-reject {
            background: #dc3545;
        }

        .btn-start {
            background: #007bff;
        }

        .booking-actions button:hover {
            opacity: .85;
        }

        /* Notifikasi */

        .booking-alert {
            padding: 13px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .booking-alert.success {
            background: #d4edda;
            color: #155724;
        }

        .booking-alert.error {
            background: #f8d7da;
            color: #721c24;
        }

        .booking-empty {
            text-align: center;
            padding: 35px;
            color: #666;
        }

        @media (max-width: 600px) {

            .booking-page {
                padding: 15px;
            }

            .booking-table-wrapper {
                padding: 12px;
            }
        }

    </style>

</head>

<body>

<div class="booking-page">

    <!-- HEADER -->

    <div class="booking-heading">

        <h1>Manajemen Pemesanan</h1>

        <p>
            Kelola dan periksa pemesanan mobil dari pelanggan.
        </p>

    </div>

    <!-- NOTIFIKASI -->

    <?php if ($pesan !== ''): ?>

        <div class="booking-alert <?= e($jenisPesan) ?>">

            <?= e($pesan) ?>

        </div>

    <?php endif; ?>

    <!-- RINGKASAN PEMESANAN -->

    <div class="booking-stats">

        <div class="booking-stat">

            <span>Menunggu Proses</span>

            <strong>
                <?= $jumlahMenunggu ?>
            </strong>

        </div>

        <div class="booking-stat">

            <span>Dikonfirmasi</span>

            <strong>
                <?= $jumlahDikonfirmasi ?>
            </strong>

        </div>

        <div class="booking-stat">

            <span>Sedang Disewa</span>

            <strong>
                <?= $jumlahBerlangsung ?>
            </strong>

        </div>

        <div class="booking-stat">

            <span>Selesai</span>

            <strong>
                <?= $jumlahSelesai ?>
            </strong>

        </div>

    </div>

    <!-- TABEL PEMESANAN -->

    <div class="booking-table-wrapper">

        <h2>Daftar Pemesanan</h2>

        <?php if (count($daftarPemesanan) > 0): ?>

            <table class="booking-table">

                <thead>

                    <tr>
                        <th>Kode Booking</th>
                        <th>Pelanggan</th>
                        <th>Mobil</th>
                        <th>Tanggal Sewa</th>
                        <th>Durasi</th>
                        <th>Total Biaya</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>

                </thead>

                <tbody>

                    <?php foreach ($daftarPemesanan as $item): ?>

                        <?php

                        $statusClass = 'status-menunggu';

                        if ($item['status'] === 'dikonfirmasi') {

                            $statusClass = 'status-dikonfirmasi';

                        } elseif ($item['status'] === 'berlangsung') {

                            $statusClass = 'status-berlangsung';

                        } elseif ($item['status'] === 'selesai') {

                            $statusClass = 'status-selesai';

                        } elseif ($item['status'] === 'dibatalkan') {

                            $statusClass = 'status-dibatalkan';
                        }

                        ?>

                        <tr>

                            <!-- KODE BOOKING -->

                            <td>

                                <strong>
                                    <?= e($item['kode_booking']) ?>
                                </strong>

                                <br>

                                <small>
                                    <?= date(
                                        'd-m-Y H:i',
                                        strtotime($item['created_at'])
                                    ) ?>
                                </small>

                            </td>

                            <!-- PELANGGAN -->

                            <td>

                                <strong>
                                    <?= e($item['nama_lengkap']) ?>
                                </strong>

                                <br>

                                <small>
                                    SIM: <?= e($item['no_sim']) ?>
                                </small>

                                <br>

                                <small>
                                    HP: <?= e($item['no_hp']) ?>
                                </small>

                            </td>

                            <!-- MOBIL -->

                            <td>

                                <strong>
                                    <?= e(
                                        $item['merk'] . ' ' . $item['nama_mobil']
                                    ) ?>
                                </strong>

                                <br>

                                <small>
                                    Plat: <?= e($item['nomor_plat']) ?>
                                </small>

                            </td>

                            <!-- TANGGAL SEWA -->

                            <td>

                                <?= date(
                                    'd-m-Y',
                                    strtotime($item['tanggal_mulai'])
                                ) ?>

                                <br>

                                sampai

                                <br>

                                <?= date(
                                    'd-m-Y',
                                    strtotime($item['tanggal_selesai'])
                                ) ?>

                            </td>

                            <!-- DURASI -->

                            <td>

                                <?= (int) $item['jumlah_hari'] ?> hari

                            </td>

                            <!-- TOTAL BIAYA -->

                            <td>

                                <strong>
                                    <?= rupiah($item['total_harga']) ?>
                                </strong>

                                <br>

                                <small>
                                    <?= rupiah($item['harga_per_hari']) ?>/hari
                                </small>

                            </td>

                            <!-- STATUS -->

                            <td>

                                <span class="status-label <?= $statusClass ?>">

                                    <?= e(
                                        labelStatusPemesanan($item['status'])
                                    ) ?>

                                </span>

                            </td>

                            <!-- AKSI ADMIN -->

                            <td>

                                <div class="booking-actions">

                                    <?php if (in_array($item['status'], [
                                        'menunggu_pembayaran',
                                        'menunggu_konfirmasi'
                                    ], true)): ?>

                                        <!-- TOMBOL KONFIRMASI -->

                                        <form method="POST"
                                              onsubmit="return confirm('Konfirmasi pemesanan ini?')">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_token']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_booking"
                                                value="<?= (int) $item['id_booking'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="konfirmasi"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-confirm"
                                            >

                                                <i class="fa-solid fa-check"></i>
                                                Konfirmasi

                                            </button>

                                        </form>

                                        <!-- TOMBOL TOLAK -->

                                        <form method="POST"
                                              onsubmit="return confirm('Tolak pemesanan ini?')">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_token']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_booking"
                                                value="<?= (int) $item['id_booking'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="tolak"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-reject"
                                            >

                                                <i class="fa-solid fa-xmark"></i>
                                                Tolak

                                            </button>

                                        </form>

                                    <?php elseif ($item['status'] === 'dikonfirmasi'): ?>

                                        <!-- TOMBOL MULAI SEWA -->

                                        <form method="POST"
                                              onsubmit="return confirm('Mulai penyewaan mobil ini?')">

                                            <input
                                                type="hidden"
                                                name="csrf_token"
                                                value="<?= e($_SESSION['csrf_token']) ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="id_booking"
                                                value="<?= (int) $item['id_booking'] ?>"
                                            >

                                            <input
                                                type="hidden"
                                                name="aksi"
                                                value="mulai_sewa"
                                            >

                                            <button
                                                type="submit"
                                                class="btn-start"
                                            >

                                                <i class="fa-solid fa-key"></i>
                                                Mulai Sewa

                                            </button>

                                        </form>

                                    <?php else: ?>

                                        <small>
                                            Tidak ada aksi
                                        </small>

                                    <?php endif; ?>

                                </div>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="booking-empty">

                <i class="fa-solid fa-calendar-xmark"></i>

                <p>Belum ada pemesanan.</p>

            </div>

        <?php endif; ?>

    </div>

    <!-- KEMBALI KE DASHBOARD -->

    <p style="margin-top: 20px;">

        <a href="index.php">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Dashboard Admin
        </a>

    </p>

</div>

</body>
</html>
