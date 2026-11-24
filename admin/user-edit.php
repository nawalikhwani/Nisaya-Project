<?php
require_once __DIR__ . '/../config/app.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Ambil info pengguna
$stmt = $pdo->prepare("
    SELECT u.*, 
           c.last_period_start, c.average_period_days, c.average_purity_days, c.cycle_regular, c.profile_completed
    FROM users u
    LEFT JOIN cycle_profiles c ON c.user_id = u.id
    WHERE u.id = ?
");
$stmt->execute([$id]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    flash('error', 'Pengguna tidak ditemukan.');
    header('Location: users.php');
    exit;
}

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'update_user') {
        $name = trim($_POST['name'] ?? '');
        $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
        $newPassword = $_POST['new_password'] ?? '';
        $role = in_array($_POST['role'] ?? '', ['user', 'admin'], true) ? $_POST['role'] : $user['role'];
        $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : $user['status'];

        // Pencegahan: Admin tidak boleh mendownngrade atau menonaktifkan akun sendiri dari halaman ini
        if ($id === (int)$_SESSION['user_id']) {
            $role = 'admin';
            $status = 'active';
        }

        if (strlen($name) < 3) {
            $error = 'Nama lengkap minimal 3 karakter.';
        } elseif (!$email) {
            $error = 'Format email tidak valid.';
        } else {
            // Cek keunikan email jika berubah
            $stmtCheck = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $stmtCheck->execute([$email, $id]);
            if ($stmtCheck->fetch()) {
                $error = 'Alamat email sudah dipakai pengguna lain.';
            } else {
                $avatarPath = $user['profile_picture'];

                // Hapus avatar jika dipilih
                if (isset($_POST['remove_avatar']) && $_POST['remove_avatar'] === '1') {
                    if ($avatarPath && file_exists(__DIR__ . '/../' . $avatarPath)) {
                        @unlink(__DIR__ . '/../' . $avatarPath);
                    }
                    $avatarPath = null;
                }

                // Upload avatar baru
                elseif (
                    isset($_FILES['avatar']) &&
                    $_FILES['avatar']['error'] !== UPLOAD_ERR_NO_FILE
                ) {
                    $file = $_FILES['avatar'];

                    // Periksa error upload
                    if ($file['error'] !== UPLOAD_ERR_OK) {
                        $error = 'Upload foto gagal. Kode error: ' . $file['error'];
                    } else {
                        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                        $allowedExtensions = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

                        $fileMime = mime_content_type($file['tmp_name']);
                        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                        if (
                            in_array($fileMime, $allowedTypes, true) &&
                            in_array($fileExt, $allowedExtensions, true)
                        ) {
                            if ($file['size'] <= 3 * 1024 * 1024) {

                                $newFilename = 'avatar_user_' . time() . '_' .
                                    bin2hex(random_bytes(4)) . '.' . $fileExt;

                                $targetDir = __DIR__ . '/../assets/uploads/avatars/';

                                if (!is_dir($targetDir)) {
                                    mkdir($targetDir, 0775, true);
                                }

                                if (move_uploaded_file($file['tmp_name'], $targetDir . $newFilename)) {

                                    if ($avatarPath && file_exists(__DIR__ . '/../' . $avatarPath)) {
                                        @unlink(__DIR__ . '/../' . $avatarPath);
                                    }

                                    $avatarPath = 'assets/uploads/avatars/' . $newFilename;
                                } else {
                                    $error = 'Gagal memindahkan foto ke folder avatars. Periksa izin folder.';
                                }
                            } else {
                                $error = 'Ukuran foto profil maksimal 3MB.';
                            }
                        } else {
                            $error = 'Format gambar tidak valid.';
                        }
                    }
                }


                if (!$error) {
                    try {
                        if (!empty($newPassword)) {
                            if (strlen($newPassword) < 8) {
                                $error = 'Password baru minimal 8 karakter.';
                            } else {
                                $hashed = password_hash($newPassword, PASSWORD_DEFAULT);
                                $upStmt = $pdo->prepare("
                                    UPDATE users 
                                    SET name = ?, email = ?, password = ?, role = ?, profile_picture = ?, status = ?, updated_at = NOW()
                                    WHERE id = ?
                                ");
                                $upStmt->execute([$name, $email, $hashed, $role, $avatarPath, $status, $id]);
                            }
                        } else {
                            $upStmt = $pdo->prepare("
                                UPDATE users 
                                SET name = ?, email = ?, role = ?, profile_picture = ?, status = ?, updated_at = NOW()
                                WHERE id = ?
                            ");
                            $upStmt->execute([$name, $email, $role, $avatarPath, $status, $id]);
                        }

                        // Jika mengedit akun sendiri, perbarui session
                        if ($id === (int)$_SESSION['user_id']) {
                            $_SESSION['user_name'] = $name;
                            $_SESSION['user_avatar'] = $avatarPath;
                        }

                        flash('success', "Data pengguna " . e($name) . " berhasil diperbarui.");
                        header("Location: user-edit.php?id=$id");
                        exit;
                    } catch (PDOException $e) {
                        error_log($e->getMessage());
                        $error = 'Gagal menyimpan perubahan ke database.';
                    }
                }
            }
        }
    } elseif ($action === 'send_direct_notif') {
        $title = trim($_POST['notif_title'] ?? '');
        $message = trim($_POST['notif_message'] ?? '');
        $type = $_POST['notif_type'] ?? 'general';
        $link = trim($_POST['notif_link'] ?? '');

        if (!$title || !$message) {
            $error = 'Judul dan pesan notifikasi wajib diisi.';
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, scheduled_at, status)
                VALUES (?, ?, ?, ?, ?, NOW(), 'pending')
            ");
            $stmt->execute([$id, $title, $message, $type, $link ?: null]);
            flash('success', "Notifikasi berhasil dikirimkan khusus ke pengguna " . e($user['name']));
            header("Location: user-edit.php?id=$id");
            exit;
        }
    }
}

// Ambil riwayat menstruasi pengguna ini
$stmtRecords = $pdo->prepare("SELECT * FROM menstrual_records WHERE user_id = ? ORDER BY start_date DESC LIMIT 5");
$stmtRecords->execute([$id]);
$userRecords = $stmtRecords->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Sunting Pengguna: ' . $user['name'];
$adminActive = 'users';

require_once __DIR__ . '/includes/header.php';
?>

<!-- BANNER EDUKASI SERASI DENGAN TAMPILAN USER -->
<section class="education-hero mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <span class="education-eyebrow">
                MANAJEMEN PENGGUNA 🌸
            </span>
            <h2>Sunting Profil Pengguna</h2>
            <p>
                Kelola data akun, kredensial, dan informasi siklus untuk <?= e($user['name']) ?> (ID: #<?= (int)$user['id'] ?>).
            </p>
        </div>
        <div class="mt-2">
            <a href="users.php" class="btn btn-admin-soft">
                ← Kembali ke Daftar Pengguna
            </a>
        </div>
    </div>
    <div class="education-hero-icon">🌷</div>
</section>

<?php if ($error): ?>
    <div class="alert alert-danger rounded-4 shadow-sm mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="row g-4">
    <!-- KOLOM KIRI: FORM SUNTING -->
    <div class="col-lg-8">
        <div class="admin-card">
            <h5 class="admin-card-title mb-4">
                <i class="bi bi-person-gear" style="color: #a36f91;"></i> Data Akun & Profil
            </h5>

            <form method="POST" action="user-edit.php?id=<?= $id ?>" enctype="multipart/form-data">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update_user">

                <!-- Foto Profil -->
                <div class="p-3 rounded-4 mb-4" style="background-color: #fdf8fb; border: 1px solid #f0e3f0;">
                    <label class="form-label fw-bold d-block mb-3" style="color: #594765;">
                        <i class="bi bi-image me-1"></i> Foto Profil Pengguna
                    </label>
                    <div class="d-flex align-items-center gap-3 flex-wrap">
                        <div>
                            <?php if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/../' . $user['profile_picture'])): ?>
                                <img src="../<?= e($user['profile_picture']) ?>" alt="Avatar" class="rounded-circle shadow-sm" style="width: 80px; height: 80px; object-fit: cover; border: 3px solid #f3c9d8;">
                            <?php else: ?>
                                <div class="rounded-circle bg-white shadow-sm d-flex align-items-center justify-content-center fs-2" style="width: 80px; height: 80px; border: 2px dashed #e5d9ed;">
                                    🌷
                                </div>
                            <?php endif; ?>
                        </div>
                        <div class="flex-grow-1" style="min-width: 200px;">
                            <label class="form-label small fw-semibold text-muted mb-1">Unggah / Ganti Foto</label>
                            <input type="file" name="avatar" class="form-control form-control-sm" style="border-color: #e5d9ed; border-radius: 8px;" accept="image/png, image/jpeg, image/webp">
                            <small class="text-muted" style="font-size: 0.75rem;">Format JPG, PNG, WEBP. Maks 3MB.</small>
                        </div>
                        <?php if (!empty($user['profile_picture'])): ?>
                            <div>
                                <label class="btn btn-sm btn-outline-danger" style="border-radius: 8px;">
                                    <input type="checkbox" name="remove_avatar" value="1" class="form-check-input me-1"> Hapus Foto
                                </label>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Nama Lengkap & Email -->
                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="color: #594765;">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" value="<?= e($user['name']) ?>" required minlength="3">
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="color: #594765;">Alamat Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" value="<?= e($user['email']) ?>" required>
                    </div>
                </div>

                <!-- Password Baru (Reset Password) -->
                <div class="mb-4 p-3 rounded-4" style="background: #faf7fc; border: 1px solid #f0e3f0;">
                    <label class="form-label fw-bold mb-1" style="color: #594765;">
                        <i class="bi bi-key-fill text-warning me-1"></i> Reset / Ganti Password
                    </label>
                    <p class="small text-muted mb-2">Kosongkan kolom di bawah jika tidak ingin mengganti password pengguna ini.</p>
                    <div class="input-group">
                        <span class="input-group-text bg-white" style="border-color: #e5d9ed; border-radius: 12px 0 0 12px;"><i class="bi bi-lock"></i></span>
                        <input type="password" name="new_password" class="form-control" style="border-color: #e5d9ed; border-radius: 0 12px 12px 0;" placeholder="Masukkan password baru (minimal 8 karakter)" minlength="8" autocomplete="new-password">
                    </div>
                </div>

                <!-- Role & Status -->
                <div class="row g-3 mb-4">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="color: #594765;">Role Akun</label>
                        <select name="role" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;" <?= $user['id'] === (int)$_SESSION['user_id'] ? 'disabled' : '' ?>>
                            <option value="user" <?= $user['role'] === 'user' ? 'selected' : '' ?>>Pengguna Biasa (User)</option>
                            <option value="admin" <?= $user['role'] === 'admin' ? 'selected' : '' ?>>Administrator (Admin)</option>
                        </select>
                        <?php if ($user['id'] === (int)$_SESSION['user_id']): ?>
                            <small class="text-muted">Anda tidak dapat menurunkan role akun Anda sendiri.</small>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="color: #594765;">Status Keaktifan</label>
                        <select name="status" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;" <?= $user['id'] === (int)$_SESSION['user_id'] ? 'disabled' : '' ?>>
                            <option value="active" <?= $user['status'] === 'active' ? 'selected' : '' ?>>Aktif (Bisa Login)</option>
                            <option value="inactive" <?= $user['status'] === 'inactive' ? 'selected' : '' ?>>Nonaktif (Tidak Bisa Login)</option>
                        </select>
                        <?php if ($user['id'] === (int)$_SESSION['user_id']): ?>
                            <small class="text-muted">Anda tidak dapat menonaktifkan akun sendiri.</small>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 pt-3 border-top">
                    <a href="users.php" class="btn btn-light border px-4" style="border-radius: 12px;">Batal</a>
                    <button type="submit" class="btn btn-admin-primary px-4">
                        <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan
                    </button>
                </div>
            </form>
        </div>

        <!-- KIRIM NOTIFIKASI KHUSUS KE PENGGUNA INI -->
        <div class="admin-card">
            <h5 class="admin-card-title mb-2">
                <i class="bi bi-send-fill" style="color: #a36f91;"></i> Kirim Notifikasi Langsung ke Pengguna Ini
            </h5>
            <p class="small text-muted mb-4">Kirimkan pesan pengingat atau informasi khusus yang akan langsung muncul di halaman notifikasi pengguna.</p>

            <form method="POST" action="user-edit.php?id=<?= $id ?>">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="send_direct_notif">

                <div class="row g-3 mb-3">
                    <div class="col-md-8">
                        <label class="form-label fw-semibold" style="color: #594765;">Judul Notifikasi</label>
                        <input type="text" name="notif_title" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" required placeholder="Contoh: 🌸 Pengingat Siklus Haid">
                    </div>
                    <div class="col-md-4">
                        <label class="form-label fw-semibold" style="color: #594765;">Tipe Notifikasi</label>
                        <select name="notif_type" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;">
                            <option value="period_reminder">🌸 Pengingat Haid</option>
                            <option value="new_article">📖 Info Edukasi</option>
                            <option value="general" selected>💬 Pengumuman Umum</option>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="color: #594765;">Isi Pesan Notifikasi</label>
                    <textarea name="notif_message" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" rows="3" required placeholder="Tuliskan pesan yang ramah dan jelas..."></textarea>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="color: #594765;">Tautan Tindakan (Opsional)</label>
                    <input type="text" name="notif_link" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" placeholder="Contoh: http://nisaya.rgi.id/nisaya/education/index.php">
                </div>

                <button type="submit" class="btn btn-admin-primary btn-sm px-3">
                    <i class="bi bi-send me-1"></i> Kirim Notifikasi ke <?= e($user['name']) ?>
                </button>
            </form>
        </div>
    </div>

    <!-- KOLOM KANAN: RINGKASAN SIKLUS & CATATAN -->
    <div class="col-lg-4">
        <!-- Info Akun Card -->
        <div class="admin-card mb-4 text-center">
            <div class="mb-3">
                <?php if (!empty($user['profile_picture']) && file_exists(__DIR__ . '/../' . $user['profile_picture'])): ?>
                    <img src="../<?= e($user['profile_picture']) ?>" alt="Avatar" class="rounded-circle shadow-sm" style="width: 90px; height: 90px; object-fit: cover; border: 3px solid #f3c9d8;">
                <?php else: ?>
                    <div class="avatar-placeholder mx-auto" style="width: 90px; height: 90px; font-size: 38px; border-radius: 50%; background: #fce6ef; display: flex; align-items: center; justify-content: center;">🌷</div>
                <?php endif; ?>
            </div>

            <h5 class="fw-bold mb-1" style="color: #594765;"><?= e($user['name']) ?></h5>
            <p class="text-muted small mb-2"><?= e($user['email']) ?></p>

            <div class="d-flex justify-content-center gap-2 mb-3">
                <span class="badge <?= $user['role'] === 'admin' ? 'badge-role-admin' : 'badge-role-user' ?>">
                    <?= $user['role'] === 'admin' ? 'Admin 🛡️' : 'User 🌸' ?>
                </span>
                <span class="badge <?= $user['status'] === 'active' ? 'badge-status-active' : 'badge-status-inactive' ?>">
                    <?= $user['status'] === 'active' ? 'Aktif' : 'Nonaktif' ?>
                </span>
            </div>

            <div class="text-start border-top pt-3 small text-muted">
                <div class="d-flex justify-content-between py-1">
                    <span>ID Pengguna:</span>
                    <strong>#<?= (int)$user['id'] ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span>Terdaftar Sejak:</span>
                    <strong><?= date('d M Y, H:i', strtotime($user['created_at'])) ?></strong>
                </div>
                <div class="d-flex justify-content-between py-1">
                    <span>Terakhir Diperbarui:</span>
                    <strong><?= $user['updated_at'] ? date('d M Y, H:i', strtotime($user['updated_at'])) : '—' ?></strong>
                </div>
            </div>
        </div>

        <!-- Profil Siklus Card -->
        <div class="admin-card mb-4">
            <h6 class="fw-bold mb-3" style="color: #a36f91;">
                <i class="bi bi-calendar2-heart me-1"></i> Data Siklus Menstruasi
            </h6>

            <div class="small">
                <div class="mb-2 p-2 rounded-3" style="background: #faf7fc;">
                    <span class="text-muted d-block">Haid Terakhir Dimulai:</span>
                    <strong style="color: #594765;">
                        <?= !empty($user['last_period_start']) ? date('d F Y', strtotime($user['last_period_start'])) : 'Belum pernah diisi' ?>
                    </strong>
                </div>

                <div class="row g-2 mb-2">
                    <div class="col-6">
                        <div class="p-2 rounded-3" style="background: #faf7fc;">
                            <span class="text-muted d-block">Rata-rata Haid:</span>
                            <strong><?= $user['average_period_days'] ? $user['average_period_days'] . ' hari' : '—' ?></strong>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="p-2 rounded-3" style="background: #faf7fc;">
                            <span class="text-muted d-block">Rata-rata Suci:</span>
                            <strong><?= $user['average_purity_days'] ? $user['average_purity_days'] . ' hari' : '—' ?></strong>
                        </div>
                    </div>
                </div>

                <div class="p-2 rounded-3 mb-2" style="background: #faf7fc;">
                    <span class="text-muted d-block">Keteraturan Siklus:</span>
                    <strong class="text-capitalize"><?= e($user['cycle_regular'] ?? 'unknown') ?></strong>
                </div>
            </div>
        </div>

        <!-- Riwayat Catatan Menstruasi Terbaru -->
        <div class="admin-card">
            <h6 class="fw-bold mb-3" style="color: #594765;">
                <i class="bi bi-clock-history me-1"></i> Catatan Haid Terbaru
            </h6>

            <?php if (empty($userRecords)): ?>
                <p class="small text-muted mb-0">Pengguna belum memiliki catatan menstruasi.</p>
            <?php else: ?>
                <div class="list-group list-group-flush small">
                    <?php foreach ($userRecords as $rec): ?>
                        <div class="list-group-item px-0 py-2 border-bottom bg-transparent">
                            <div class="d-flex justify-content-between">
                                <strong style="color: #594765;"><?= date('d M Y', strtotime($rec['start_date'])) ?></strong>
                                <span class="badge bg-light text-muted border"><?= e($rec['status']) ?></span>
                            </div>
                            <div class="text-muted" style="font-size: 0.78rem;">
                                <?= !empty($rec['end_date']) ? 's.d. ' . date('d M Y', strtotime($rec['end_date'])) : 'Sedang berlangsung' ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>