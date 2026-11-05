
<?php
session_start();

// Logout hanya boleh dilakukan melalui POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// Validasi token keamanan
if (
    !isset($_POST['csrf_token'], $_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])
) {
    http_response_code(403);
    exit('Permintaan tidak valid.');
}

// Hapus seluruh data session
$_SESSION = [];

// Hapus cookie session
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'],
        $params['domain'],
        $params['secure'],
        $params['httponly']
    );
}

session_destroy();

header('Location: index.php');
exit;
