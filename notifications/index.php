<?php
require_once '../config/app.php';
require_login();

$uid = (int) $_SESSION['user_id'];

require_once __DIR__ . '/generate.php';

// HAPUS NOTIFIKASI
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'delete'
) {
    verify_csrf();

    $id = (int) ($_POST['id'] ?? 0);

    $q = $pdo->prepare(
        'UPDATE notifications
         SET is_deleted = 1
         WHERE id = ? AND user_id = ?'
    );

    $q->execute([$id, $uid]);

    header('Location: index.php');
    exit;
}

// TANDAI NOTIFIKASI SUDAH DIBACA
if (
    $_SERVER['REQUEST_METHOD'] === 'POST'
    && ($_POST['action'] ?? '') === 'read'
) {
    verify_csrf();

    $id = (int) ($_POST['id'] ?? 0);

    $q = $pdo->prepare(
        'UPDATE notifications
         SET is_read = 1
         WHERE id = ? AND user_id = ?'
    );

    $q->execute([$id, $uid]);

    header('Location: index.php');
    exit;
}

// AMBIL SEMUA NOTIFIKASI
$q = $pdo->prepare(
    'SELECT * FROM notifications
     WHERE user_id = ?
       AND is_deleted = 0
     ORDER BY id DESC'
);

$q->execute([$uid]);
$items = $q->fetchAll(PDO::FETCH_ASSOC);

// HITUNG STATISTIK NOTIFIKASI
$totalNotifications = count($items);
$unreadCount = 0;
$readCount = 0;

foreach ($items as $item) {
    if ((int) $item['is_read'] === 1) {
        $readCount++;
    } else {
        $unreadCount++;
    }
}

$pageTitle = 'Notifikasi';
$active = 'notifications';

require '../includes/header.php';
?>

<div class="notification-page">

    <!-- HERO -->
    <section class="notification-hero mb-4">
        <div>
            <span class="notification-eyebrow">
                PUSAT INFORMASI NISAYA
            </span>

            <h2>Notifikasi 🔔</h2>

            <p>
                Semua informasi dan pengingat penting
                untuk menemani perjalananmu.
            </p>
        </div>

        <div class="notification-hero-icon">💌</div>
    </section>

    <!-- STATISTIK -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="notification-stat notification-stat-pink">
                <div class="notification-stat-icon">📬</div>

                <div>
                    <div class="notification-stat-label">
                        Total notifikasi
                    </div>

                    <div class="notification-stat-value">
                        <?= $totalNotifications ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="notification-stat notification-stat-purple">
                <div class="notification-stat-icon">💗</div>

                <div>
                    <div class="notification-stat-label">
                        Belum dibaca
                    </div>

                    <div class="notification-stat-value">
                        <?= $unreadCount ?>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="notification-stat notification-stat-peach">
                <div class="notification-stat-icon">✓</div>

                <div>
                    <div class="notification-stat-label">
                        Sudah dibaca
                    </div>

                    <div class="notification-stat-value">
                        <?= $readCount ?>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- DAFTAR NOTIFIKASI -->
    <section class="notification-list-card">

        <div class="notification-heading">
            <div>
                <span class="notification-section-label">
                    PEMBARUAN TERBARU
                </span>

                <h5>Semua notifikasi</h5>

                <p>
                    Informasi yang dikirimkan oleh NISAYA.
                </p>
            </div>

            <div class="notification-heading-icon">🔔</div>
        </div>

        <?php if ($items): ?>

            <div class="notification-items">

                <?php foreach ($items as $n): ?>

                    <article class="notification-item <?= !(int) $n['is_read'] ? 'notification-unread' : '' ?>">

                        <div class="notification-item-icon">
                            <?php
                            if (($n['type'] ?? '') === 'period_reminder') {
                                echo '🌸';
                            } elseif (($n['type'] ?? '') === 'new_article') {
                                echo '📖';
                            } elseif (!(int) $n['is_read']) {
                                echo '💌';
                            } else {
                                echo '📨';
                            }
                            ?>
                        </div>

                        <div class="notification-item-content">

                            <div class="notification-item-top">
                                <h6>
                                    <?= e($n['title']) ?>
                                </h6>

                                <?php if (!(int) $n['is_read']): ?>

                                    <span class="notification-new">
                                        ● Baru
                                    </span>

                                <?php else: ?>

                                    <span class="notification-read">
                                        ✓ Dibaca
                                    </span>

                                <?php endif; ?>
                            </div>

                            <p>
                                <?= e($n['message']) ?>
                            </p>

                            <?php if (!empty($n['link'])): ?>
                                <div class="mb-2">
                                    <a href="<?= e($n['link']) ?>" class="btn btn-sm text-white" style="background-color: #AB6E90; border-radius: 8px; font-size: 0.82rem; padding: 4px 12px;">
                                        <?= ($n['type'] ?? '') === 'new_article' ? '📖 Baca Artikel' : '🌸 Buka Tautan' ?> →
                                    </a>
                                </div>
                            <?php endif; ?>

                            <div class="notification-item-bottom">

                                <small>
                                    🕒 <?= e($n['scheduled_at']) ?>
                                </small>

                                <div class="d-flex gap-2 align-items-center">

                                    <?php if (!(int) $n['is_read']): ?>

                                        <form method="POST" action="index.php">

                                            <input
                                                type="hidden"
                                                name="csrf"
                                                value="<?= e(csrf_token()) ?>">

                                            <input
                                                type="hidden"
                                                name="action"
                                                value="read">

                                            <input
                                                type="hidden"
                                                name="id"
                                                value="<?= (int) $n['id'] ?>">

                                            <button
                                                type="submit"
                                                class="notification-read-btn">
                                                Tandai dibaca
                                                <span>→</span>
                                            </button>

                                        </form>

                                    <?php endif; ?>

                                    <form
                                        method="POST"
                                        action="index.php"
                                        onsubmit="return confirm('Yakin ingin menghapus notifikasi ini?')">

                                        <input
                                            type="hidden"
                                            name="csrf"
                                            value="<?= e(csrf_token()) ?>">

                                        <input
                                            type="hidden"
                                            name="action"
                                            value="delete">

                                        <input
                                            type="hidden"
                                            name="id"
                                            value="<?= (int) $n['id'] ?>">

                                    </form>

                                </div>

                            </div>

                        </div>

                    </article>

                <?php endforeach; ?>

            </div>

        <?php else: ?>

            <div class="notification-empty">
                <div>💌</div>

                <h6>Belum ada notifikasi</h6>

                <p>
                    Jika ada informasi atau pengingat baru,
                    semuanya akan muncul di sini.
                </p>
            </div>

        <?php endif; ?>

    </section>

    <!-- REMINDER -->
    <div class="notification-reminder mt-4">
        <span>🌷</span>

        <p>
            Jangan lupa luangkan waktu untuk memperhatikan
            kesehatan dan kondisi tubuhmu.
        </p>
    </div>

</div>

<?php require '../includes/footer.php'; ?>