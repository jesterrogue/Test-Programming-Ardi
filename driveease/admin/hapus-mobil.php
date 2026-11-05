
<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'auth.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: mobil.php');
    exit;
}

adminValidasiCsrf();

$idMobil = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$idMobil) {
    header('Location: mobil.php?error=gagal');
    exit;
}

try {
    $pdo->beginTransaction();

    // Kunci data mobil selama proses penghapusan
    $stmt = $pdo->prepare("
        SELECT id_mobil FROM mobil
        WHERE id_mobil = :id
        FOR UPDATE
    ");
    $stmt->execute(['id' => $idMobil]);

    if (!$stmt->fetch()) {
        $pdo->rollBack();
        header('Location: mobil.php?error=gagal');
        exit;
    }

    // Jangan hapus mobil yang sudah memiliki riwayat pemesanan
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM pemesanan
        WHERE id_mobil = :id
    ");
    $stmt->execute(['id' => $idMobil]);

    if ($stmt->fetchColumn() > 0) {
        $pdo->rollBack();
        header('Location: mobil.php?error=digunakan');
        exit;
    }

    // Ambil nama file gambar
    $stmt = $pdo->prepare("
        SELECT nama_file FROM gambar_mobil
        WHERE id_mobil = :id
    ");
    $stmt->execute(['id' => $idMobil]);
    $gambarList = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Hapus data gambar dari database
    $stmt = $pdo->prepare("
        DELETE FROM gambar_mobil WHERE id_mobil = :id
    ");
    $stmt->execute(['id' => $idMobil]);

    // Hapus data mobil
    $stmt = $pdo->prepare("
        DELETE FROM mobil WHERE id_mobil = :id
    ");
    $stmt->execute(['id' => $idMobil]);

    $pdo->commit();

    // Hapus file gambar setelah transaksi database berhasil
    foreach ($gambarList as $gambar) {
        adminHapusFileGambar($gambar);
    }

    header('Location: mobil.php?pesan=hapus');
    exit;

} catch (PDOException $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    header('Location: mobil.php?error=gagal');
    exit;
}
