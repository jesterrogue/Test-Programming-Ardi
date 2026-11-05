
<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'auth.php';

$stmt = $pdo->query("
    SELECT
        m.*,
        k.nama_kategori,
        (
            SELECT gm.nama_file
            FROM gambar_mobil gm
            WHERE gm.id_mobil = m.id_mobil
            ORDER BY gm.is_utama DESC, gm.id_gambar ASC
            LIMIT 1
        ) AS gambar
    FROM mobil m
    LEFT JOIN kategori_mobil k
        ON m.id_kategori = k.id_kategori
    ORDER BY m.created_at DESC
");

$mobilList = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pesan = $_GET['pesan'] ?? '';
$error = $_GET['error'] ?? '';
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manajemen Mobil - DriveEase</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body>

<main class="admin-page">

    <div class="admin-heading">
        <div>
            <h1>Manajemen Mobil</h1>
            <p>Kelola data kendaraan DriveEase.</p>
        </div>

        <div class="admin-actions">
            <a href="index.php" class="admin-button secondary">Dashboard</a>
            <a href="tambah-mobil.php" class="admin-button">
                <i class="fa-solid fa-plus"></i> Tambah Mobil
            </a>
        </div>
    </div>

    <?php if ($pesan === 'tambah'): ?>
        <div class="admin-alert">Mobil berhasil ditambahkan.</div>
    <?php elseif ($pesan === 'edit'): ?>
        <div class="admin-alert">Data mobil berhasil diperbarui.</div>
    <?php elseif ($pesan === 'hapus'): ?>
        <div class="admin-alert">Mobil berhasil dihapus.</div>
    <?php endif; ?>

    <?php if ($error === 'digunakan'): ?>
        <div class="admin-alert error">
            Mobil tidak dapat dihapus karena sudah memiliki riwayat pemesanan.
            Ubah statusnya menjadi nonaktif jika tidak ingin menyewakannya lagi.
        </div>
    <?php elseif ($error === 'gagal'): ?>
        <div class="admin-alert error">Terjadi kesalahan saat menghapus mobil.</div>
    <?php endif; ?>

    <section class="admin-card">
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Foto</th>
                        <th>Mobil</th>
                        <th>Nomor Plat</th>
                        <th>Kategori</th>
                        <th>Tarif/Hari</th>
                        <th>Status</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>
                <?php if (!$mobilList): ?>
                    <tr>
                        <td colspan="7">Belum ada data mobil.</td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($mobilList as $mobil): ?>
                        <tr>
                            <td>
                                <?php if (!empty($mobil['gambar'])): ?>
                                    <img
                                        src="../assets/mobil/<?= rawurlencode($mobil['gambar']) ?>"
                                        alt="<?= e($mobil['nama_mobil']) ?>"
                                    >
                                <?php else: ?>
                                    -
                                <?php endif; ?>
                            </td>

                            <td>
                                <strong><?= e($mobil['merk'] . ' ' . $mobil['nama_mobil']) ?></strong>
                                <br>
                                <?= e($mobil['tahun'] ?? '-') ?>
                            </td>

                            <td><?= e($mobil['nomor_plat']) ?></td>
                            <td><?= e($mobil['nama_kategori'] ?? '-') ?></td>
                            <td><?= rupiah($mobil['harga_per_hari']) ?></td>
                            <td>
                                <span class="admin-status">
                                    <?= e(ucfirst($mobil['status'])) ?>
                                </span>
                            </td>

                            <td>
                                <div class="admin-actions">
                                    <a
                                        href="edit-mobil.php?id=<?= (int) $mobil['id_mobil'] ?>"
                                        class="admin-button secondary"
                                    >
                                        Edit
                                    </a>

                                    <form
                                        action="hapus-mobil.php"
                                        method="POST"
                                        onsubmit="return confirm('Yakin ingin menghapus mobil ini?')"
                                    >
                                        <input type="hidden" name="id"
                                            value="<?= (int) $mobil['id_mobil'] ?>">
                                        <input type="hidden" name="csrf_token"
                                            value="<?= e($_SESSION['csrf_token']) ?>">

                                        <button type="submit" class="admin-button danger">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

</main>

</body>
</html>
