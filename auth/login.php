<?php

require_once '../config/app.php';

// Jika pengguna sudah login, arahkan ke dashboard
if (!empty($_SESSION['user_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}

$error = '';

// Proses login ketika form dikirim
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Memeriksa token CSRF
    verify_csrf();

    // Mengambil data pengguna berdasarkan email
    $q = $pdo->prepare(
        'SELECT * FROM users WHERE email = ?'
    );

    $q->execute([
        trim($_POST['email'] ?? '')
    ]);

    $u = $q->fetch();

    // Memeriksa email dan password
    if ($u && password_verify($_POST['password'] ?? '', $u['password'])) {

        if (isset($u['status']) && $u['status'] !== 'active') {
            $error = 'Akun Anda sedang dinonaktifkan oleh administrator. Silakan hubungi admin.';
        } else {
            // Memperbarui session ID setelah login
            session_regenerate_id(true);

            $_SESSION['user_id'] = $u['id'];
            $_SESSION['user_name'] = $u['name'];
            $_SESSION['user_role'] = $u['role'] ?? 'user';
            $_SESSION['user_avatar'] = $u['profile_picture'] ?? null;

            // Jika role adalah admin, arahkan ke dashboard admin
            if (($_SESSION['user_role'] ?? '') === 'admin') {
                header('Location: ../admin/index.php');
                exit;
            }

            // Mengarahkan pengguna ke dashboard
            header('Location: ../dashboard/index.php');
            exit;
        }
    } else {
        $error = 'Email atau password tidak sesuai.';
    }
}

?>

<!doctype html>
<html lang="id">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="icon" type="image/jpeg"
        href="../assets/img/ikon.jpeg">
    <title>Login | NISAYA</title>

    <!-- Bootstrap CSS -->
    <link
        href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"
        rel="stylesheet">

    <!-- Custom CSS -->
    <link href="../assets/style.css" rel="stylesheet">
</head>

<body>

    <div class="auth-wrap">
        <div class="auth-card">

            <!-- Judul halaman -->
            <h2 class="fw-bold">🌸 NISAYA</h2>
            <p class="muted">Selamat datang kembali.</p>

            <!-- Pesan berhasil -->
            <?php if ($m = flash('success')): ?>
                <div class="alert alert-success">
                    <?= e($m) ?>
                </div>
            <?php endif; ?>

            <!-- Pesan kesalahan -->
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <!-- Form login -->
            <form method="post">

                <input
                    type="hidden"
                    name="csrf"
                    value="<?= e(csrf_token()) ?>">

                <label class="form-label">Email</label>

                <input
                    class="form-control mb-3"
                    type="email"
                    name="email"
                    required>

                <label class="form-label">Password</label>

                <input
                    class="form-control mb-3"
                    type="password"
                    name="password"
                    required>

                <!-- Tombol Login -->
                <button type="submit" class="btn btn-primary w-100 mb-2">
                    Login
                </button>

                <!-- Tombol Kembali ke Beranda -->
                <a href="../landing/index.php"
                    class="btn btn-primary w-100 mb-2">
                    ← Kembali ke Beranda
                </a>

            </form>

            <!-- Link registrasi -->
            <p class="mt-3 mb-0">
                Belum punya akun?
                <a href="register.php">Daftar</a>
            </p>


        </div>
    </div>

</body>

</html>