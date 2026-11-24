<?php

require_once '../config/app.php';
require_login();

$uid = $_SESSION['user_id'];

require_once __DIR__ . '/../notifications/generate.php';

// Ambil profil siklus.
$q = $pdo->prepare(
    'SELECT *
     FROM cycle_profiles
     WHERE user_id = ?'
);
$q->execute([$uid]);
$profile = $q->fetch(PDO::FETCH_ASSOC) ?: [];

// Ambil lima riwayat menstruasi terbaru.
$q = $pdo->prepare(
    'SELECT *
     FROM menstrual_records
     WHERE user_id = ?
     ORDER BY start_date DESC
     LIMIT 5'
);
$q->execute([$uid]);
$records = $q->fetchAll(PDO::FETCH_ASSOC);

// Hitung jumlah notifikasi yang belum dibaca.
$q = $pdo->prepare(
    'SELECT COUNT(*)
     FROM notifications
     WHERE user_id = ?
       AND is_read = 0
       AND is_deleted = 0'
);
$q->execute([$uid]);
$unread = (int) $q->fetchColumn();

// Tentukan tanggal haid terakhir.
// Riwayat menjadi prioritas, profil menjadi cadangan.
$lastPeriod = !empty($records[0]['start_date'])
    ? $records[0]['start_date']
    : ($profile['last_period_start'] ?? null);

// Hitung hari siklus.
$cycleDay = '—';

if ($lastPeriod) {
    try {
        $start = new DateTimeImmutable($lastPeriod);
        $today = new DateTimeImmutable('today');

        if ($start <= $today) {
            $cycleDay = $start->diff($today)->days + 1;
        }
    } catch (Exception $e) {
        $cycleDay = '—';
    }
}

// Format tanggal dalam bahasa Indonesia.
$formattedDate = 'Belum diisi';

if ($lastPeriod) {
    try {
        $date = new DateTimeImmutable($lastPeriod);

        $months = [
            1  => 'Jan',
            2  => 'Feb',
            3  => 'Mar',
            4  => 'Apr',
            5  => 'Mei',
            6  => 'Jun',
            7  => 'Jul',
            8  => 'Agu',
            9  => 'Sep',
            10 => 'Okt',
            11 => 'Nov',
            12 => 'Des',
        ];

        $formattedDate =
            $date->format('d') . ' ' .
            $months[(int) $date->format('n')] . ' ' .
            $date->format('Y');
    } catch (Exception $e) {
        $formattedDate = 'Belum diisi';
    }
}

$pageTitle = 'Dashboard';
$active = 'dashboard';

require '../includes/header.php';

?>

<!-- BANNER SAMBUTAN -->
<?php if (is_admin()): ?>
    <div class="alert alert-warning border d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2" style="border-radius: 16px; background-color: #fdf6ec; border-color: #f5deb3 !important;">
        <div class="d-flex align-items-center gap-3">
            <span class="fs-3">🛡️</span>
            <div>
                <strong style="color: #8c531b;">Panel Administrator Aktif</strong>
                <div class="small text-muted">Anda memiliki hak akses admin untuk mengelola pengguna & anggota, materi edukasi, dan push notifikasi.</div>
            </div>
        </div>
        <a href="../admin/index.php" class="btn btn-sm text-white px-3 py-2 fw-semibold shadow-sm" style="background-color: #92400e; border-radius: 10px;">
            Buka Dashboard Admin →
        </a>
    </div>
<?php endif; ?>

<section class="dashboard-banner mb-4">
    <div class="banner-content">
        <span class="banner-label">YOUR PERSONAL CYCLE COMPANION 🌷</span>

        <h1>
            Halo, <?= e($_SESSION['user_name']) ?>!
        </h1>

        <p>
            Senang melihatmu kembali.
            Yuk, kenali tubuhmu dan catat siklusmu dengan lebih teratur.
        </p>

        <a href="../profile/index.php" class="banner-button">
            Lengkapi profil <span>→</span>
        </a>
    </div>

    <div class="banner-decoration">🌸</div>
    <div class="banner-flower">✿</div>
</section>

<!-- KARTU RINGKASAN -->
<div class="row g-3 mb-4">

    <!-- Kartu 1 -->
    <div class="col-md-6 col-xl-3">
        <div class="dashboard-stat stat-pink">
            <div class="stat-icon">🌷</div>
            <div class="stat-label">Hari siklus saat ini</div>

            <div class="stat-value">
                <?= e($cycleDay) ?>
                <?= is_numeric($cycleDay) ? '<small>hari</small>' : '' ?>
            </div>

            <div class="stat-note">Berdasarkan catatan terakhir</div>
        </div>
    </div>

    <!-- Kartu 2 -->
    <div class="col-md-6 col-xl-3">
        <div class="dashboard-stat stat-purple">
            <div class="stat-icon">🗓️</div>
            <div class="stat-label">Haid terakhir</div>

            <div class="stat-value stat-date">
                <?= e($formattedDate) ?>
            </div>

            <div class="stat-note">
                <?php if ($lastPeriod): ?>
                    <?= !empty($records[0]['start_date'])
                        ? 'Berdasarkan riwayat menstruasi'
                        : 'Berdasarkan profil menstruasi' ?>
                <?php else: ?>
                    Belum ada data tanggal haid
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Kartu 3 -->
    <div class="col-md-6 col-xl-3">
        <div class="dashboard-stat stat-peach">
            <div class="stat-icon">💗</div>
            <div class="stat-label">Rata-rata lama haid</div>

            <div class="stat-value">
                <?= e($profile['average_period_days'] ?? '—') ?>
                <?= !empty($profile['average_period_days']) ? '<small>hari</small>' : '' ?>
            </div>

            <div class="stat-note">Data dari profil siklus</div>
        </div>
    </div>

    <!-- Kartu 4 -->
    <div class="col-md-6 col-xl-3">
        <div class="dashboard-stat stat-lavender">
            <div class="stat-icon">🔔</div>
            <div class="stat-label">Notifikasi baru</div>

            <div class="stat-value">
                <?= e($unread) ?>
            </div>

            <div class="stat-note">
                <a href="../notifications/index.php">Lihat notifikasi →</a>
            </div>
        </div>
    </div>

</div>

<!-- KONTEN UTAMA -->
<div class="row g-4 mb-4">

    <!-- RIWAYAT -->
    <div class="col-lg-8">
        <div class="dashboard-panel h-100">

            <div class="panel-heading">
                <div>
                    <span class="panel-eyebrow">CATATAN SIKLUS</span>
                    <h4>Riwayat Terbaru 🌸</h4>
                </div>

                <a href="../history/index.php" class="panel-link">
                    Lihat semua →
                </a>
            </div>

            <div class="table-responsive">
                <table class="table dashboard-table align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Mulai</th>
                            <th>Selesai</th>
                            <th>Status</th>
                        </tr>
                    </thead>

                    <tbody>
                        <?php foreach ($records as $r): ?>
                            <tr>
                                <td><?= e($r['start_date']) ?></td>
                                <td><?= e($r['end_date'] ?: '—') ?></td>
                                <td>
                                    <span class="record-status <?= $r['status'] === 'ongoing' ? 'status-ongoing' : 'status-done' ?>">
                                        <?= e($r['status'] === 'ongoing' ? 'Berlangsung' : 'Selesai') ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endforeach; ?>

                        <?php if (!$records): ?>
                            <tr>
                                <td colspan="3" class="text-center py-5">
                                    <div class="empty-icon">🌷</div>
                                    <p class="muted mb-2">Belum ada catatan siklus.</p>

                                    <a href="../history/index.php" class="panel-link">
                                        Tambahkan catatan pertama →
                                    </a>
                                </td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>

    <!-- TIPS -->
    <div class="col-lg-4">
        <div class="dashboard-panel tips-panel h-100">

            <div class="panel-heading">
                <div>
                    <span class="panel-eyebrow">UNTUK HARI INI</span>
                    <h4>Tips Sehat ✨</h4>
                </div>

                <span class="tips-icon">💧</span>
            </div>

            <div class="tips-illustration">🥛🌷</div>

            <h5>Jangan lupa minum air putih!</h5>

            <p>
                Cukupi kebutuhan cairan tubuh sepanjang hari.
                Dengarkan kebutuhan tubuhmu dan jangan lupa beristirahat.
            </p>

            <div class="tips-footer">
                Sayangi dirimu, satu langkah setiap hari ♡
            </div>

        </div>
    </div>

</div>

<!-- EDUKASI -->
<section class="education-banner mb-4">
    <div class="education-icon">📚</div>

    <div class="education-content">
        <span class="panel-eyebrow">LET'S LEARN TOGETHER</span>
        <h4>Kenali tubuhmu lebih dekat!</h4>

        <p>
            Pelajari informasi seputar menstruasi, siklus,
            dan kesehatan reproduksi melalui materi edukasi NISAYA.
        </p>
    </div>

    <a href="../education/index.php" class="education-button">
        Jelajahi edukasi →
    </a>
</section>

<?php require '../includes/footer.php'; ?>