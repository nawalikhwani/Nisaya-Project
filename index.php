
<?php

require_once __DIR__ . '/config/app.php';

// Jika pengguna sudah login, arahkan ke dashboard.
if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard/index.php');
    exit;
}

// Jika belum login, arahkan ke landing page.
header('Location: landing/index.php');
exit;
