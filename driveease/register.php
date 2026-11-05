
<?php
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$nama = '';
$noSim = '';
$noHp = '';
$alamat = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $nama = trim($_POST['nama_lengkap'] ?? '');
    $noSim = trim($_POST['no_sim'] ?? '');
    $noHp = trim($_POST['no_hp'] ?? '');
    $alamat = trim($_POST['alamat'] ?? '');
    $password = $_POST['password'] ?? '';
    $konfirmasi = $_POST['konfirmasi_password'] ?? '';

    // Validasi CSRF
    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }

    // Validasi input
    if ($nama === '') {
        $errors[] = 'Nama lengkap wajib diisi.';
    }

	if ($noSim === '') {
    	$errors[] = 'Nomor SIM wajib diisi.';
	} elseif (strlen($noSim) > 30) {
    	$errors[] = 'Nomor SIM maksimal 30 karakter.';
	}
    if ($noHp === '') {
        $errors[] = 'Nomor HP wajib diisi.';
    }

    if (strlen($password) < 8) {
        $errors[] = 'Password minimal 8 karakter.';
    }

    if ($password !== $konfirmasi) {
        $errors[] = 'Konfirmasi password tidak sama.';
    }

    if (empty($errors)) {

$stmt = $pdo->prepare("
    SELECT id_user
    FROM users
    WHERE no_sim = :no_sim
    LIMIT 1
");

$stmt->execute(['no_sim' => $noSim]);

if ($stmt->fetch()) {
    $errors[] = 'Nomor SIM sudah terdaftar.';
}
    }

    // Simpan pengguna
    if (empty($errors)) {

        $passwordHash = password_hash($password, PASSWORD_DEFAULT);
$stmt = $pdo->prepare("
    INSERT INTO users (
        nama_lengkap,
        no_sim,
        no_hp,
        password,
        role,
        alamat
    ) VALUES (
        :nama,
        :no_sim,
        :no_hp,
        :password,
        'pelanggan',
        :alamat
    )
");

$stmt->execute([
    'nama' => $nama,
    'no_sim' => $noSim,
    'no_hp' => $noHp,
    'password' => $passwordHash,
    'alamat' => $alamat
]);

        header('Location: login.php?registered=1');
        exit;
    }
}

require_once 'includes/header.php';
?>

<section class="auth-container">

    <div class="auth-card">

        <div class="auth-heading">
            <h1>Buat Akun DriveEase</h1>
            <p>Daftar untuk mulai menyewa mobil dengan mudah.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="auth-alert error">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="register.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <div class="auth-form-group">
                <label for="nama_lengkap">Nama Lengkap</label>
                <input
                    type="text"
                    id="nama_lengkap"
                    name="nama_lengkap"
                    value="<?= e($nama) ?>"
                    placeholder="Masukkan nama lengkap"
                    required
                >
            </div>

<div class="auth-form-group">
    <label for="no_sim">Nomor SIM</label>
    <input
        type="text"
        id="no_sim"
        name="no_sim"
        value="<?= e($noSim) ?>"
        placeholder="Masukkan Nomor SIM"
        maxlength="30"
        required
    >
</div>

            <div class="auth-form-group">
                <label for="no_hp">Nomor HP</label>
                <input
                    type="tel"
                    id="no_hp"
                    name="no_hp"
                    value="<?= e($noHp) ?>"
                    placeholder="Masukkan nomor HP"
                    required
                >
            </div>

            <div class="auth-form-group">
                <label for="alamat">Alamat</label>
                <textarea
                    id="alamat"
                    name="alamat"
                    rows="3"
                    placeholder="Masukkan alamat"
                ><?= e($alamat) ?></textarea>
            </div>

            <div class="auth-form-group">
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Minimal 8 karakter"
                    minlength="8"
                    required
                >
            </div>

            <div class="auth-form-group">
                <label for="konfirmasi_password">Konfirmasi Password</label>
                <input
                    type="password"
                    id="konfirmasi_password"
                    name="konfirmasi_password"
                    placeholder="Ulangi password"
                    minlength="8"
                    required
                >
            </div>

            <button type="submit" class="auth-button">
                Daftar Sekarang
            </button>

        </form>

        <p class="auth-switch">
            Sudah punya akun?
            <a href="login.php">Login di sini</a>
        </p>

    </div>

</section>

<?php require_once 'includes/footer.php'; ?>
