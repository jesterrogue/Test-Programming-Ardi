<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
require_once __DIR__ . '/functions.php';

$pageTitle = $pageTitle ?? 'DriveEase - Rental Mobil';
$currentPage = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="id">

<head>
    <meta charset="UTF-8">

    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title><?= e($pageTitle) ?></title>

    <link rel="preconnect"
          href="https://fonts.googleapis.com">

    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700;800&display=swap"
          rel="stylesheet">

    <link rel="stylesheet"
          href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

    <link rel="stylesheet"
          href="assets/css/style.css">
</head>

<body>

<header class="header">

    <div class="container navbar">

        <a href="index.php" class="logo">
            <span class="logo-icon">D</span>
            Drive<span>Ease</span>
        </a>

        <button class="menu-toggle"
                id="menuToggle"
                aria-label="Buka menu"
                aria-expanded="false">
            ☰
        </button>

        <nav class="nav-menu" id="navMenu">

            <a href="index.php"
               class="<?= $currentPage == 'index.php' ? 'active' : '' ?>">
                Beranda
            </a>

            <a href="mobil.php"
               class="<?= $currentPage == 'mobil.php' ? 'active' : '' ?>">
                Pilihan Mobil
            </a>

            </a>
            <a href="riwayat-pemesanan.php"
               class="<?= $currentPage == 'riwayat-pemesanan.php' ? 'active' : '' ?>">
                History Pesanan
            </a>

        </nav>

<div class="nav-buttons">
    <?php if (isset($_SESSION['user_id'])): ?>

        <span class="nav-user">
            Halo, <?= e($_SESSION['nama_lengkap'] ?? 'Pengguna') ?>
        </span>

        <form action="logout.php" method="POST" class="logout-form">
            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token'] ?? '') ?>"
            >

            <button type="submit" class="nav-logout">
                Logout
            </button>
        </form>

    <?php else: ?>

        <a href="login.php" class="btn-outline">Masuk</a>
        <a href="register.php" class="btn-primary">Daftar</a>

    <?php endif; ?>
</div>


    </div>

</header>

<main>
