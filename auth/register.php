<?php require_once '../config/app.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: ../dashboard/index.php');
    exit;
}
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim($_POST['name'] ?? '');
    $email = filter_var($_POST['email'] ?? '', FILTER_VALIDATE_EMAIL);
    $pw = $_POST['password'] ?? '';
    $confirm = $_POST['confirm_password'] ?? '';

    if (strlen($name) < 3) {
        $error = 'Nama minimal 3 karakter.';
    } elseif (!$email) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($pw) < 8) {
        $error = 'Password minimal 8 karakter.';
    } elseif ($pw !== $confirm) {
        $error = 'Konfirmasi password tidak cocok.';
    } else {
        $q = $pdo->prepare('SELECT id FROM users WHERE email=?');
        $q->execute([$email]);
        if ($q->fetch()) $error = 'Email sudah terdaftar.';
        else {
            try {
                $pdo->beginTransaction();

                $q = $pdo->prepare(
                    'INSERT INTO users(name, email, password)
             VALUES(?, ?, ?)'
                );

                $q->execute([
                    $name,
                    $email,
                    password_hash($pw, PASSWORD_DEFAULT)
                ]);

                $id = $pdo->lastInsertId();

                $q = $pdo->prepare(
                    'INSERT INTO cycle_profiles(user_id)
             VALUES(?)'
                );

                $q->execute([$id]);

                $pdo->commit();

                flash('success', 'Registrasi berhasil. Silakan login.');
                header('Location: login.php');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($e->getMessage());
                $error = 'Registrasi gagal. Silakan coba lagi.';
            }
        }
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
    <title>Daftar | NISAYA</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="../assets/style.css" rel="stylesheet">
</head>

<body>
    <div class="auth-wrap">
        <div class="auth-card">
            <h2 class="fw-bold">🌸 Buat Akun NISAYA</h2>
            <p class="muted">Mulai catat dan kenali siklusmu.</p><?php if ($error): ?><div class="alert alert-danger"><?= e($error) ?></div><?php endif; ?><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>"><label class="form-label">Nama</label><input class="form-control mb-3" name="name" required minlength="3" value="<?= e($_POST['name'] ?? '') ?>"><label class="form-label">Email</label><input class="form-control mb-3" type="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>"><label class="form-label">Password</label><input class="form-control mb-3" type="password" name="password" required minlength="8"><label class="form-label">Konfirmasi Password</label><input class="form-control mb-3" type="password" name="confirm_password" required><button class="btn btn-primary w-100">Daftar</button></form>
            <p class="mt-3 mb-0">Sudah punya akun? <a href="login.php">Login</a></p>
        </div>
    </div>
</body>

</html>