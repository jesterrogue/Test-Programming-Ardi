
<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

$pesan = '';
$jenisPesan = '';

// ==========================================
// PROSES PERSETUJUAN PENGEMBALIAN
// ==========================================

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    adminValidasiCsrf();

    $idPengembalian = filter_input(
        INPUT_POST,
        'id_pengembalian',
        FILTER_VALIDATE_INT
    );

    $aksi = $_POST['aksi'] ?? '';

    $biayaTambahanInput = $_POST['biaya_tambahan'] ?? '0';

    if (
        !$idPengembalian ||
        $aksi !== 'setujui' ||
        !is_numeric($biayaTambahanInput) ||
        (float) $biayaTambahanInput < 0
    ) {
        $pesan = 'Data pengembalian tidak valid.';
        $jenisPesan = 'error';

    } else {

        try {

            $pdo->beginTransaction();

            // Ambil data pengembalian dan kunci baris
            $stmt = $pdo->prepare("
                SELECT
                    r.id_pengembalian,
                    r.id_booking,
                    r.jumlah_hari_aktual,
                    r.status_pengembalian,

                    p.status AS status_booking,
                    p.harga_per_hari

                FROM pengembalian r

                INNER JOIN pemesanan p
                    ON r.id_booking = p.id_booking

                WHERE r.id_pengembalian = :id_pengembalian

                FOR UPDATE
            ");

            $stmt->execute([
                'id_pengembalian' => $idPengembalian
            ]);

            $data = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$data) {
                throw new Exception(
                    'Data pengembalian tidak ditemukan.'
                );
            }

            // Pastikan pengajuan masih menunggu konfirmasi
            if (
                $data['status_pengembalian'] !== 'menunggu_konfirmasi'
            ) {
                throw new Exception(
                    'Pengembalian ini sudah diproses sebelumnya.'
                );
            }

            // Pastikan pemesanan masih berlangsung
            if ($data['status_booking'] !== 'berlangsung') {
                throw new Exception(
                    'Pemesanan tidak dalam status sedang disewa.'
                );
            }

            // Hitung total biaya
            $biayaTambahan = (float) $biayaTambahanInput;

            $hargaPerHari = (float) $data['harga_per_hari'];

            $jumlahHari = (int) $data['jumlah_hari_aktual'];

            $totalBiayaAkhir =
                ($jumlahHari * $hargaPerHari) + $biayaTambahan;

            // Update data pengembalian
            $stmtUpdate = $pdo->prepare("
                UPDATE pengembalian

                SET
                    status_pengembalian = 'disetujui',
                    biaya_tambahan = :biaya_tambahan,
                    total_biaya_akhir = :total_biaya_akhir

                WHERE id_pengembalian = :id_pengembalian
            ");

            $stmtUpdate->execute([
                'biaya_tambahan' => $biayaTambahan,
                'total_biaya_akhir' => $totalBiayaAkhir,
                'id_pengembalian' => $idPengembalian
            ]);

            // Selesaikan pemesanan
            $stmtBooking = $pdo->prepare("
                UPDATE pemesanan

                SET status = 'selesai'

                WHERE id_booking = :id_booking
                  AND status = 'berlangsung'
            ");

            $stmtBooking->execute([
                'id_booking' => $data['id_booking']
            ]);

            if ($stmtBooking->rowCount() !== 1) {
                throw new Exception(
                    'Status pemesanan gagal diperbarui.'
                );
            }

            $pdo->commit();

            $pesan = 'Pengembalian berhasil disetujui. Pemesanan telah selesai.';
            $jenisPesan = 'success';

        } catch (PDOException $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $pesan = 'Terjadi kesalahan database saat memproses pengembalian.';
            $jenisPesan = 'error';

        } catch (Exception $e) {

            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }

            $pesan = $e->getMessage();
            $jenisPesan = 'error';
        }
    }
}

// ==========================================
// AMBIL DATA PENGEMBALIAN
// ==========================================

$stmt = $pdo->query("
    SELECT
        r.id_pengembalian,
        r.id_booking,
        r.tanggal_pengembalian,
        r.jumlah_hari_aktual,
        r.kondisi_mobil,
        r.status_pengembalian,
        r.biaya_tambahan,
        r.total_biaya_akhir,
        r.catatan,

        p.kode_booking,
        p.tanggal_mulai,
        p.tanggal_selesai,
        p.harga_per_hari,
        p.total_harga,
        p.status AS status_booking,

        u.nama_lengkap,
        u.no_sim,
        u.no_hp,

        m.nama_mobil,
        m.merk,
        m.nomor_plat

    FROM pengembalian r

    INNER JOIN pemesanan p
        ON r.id_booking = p.id_booking

    INNER JOIN users u
        ON p.id_user = u.id_user

    INNER JOIN mobil m
        ON p.id_mobil = m.id_mobil

    ORDER BY r.tanggal_pengembalian DESC
");

$daftarPengembalian = $stmt->fetchAll(PDO::FETCH_ASSOC);

// ==========================================
// HITUNG RINGKASAN
// ==========================================

$jumlahMenunggu = 0;
$jumlahDisetujui = 0;
$jumlahDitolak = 0;

foreach ($daftarPengembalian as $item) {

    if ($item['status_pengembalian'] === 'menunggu_konfirmasi') {
        $jumlahMenunggu++;

    } elseif ($item['status_pengembalian'] === 'disetujui') {
        $jumlahDisetujui++;

    } elseif ($item['status_pengembalian'] === 'ditolak') {
        $jumlahDitolak++;
    }
}

// ==========================================
// LABEL STATUS
// ==========================================

function labelStatusPengembalian($status)
{
    $labels = [
        'menunggu_konfirmasi' => 'Menunggu Konfirmasi',
        'disetujui' => 'Disetujui',
        'ditolak' => 'Ditolak'
    ];

    return $labels[$status] ?? ucfirst($status);
}

?>

<!DOCTYPE html>
<html lang="id">

<head>

    <meta charset="UTF-8">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Manajemen Pengembalian - DriveEase</title>

    <link rel="stylesheet" href="../assets/css/admin.css">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>

        .return-page {
            padding: 25px;
        }

        .return-heading {
            margin-bottom: 22px;
        }

        .return-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
            gap: 15px;
            margin-bottom: 25px;
        }

        .return-stat {
            background: #fff;
            border-radius: 10px;
            padding: 18px;
            box-shadow: 0 2px 8px rgba(0,0,0,.06);
        }

        .return-stat strong {
            display: block;
            font-size: 25px;
            color: #007bff;
            margin-top: 8px;
        }

        .return-table-wrapper {
            background: #fff;
            border-radius: 10px;
            padding: 18px;
            overflow-x: auto;
        }

        .return-table {
            width: 100%;
            border-collapse: collapse;
            min-width: 1200px;
        }

        .return-table th,
        .return-table td {
            padding: 13px;
            border-bottom: 1px solid #eee;
            text-align: left;
            vertical-align: top;
        }

        .return-table th {
            background: #f4f8fc;
            white-space: nowrap;
        }

        .return-table small {
            color: #666;
        }

        .return-status {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            white-space: nowrap;
        }

        .return-waiting {
            background: #fff3cd;
            color: #856404;
        }

        .return-approved {
            background: #d4edda;
            color: #155724;
        }

        .return-rejected {
            background: #f8d7da;
            color: #721c24;
        }

        .return-alert {
            padding: 13px 16px;
            margin-bottom: 20px;
            border-radius: 8px;
        }

        .return-alert.success {
            background: #d4edda;
            color: #155724;
        }

        .return-alert.error {
            background: #f8d7da;
            color: #721c24;
        }

        .return-approve-form {
            min-width: 150px;
        }

        .return-approve-form input[type="number"] {
            width: 130px;
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 6px;
            margin-bottom: 8px;
        }

        .return-approve-form button {
            border: none;
            background: #198754;
            color: white;
            padding: 9px 12px;
            border-radius: 6px;
            cursor: pointer;
        }

        .return-approve-form button:hover {
            opacity: .85;
        }

        .return-empty {
            text-align: center;
            padding: 35px;
            color: #666;
        }

        @media (max-width: 600px) {
            .return-page {
                padding: 15px;
            }

            .return-table-wrapper {
                padding: 12px;
            }
        }

    </style>

</head>

<body>

<div class="return-page">

    <div class="return-heading">

        <h1>Manajemen Pengembalian</h1>

        <p>
            Periksa kondisi mobil dan proses pengembalian dari pelanggan.
        </p>

    </div>

    <!-- NOTIFIKASI -->

    <?php if ($pesan !== ''): ?>

        <div class="return-alert <?= e($jenisPesan) ?>">
            <?= e($pesan) ?>
        </div>

    <?php endif; ?>

    <!-- RINGKASAN -->

    <div class="return-stats">

        <div class="return-stat">
            <span>Menunggu Konfirmasi</span>
            <strong><?= $jumlahMenunggu ?></strong>
        </div>

        <div class="return-stat">
            <span>Disetujui</span>
            <strong><?= $jumlahDisetujui ?></strong>
        </div>

        <div class="return-stat">
            <span>Ditolak</span>
            <strong><?= $jumlahDitolak ?></strong>
        </div>

    </div>

    <!-- DAFTAR PENGEMBALIAN -->

    <div class="return-table-wrapper">

        <h2>Daftar Pengembalian</h2>

        <?php if (count($daftarPengembalian) > 0): ?>

            <table class="return-table">

                <thead>
                    <tr>
                        <th>Kode Booking</th>
                        <th>Pelanggan</th>
                        <th>Mobil</th>
                        <th>Tanggal Pengembalian</th>
                        <th>Durasi Aktual</th>
                        <th>Kondisi Mobil</th>
                        <th>Catatan</th>
                        <th>Total Biaya</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php foreach ($daftarPengembalian as $item): ?>

                        <?php

                        $statusClass = 'return-waiting';

                        if ($item['status_pengembalian'] === 'disetujui') {
                            $statusClass = 'return-approved';

                        } elseif ($item['status_pengembalian'] === 'ditolak') {
                            $statusClass = 'return-rejected';
                        }

                        $totalDasar =
                            (int) $item['jumlah_hari_aktual'] *
                            (float) $item['harga_per_hari'];

                        ?>

                        <tr>

                            <td>
                                <strong>
                                    <?= e($item['kode_booking']) ?>
                                </strong>
                            </td>

                            <td>
                                <strong>
                                    <?= e($item['nama_lengkap']) ?>
                                </strong>
                                <br>
                                <small>SIM: <?= e($item['no_sim']) ?></small>
                                <br>
                                <small>HP: <?= e($item['no_hp']) ?></small>
                            </td>

                            <td>
                                <strong>
                                    <?= e($item['merk'] . ' ' . $item['nama_mobil']) ?>
                                </strong>
                                <br>
                                <small>
                                    Plat: <?= e($item['nomor_plat']) ?>
                                </small>
                            </td>

                            <td>
                                <?php if ($item['tanggal_pengembalian']): ?>
                                    <?= date(
                                        'd-m-Y H:i',
                                        strtotime($item['tanggal_pengembalian'])
                                    ) ?>
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= (int) $item['jumlah_hari_aktual'] ?> hari
                                <br>
                                <small>
                                    <?= rupiah($item['harga_per_hari']) ?>/hari
                                </small>
                            </td>

                            <td>
                                <?php if ($item['kondisi_mobil'] === 'baik'): ?>

                                    <span style="color:#198754;font-weight:600;">
                                        <i class="fa-solid fa-circle-check"></i>
                                        Baik
                                    </span>

                                <?php elseif ($item['kondisi_mobil'] === 'ada_kerusakan'): ?>

                                    <span style="color:#dc3545;font-weight:600;">
                                        <i class="fa-solid fa-triangle-exclamation"></i>
                                        Ada Kerusakan
                                    </span>

                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <?= $item['catatan']
                                    ? nl2br(e($item['catatan']))
                                    : '-' ?>
                            </td>

                            <td>
                                <strong>
                                    <?= rupiah(
                                        $item['total_biaya_akhir'] ?? $totalDasar
                                    ) ?>
                                </strong>

                                <br>

                                <small>
                                    Tambahan:
                                    <?= rupiah($item['biaya_tambahan']) ?>
                                </small>
                            </td>

                            <td>
                                <span class="return-status <?= $statusClass ?>">
                                    <?= e(
                                        labelStatusPengembalian(
                                            $item['status_pengembalian']
                                        )
                                    ) ?>
                                </span>
                            </td>

                            <td>

                                <?php if (
                                    $item['status_pengembalian'] === 'menunggu_konfirmasi'
                                ): ?>

                                    <form method="POST"
                                          class="return-approve-form"
                                          onsubmit="return confirm('Setujui pengembalian mobil ini? Status pemesanan akan menjadi selesai.')">

                                        <input
                                            type="hidden"
                                            name="csrf_token"
                                            value="<?= e($_SESSION['csrf_token']) ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="id_pengembalian"
                                            value="<?= (int) $item['id_pengembalian'] ?>"
                                        >

                                        <input
                                            type="hidden"
                                            name="aksi"
                                            value="setujui"
                                        >

                                        <label>
                                            <small>Biaya tambahan (Rp)</small>
                                        </label>

                                        <input
                                            type="number"
                                            name="biaya_tambahan"
                                            min="0"
                                            step="1000"
                                            value="<?= (float) $item['biaya_tambahan'] ?>"
                                            required
                                        >

                                        <button type="submit">
                                            <i class="fa-solid fa-check"></i>
                                            Setujui
                                        </button>

                                    </form>

                                <?php else: ?>

                                    <small>Tidak ada aksi</small>

                                <?php endif; ?>

                            </td>

                        </tr>

                    <?php endforeach; ?>

                </tbody>

            </table>

        <?php else: ?>

            <div class="return-empty">

                <i class="fa-solid fa-clipboard-check"></i>

                <p>Belum ada pengajuan pengembalian.</p>

            </div>

        <?php endif; ?>

    </div>

    <p style="margin-top:20px;">

        <a href="index.php">
            <i class="fa-solid fa-arrow-left"></i>
            Kembali ke Dashboard Admin
        </a>

    </p>

</div>

</body>
</html>
