
<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'auth.php';

$idMobil = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

if (!$idMobil) {
    header('Location: mobil.php');
    exit;
}

$stmt = $pdo->prepare("
    SELECT m.*,
        (
            SELECT gm.nama_file
            FROM gambar_mobil gm
            WHERE gm.id_mobil = m.id_mobil
            ORDER BY gm.is_utama DESC, gm.id_gambar ASC
            LIMIT 1
        ) AS gambar
    FROM mobil m
    WHERE m.id_mobil = :id
");
$stmt->execute(['id' => $idMobil]);
$mobil = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$mobil) {
    header('Location: mobil.php');
    exit;
}

$kategori = $pdo->query("
    SELECT id_kategori, nama_kategori
    FROM kategori_mobil
    ORDER BY nama_kategori
")->fetchAll(PDO::FETCH_ASSOC);

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminValidasiCsrf();

    $idKategori = filter_input(INPUT_POST, 'id_kategori', FILTER_VALIDATE_INT);
    $nama = trim($_POST['nama_mobil'] ?? '');
    $merk = trim($_POST['merk'] ?? '');
    $plat = strtoupper(trim($_POST['nomor_plat'] ?? ''));
    $tahun = trim($_POST['tahun'] ?? '');
    $transmisi = $_POST['transmisi'] ?? '';
    $kapasitas = filter_input(INPUT_POST, 'kapasitas', FILTER_VALIDATE_INT);
    $bahan = trim($_POST['bahan_bakar'] ?? '');
    $harga = filter_input(INPUT_POST, 'harga_per_hari', FILTER_VALIDATE_FLOAT);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status = $_POST['status'] ?? '';

    if (
        $nama === '' || $merk === '' || $plat === '' ||
        !in_array($transmisi, ['manual', 'matic'], true) ||
        !$kapasitas || $kapasitas < 1 ||
        $harga === false || $harga <= 0 ||
        !in_array($status, ['tersedia', 'nonaktif', 'perawatan'], true)
    ) {
        $error = 'Periksa kembali data wajib dan pastikan nilainya benar.';
    } elseif ($tahun !== '' && (!ctype_digit($tahun) || (int)$tahun < 1900 || (int)$tahun > (int)date('Y') + 1)) {
        $error = 'Tahun kendaraan tidak valid.';
    } elseif ($idKategori && !in_array((int)$idKategori, array_column($kategori, 'id_kategori'))) {
        $error = 'Kategori mobil tidak valid.';
    } else {
        $stmt = $pdo->prepare("
            SELECT COUNT(*) FROM mobil
            WHERE nomor_plat = :plat AND id_mobil <> :id
        ");
        $stmt->execute(['plat' => $plat, 'id' => $idMobil]);

        if ($stmt->fetchColumn() > 0) {
            $error = 'Nomor plat sudah digunakan mobil lain.';
        } else {
            $gambarBaru = adminUploadGambar($_FILES['gambar'] ?? []);

            if ($gambarBaru === false) {
                $error = 'Foto tidak valid. Gunakan JPG, PNG, atau WEBP maksimal 2 MB.';
            } else {
                $gambarLama = $mobil['gambar'];

                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("
                        UPDATE mobil SET
                            id_kategori = :kategori,
                            nama_mobil = :nama,
                            nomor_plat = :plat,
                            merk = :merk,
                            tahun = :tahun,
                            transmisi = :transmisi,
                            kapasitas = :kapasitas,
                            bahan_bakar = :bahan,
                            harga_per_hari = :harga,
                            deskripsi = :deskripsi,
                            status = :status
                        WHERE id_mobil = :id
                    ");

                    $stmt->execute([
                        'kategori' => $idKategori ?: null,
                        'nama' => $nama,
                        'plat' => $plat,
                        'merk' => $merk,
                        'tahun' => $tahun !== '' ? $tahun : null,
                        'transmisi' => $transmisi,
                        'kapasitas' => $kapasitas,
                        'bahan' => $bahan ?: null,
                        'harga' => $harga,
                        'deskripsi' => $deskripsi ?: null,
                        'status' => $status,
                        'id' => $idMobil
                    ]);

                    if ($gambarBaru) {
                        $stmt = $pdo->prepare("
                            SELECT id_gambar FROM gambar_mobil
                            WHERE id_mobil = :id
                            ORDER BY is_utama DESC, id_gambar ASC
                            LIMIT 1
                        ");
                        $stmt->execute(['id' => $idMobil]);
                        $idGambar = $stmt->fetchColumn();

                        if ($idGambar) {
                            $stmt = $pdo->prepare("
                                UPDATE gambar_mobil
                                SET nama_file = :gambar, is_utama = 1
                                WHERE id_gambar = :id
                            ");
                            $stmt->execute([
                                'gambar' => $gambarBaru,
                                'id' => $idGambar
                            ]);
                        } else {
                            $stmt = $pdo->prepare("
                                INSERT INTO gambar_mobil (id_mobil, nama_file, is_utama)
                                VALUES (:id, :gambar, 1)
                            ");
                            $stmt->execute([
                                'id' => $idMobil,
                                'gambar' => $gambarBaru
                            ]);
                        }
                    }

                    $pdo->commit();

                    if ($gambarBaru) {
                        adminHapusFileGambar($gambarLama);
                    }

                    header('Location: mobil.php?pesan=edit');
                    exit;

                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    adminHapusFileGambar($gambarBaru);
                    $error = 'Gagal memperbarui data mobil.';
                }
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Mobil - DriveEase</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<main class="admin-page">

    <div class="admin-heading">
        <div>
            <h1>Edit Mobil</h1>
            <p>Perbarui informasi kendaraan.</p>
        </div>
        <a href="mobil.php" class="admin-button secondary">Kembali</a>
    </div>

    <?php if ($error): ?>
        <div class="admin-alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <section class="admin-card">

        <form method="POST" enctype="multipart/form-data" class="admin-form">

            <input type="hidden" name="csrf_token" value="<?= e($_SESSION['csrf_token']) ?>">

            <div>
                <label>Nama Model Mobil *</label>
                <input type="text" name="nama_mobil" required
                    value="<?= e($_POST['nama_mobil'] ?? $mobil['nama_mobil']) ?>">
            </div>

            <div>
                <label>Merek *</label>
                <input type="text" name="merk" required
                    value="<?= e($_POST['merk'] ?? $mobil['merk']) ?>">
            </div>

            <div>
                <label>Nomor Plat *</label>
                <input type="text" name="nomor_plat" required
                    value="<?= e($_POST['nomor_plat'] ?? $mobil['nomor_plat']) ?>">
            </div>

            <div>
                <label>Kategori</label>
                <select name="id_kategori">
                    <option value="">Pilih kategori</option>
                    <?php foreach ($kategori as $item): ?>
                        <option value="<?= (int)$item['id_kategori'] ?>"
                            <?= (($_POST['id_kategori'] ?? $mobil['id_kategori']) == $item['id_kategori']) ? 'selected' : '' ?>>
                            <?= e($item['nama_kategori']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Tahun</label>
                <input type="number" name="tahun" min="1900"
                    max="<?= (int)date('Y') + 1 ?>"
                    value="<?= e($_POST['tahun'] ?? $mobil['tahun'] ?? '') ?>">
            </div>

            <div>
                <label>Transmisi *</label>
                <select name="transmisi" required>
                    <option value="manual" <?= (($_POST['transmisi'] ?? $mobil['transmisi']) === 'manual') ? 'selected' : '' ?>>Manual</option>
                    <option value="matic" <?= (($_POST['transmisi'] ?? $mobil['transmisi']) === 'matic') ? 'selected' : '' ?>>Matic</option>
                </select>
            </div>

            <div>
                <label>Kapasitas Penumpang *</label>
                <input type="number" name="kapasitas" min="1" required
                    value="<?= e($_POST['kapasitas'] ?? $mobil['kapasitas']) ?>">
            </div>

            <div>
                <label>Bahan Bakar</label>
                <input type="text" name="bahan_bakar"
                    value="<?= e($_POST['bahan_bakar'] ?? $mobil['bahan_bakar'] ?? '') ?>">
            </div>

            <div>
                <label>Harga Sewa per Hari (Rp) *</label>
                <input type="number" name="harga_per_hari" min="1" step="0.01" required
                    value="<?= e($_POST['harga_per_hari'] ?? $mobil['harga_per_hari']) ?>">
            </div>

            <div>
                <label>Status Mobil *</label>
                <select name="status" required>
                    <?php foreach (['tersedia', 'nonaktif', 'perawatan'] as $s): ?>
                        <option value="<?= $s ?>"
                            <?= (($_POST['status'] ?? $mobil['status']) === $s) ? 'selected' : '' ?>>
                            <?= ucfirst($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="full">
                <label>Foto Saat Ini</label>

                <?php if ($mobil['gambar']): ?>
                    <img class="admin-current-image"
                        src="../assets/mobil/<?= rawurlencode($mobil['gambar']) ?>"
                        alt="Foto mobil">
                <?php else: ?>
                    <p>Belum ada foto.</p>
                <?php endif; ?>

                <label>Ganti Foto (Opsional)</label>
                <input type="file" name="gambar" accept=".jpg,.jpeg,.png,.webp">
                <small>JPG, PNG, atau WEBP maksimal 2 MB.</small>
            </div>

            <div class="full">
                <label>Deskripsi</label>
                <textarea name="deskripsi"><?= e($_POST['deskripsi'] ?? $mobil['deskripsi'] ?? '') ?></textarea>
            </div>

            <div class="full admin-actions">
                <button type="submit" class="admin-button">Simpan Perubahan</button>
                <a href="mobil.php" class="admin-button secondary">Batal</a>
            </div>

        </form>

    </section>
</main>

</body>
</html>
