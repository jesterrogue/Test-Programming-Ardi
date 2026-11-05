
<?php
session_start();

require_once 'config/database.php';
require_once 'includes/functions.php';

function arahkanSetelahLogin($role, $redirect = 'index.php')
{
    if ($role === 'admin') {
        header('Location: admin/index.php');
        exit;
    }

    header('Location: ' . $redirect);
    exit;
}

if (isset($_SESSION['user_id'])) {
    $role = $_SESSION['role'] ?? 'pelanggan';

    arahkanSetelahLogin($role);
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$errors = [];
$noSim = '';

$redirect = $_GET['redirect'] ?? $_POST['redirect'] ?? 'index.php';

if (!preg_match('/^booking\.php\?id=[1-9][0-9]*$/', $redirect)) {
    $redirect = 'index.php';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $noSim = trim($_POST['no_sim'] ?? '');
    $password = $_POST['password'] ?? '';

    if (
        !isset($_POST['csrf_token']) ||
        !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
    ) {
        $errors[] = 'Permintaan tidak valid. Silakan muat ulang halaman.';
    }

    if ($noSim === '' || strlen($noSim) > 30) {
        $errors[] = 'Nomor SIM tidak valid.';
    }

    if ($password === '') {
        $errors[] = 'Password wajib diisi.';
    }

    if (empty($errors)) {

        $stmt = $pdo->prepare("
            SELECT
                id_user,
                nama_lengkap,
                no_sim,
                password,
                role
            FROM users
            WHERE no_sim = :no_sim
            LIMIT 1
        ");

        $stmt->execute(['no_sim' => $noSim]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($password, $user['password'])) {

            if (password_needs_rehash(
                $user['password'],
                PASSWORD_DEFAULT
            )) {
                $newHash = password_hash($password, PASSWORD_DEFAULT);

                $update = $pdo->prepare("
                    UPDATE users
                    SET password = :password
                    WHERE id_user = :id
                ");

                $update->execute([
                    'password' => $newHash,
                    'id' => $user['id_user']
                ]);
            }

            session_regenerate_id(true);

            $_SESSION['user_id'] = $user['id_user'];
            $_SESSION['nama_lengkap'] = $user['nama_lengkap'];
            $_SESSION['role'] = $user['role'];

            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));

            arahkanSetelahLogin($user['role'], $redirect);

        } else {
            $errors[] = 'SIM atau password salah.';
        }
    }
}

require_once 'includes/header.php';
?>

<section class="auth-container">

    <div class="auth-card">

        <div class="auth-heading">
            <h1>Selamat Datang</h1>
            <p>Login ke akun DriveEase Anda.</p>
        </div>

        <?php if (isset($_GET['registered'])): ?>
            <div class="auth-alert success">
                Registrasi berhasil. Silakan login menggunakan akun Anda.
            </div>
        <?php endif; ?>

        <?php if (!empty($errors)): ?>
            <div class="auth-alert error">
                <?php foreach ($errors as $error): ?>
                    <p><?= e($error) ?></p>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" action="login.php">

            <input
                type="hidden"
                name="csrf_token"
                value="<?= e($_SESSION['csrf_token']) ?>"
            >

            <input
                type="hidden"
                name="redirect"
                value="<?= e($redirect) ?>"
            >

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
                <label for="password">Password</label>
                <input
                    type="password"
                    id="password"
                    name="password"
                    placeholder="Masukkan password"
                    required
                >
            </div>

            <button type="submit" class="auth-button">
                Login
            </button>

        </form>

        <p class="auth-switch">
            Belum punya akun?
            <a href="register.php">Daftar sekarang</a>
        </p>

    </div>

</section>

<?php require_once 'includes/footer.php'; ?>
