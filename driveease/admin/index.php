
<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'auth.php';

$totalMobil = $pdo->query("SELECT COUNT(*) FROM mobil")->fetchColumn();
$totalTersedia = $pdo->query("
    SELECT COUNT(*) FROM mobil WHERE status = 'tersedia'
")->fetchColumn();

$totalBooking = $pdo->query("
    SELECT COUNT(*) FROM pemesanan
    WHERE status IN (
        'menunggu_pembayaran',
        'menunggu_konfirmasi'
    )
")->fetchColumn();
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard Admin - DriveEase</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<main class="admin-page">

    <div class="admin-heading">
        <div>
            <h1>Dashboard Admin</h1>
            <p>Selamat datang, <?= e($_SESSION['nama_lengkap'] ?? 'Admin') ?>.</p>
        </div>

        <a href="../index.php" class="admin-button secondary">
            <i class="fa-solid fa-house"></i> Lihat Website
        </a>
    </div>

    <div class="admin-card">
        <h3>Ringkasan</h3>

        <div class="admin-form">
            <div>
                <strong>Total Mobil</strong>
                <h2><?= (int) $totalMobil ?></h2>
            </div>

            <div>
                <strong>Mobil Tersedia</strong>
                <h2><?= (int) $totalTersedia ?></h2>
            </div>

            <div>
                <strong>Pemesanan Menunggu Proses</strong>
                <h2><?= (int) $totalBooking ?></h2>
            </div>
        </div>
    </div>

    <div class="admin-card">
        <h3>Manajemen</h3>

        <div class="admin-actions">
            <a href="mobil.php" class="admin-button">
                <i class="fa-solid fa-car"></i> Kelola Mobil
            </a>
        <div class="admin-actions">
            <a href="pemesanan.php" class="admin-button">
                <i class="fa-solid fa-calendar-check"></i> Manajemen Pemesanan
            </a>
        <div class="admin-actions">
            <a href="pengembalian.php" class="admin-button">
                <i class="fa-solid fa-rotate-left"></i> Manajemen Pengembalian
            </a>
        <div class="admin-actions">
            <a href="penyewaan-aktif.php" class="admin-button">
                <i class="fas fa-car-side"></i> Mobil Sedang Disewa
            </a>
<form action="../logout.php" method="POST">
    <input
        type="hidden"
        name="csrf_token"
        value="<?= e($_SESSION['csrf_token']) ?>"
    >
    <button type="submit" class="admin-button secondary">
        Logout
    </button>
</form>
        </div>
    </div>

</main>

</body>
</html>
