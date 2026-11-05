
<?php
require_once '../config/database.php';
require_once '../includes/functions.php';
require_once 'auth.php';

$error = '';

$kategori = $pdo->query("
    SELECT id_kategori, nama_kategori
    FROM kategori_mobil
    ORDER BY nama_kategori
")->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    adminValidasiCsrf();

    $idKategori = filter_input(INPUT_POST, 'id_kategori', FILTER_VALIDATE_INT);
    $nama = trim($_POST['nama_mobil'] ?? '');
    $merk = trim($_POST['merk'] ?? '');
    $plat = strtoupper(trim($_POST['nomor_plat'] ?? ''));
    $tahun = trim($_POST['tahun'] ?? '');
    $transmisi = $_POST['transmisi'] ?? '';
    $kapasitas = filter_input(INPUT_POST, 'kapasitas', FILTER_VALIDATE_INT);
    $bahanBakar = trim($_POST['bahan_bakar'] ?? '');
    $harga = filter_input(INPUT_POST, 'harga_per_hari', FILTER_VALIDATE_FLOAT);
    $deskripsi = trim($_POST['deskripsi'] ?? '');
    $status = $_POST['status'] ?? 'tersedia';

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
            SELECT COUNT(*) FROM mobil WHERE nomor_plat = :plat
        ");
        $stmt->execute(['plat' => $plat]);

        if ($stmt->fetchColumn() > 0) {
            $error = 'Nomor plat tersebut sudah digunakan.';
        } else {
            $gambar = adminUploadGambar($_FILES['gambar'] ?? []);

            if ($gambar === false) {
                $error = 'Foto gagal diunggah. Gunakan JPG, PNG, atau WEBP maksimal 2 MB.';
            } else {
                try {
                    $pdo->beginTransaction();

                    $stmt = $pdo->prepare("
                        INSERT INTO mobil (
                            id_kategori, nama_mobil, nomor_plat, merk,
                            tahun, transmisi, kapasitas, bahan_bakar,
                            harga_per_hari, deskripsi, status
                        ) VALUES (
                            :kategori, :nama, :plat, :merk,
                            :tahun, :transmisi, :kapasitas, :bahan,
                            :harga, :deskripsi, :status
                        )
                    ");

                    $stmt->execute([
                        'kategori' => $idKategori ?: null,
                        'nama' => $nama,
                        'plat' => $plat,
                        'merk' => $merk,
                        'tahun' => $tahun !== '' ? $tahun : null,
                        'transmisi' => $transmisi,
                        'kapasitas' => $kapasitas,
                        'bahan' => $bahanBakar ?: null,
                        'harga' => $harga,
                        'deskripsi' => $deskripsi ?: null,
                        'status' => $status
                    ]);

                    $idMobil = (int) $pdo->lastInsertId();

                    if ($gambar) {
                        $stmt = $pdo->prepare("
                            INSERT INTO gambar_mobil (id_mobil, nama_file, is_utama)
                            VALUES (:id, :gambar, 1)
                        ");
                        $stmt->execute([
                            'id' => $idMobil,
                            'gambar' => $gambar
                        ]);
                    }

                    $pdo->commit();

                    header('Location: mobil.php?pesan=tambah');
                    exit;

                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }

                    adminHapusFileGambar($gambar);
                    $error = 'Gagal menyimpan mobil. Periksa kembali data yang dimasukkan.';
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
    <title>Tambah Mobil - DriveEase</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <link rel="stylesheet" href="../assets/css/admin.css">
</head>
<body>

<main class="admin-page">
    <div class="admin-heading">
        <div>
            <h1>Tambah Mobil</h1>
            <p>Masukkan informasi kendaraan baru.</p>
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
                    value="<?= e($_POST['nama_mobil'] ?? '') ?>">
            </div>

            <div>
                <label>Merek *</label>
                <input type="text" name="merk" required
                    value="<?= e($_POST['merk'] ?? '') ?>">
            </div>

            <div>
                <label>Nomor Plat *</label>
                <input type="text" name="nomor_plat" required
                    value="<?= e($_POST['nomor_plat'] ?? '') ?>">
            </div>

            <div>
                <label>Kategori</label>
                <select name="id_kategori">
                    <option value="">Pilih kategori</option>
                    <?php foreach ($kategori as $item): ?>
                        <option value="<?= (int)$item['id_kategori'] ?>"
                            <?= (($_POST['id_kategori'] ?? '') == $item['id_kategori']) ? 'selected' : '' ?>>
                            <?= e($item['nama_kategori']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Tahun</label>
                <input type="number" name="tahun" min="1900"
                    max="<?= (int)date('Y') + 1 ?>"
                    value="<?= e($_POST['tahun'] ?? '') ?>">
            </div>

            <div>
                <label>Transmisi *</label>
                <select name="transmisi" required>
                    <option value="">Pilih transmisi</option>
                    <option value="manual">Manual</option>
                    <option value="matic">Matic</option>
                </select>
            </div>

            <div>
                <label>Kapasitas Penumpang *</label>
                <input type="number" name="kapasitas" min="1" required
                    value="<?= e($_POST['kapasitas'] ?? '') ?>">
            </div>

            <div>
                <label>Bahan Bakar</label>
                <input type="text" name="bahan_bakar"
                    value="<?= e($_POST['bahan_bakar'] ?? '') ?>">
            </div>

            <div>
                <label>Harga Sewa per Hari (Rp) *</label>
                <input type="number" name="harga_per_hari" min="1" step="0.01" required
                    value="<?= e($_POST['harga_per_hari'] ?? '') ?>">
            </div>

            <div>
                <label>Status Mobil *</label>
                <select name="status" required>
                    <option value="tersedia">Tersedia</option>
                    <option value="nonaktif">Nonaktif</option>
                    <option value="perawatan">Perawatan</option>
                </select>
            </div>

            <div class="full">
                <label>Foto Mobil</label>
                <input type="file" name="gambar" accept=".jpg,.jpeg,.png,.webp">
                <small>Format JPG, PNG, WEBP. Maksimal 2 MB.</small>
            </div>

            <div class="full">
                <label>Deskripsi</label>
                <textarea name="deskripsi"><?= e($_POST['deskripsi'] ?? '') ?></textarea>
            </div>

            <div class="full admin-actions">
                <button type="submit" class="admin-button">Simpan Mobil</button>
                <a href="mobil.php" class="admin-button secondary">Batal</a>
            </div>

        </form>
    </section>
</main>

</body>
</html>
