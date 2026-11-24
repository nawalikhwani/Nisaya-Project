<?php
require_once __DIR__ . '/../config/app.php';
require_admin();

// Hitung Statistik
$statUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$statActiveUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$statInactiveUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status != 'active'")->fetchColumn();

$statArticles = $pdo->query("SELECT COUNT(*) FROM education_articles")->fetchColumn();
$statPublishedArticles = $pdo->query("SELECT COUNT(*) FROM education_articles WHERE status = 'published'")->fetchColumn();

$statNotifications = $pdo->query("SELECT COUNT(*) FROM notifications")->fetchColumn();
$statPeriodReminders = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'period_reminder'")->fetchColumn();
$statArticleNotifs = $pdo->query("SELECT COUNT(*) FROM notifications WHERE type = 'new_article'")->fetchColumn();

// Ambil pengguna terbaru
$recentUsers = $pdo->query("
    SELECT id, name, email, role, profile_picture, status, created_at 
    FROM users 
    ORDER BY id DESC 
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

// Ambil materi edukasi terbaru
$recentArticles = $pdo->query("
    SELECT id, title, category, icon, status, created_at, updated_at 
    FROM education_articles 
    ORDER BY id DESC 
    LIMIT 5
")->fetchAll(PDO::FETCH_ASSOC);

// Ambil riwayat push notifikasi terbaru
$recentNotifications = $pdo->query("
    SELECT n.*, u.name as user_name 
    FROM notifications n
    LEFT JOIN users u ON u.id = n.user_id
    ORDER BY n.id DESC 
    LIMIT 6
")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Dashboard Admin';
$adminActive = 'dashboard';

require_once __DIR__ . '/includes/header.php';
?>

<!-- BANNER ADMIN SERASI DENGAN DASHBOARD USER -->
<section class="dashboard-banner mb-4">
    <div class="banner-content">
        <span class="banner-label">ADMINISTRATOR CONTROL PANEL 🌸</span>
        <h1>Selamat Datang, <?= e($currentAdmin['name'] ?? 'Admin') ?>!</h1>
        <p>
            Kelola data akun anggota, pembaruan konten edukasi seputar haid & fikih, serta siarkan push notifikasi pengingat siklus haid secara real-time.
        </p>
        <div class="d-flex flex-wrap gap-2 mt-3">
            <a href="article-create.php" class="btn btn-admin-primary">
                <i class="bi bi-journal-plus me-1"></i> Tulis Materi Baru
            </a>
            <a href="notifications.php" class="btn btn-admin-soft">
                <i class="bi bi-bell-fill me-1"></i> Push Notifikasi
            </a>
            <a href="users.php" class="btn btn-admin-soft">
                <i class="bi bi-people-fill me-1"></i> Kelola Pengguna
            </a>
        </div>
    </div>
    <div class="banner-decoration">🌸</div>
    <div class="banner-flower">✿</div>
</section>

<!-- STATS CARDS MENGGUNAKAN GAYA DASHBOARD USER -->
<div class="row g-3 mb-4">
    <!-- Total Pengguna -->
    <div class="col-sm-6 col-xl-3">
        <div class="dashboard-stat stat-pink">
            <div class="stat-icon">👥</div>
            <div class="stat-label">TOTAL ANGGOTA / USER</div>
            <div class="stat-value"><?= number_format($statUsers) ?></div>
            <div class="stat-note d-flex align-items-center gap-2 mt-1">
                <span class="text-success fw-bold">✓ <?= $statActiveUsers ?> Aktif</span>
                <?php if ($statInactiveUsers > 0): ?>
                    <span class="text-danger fw-bold">• <?= $statInactiveUsers ?> Nonaktif</span>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Total Artikel Edukasi -->
    <div class="col-sm-6 col-xl-3">
        <div class="dashboard-stat stat-purple">
            <div class="stat-icon">📖</div>
            <div class="stat-label">MATERI EDUKASI</div>
            <div class="stat-value"><?= number_format($statArticles) ?></div>
            <div class="stat-note mt-1">
                <span class="fw-bold" style="color: #76618e;">✓ <?= $statPublishedArticles ?> Terbit</span>
                <span>• Siap dibaca user</span>
            </div>
        </div>
    </div>

    <!-- Push Notifikasi Terkirim -->
    <div class="col-sm-6 col-xl-3">
        <div class="dashboard-stat stat-peach">
            <div class="stat-icon">🔔</div>
            <div class="stat-label">NOTIFIKASI TERKIRIM</div>
            <div class="stat-value"><?= number_format($statNotifications) ?></div>
            <div class="stat-note mt-1">
                <span>🌸 <?= $statPeriodReminders ?> Haid</span>
                <span>•</span>
                <span>📖 <?= $statArticleNotifs ?> Artikel</span>
            </div>
        </div>
    </div>

    <!-- Aksi Cepat Pengingat Siklus -->
    <div class="col-sm-6 col-xl-3">
        <div class="dashboard-stat stat-lavender d-flex flex-column justify-content-between">
            <div>
                <div class="stat-icon">⚡</div>
                <div class="stat-label">PENGINGAT SIKLUS</div>
                <div class="fw-bold mt-1" style="font-size: 1.05rem; color: #594765;">Deteksi Datangnya Haid</div>
                <div class="stat-note mt-1">Pindai perkiraan haid anggota ≤ 7 hari.</div>
            </div>
            <div class="mt-2">
                <a href="notifications.php" class="btn btn-admin-soft w-100 justify-content-center py-1">
                    Buka Pengingat Haid →
                </a>
            </div>
        </div>
    </div>
</div>

<!-- 2-KOLOM: PENGGUNA TERBARU & ARTIKEL TERBARU -->
<div class="row g-4 mb-4">
    <!-- Tabel Pengguna Terbaru -->
    <div class="col-xl-7">
        <div class="admin-card h-100 mb-0">
            <div class="admin-card-header">
                <div>
                    <h5 class="admin-card-title">
                        <i class="bi bi-people" style="color: #a36f91;"></i> Anggota & Pengguna Terbaru
                    </h5>
                    <small class="text-muted">Daftar pengguna yang terdaftar di NISAYA</small>
                </div>
                <div class="d-flex gap-2">
                    <a href="users.php" class="btn btn-admin-soft">
                        Lihat Semua User →
                    </a>
                </div>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Profil</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Status</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recentUsers as $u): ?>
                            <tr>
                                <td>
                                    <div class="d-flex align-items-center gap-3">
                                        <?php if (!empty($u['profile_picture']) && file_exists(__DIR__ . '/../' . $u['profile_picture'])): ?>
                                            <img src="../<?= e($u['profile_picture']) ?>" alt="Avatar" class="avatar-sm">
                                        <?php else: ?>
                                            <div class="avatar-placeholder-sm">🌷</div>
                                        <?php endif; ?>
                                        <div>
                                            <strong class="d-block" style="color: #594765;"><?= e($u['name']) ?></strong>
                                            <small class="text-muted">ID: #<?= (int)$u['id'] ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td><small class="text-muted"><?= e($u['email']) ?></small></td>
                                <td>
                                    <span class="badge <?= $u['role'] === 'admin' ? 'badge-role-admin' : 'badge-role-user' ?>">
                                        <?= $u['role'] === 'admin' ? 'Admin 🛡️' : 'User 🌸' ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="badge <?= $u['status'] === 'active' ? 'badge-status-active' : 'badge-status-inactive' ?>">
                                        <?= $u['status'] === 'active' ? '● Aktif' : '● Nonaktif' ?>
                                    </span>
                                </td>
                                <td class="text-end">
                                    <a href="user-edit.php?id=<?= (int)$u['id'] ?>" class="btn-action-edit py-1 px-2" title="Kelola Anggota">
                                        <i class="bi bi-pencil-square"></i> Sunting
                                    </a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- Tabel Materi Edukasi Terbaru -->
    <div class="col-xl-5">
        <div class="admin-card h-100 mb-0">
            <div class="admin-card-header">
                <div>
                    <h5 class="admin-card-title">
                        <i class="bi bi-journal-richtext" style="color: #a36f91;"></i> Materi Edukasi
                    </h5>
                    <small class="text-muted">Konten edukasi yang tersedia untuk pengguna</small>
                </div>
                <a href="article-create.php" class="btn btn-sm btn-admin-primary">
                    <i class="bi bi-plus-lg"></i> Tambah
                </a>
            </div>

            <div class="list-group list-group-flush">
                <?php foreach ($recentArticles as $art): ?>
                    <div class="list-group-item px-0 py-3 border-bottom d-flex justify-content-between align-items-center bg-transparent">
                        <div class="d-flex align-items-center gap-3">
                            <div class="fs-3"><?= !empty($art['icon']) ? e($art['icon']) : '🌸' ?></div>
                            <div>
                                <h6 class="mb-1 fw-bold" style="color: #594765;">
                                    <a href="article-edit.php?id=<?= (int)$art['id'] ?>" class="text-decoration-none" style="color: inherit;">
                                        <?= e($art['title']) ?>
                                    </a>
                                </h6>
                                <div class="d-flex align-items-center gap-2 small text-muted">
                                    <span class="badge" style="background: #faf4fb; color: #a36f91; border: 1px solid #f2e3ed;"><?= e($art['category']) ?></span>
                                    <span>•</span>
                                    <span><?= date('d M Y', strtotime($art['created_at'])) ?></span>
                                </div>
                            </div>
                        </div>
                        <div class="d-flex align-items-center gap-1">
                            <a href="notifications.php?article_id=<?= (int)$art['id'] ?>" class="btn-action-push py-1 px-2" title="Push Notifikasi Materi Ini">
                                <i class="bi bi-bell-fill"></i>
                            </a>
                            <a href="article-edit.php?id=<?= (int)$art['id'] ?>" class="btn-action-edit py-1 px-2" title="Edit Artikel">
                                <i class="bi bi-pencil-square"></i>
                            </a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="mt-3 text-center">
                <a href="articles.php" class="text-decoration-none fw-semibold" style="color: #a36f91;">
                    Lihat Semua Artikel Edukasi (<?= $statArticles ?>) →
                </a>
            </div>
        </div>
    </div>
</div>

<!-- RIWAYAT PUSH NOTIFIKASI TERAKHIR -->
<div class="admin-card">
    <div class="admin-card-header">
        <div>
            <h5 class="admin-card-title">
                <i class="bi bi-clock-history" style="color: #a36f91;"></i> Riwayat Push Notifikasi Terkini
            </h5>
            <small class="text-muted">Notifikasi pengingat siklus haid, artikel baru, dan pengumuman yang dikirim ke pengguna</small>
        </div>
        <a href="notifications.php" class="btn btn-admin-soft">
            Buka Notification Center →
        </a>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Tipe</th>
                    <th>Penerima</th>
                    <th>Judul Notifikasi</th>
                    <th>Pesan</th>
                    <th>Waktu Jadwal/Kirim</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($recentNotifications)): ?>
                    <tr>
                        <td colspan="6" class="text-center py-4 text-muted">
                            <span class="fs-1 d-block mb-2">🌸</span>
                            Belum ada riwayat notifikasi.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($recentNotifications as $notif): ?>
                        <tr>
                            <td>
                                <?php if (($notif['type'] ?? '') === 'period_reminder'): ?>
                                    <span class="badge" style="background: #fce6ef; color: #a34f7b; border: 1px solid #f6cfdc; border-radius: 20px;">🌸 Haid</span>
                                <?php elseif (($notif['type'] ?? '') === 'new_article'): ?>
                                    <span class="badge" style="background: #f0e8fa; color: #76618e; border: 1px solid #dfd4f5; border-radius: 20px;">📖 Artikel</span>
                                <?php else: ?>
                                    <span class="badge" style="background: #eee9fb; color: #594765; border: 1px solid #e3dbf7; border-radius: 20px;">💬 Umum</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <strong style="color: #594765;"><?= e($notif['user_name'] ?? 'User #' . $notif['user_id']) ?></strong>
                            </td>
                            <td class="fw-semibold" style="color: #594765; max-width: 200px;">
                                <div class="text-truncate"><?= e($notif['title']) ?></div>
                            </td>
                            <td style="max-width: 320px;">
                                <div class="text-truncate text-muted"><?= e($notif['message']) ?></div>
                            </td>
                            <td>
                                <small class="text-muted"><?= date('d M Y, H:i', strtotime($notif['scheduled_at'])) ?></small>
                            </td>
                            <td>
                                <?php if ((int)$notif['is_read'] === 1): ?>
                                    <span class="badge bg-light text-muted border">✓ Dibaca</span>
                                <?php else: ?>
                                    <span class="badge-status-active">Terkirim</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<?php
require_once __DIR__ . '/includes/footer.php';
?>