<?php
require_once __DIR__ . '/../config/app.php';
require_admin();

$today = new DateTimeImmutable('today');
$error = '';

// Ambil semua artikel edukasi untuk dropdown pilihan
$articlesList = $pdo->query("SELECT id, title, category, icon FROM education_articles WHERE status = 'published' ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// Ambil semua pengguna aktif
$activeUsers = $pdo->query("SELECT id, name, email FROM users WHERE status = 'active' ORDER BY name ASC")->fetchAll(PDO::FETCH_ASSOC);

// ========================================================
// LOGIKA SMART CYCLE SCAN (PENGINGAT DATANGNYA HAID)
// ========================================================
$upcomingPeriodUsers = [];
$scanStmt = $pdo->query("
    SELECT u.id, u.name, u.email, u.profile_picture,
           c.last_period_start as profile_last_start,
           c.average_period_days, c.average_purity_days,
           (SELECT start_date FROM menstrual_records WHERE user_id = u.id ORDER BY start_date DESC LIMIT 1) as record_last_start
    FROM users u
    LEFT JOIN cycle_profiles c ON c.user_id = u.id
    WHERE u.status = 'active'
");
$allUsersCycle = $scanStmt->fetchAll(PDO::FETCH_ASSOC);

foreach ($allUsersCycle as $row) {
    $lastStart = !empty($row['record_last_start']) ? $row['record_last_start'] : $row['profile_last_start'];
    if (!$lastStart) continue;

    try {
        $lastStartDate = new DateTimeImmutable($lastStart);
        $periodDays = !empty($row['average_period_days']) ? (int)$row['average_period_days'] : 7;
        $purityDays = !empty($row['average_purity_days']) ? (int)$row['average_purity_days'] : 21;
        $cycleLength = $periodDays + $purityDays;
        if ($cycleLength < 20 || $cycleLength > 45) {
            $cycleLength = 28; // Default standard cycle
        }

        // Hitung estimasi haid berikutnya
        $nextPeriodDate = $lastStartDate->modify("+{$cycleLength} days");

        // Jika estimasi sudah lewat di masa lalu, proyeksikan ke depan
        while ($nextPeriodDate < $today) {
            $nextPeriodDate = $nextPeriodDate->modify("+{$cycleLength} days");
        }

        $diffDays = (int)$today->diff($nextPeriodDate)->format('%r%a');

        // Target: Jika haid diperkirakan tiba dalam 0 s.d 7 hari ke depan
        if ($diffDays >= 0 && $diffDays <= 7) {
            // Cek apakah sudah pernah dikirimkan notif period_reminder dalam 5 hari terakhir
            $chkStmt = $pdo->prepare("
                SELECT COUNT(*) FROM notifications 
                WHERE user_id = ? AND type = 'period_reminder' 
                  AND scheduled_at >= DATE_SUB(NOW(), INTERVAL 5 DAY)
            ");
            $chkStmt->execute([$row['id']]);
            $alreadyNotified = (int)$chkStmt->fetchColumn() > 0;

            $upcomingPeriodUsers[] = [
                'user_id' => $row['id'],
                'name' => $row['name'],
                'email' => $row['email'],
                'profile_picture' => $row['profile_picture'],
                'last_start' => $lastStart,
                'next_start' => $nextPeriodDate->format('Y-m-d'),
                'diff_days' => $diffDays,
                'already_notified' => $alreadyNotified,
            ];
        }
    } catch (Exception $e) {
        continue;
    }
}

// Urutkan berdasarkan yang paling dekat dengan hari ini
usort($upcomingPeriodUsers, fn($a, $b) => $a['diff_days'] <=> $b['diff_days']);

// ========================================================
// PROSES PENGIRIMAN PUSH NOTIFIKASI
// ========================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    // 1. Push Cerdas Berdasarkan Hasil Scan
    if ($action === 'push_smart_haid') {
        $selectedUserIds = $_POST['selected_users'] ?? [];
        if (empty($selectedUserIds)) {
            flash('error', 'Pilih minimal satu pengguna untuk dikirimkan notifikasi pengingat haid.');
        } else {
            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, scheduled_at, status)
                VALUES (?, ?, ?, 'period_reminder', '../calendar/index.php', NOW(), 'pending')
            ");

            $sent = 0;
            foreach ($upcomingPeriodUsers as $cand) {
                if (in_array((string)$cand['user_id'], $selectedUserIds, true)) {
                    $daysText = $cand['diff_days'] === 0 ? "hari ini" : "dalam {$cand['diff_days']} hari ke depan";
                    $title = "🌸 Pengingat Perkiraan Haid";
                    $message = "Halo {$cand['name']}, berdasarkan siklusmu, perkiraan haidmu akan tiba {$daysText} (sekitar tanggal " . date('d M Y', strtotime($cand['next_start'])) . "). Persiapkan kebutuhanmu dan pantau kalendermu di NISAYA ya!";

                    $notifStmt->execute([$cand['user_id'], $title, $message]);
                    $sent++;
                }
            }

            flash('success', "Pengingat siklus haid cerdas berhasil dikirimkan ke {$sent} pengguna.");
            header('Location: notifications.php');
            exit;
        }
    }

    // 2. Broadcast Manual Pengingat Haid
    elseif ($action === 'broadcast_haid') {
        $title = trim($_POST['title'] ?? '🌸 Pengingat Siklus Haid');
        $message = trim($_POST['message'] ?? '');
        $targetAudience = $_POST['target'] ?? 'all';
        $specificUserId = (int)($_POST['specific_user_id'] ?? 0);

        if (!$message) {
            flash('error', 'Pesan pengingat haid tidak boleh kosong.');
        } else {
            $recipientIds = [];
            if ($targetAudience === 'all') {
                $recipientIds = array_column($activeUsers, 'id');
            } elseif ($specificUserId > 0) {
                $recipientIds = [$specificUserId];
            }

            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, scheduled_at, status)
                VALUES (?, ?, ?, 'period_reminder', '../calendar/index.php', NOW(), 'pending')
            ");

            $sent = 0;
            foreach ($recipientIds as $uId) {
                $notifStmt->execute([$uId, $title, $message]);
                $sent++;
            }

            flash('success', "Broadcast pengingat siklus haid berhasil dikirimkan ke {$sent} pengguna.");
            header('Location: notifications.php');
            exit;
        }
    }

    // 3. Push Notifikasi Materi Edukasi
    elseif ($action === 'push_article') {
        $articleId = (int)($_POST['article_id'] ?? 0);
        $customTitle = trim($_POST['article_notif_title'] ?? '');
        $customMessage = trim($_POST['article_notif_message'] ?? '');
        $targetAudience = $_POST['target'] ?? 'all';
        $specificUserId = (int)($_POST['specific_user_id'] ?? 0);

        $artStmt = $pdo->prepare("SELECT id, title, description FROM education_articles WHERE id = ?");
        $artStmt->execute([$articleId]);
        $art = $artStmt->fetch(PDO::FETCH_ASSOC);

        if (!$art) {
            flash('error', 'Artikel edukasi tidak ditemukan.');
        } else {
            $title = $customTitle ?: "📖 Materi Baru: " . $art['title'];
            $message = $customMessage ?: "Materi edukasi baru telah hadir di NISAYA: '" . $art['title'] . "'. Yuk luangkan waktu membaca untuk menambah pemahaman fikih & kesehatanmu!";
            $link = "../education/detail.php?id=" . $art['id'];

            $recipientIds = [];
            if ($targetAudience === 'all') {
                $recipientIds = array_column($activeUsers, 'id');
            } elseif ($specificUserId > 0) {
                $recipientIds = [$specificUserId];
            }

            $notifStmt = $pdo->prepare("
                INSERT INTO notifications (user_id, title, message, type, link, article_id, scheduled_at, status)
                VALUES (?, ?, ?, 'new_article', ?, ?, NOW(), 'pending')
            ");

            $sent = 0;
            foreach ($recipientIds as $uId) {
                $notifStmt->execute([$uId, $title, $message, $link, $art['id']]);
                $sent++;
            }

            flash('success', "Push notifikasi artikel edukasi '" . e($art['title']) . "' berhasil dikirimkan ke {$sent} pengguna.");
            header('Location: notifications.php');
            exit;
        }
    }


    // 4. Hapus Riwayat Notifikasi
    elseif ($action === 'delete_notif') {
        $notifId = (int)($_POST['notif_id'] ?? 0);
        $filterType = $_POST['filter_type'] ?? '';

        $del = $pdo->prepare("DELETE FROM notifications WHERE id = ?");
        $del->execute([$notifId]);

        flash('success', 'Riwayat notifikasi berhasil dihapus.');

        $redirect = 'notifications.php?tab=history';

        if (in_array($filterType, ['period_reminder', 'new_article'], true)) {
            $redirect .= '&type=' . urlencode($filterType);
        }

        header('Location: ' . $redirect . '#history-content');
        exit;
    }
}

// Ambil Riwayat Notifikasi Terkirim
$filterType = trim($_GET['type'] ?? '');
$historySql = "
    SELECT n.*, u.name as user_name, u.email as user_email, a.title as article_title
    FROM notifications n
    LEFT JOIN users u ON u.id = n.user_id
    LEFT JOIN education_articles a ON a.id = n.article_id
    WHERE 1=1
";
$historyParams = [];
if ($filterType !== '') {
    $historySql .= " AND n.type = ?";
    $historyParams[] = $filterType;
}
$historySql .= " ORDER BY n.id DESC LIMIT 50";

$historyStmt = $pdo->prepare($historySql);
$historyStmt->execute($historyParams);
$sentNotifications = $historyStmt->fetchAll(PDO::FETCH_ASSOC);

// Pre-selected article id from GET parameter
$preSelectedArticleId = (int)($_GET['article_id'] ?? 0);

// Menentukan tab yang aktif setelah halaman dimuat ulang
$activeTab = $_GET['tab'] ?? '';
if (!in_array($activeTab, ['haid', 'article', 'history'], true)) {
    $activeTab = $preSelectedArticleId > 0 ? 'article' : 'haid';
}

$pageTitle = 'Pusat Push Notifikasi';
$adminActive = 'notifications';

require_once __DIR__ . '/includes/header.php';
?>

<!-- BANNER EDUKASI SERASI DENGAN TAMPILAN USER -->
<section class="education-hero mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <span class="education-eyebrow">
                BROADCAST & PENGINGAT 🌸
            </span>
            <h2>Pusat Push Notifikasi</h2>
            <p>
                Kirimkan pengingat datangnya haid berdasarkan perhitungan siklus anggota, serta push notifikasi ketika materi edukasi baru diterbitkan.
            </p>
        </div>
    </div>
    <div class="education-hero-icon">🔔</div>
</section>

<!-- NAV TABS BERGAYA NISAYA -->
<ul class="nav nav-pills mb-4 gap-2" id="notifTabs" role="tablist">
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'haid' ? 'active' : '' ?> fw-semibold px-4 py-2" id="haid-tab" data-bs-toggle="pill" data-bs-target="#haid-content" type="button" role="tab" style="border-radius: 14px;">
            🌸 Pengingat Datangnya Haid
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'article' ? 'active' : '' ?> fw-semibold px-4 py-2" id="article-tab" data-bs-toggle="pill" data-bs-target="#article-content" type="button" role="tab" style="border-radius: 14px;">
            📖 Push Notifikasi Materi Edukasi
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link <?= $activeTab === 'history' ? 'active' : '' ?> fw-semibold px-4 py-2" id="history-tab" data-bs-toggle="pill" data-bs-target="#history-content" type="button" role="tab" style="border-radius: 14px;">
            🕒 Riwayat Notifikasi Terkirim (<?= count($sentNotifications) ?>)
        </button>
    </li>
</ul>

<div class="tab-content" id="notifTabsContent">

    <!-- ==================================================== -->
    <!-- TAB 1: PENGINGAT DATANGNYA HAID                      -->
    <!-- ==================================================== -->
    <div class="tab-pane fade <?= $activeTab === 'haid' ? 'show active' : '' ?>" id="haid-content" role="tabpanel">

        <!-- BAGIAN A: SMART CYCLE SCAN -->
        <div class="admin-card mb-4">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <div>
                    <h5 class="admin-card-title mb-1">
                        <i class="bi bi-magic" style="color: #a36f91;"></i> Pindai Cerdas: Anggota Mendekati Masa Haid (≤ 7 Hari)
                    </h5>
                    <small class="text-muted">
                        Sistem otomatis menghitung siklus berdasarkan data haid terakhir dan rata-rata masa suci setiap pengguna.
                    </small>
                </div>
                <span class="badge" style="background: #fce6ef; color: #a34f7b; border: 1px solid #f6cfdc; padding: 6px 14px; border-radius: 20px; font-weight: 700;">
                    <?= count($upcomingPeriodUsers) ?> Pengguna Terdeteksi
                </span>
            </div>

            <?php if (empty($upcomingPeriodUsers)): ?>
                <div class="alert alert-light border py-4 text-center rounded-4" style="background: #faf7fc;">
                    <span class="fs-1 d-block mb-2">🌸</span>
                    <strong class="d-block mb-1" style="color: #594765;">Tidak ada anggota yang mendekati perkiraan haid dalam 7 hari ke depan.</strong>
                    <span class="text-muted small">Semua pengguna berada di fase suci atau belum mencatat data haid. Anda tetap dapat mengirimkan broadcast pengingat haid manual di bawah ini.</span>
                </div>
            <?php else: ?>
                <form method="POST" action="notifications.php">
                    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="push_smart_haid">

                    <div class="admin-table-wrap mb-3">
                        <table class="admin-table">
                            <thead>
                                <tr>
                                    <th style="width: 40px;">
                                        <input type="checkbox" id="selectAllScan" class="form-check-input" checked>
                                    </th>
                                    <th>Anggota</th>
                                    <th>Haid Terakhir</th>
                                    <th>Perkiraan Haid Tiba</th>
                                    <th>Sisa Waktu</th>
                                    <th>Status Notifikasi</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($upcomingPeriodUsers as $cand): ?>
                                    <tr>
                                        <td>
                                            <input type="checkbox" name="selected_users[]" value="<?= (int)$cand['user_id'] ?>" class="form-check-input user-scan-check" checked>
                                        </td>
                                        <td>
                                            <div class="d-flex align-items-center gap-2">
                                                <?php if (!empty($cand['profile_picture']) && file_exists(__DIR__ . '/../' . $cand['profile_picture'])): ?>
                                                    <img src="../<?= e($cand['profile_picture']) ?>" alt="Avatar" class="avatar-sm">
                                                <?php else: ?>
                                                    <div class="avatar-placeholder-sm">🌷</div>
                                                <?php endif; ?>
                                                <div>
                                                    <strong class="d-block" style="color: #594765;"><?= e($cand['name']) ?></strong>
                                                    <small class="text-muted"><?= e($cand['email']) ?></small>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <small class="text-muted"><?= date('d M Y', strtotime($cand['last_start'])) ?></small>
                                        </td>
                                        <td>
                                            <strong style="color: #a36f91;">
                                                📅 <?= date('d M Y', strtotime($cand['next_start'])) ?>
                                            </strong>
                                        </td>
                                        <td>
                                            <?php if ($cand['diff_days'] === 0): ?>
                                                <span class="badge text-bg-danger px-2 py-1">Hari Ini! ⚠️</span>
                                            <?php elseif ($cand['diff_days'] === 1): ?>
                                                <span class="badge text-bg-warning px-2 py-1">Besok</span>
                                            <?php else: ?>
                                                <span class="badge" style="background: #fdf8fb; color: #a36f91; border: 1px solid #f0e3f0;"><?= $cand['diff_days'] ?> hari lagi</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cand['already_notified']): ?>
                                                <span class="badge bg-secondary-subtle text-secondary small">Sudah diingatkan baru-baru ini</span>
                                            <?php else: ?>
                                                <span class="badge-status-active">Siap dikirimkan notif</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-2">
                        <small class="text-muted">Centang pengguna yang ingin Anda kirimkan pengingat datangnya haid secara serentak.</small>
                        <button type="submit" class="btn btn-admin-primary px-4 py-2">
                            <i class="bi bi-send-fill me-1"></i> Kirim Pengingat Haid ke Anggota Terpilih
                        </button>
                    </div>
                </form>
            <?php endif; ?>
        </div>

        <!-- BAGIAN B: BROADCAST PENGINGAT HAID MANUAL -->
        <div class="admin-card">
            <h5 class="admin-card-title mb-2">
                <i class="bi bi-broadcast" style="color: #a36f91;"></i> Broadcast Pengingat Siklus Haid (Manual)
            </h5>
            <p class="small text-muted mb-4">
                Kirimkan pengingat haid umum ke seluruh pengguna aktif atau satu pengguna tertentu dengan pesan kustom.
            </p>

            <form method="POST" action="notifications.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="broadcast_haid">

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="color: #594765;">Target Penerima</label>
                        <select name="target" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;" id="haidTargetSelect">
                            <option value="all">Semua Pengguna Aktif (<?= count($activeUsers) ?> Pengguna)</option>
                            <option value="specific">Pilih Satu Pengguna Tertentu...</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="haidSpecificUserWrap" style="display: none;">
                        <label class="form-label fw-semibold" style="color: #594765;">Pilih Anggota</label>
                        <select name="specific_user_id" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;">
                            <?php foreach ($activeUsers as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-12">
                        <label class="form-label fw-semibold" style="color: #594765;">Judul Notifikasi</label>
                        <input type="text" name="title" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" value="🌸 Pengingat Siklus Haid" required>
                    </div>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold" style="color: #594765;">Isi Pesan Pengingat</label>
                    <textarea name="message" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" rows="3" required>Hai! Jangan lupa untuk selalu memantau kalender siklusmu dan mempersiapkan kebutuhan harianmu. Tetap jaga kesehatan dan jangan lupa catat hari pertama haid di NISAYA ya 🌸</textarea>
                </div>

                <button type="submit" class="btn btn-admin-primary px-4">
                    <i class="bi bi-megaphone-fill me-1"></i> Broadcast Pengingat Haid
                </button>
            </form>
        </div>

    </div>

    <!-- ==================================================== -->
    <!-- TAB 2: PUSH NOTIFIKASI ARTIKEL EDUKASI BARU         -->
    <!-- ==================================================== -->
    <div class="tab-pane fade <?= $activeTab === 'article' ? 'show active' : '' ?>" id="article-content" role="tabpanel">
        <div class="admin-card">
            <h5 class="admin-card-title mb-2">
                <i class="bi bi-journal-check" style="color: #a36f91;"></i> Push Notifikasi Artikel & Materi Edukasi
            </h5>
            <p class="small text-muted mb-4">
                Kirimkan push notifikasi ke pengguna untuk mengabarkan hadirnya materi edukasi baru atau merekomendasikan bacaan penting seputar haid & fikih wanita.
            </p>

            <form method="POST" action="notifications.php">
                <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="push_article">

                <!-- Pilih Artikel -->
                <div class="mb-3">
                    <label class="form-label fw-semibold" style="color: #594765;">Pilih Materi Edukasi <span class="text-danger">*</span></label>
                    <select name="article_id" class="form-select form-select-lg" style="border-color: #e5d9ed; border-radius: 12px;" id="articleSelect" required>
                        <option value="">-- Pilih Materi untuk Dinotifikasikan --</option>
                        <?php foreach ($articlesList as $artItem): ?>
                            <option value="<?= (int)$artItem['id'] ?>" <?= $preSelectedArticleId === (int)$artItem['id'] ? 'selected' : '' ?> data-title="<?= e($artItem['title']) ?>">
                                <?= !empty($artItem['icon']) ? e($artItem['icon']) : '📖' ?> <?= e($artItem['title']) ?> (Kategori: <?= e($artItem['category']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-md-6">
                        <label class="form-label fw-semibold" style="color: #594765;">Target Penerima</label>
                        <select name="target" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;" id="artTargetSelect">
                            <option value="all">Semua Pengguna Aktif (<?= count($activeUsers) ?> Pengguna)</option>
                            <option value="specific">Pilih Satu Pengguna Tertentu...</option>
                        </select>
                    </div>
                    <div class="col-md-6" id="artSpecificUserWrap" style="display: none;">
                        <label class="form-label fw-semibold" style="color: #594765;">Pilih Anggota</label>
                        <select name="specific_user_id" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;">
                            <?php foreach ($activeUsers as $u): ?>
                                <option value="<?= (int)$u['id'] ?>"><?= e($u['name']) ?> (<?= e($u['email']) ?>)</option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="mb-3">
                    <label class="form-label fw-semibold" style="color: #594765;">Judul Notifikasi (Otomatis / Kustom)</label>
                    <input type="text" name="article_notif_title" id="artNotifTitle" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" placeholder="Contoh: 📖 Materi Edukasi Baru: ...">
                    <small class="text-muted">Biarkan kosong untuk menggunakan format default otomatis.</small>
                </div>

                <div class="mb-4">
                    <label class="form-label fw-semibold" style="color: #594765;">Pesan Notifikasi (Otomatis / Kustom)</label>
                    <textarea name="article_notif_message" id="artNotifMessage" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" rows="3" placeholder="Tuliskan pesan menarik agar pengguna tertarik membuka materi..."></textarea>
                    <small class="text-muted">Biarkan kosong untuk menggunakan format pesan rekomendasi default.</small>
                </div>

                <button type="submit" class="btn btn-admin-primary px-4 py-2">
                    <i class="bi bi-send-check-fill me-1"></i> Kirim Push Notifikasi Artikel Sekarang
                </button>
            </form>
        </div>
    </div>

    <!-- ==================================================== -->
    <!-- TAB 3: RIWAYAT NOTIFIKASI TERKIRIM                   -->
    <!-- ==================================================== -->
    <div class="tab-pane fade <?= $activeTab === 'history' ? 'show active' : '' ?>" id="history-content" role="tabpanel">
        <div class="admin-card">
            <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
                <h5 class="admin-card-title mb-0">
                    <i class="bi bi-clock-history" style="color: #a36f91;"></i> Riwayat Push Notifikasi
                </h5>
                <div class="d-flex gap-2">
                    <a href="notifications.php?tab=history" class="btn btn-sm <?= $filterType === '' ? 'btn-admin-primary' : 'btn-light border' ?>" style="border-radius: 10px;">Semua</a>
                    <a href="notifications.php?tab=history&type=period_reminder" class="btn btn-sm <?= $filterType === 'period_reminder' ? 'btn-admin-primary' : 'btn-light border' ?>" style="border-radius: 10px;">🌸 Haid</a>
                    <a href="notifications.php?tab=history&type=new_article" class="btn btn-sm <?= $filterType === 'new_article' ? 'btn-admin-primary' : 'btn-light border' ?>" style="border-radius: 10px;">📖 Artikel</a>
                </div>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Tipe</th>
                            <th>Penerima</th>
                            <th>Judul & Tautan</th>
                            <th>Isi Pesan</th>
                            <th>Waktu Kirim</th>
                            <th>Status Dibaca</th>
                            <th class="text-end">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($sentNotifications)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5 text-muted">
                                    <span class="fs-1 d-block mb-2">🌸</span>
                                    Belum ada notifikasi yang cocok dengan filter.
                                </td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($sentNotifications as $notif): ?>
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
                                        <strong class="d-block" style="color: #594765;"><?= e($notif['user_name'] ?? 'User #' . $notif['user_id']) ?></strong>
                                        <small class="text-muted"><?= e($notif['user_email'] ?? '') ?></small>
                                    </td>
                                    <td>
                                        <strong class="d-block" style="color: #594765;"><?= e($notif['title']) ?></strong>
                                        <?php if (!empty($notif['link'])): ?>
                                            <small><a href="<?= e($notif['link']) ?>" target="_blank" class="text-decoration-none" style="color: #a36f91;">Tautan ↗</a></small>
                                        <?php endif; ?>
                                    </td>
                                    <td style="max-width: 320px;">
                                        <div class="text-truncate text-muted"><?= e($notif['message']) ?></div>
                                    </td>
                                    <td>
                                        <small class="text-muted"><?= date('d M Y, H:i', strtotime($notif['scheduled_at'])) ?></small>
                                    </td>
                                    <td>
                                        <?php if ((int)$notif['is_read'] === 1): ?>
                                            <span class="badge bg-light text-muted border">✓ Sudah Dibaca</span>
                                        <?php else: ?>
                                            <span class="badge-status-active">Belum Dibaca</span>
                                        <?php endif; ?>
                                    </td>

                                    <td class="text-end">
                                        <button type="button"
                                            class="btn-action-delete border-0"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteNotifModal<?= (int)$notif['id'] ?>"
                                            title="Hapus Log Notifikasi">
                                            <i class="bi bi-trash3-fill"></i>
                                            <span>Hapus</span>
                                        </button>
                                    </td>

                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

</div>

<!-- MODAL KONFIRMASI HAPUS LOG NOTIFIKASI -->
<?php foreach ($sentNotifications as $notif): ?>

    <div class="modal fade"
        id="deleteNotifModal<?= (int)$notif['id'] ?>"
        tabindex="-1"
        aria-hidden="true">

        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content border-0 shadow"
                style="border-radius:20px;">

                <div class="modal-body text-center p-4">

                    <div class="logout-icon mb-3">
                        <i class="bi bi-exclamation-circle"></i>
                    </div>

                    <h5 class="fw-bold">Konfirmasi Hapus Notifikasi</h5>

                    <p class="text-muted">
                        Apakah Anda yakin ingin menghapus log notifikasi ini?
                        Tindakan ini tidak dapat dibatalkan.
                    </p>

                    <form method="POST" action="notifications.php">

                        <input type="hidden" name="csrf"
                            value="<?= e(csrf_token()) ?>">

                        <input type="hidden" name="action"
                            value="delete_notif">

                        <input type="hidden" name="notif_id"
                            value="<?= (int)$notif['id'] ?>">

                        <!-- Mempertahankan filter riwayat -->
                        <input type="hidden" name="filter_type"
                            value="<?= e($filterType) ?>">

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

<?php endforeach; ?>
<!-- AKHIR MODAL HAPUS LOG NOTIFIKASI -->


<script>
    // Checkbox select all for smart scan
    const selectAll = document.getElementById('selectAllScan');
    if (selectAll) {
        selectAll.addEventListener('change', function() {
            document.querySelectorAll('.user-scan-check').forEach(cb => cb.checked = selectAll.checked);
        });
    }

    // Toggle target audience dropdowns
    const haidTargetSelect = document.getElementById('haidTargetSelect');
    const haidSpecificWrap = document.getElementById('haidSpecificUserWrap');
    if (haidTargetSelect && haidSpecificWrap) {
        haidTargetSelect.addEventListener('change', function() {
            haidSpecificWrap.style.display = this.value === 'specific' ? 'block' : 'none';
        });
    }

    const artTargetSelect = document.getElementById('artTargetSelect');
    const artSpecificWrap = document.getElementById('artSpecificUserWrap');
    if (artTargetSelect && artSpecificWrap) {
        artTargetSelect.addEventListener('change', function() {
            artSpecificWrap.style.display = this.value === 'specific' ? 'block' : 'none';
        });
    }

    // Auto update title when choosing an article
    const articleSelect = document.getElementById('articleSelect');
    const artNotifTitle = document.getElementById('artNotifTitle');
    const artNotifMessage = document.getElementById('artNotifMessage');
    if (articleSelect && artNotifTitle && artNotifMessage) {
        articleSelect.addEventListener('change', function() {
            const opt = this.options[this.selectedIndex];
            const title = opt.getAttribute('data-title');
            if (title) {
                if (!artNotifTitle.value || artNotifTitle.value.startsWith('📖 Materi Baru:')) {
                    artNotifTitle.value = '📖 Materi Baru: ' + title;
                }
                if (!artNotifMessage.value || artNotifMessage.value.includes('Materi edukasi baru')) {
                    artNotifMessage.value = `Materi edukasi baru telah hadir di NISAYA: '${title}'. Yuk luangkan waktu membaca untuk menambah pemahaman fikih & kesehatanmu!`;
                }
            }
        });
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>