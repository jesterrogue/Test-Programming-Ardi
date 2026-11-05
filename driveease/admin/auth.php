
<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['user_id'])) {
    header('Location: ../login.php');
    exit;
}

if (($_SESSION['role'] ?? '') !== 'admin') {
    header('Location: ../index.php');
    exit;
}

// Membuat token CSRF
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Validasi CSRF untuk permintaan POST
function adminValidasiCsrf(): void
{
    $token = $_POST['csrf_token'] ?? '';

    if (
        empty($_SESSION['csrf_token']) ||
        !is_string($token) ||
        !hash_equals($_SESSION['csrf_token'], $token)
    ) {
        die('Permintaan tidak valid.');
    }
}

function adminUploadGambar(array $file)
{
    if (
        !isset($file['error']) ||
        $file['error'] === UPLOAD_ERR_NO_FILE
    ) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        return false;
    }

    if ($file['size'] > 2 * 1024 * 1024) {
        return false;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($file['tmp_name']);

    $extensions = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp'
    ];

    if (!isset($extensions[$mime])) {
        return false;
    }

    $folder = __DIR__ . '/../assets/mobil/';

    if (!is_dir($folder)) {
        return false;
    }

    $namaFile = bin2hex(random_bytes(16)) . '.' . $extensions[$mime];

    if (!move_uploaded_file($file['tmp_name'], $folder . $namaFile)) {
        return false;
    }

    return $namaFile;
}

function adminHapusFileGambar(?string $namaFile): void
{
    if (!$namaFile) {
        return;
    }

    $namaFile = basename($namaFile);
    $path = __DIR__ . '/../assets/mobil/' . $namaFile;

    if (is_file($path)) {
        unlink($path);
    }
}
