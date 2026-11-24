<?php
require_once '../config/app.php';
require_login();

$uid = (int) $_SESSION['user_id'];

// Ambil data profil dan user
$q = $pdo->prepare(
    'SELECT u.id, u.name, u.email, u.role, u.profile_picture, u.status, c.*
     FROM users u
     LEFT JOIN cycle_profiles c ON c.user_id = u.id
     WHERE u.id = ?'
);
$q->execute([$uid]);
$data = $q->fetch(PDO::FETCH_ASSOC) ?: [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? 'profile';

    // ==========================================
    // 1. PERBARUI PROFIL
    // ==========================================
    if ($action === 'profile') {
        $name = trim($_POST['name'] ?? '');
        $last = !empty($_POST['last_period_start'])
            ? $_POST['last_period_start']
            : null;

        $period = (int) ($_POST['average_period_days'] ?? 0);
        $purity = (int) ($_POST['average_purity_days'] ?? 0);
        $regular = $_POST['cycle_regular'] ?? 'unknown';

        // Validasi tanggal
        $validDate = true;

        if ($last !== null) {
            $date = DateTimeImmutable::createFromFormat('!Y-m-d', $last);
            $validDate = $date && $date->format('Y-m-d') === $last;
        }

        // Upload foto profil
        $avatarPath = $data['profile_picture'] ?? null;

        if (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1') {
            if ($avatarPath && file_exists(__DIR__ . '/../' . $avatarPath)) {
                @unlink(__DIR__ . '/../' . $avatarPath);
            }

            $avatarPath = null;
        } elseif (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['avatar'];

            $allowedTypes = [
                'image/jpeg',
                'image/png',
                'image/webp',
                'image/gif'
            ];

            $fileMime = mime_content_type($file['tmp_name']);
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (
                in_array($fileMime, $allowedTypes, true) &&
                in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)
            ) {
                if ($file['size'] <= 3 * 1024 * 1024) {
                    $newFilename = 'avatar_' . $uid . '_' . time() . '.' . $fileExt;

                    $targetDir = __DIR__ . '/../assets/uploads/avatars/';

                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0775, true);
                    }

                    $targetPath = $targetDir . $newFilename;

                    if (move_uploaded_file($file['tmp_name'], $targetPath)) {

                        // Hapus foto lama
                        if ($avatarPath && file_exists(__DIR__ . '/../' . $avatarPath)) {
                            @unlink(__DIR__ . '/../' . $avatarPath);
                        }

                        $avatarPath = 'assets/uploads/avatars/' . $newFilename;
                    }
                } else {
                    flash('error', 'Ukuran gambar profil maksimal 3MB.');
                    header('Location: index.php');
                    exit;
                }
            } else {
                flash('error', 'Format gambar harus berupa JPG, PNG, WEBP, atau GIF.');
                header('Location: index.php');
                exit;
            }
        }

        // Validasi data profil
        if (
            strlen($name) < 3 ||
            !$validDate ||
            ($period && ($period < 1 || $period > 15)) ||
            ($purity && $purity < 1) ||
            !in_array($regular, ['unknown', 'regular', 'irregular'], true)
        ) {
            flash('error', 'Periksa kembali data yang dimasukkan.');
        } else {
            try {
                $pdo->beginTransaction();

                // Perbarui data pengguna
                $q = $pdo->prepare(
                    'UPDATE users
                     SET name = ?, profile_picture = ?, updated_at = NOW()
                     WHERE id = ?'
                );

                $q->execute([
                    $name,
                    $avatarPath,
                    $uid
                ]);

                // Simpan profil siklus menstruasi
                $q = $pdo->prepare("
                    INSERT INTO cycle_profiles (
                        user_id,
                        last_period_start,
                        average_period_days,
                        average_purity_days,
                        cycle_regular,
                        profile_completed,
                        updated_at
                    )
                    VALUES (?, ?, ?, ?, ?, 1, NOW())
                    ON DUPLICATE KEY UPDATE
                        last_period_start = VALUES(last_period_start),
                        average_period_days = VALUES(average_period_days),
                        average_purity_days = VALUES(average_purity_days),
                        cycle_regular = VALUES(cycle_regular),
                        profile_completed = 1,
                        updated_at = NOW()
                ");

                $q->execute([
                    $uid,
                    $last,
                    $period ?: null,
                    $purity ?: null,
                    $regular
                ]);

                $pdo->commit();

                $_SESSION['user_name'] = $name;
                $_SESSION['user_avatar'] = $avatarPath;

                flash('success', 'Profil dan foto berhasil diperbarui.');
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }

                error_log($e->getMessage());

                flash('error', 'Profil gagal diperbarui. Silakan coba kembali.');
            }
        }

        // ==========================================
        // 2. PERBARUI PASSWORD
        // ==========================================
    } elseif ($action === 'password') {
        $oldPass = $_POST['old_password'] ?? '';
        $newPass = $_POST['new_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';

        $stmt = $pdo->prepare(
            "SELECT password FROM users WHERE id = ?"
        );
        $stmt->execute([$uid]);

        $currentHashed = $stmt->fetchColumn();

        if (!password_verify($oldPass, $currentHashed)) {
            flash('error', 'Password lama tidak sesuai.');
        } elseif (strlen($newPass) < 8) {
            flash('error', 'Password baru minimal 8 karakter.');
        } elseif ($newPass !== $confirmPass) {
            flash('error', 'Konfirmasi password baru tidak cocok.');
        } else {
            $newHashed = password_hash($newPass, PASSWORD_DEFAULT);

            $stmt = $pdo->prepare(
                "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?"
            );

            $stmt->execute([
                $newHashed,
                $uid
            ]);

            flash('success', 'Password berhasil diperbarui.');
        }
    }

    header('Location: index.php');
    exit;
}

$pageTitle = 'Profil';
$active = 'profile';

require '../includes/header.php';
?>

<div class="profile-page">

    <!-- ==========================================
         HEADER PROFIL
    =========================================== -->
    <section class="profile-hero mb-4">
        <div>
            <span class="profile-eyebrow">RUANG PRIBADIMU</span>

            <h2>Profil Saya 🌸</h2>

            <p>
                Kelola informasi akun, foto profil, dan catatan siklusmu
                dalam satu tempat.
            </p>
        </div>

        <div class="profile-hero-icon">🩷</div>
    </section>

    <div class="row g-4">

        <!-- ==========================================
             KOLOM KIRI - INFORMASI AKUN
        =========================================== -->
        <div class="col-lg-4">

            <section class="profile-card profile-account-card text-center">

                <!-- Foto Profil -->
                <div class="mb-3 d-flex justify-content-center">

                    <?php if (
                        !empty($data['profile_picture']) &&
                        file_exists(__DIR__ . '/../' . $data['profile_picture'])
                    ): ?>

                        <img
                            src="../<?= e($data['profile_picture']) ?>"
                            alt="Avatar"
                            class="rounded-circle shadow-sm"
                            style="
                                width: 100px;
                                height: 100px;
                                object-fit: cover;
                                border: 4px solid #f3c9d8;
                            ">

                    <?php else: ?>

                        <div class="profile-avatar mx-auto">
                            🌷
                        </div>

                    <?php endif; ?>

                </div>

                <h5><?= e($data['name'] ?? '') ?></h5>

                <p class="profile-email">
                    <?= e($data['email'] ?? '') ?>
                </p>

                <!-- Role dan Status -->
                <div class="d-flex justify-content-center gap-2 mb-3">

                    <?php if (($data['role'] ?? 'user') === 'admin'): ?>

                        <span class="badge text-bg-warning px-3 py-1">
                            Administrator 🛡️
                        </span>

                    <?php else: ?>

                        <span class="badge text-bg-secondary px-3 py-1">
                            Anggota 🌸
                        </span>

                    <?php endif; ?>

                    <span class="badge <?= ($data['status'] ?? 'active') === 'active' ? 'text-bg-success' : 'text-bg-danger' ?> px-2 py-1">
                        <?= ($data['status'] ?? 'active') === 'active' ? 'Aktif' : 'Nonaktif' ?>
                    </span>

                </div>

                <div class="profile-divider"></div>

                <!-- Detail Akun -->
                <div class="profile-account-info text-start">
                    <span>👤</span>

                    <div>
                        <small>Nama akun</small>
                        <strong><?= e($data['name'] ?? '') ?></strong>
                    </div>
                </div>

                <div class="profile-account-info text-start">
                    <span>✉️</span>

                    <div>
                        <small>Email terdaftar</small>
                        <strong><?= e($data['email'] ?? '') ?></strong>
                    </div>
                </div>

                <div class="profile-account-note text-start">
                    Informasi akunmu digunakan untuk mengelola data pribadi di NISAYA.
                </div>

            </section>

            <!-- Catatan -->
            <section class="profile-note-card mt-4">

                <div class="profile-note-icon">💗</div>

                <h6>Kenali tubuhmu</h6>

                <p>
                    Catatan siklus dapat membantumu mengenali pola menstruasi
                    dari waktu ke waktu. Setiap orang bisa memiliki pola yang berbeda.
                </p>

            </section>

        </div>

        <!-- ==========================================
             KOLOM KANAN - PENGATURAN PROFIL
        =========================================== -->
        <div class="col-lg-8">

            <!-- FORM PROFIL -->
            <section class="profile-card profile-form-card mb-4">

                <div class="profile-section-heading">

                    <div>
                        <span class="profile-section-label">
                            INFORMASI AKUN
                        </span>

                        <h5>Pengaturan profil & foto</h5>

                        <p>
                            Perbarui informasi akun dan foto profilmu di sini.
                        </p>
                    </div>

                    <div class="profile-section-icon">✎</div>

                </div>

                <form
                    method="post"
                    action="index.php"
                    enctype="multipart/form-data">

                    <input
                        type="hidden"
                        name="csrf"
                        value="<?= e(csrf_token()) ?>">

                    <input
                        type="hidden"
                        name="action"
                        value="profile">

                    <div class="profile-form-section">

                        <!-- FOTO PROFIL -->
                        <h6>Foto Profil</h6>

                        <div class="mb-4 d-flex align-items-center gap-3">

                            <div>

                                <?php if (
                                    !empty($data['profile_picture']) &&
                                    file_exists(__DIR__ . '/../' . $data['profile_picture'])
                                ): ?>

                                    <img
                                        src="../<?= e($data['profile_picture']) ?>"
                                        alt="Avatar"
                                        class="rounded-circle shadow-sm"
                                        style="
                                            width: 70px;
                                            height: 70px;
                                            object-fit: cover;
                                            border: 2px solid #e5d9ed;
                                        ">

                                <?php else: ?>

                                    <div
                                        class="rounded-circle d-flex align-items-center justify-content-center bg-light fs-3"
                                        style="
                                            width: 70px;
                                            height: 70px;
                                            border: 2px dashed #dcccf2;
                                        ">
                                        🌷
                                    </div>

                                <?php endif; ?>

                            </div>

                            <div class="flex-grow-1">

                                <label class="form-label mb-1 small fw-semibold">
                                    Pilih Foto Baru
                                </label>

                                <input
                                    type="file"
                                    name="avatar"
                                    class="form-control form-control-sm"
                                    accept="image/png, image/jpeg, image/webp, image/gif">

                                <small
                                    class="text-muted"
                                    style="font-size: 0.75rem;">
                                    Maksimal 3MB (Format: JPG, PNG, WEBP)
                                </small>

                            </div>

                            <?php if (!empty($data['profile_picture'])): ?>

                                <div>
                                    <label class="form-check-label text-danger small">

                                        <input
                                            type="checkbox"
                                            name="remove_avatar"
                                            value="1"
                                            class="form-check-input">

                                        Hapus Foto

                                    </label>
                                </div>

                            <?php endif; ?>

                        </div>

                        <!-- INFORMASI PRIBADI -->
                        <h6>Informasi pribadi</h6>

                        <div class="mb-3">

                            <label class="form-label">
                                Nama lengkap
                            </label>

                            <input
                                type="text"
                                class="form-control"
                                name="name"
                                value="<?= e($data['name'] ?? '') ?>"
                                required
                                minlength="3">

                        </div>

                        <div class="mb-4">

                            <label class="form-label">
                                Email
                            </label>

                            <input
                                type="email"
                                class="form-control profile-readonly"
                                value="<?= e($data['email'] ?? '') ?>"
                                readonly>

                            <small class="profile-help">
                                Email tidak dapat diubah melalui halaman ini.
                            </small>

                        </div>

                    </div>

                    <!-- ==========================================
                         PROFIL MENSTRUASI
                    =========================================== -->
                    <div class="profile-form-section">

                        <div class="profile-section-heading">

                            <div>
                                <span class="profile-section-label">
                                    CATATAN SIKLUS
                                </span>

                                <h5>Profil menstruasi</h5>

                                <p>
                                    Isi berdasarkan catatan siklus yang kamu ketahui.
                                </p>
                            </div>

                            <div class="profile-section-icon">🌸</div>

                        </div>

                        <!-- Hari Pertama Haid -->
                        <div class="mb-3">

                            <label class="form-label">
                                Hari pertama haid terakhir
                            </label>

                            <input
                                type="date"
                                class="form-control"
                                name="last_period_start"
                                value="<?= e($data['last_period_start'] ?? '') ?>">

                        </div>

                        <!-- Lama Haid dan Masa Suci -->
                        <div class="row">

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Rata-rata lama haid (hari)
                                </label>

                                <input
                                    type="number"
                                    class="form-control"
                                    name="average_period_days"
                                    min="1"
                                    max="15"
                                    value="<?= e($data['average_period_days'] ?? '') ?>"
                                    placeholder="Contoh: 7">

                            </div>

                            <div class="col-md-6 mb-3">

                                <label class="form-label">
                                    Rata-rata masa suci (hari)
                                </label>

                                <input
                                    type="number"
                                    class="form-control"
                                    name="average_purity_days"
                                    min="1"
                                    value="<?= e($data['average_purity_days'] ?? '') ?>"
                                    placeholder="Isi jika diketahui">

                            </div>

                        </div>

                        <!-- Keteraturan Siklus -->
                        <div class="mb-4">

                            <label class="form-label">
                                Keteraturan siklus
                            </label>

                            <select
                                class="form-select"
                                name="cycle_regular">

                                <option
                                    value="unknown"
                                    <?= ($data['cycle_regular'] ?? 'unknown') === 'unknown' ? 'selected' : '' ?>>
                                    Belum diketahui
                                </option>

                                <option
                                    value="regular"
                                    <?= ($data['cycle_regular'] ?? '') === 'regular' ? 'selected' : '' ?>>
                                    Teratur
                                </option>

                                <option
                                    value="irregular"
                                    <?= ($data['cycle_regular'] ?? '') === 'irregular' ? 'selected' : '' ?>>
                                    Tidak teratur
                                </option>

                            </select>

                        </div>

                    </div>

                    <!-- TOMBOL SIMPAN PROFIL -->
                    <div class="profile-form-footer">

                        <button
                            type="submit"
                            class="profile-save-btn">
                            ✓ Simpan perubahan profil
                        </button>

                    </div>

                </form>

            </section>

            <!-- ==========================================
                 PENGATURAN KEAMANAN AKUN
            =========================================== -->
            <section class="profile-card profile-form-card">

                <div class="profile-section-heading">

                    <div>
                        <span class="profile-section-label">
                            KEAMANAN AKUN
                        </span>

                        <h5>Ganti Password</h5>

                        <p>
                            Pastikan menggunakan password yang aman dan sulit ditebak.
                        </p>
                    </div>

                    <div class="profile-section-icon">🔒</div>

                </div>

                <div class="d-flex align-items-center justify-content-between flex-wrap gap-3">

                    <div>
                        <strong>Perbarui keamanan akunmu</strong>

                        <p class="text-muted mb-0 small">
                            Ganti password secara berkala untuk menjaga keamanan akun.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="btn btn-outline-secondary px-4 py-2"
                        style="border-radius: 12px; font-weight: 600;"
                        data-bs-toggle="modal"
                        data-bs-target="#passwordModal">
                        🔒 Perbarui Password
                    </button>

                </div>

            </section>

            <!-- ==========================================
                 MODAL GANTI PASSWORD
            =========================================== -->
            <div
                class="modal fade"
                id="passwordModal"
                tabindex="-1"
                aria-labelledby="passwordModalLabel"
                aria-hidden="true">

                <div class="modal-dialog modal-dialog-centered">

                    <div
                        class="modal-content border-0 shadow"
                        style="border-radius: 20px; overflow: hidden;">

                        <!-- HEADER MODAL -->
                        <div class="modal-header border-0 px-4 pt-4">

                            <div>

                                <span class="profile-section-label">
                                    KEAMANAN AKUN
                                </span>

                                <h5
                                    class="modal-title fw-bold mt-1"
                                    id="passwordModalLabel">
                                    🔒 Ganti Password
                                </h5>

                                <p class="text-muted small mb-0">
                                    Masukkan password lama dan password baru kamu.
                                </p>

                            </div>

                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"
                                aria-label="Close"></button>

                        </div>

                        <!-- BODY MODAL -->
                        <div class="modal-body px-4 pb-2">

                            <form
                                method="post"
                                id="passwordForm">

                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= e(csrf_token()) ?>">

                                <input
                                    type="hidden"
                                    name="action"
                                    value="password">

                                <!-- PASSWORD LAMA -->
                                <div class="mb-3">

                                    <label class="form-label fw-semibold">
                                        Password Saat Ini
                                    </label>

                                    <input
                                        type="password"
                                        name="old_password"
                                        class="form-control"
                                        required
                                        placeholder="Masukkan password lama"
                                        style="border-radius: 10px;">

                                </div>

                                <!-- PASSWORD BARU -->
                                <div class="mb-3">

                                    <label class="form-label fw-semibold">
                                        Password Baru
                                    </label>

                                    <input
                                        type="password"
                                        name="new_password"
                                        class="form-control"
                                        required
                                        minlength="8"
                                        placeholder="Minimal 8 karakter"
                                        style="border-radius: 10px;">

                                </div>

                                <!-- KONFIRMASI PASSWORD -->
                                <div class="mb-3">

                                    <label class="form-label fw-semibold">
                                        Konfirmasi Password Baru
                                    </label>

                                    <input
                                        type="password"
                                        name="confirm_password"
                                        class="form-control"
                                        required
                                        minlength="8"
                                        placeholder="Ulangi password baru"
                                        style="border-radius: 10px;">

                                </div>

                                <!-- FOOTER MODAL -->
                                <div class="d-flex justify-content-end gap-2 mt-4 mb-2">

                                    <button
                                        type="button"
                                        class="btn btn-light px-3"
                                        data-bs-dismiss="modal"
                                        style="border-radius: 10px;">
                                        Batal
                                    </button>

                                    <button
                                        type="submit"
                                        class="btn btn-outline-secondary px-3"
                                        style="border-radius: 10px; font-weight: 600;">
                                        🔒 Simpan Password
                                    </button>

                                </div>

                            </form>

                        </div>

                    </div>

                </div>

            </div>

        </div>

    </div>

</div>

<?php require '../includes/footer.php'; ?>