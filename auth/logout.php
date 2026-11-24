
<?php

require_once '../config/app.php';

// Pastikan permintaan menggunakan POST.
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    exit('Metode permintaan tidak diizinkan.');
}

// Validasi token CSRF.
verify_csrf();

// Mengosongkan data session.
$_SESSION = [];

// Menghapus cookie session.
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();

    setcookie(
        session_name(),
        '',
        [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite']
        ]
    );
}

// Menghancurkan session.
session_destroy();

// Kembali ke halaman login.
header('Location: login.php');
exit;
