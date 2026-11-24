<?php
require_once __DIR__ . '/../config/app.php';
require_admin();

$error = '';
$success = '';

// Handle Tambah Pengguna Baru
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create_user') {
    verify_csrf();

    $name = trim($_POST['name'] ?? '');
    $email = filter_var(trim($_POST['email'] ?? ''), FILTER_VALIDATE_EMAIL);
    $password = $_POST['password'] ?? '';
    $role = in_array($_POST['role'] ?? '', ['user', 'admin'], true) ? $_POST['role'] : 'user';
    $status = in_array($_POST['status'] ?? '', ['active', 'inactive'], true) ? $_POST['status'] : 'active';

    if (strlen($name) < 3) {
        $error = 'Nama lengkap minimal 3 karakter.';
    } elseif (!$email) {
        $error = 'Format email tidak valid.';
    } elseif (strlen($password) < 8) {
        $error = 'Password minimal 8 karakter.';
    } else {
        // Cek apakah email sudah terdaftar
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $error = 'Email sudah digunakan oleh akun lain.';
        } else {
            // Upload foto profil jika ada
            $avatarPath = null;
            if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                $file = $_FILES['avatar'];
                $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
                $fileMime = mime_content_type($file['tmp_name']);
                $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

                if (in_array($fileMime, $allowedTypes, true) && in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                    if ($file['size'] <= 3 * 1024 * 1024) {
                        $newFilename = 'avatar_user_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                        $targetDir = __DIR__ . '/../assets/uploads/avatars/';
                        if (!is_dir($targetDir)) {
                            mkdir($targetDir, 0775, true);
                        }
                        if (move_uploaded_file($file['tmp_name'], $targetDir . $newFilename)) {
                            $avatarPath = 'assets/uploads/avatars/' . $newFilename;
                        }
                    } else {
                        $error = 'Ukuran foto profil maksimal 3MB.';
                    }
                } else {
                    $error = 'Format foto tidak valid (gunakan JPG, PNG, WEBP).';
                }
            }

            if (!$error) {
                try {
                    $pdo->beginTransaction();

                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    $ins = $pdo->prepare("
                        INSERT INTO users (name, email, password, role, profile_picture, status, created_at, updated_at)
                        VALUES (?, ?, ?, ?, ?, ?, NOW(), NOW())
                    ");
                    $ins->execute([$name, $email, $hashedPassword, $role, $avatarPath, $status]);
                    $newUserId = $pdo->lastInsertId();

                    // Buat cycle profile default jika role user
                    $initProf = $pdo->prepare("
                        INSERT INTO cycle_profiles (user_id, average_period_days, average_purity_days, cycle_regular, profile_completed)
                        VALUES (?, 7, 21, 1, 0)
                    ");
                    $initProf->execute([$newUserId]);

                    $pdo->commit();
                    flash('success', "Pengguna {$name} ({$email}) berhasil didaftarkan.");
                    header('Location: users.php');
                    exit;
                } catch (PDOException $e) {
                    if ($pdo->inTransaction()) $pdo->rollBack();
                    error_log($e->getMessage());
                    $error = 'Gagal menyimpan pengguna baru.';
                }
            }
        }
    }
}

// Handle Ubah Status Cepat (Aktifkan / Nonaktifkan)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    verify_csrf();
    $targetId = (int)($_POST['user_id'] ?? 0);
    $currentStatus = $_POST['current_status'] ?? 'active';
    $newStatus = $currentStatus === 'active' ? 'inactive' : 'active';

    if ($targetId === (int)$_SESSION['user_id']) {
        flash('error', 'Anda tidak dapat menonaktifkan akun sendiri.');
    } else {
        $stmt = $pdo->prepare("UPDATE users SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$newStatus, $targetId]);
        flash('success', "Status pengguna berhasil diubah menjadi: " . ($newStatus === 'active' ? 'Aktif' : 'Nonaktif'));
    }
    header('Location: users.php');
    exit;
}

// Handle Hapus Pengguna
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_user') {
    verify_csrf();
    $targetId = (int)($_POST['user_id'] ?? 0);

    if ($targetId === (int)$_SESSION['user_id']) {
        flash('error', 'Anda tidak dapat menghapus akun Anda sendiri.');
    } else {
        // Ambil info foto profil untuk dihapus dari disk
        $stmt = $pdo->prepare("SELECT profile_picture, name FROM users WHERE id = ?");
        $stmt->execute([$targetId]);
        $targetUser = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($targetUser) {
            if (!empty($targetUser['profile_picture']) && file_exists(__DIR__ . '/../' . $targetUser['profile_picture'])) {
                @unlink(__DIR__ . '/../' . $targetUser['profile_picture']);
            }
            $del = $pdo->prepare("DELETE FROM users WHERE id = ?");
            $del->execute([$targetId]);
            flash('success', "Pengguna " . e($targetUser['name']) . " berhasil dihapus.");
        }
    }
    header('Location: users.php');
    exit;
}

// Filter dan Pencarian
$search = trim($_GET['search'] ?? '');
$roleFilter = trim($_GET['role'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT u.*, 
        c.last_period_start, c.average_period_days, c.average_purity_days, c.cycle_regular, c.profile_completed
        FROM users u
        LEFT JOIN cycle_profiles c ON c.user_id = u.id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (u.name LIKE ? OR u.email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($roleFilter !== '') {
    $sql .= " AND u.role = ?";
    $params[] = $roleFilter;
}

if ($statusFilter !== '') {
    $sql .= " AND u.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY u.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Kelola Pengguna & Anggota';
$adminActive = 'users';

require_once __DIR__ . '/includes/header.php';
?>

<!-- BANNER PENGGUNA SERASI DENGAN TAMPILAN USER -->
<section class="education-hero mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <span class="education-eyebrow">
                MANAJEMEN PENGGUNA 🌸
            </span>

            <h2>Kelola Pengguna & Anggota</h2>

            <p>
                Kelola akun, foto profil, nama lengkap, status keaktifan, dan kredensial seluruh pengguna NISAYA.
            </p>

            <div class="education-total">
                <span>👥</span>
                <?= count($users) ?> akun terdaftar
            </div>
        </div>

        <div class="mt-2">
            <button type="button" class="btn btn-admin-primary shadow-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus-fill me-1"></i> Tambah Pengguna Baru
            </button>
        </div>
    </div>

    <div class="education-hero-icon">👥</div>
</section>

<?php if ($error): ?>
    <div class="alert alert-danger rounded-4 shadow-sm mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<!-- FILTER & SEARCH CARD -->
<div class="admin-card mb-4 p-3">
    <form method="GET" action="users.php" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted" style="border-color: #e5d9ed; border-radius: 12px 0 0 12px;"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0" style="border-color: #e5d9ed; border-radius: 0 12px 12px 0;" placeholder="Cari nama lengkap atau email..." value="<?= e($search) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="role" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;">
                <option value="">Semua Role</option>
                <option value="user" <?= $roleFilter === 'user' ? 'selected' : '' ?>>Pengguna (User)</option>
                <option value="admin" <?= $roleFilter === 'admin' ? 'selected' : '' ?>>Administrator</option>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;">
                <option value="">Semua Status</option>
                <option value="active" <?= $statusFilter === 'active' ? 'selected' : '' ?>>Aktif</option>
                <option value="inactive" <?= $statusFilter === 'inactive' ? 'selected' : '' ?>>Nonaktif</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-admin-primary w-100 justify-content-center">
                Filter
            </button>
            <?php if ($search !== '' || $roleFilter !== '' || $statusFilter !== ''): ?>
                <a href="users.php" class="btn btn-light border" style="border-radius: 12px;" title="Reset Filter">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- DAFTAR PENGGUNA -->
<div class="admin-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="fw-bold" style="color: #493347;">
            Total: <?= count($users) ?> Pengguna ditemukan
        </div>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Foto & Nama Lengkap</th>
                    <th>Email</th>
                    <th>Role</th>
                    <th>Status</th>
                    <th>Siklus Terakhir</th>
                    <th>Tgl Bergabung</th>
                    <th class="text-end">Aksi & Pengelolaan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($users)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <span class="fs-1 d-block mb-2">🌸</span>
                            Tidak ada pengguna yang cocok dengan kriteria pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($users as $u): ?>
                        <tr>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if (!empty($u['profile_picture']) && file_exists(__DIR__ . '/../' . $u['profile_picture'])): ?>
                                        <img src="../<?= e($u['profile_picture']) ?>" alt="Avatar" class="avatar-sm shadow-sm">
                                    <?php else: ?>
                                        <div class="avatar-placeholder-sm">🌷</div>
                                    <?php endif; ?>
                                    <div>
                                        <a href="user-edit.php?id=<?= (int)$u['id'] ?>" class="fw-bold text-decoration-none" style="color: #594765;">
                                            <?= e($u['name']) ?>
                                        </a>
                                        <small class="text-muted d-block">ID: #<?= (int)$u['id'] ?></small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="text-muted"><?= e($u['email']) ?></span>
                            </td>
                            <td>
                                <span class="badge <?= $u['role'] === 'admin' ? 'badge-role-admin' : 'badge-role-user' ?>">
                                    <?= $u['role'] === 'admin' ? '🛡️ Admin' : '🌸 Anggota' ?>
                                </span>
                            </td>
                            <td>
                                <span class="badge <?= $u['status'] === 'active' ? 'badge-status-active' : 'badge-status-inactive' ?>">
                                    <?= $u['status'] === 'active' ? '● Aktif' : '● Nonaktif' ?>
                                </span>
                            </td>
                            <td>
                                <?php if (!empty($u['last_period_start'])): ?>
                                    <span class="small fw-semibold" style="color: #a36f91;">
                                        📅 <?= date('d M Y', strtotime($u['last_period_start'])) ?>
                                    </span>
                                <?php else: ?>
                                    <span class="small text-muted">Belum ada</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><?= date('d M Y', strtotime($u['created_at'])) ?></small>
                            </td>

                            <td class="text-end text-nowrap">
                                <div class="action-btn-group justify-content-end">

                                    <a href="user-edit.php?id=<?= (int)$u['id'] ?>"
                                        class="btn-action-edit"
                                        title="Sunting data pengguna">
                                        <i class="bi bi-pencil-square"></i>
                                        <span>Sunting</span>
                                    </a>

                                    <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>

                                        <!-- Tombol Matikan/Aktifkan -->
                                        <button type="button"
                                            class="btn-action-push border-0"
                                            data-bs-toggle="modal"
                                            data-bs-target="#toggleUserModal<?= (int)$u['id'] ?>">
                                            <i class="bi bi-power"></i>
                                            <span><?= $u['status'] === 'active' ? 'Matikan' : 'Aktifkan' ?></span>
                                        </button>

                                        <!-- Tombol Hapus -->
                                        <button type="button"
                                            class="btn-action-delete border-0"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteUserModal<?= (int)$u['id'] ?>">
                                            <i class="bi bi-trash3-fill"></i>
                                            <span>Hapus</span>
                                        </button>

                                    <?php endif; ?>

                                </div>
                            </td>

                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL KONFIRMASI SETIAP PENGGUNA -->
<?php foreach ($users as $u): ?>
    <?php if ((int)$u['id'] !== (int)$_SESSION['user_id']): ?>

        <!-- MODAL MATIKAN / AKTIFKAN -->
        <div class="modal fade"
            id="toggleUserModal<?= (int)$u['id'] ?>"
            tabindex="-1"
            aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow"
                    style="border-radius:20px;">

                    <div class="modal-body text-center p-4">

                        <div class="logout-icon mb-3">
                            <i class="bi bi-exclamation-circle"></i>
                        </div>

                        <h5 class="fw-bold">Konfirmasi Perubahan Status</h5>

                        <p class="text-muted">
                            Apakah Anda yakin ingin
                            <?= $u['status'] === 'active'
                                ? 'menonaktifkan'
                                : 'mengaktifkan kembali' ?>
                            akun <?= e($u['name']) ?>?
                        </p>

                        <form method="POST" action="users.php">

                            <input type="hidden" name="csrf"
                                value="<?= e(csrf_token()) ?>">

                            <input type="hidden" name="action"
                                value="toggle_status">

                            <input type="hidden" name="user_id"
                                value="<?= (int)$u['id'] ?>">

                            <input type="hidden" name="current_status"
                                value="<?= e($u['status']) ?>">

                            <div class="d-flex justify-content-center gap-2 mt-4">

                                <button type="button"
                                    class="btn btn-secondary px-4"
                                    data-bs-dismiss="modal">
                                    Batal
                                </button>

                                <button type="submit"
                                    class="btn btn-danger px-4">
                                    Ya, <?= $u['status'] === 'active'
                                            ? 'Matikan'
                                            : 'Aktifkan' ?>
                                </button>

                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>


        <!-- MODAL HAPUS PENGGUNA -->
        <div class="modal fade"
            id="deleteUserModal<?= (int)$u['id'] ?>"
            tabindex="-1"
            aria-hidden="true">

            <div class="modal-dialog modal-dialog-centered">
                <div class="modal-content border-0 shadow"
                    style="border-radius:20px;">

                    <div class="modal-body text-center p-4">

                        <div class="logout-icon mb-3">
                            <i class="bi bi-exclamation-circle"></i>
                        </div>

                        <h5 class="fw-bold">Konfirmasi Hapus Pengguna</h5>

                        <p class="text-muted">
                            Apakah Anda yakin ingin menghapus
                            pengguna <strong><?= e($u['name']) ?></strong>?
                            Tindakan ini tidak dapat dibatalkan.
                        </p>

                        <form method="POST" action="users.php">

                            <input type="hidden" name="csrf"
                                value="<?= e(csrf_token()) ?>">

                            <input type="hidden" name="action"
                                value="delete_user">

                            <input type="hidden" name="user_id"
                                value="<?= (int)$u['id'] ?>">

                            <div class="d-flex justify-content-center gap-2 mt-4">

                                <button type="button"
                                    class="btn btn-secondary px-4"
                                    data-bs-dismiss="modal">
                                    Batal
                                </button>

                                <button type="submit"
                                    class="btn btn-danger px-4">
                                    Ya, Hapus
                                </button>

                            </div>
                        </form>

                    </div>
                </div>
            </div>
        </div>

    <?php endif; ?>
<?php endforeach; ?>
<!-- AKHIR MODAL KONFIRMASI -->


<!-- MODAL TAMBAH PENGGUNA BARU -->
<div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow-lg" style="border-radius: 22px;">
            <div class="modal-header border-bottom-0 pb-0">
                <h5 class="modal-title fw-bold" id="addUserModalLabel" style="color: #594765;">
                    <i class="bi bi-person-plus-fill me-2" style="color: #a36f91;"></i> Tambah Pengguna Baru
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form method="POST" action="users.php" enctype="multipart/form-data">
                <div class="modal-body p-4">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="create_user">

                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="color: #594765;">Nama Lengkap <span class="text-danger">*</span></label>
                        <input type="text" name="name" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" required placeholder="Contoh: Siti Rahmawati">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="color: #594765;">Alamat Email <span class="text-danger">*</span></label>
                        <input type="email" name="email" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" required placeholder="nama@email.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label fw-semibold" style="color: #594765;">Password <span class="text-danger">*</span></label>
                        <input type="password" name="password" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" required minlength="8" placeholder="Minimal 8 karakter">
                    </div>

                    <div class="row g-2 mb-3">
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="color: #594765;">Role</label>
                            <select name="role" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;">
                                <option value="user" selected>Pengguna (User)</option>
                                <option value="admin">Administrator</option>
                            </select>
                        </div>
                        <div class="col-6">
                            <label class="form-label fw-semibold" style="color: #594765;">Status</label>
                            <select name="status" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;">
                                <option value="active" selected>Aktif</option>
                                <option value="inactive">Nonaktif</option>
                            </select>
                        </div>
                    </div>

                    <div class="mb-2">
                        <label class="form-label fw-semibold" style="color: #594765;">Foto Profil (Opsional)</label>
                        <input type="file" name="avatar" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" accept="image/png, image/jpeg, image/webp">
                        <small class="text-muted">Maksimal 3MB (JPG, PNG, WEBP)</small>
                    </div>
                </div>
                <div class="modal-footer border-top-0 pt-0">
                    <button type="button" class="btn btn-light" style="border-radius: 12px;" data-bs-dismiss="modal">Batal</button>
                    <button type="submit" class="btn btn-admin-primary px-4">Simpan Pengguna</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>