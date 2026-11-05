
<?php
require_once 'auth.php';
require_once '../config/database.php';
require_once '../includes/functions.php';

// Ambil daftar pemesanan yang sedang berlangsung
$sql = "SELECT
            p.id_booking,
            p.kode_booking,
            p.tanggal_mulai,
            p.tanggal_selesai,
            p.jumlah_hari,
            p.harga_per_hari,
            p.total_harga,
            u.nama_lengkap,
            u.no_hp,
            m.nama_mobil,
            m.merk,
            m.nomor_plat
        FROM pemesanan p
        INNER JOIN users u ON p.id_user = u.id_user
        INNER JOIN mobil m ON p.id_mobil = m.id_mobil
        WHERE p.status = 'berlangsung'
        ORDER BY p.tanggal_selesai ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute();
$penyewaanAktif = $stmt->fetchAll(PDO::FETCH_ASSOC);

function tanggalIndonesia($tanggal)
{
    if (!$tanggal) {
        return '-';
    }

    return date('d-m-Y', strtotime($tanggal));
}
?>

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Penyewaan Aktif - DriveEase</title>
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <style>
        .content {
            padding: 25px;
        }

        .page-header {
            margin-bottom: 25px;
        }

        .page-header h1 {
            color: #1e293b;
            margin-bottom: 8px;
        }

        .page-header p {
            color: #64748b;
        }

        .summary-box {
            background: #eaf4ff;
            border-left: 5px solid #007bff;
            padding: 18px;
            border-radius: 8px;
            margin-bottom: 22px;
        }

        .summary-box strong {
            display: block;
            font-size: 28px;
            color: #007bff;
        }

        .table-container {
            overflow-x: auto;
            background: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.06);
        }

        table {
            width: 100%;
            border-collapse: collapse;
            min-width: 950px;
        }

        th, td {
            padding: 13px 12px;
            text-align: left;
            border-bottom: 1px solid #e5e7eb;
            font-size: 14px;
        }

        th {
            background: #f1f5f9;
            color: #334155;
            white-space: nowrap;
        }

        td {
            color: #475569;
        }

        .badge-aktif {
            display: inline-block;
            background: #dcfce7;
            color: #166534;
            padding: 6px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: bold;
        }

        .empty-state {
            text-align: center;
            padding: 45px 15px;
            color: #64748b;
        }

        .empty-state i {
            font-size: 40px;
            color: #94a3b8;
            margin-bottom: 12px;
        }

        @media (max-width: 600px) {
            .content {
                padding: 15px;
            }
        }
    </style>
</head>
<body>

<div class="content">

    <div class="page-header">
        <h1><i class="fas fa-car-side"></i> Penyewaan Aktif</h1>
        <p>Daftar mobil yang saat ini sedang digunakan pelanggan.</p>
    </div>

    <div class="summary-box">
        <strong><?= count($penyewaanAktif); ?></strong>
        Mobil sedang disewa
    </div>

    <div class="table-container">

        <?php if (count($penyewaanAktif) > 0): ?>

            <table>
                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Kode Booking</th>
                        <th>Pelanggan</th>
                        <th>Mobil</th>
                        <th>Nomor Plat</th>
                        <th>Tanggal Mulai</th>
                        <th>Rencana Kembali</th>
                        <th>Durasi</th>
                        <th>Total Biaya</th>
                        <th>Status</th>
                    </tr>
                </thead>

                <tbody>
                    <?php foreach ($penyewaanAktif as $index => $data): ?>
                        <tr>
                            <td><?= $index + 1; ?></td>

                            <td>
                                <?= e($data['kode_booking']); ?>
                            </td>

                            <td>
                                <strong><?= e($data['nama_lengkap']); ?></strong><br>
                                <?= e($data['no_hp']); ?>
                            </td>

                            <td>
                                <?= e($data['merk'] . ' ' . $data['nama_mobil']); ?>
                            </td>

                            <td>
                                <?= e($data['nomor_plat']); ?>
                            </td>

                            <td>
                                <?= tanggalIndonesia($data['tanggal_mulai']); ?>
                            </td>

                            <td>
                                <?= tanggalIndonesia($data['tanggal_selesai']); ?>
                            </td>

                            <td>
                                <?= (int) $data['jumlah_hari']; ?> hari
                            </td>

                            <td>
                                <?= rupiah($data['total_harga']); ?>
                            </td>

                            <td>
                                <span class="badge-aktif">Sedang Disewa</span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

        <?php else: ?>

            <div class="empty-state">
                <i class="fas fa-car"></i>
                <h3>Belum Ada Penyewaan Aktif</h3>
                <p>Saat ini tidak ada mobil yang sedang disewa pelanggan.</p>
            </div>

        <?php endif; ?>

    </div>

</div>

</body>
</html>
