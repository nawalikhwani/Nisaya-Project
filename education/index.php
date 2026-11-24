<?php
require_once '../config/app.php';
require_login();

// Ambil data materi edukasi dari database
try {
    $stmt = $pdo->query("SELECT * FROM education_articles WHERE status = 'published' ORDER BY id ASC");
    $articles = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $articles = [];
}

$pageTitle = 'Edukasi';
$active = 'education';

require '../includes/header.php';
?>

<div class="education-page">

    <!-- Hero -->
    <section class="education-hero mb-4">
        <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
            <div>
                <span class="education-eyebrow">
                    BELAJAR DAN KENALI DIRIMU
                </span>

                <h2>Pusat Edukasi 🌸</h2>

                <p>
                    Temukan informasi seputar menstruasi, fikih,
                    dan kesehatan melalui materi yang tersedia.
                </p>

                <div class="education-total">
                    <span>📚</span>
                    <?= count($articles) ?> materi tersedia
                </div>
            </div>

            <?php if (is_admin()): ?>
                <div class="mt-2">
                    <a href="../admin/articles.php" class="btn btn-light shadow-sm" style="border-radius: 12px; font-weight: 600; color: #884b6f;">
                        ⚙️ Kelola Materi Edukasi (Admin)
                    </a>
                </div>
            <?php endif; ?>
        </div>

        <div class="education-hero-icon">📖</div>
    </section>

    <!-- Pengantar -->
    <section class="education-intro mb-4">
        <div class="education-intro-icon">💗</div>

        <div>
            <h6>Belajar dengan nyaman</h6>
            <p>
                Pelajari setiap materi secara bertahap.
                Gunakan informasi ini sebagai pengetahuan awal
                dan rujukan belajar.
            </p>
        </div>
    </section>

    <!-- Daftar materi -->
    <div class="education-heading">
        <div>
            <span class="education-section-label">
                KOLEKSI PEMBELAJARAN
            </span>

            <h4>Materi untukmu</h4>

            <p>Pilih materi yang ingin kamu pelajari.</p>
        </div>

        <div class="education-heading-icon">🌷</div>
    </div>

    <?php if (empty($articles)): ?>
        <div class="alert alert-light text-center py-4">
            Belum ada materi edukasi yang dipublikasikan.
        </div>
    <?php else: ?>
        <div class="row g-4">
            <?php foreach ($articles as $index => $art): ?>
                <div class="col-md-6 col-xl-4">
                    <article class="education-card h-100 position-relative">
                        <div class="education-card-top">
                            <div class="education-material-icon">
                                <?= !empty($art['icon']) ? e($art['icon']) : '🌸' ?>
                            </div>

                            <span class="education-number">
                                <?= str_pad($index + 1, 2, '0', STR_PAD_LEFT) ?>
                            </span>
                        </div>

                        <span class="education-category">
                            <?= e($art['category']) ?>
                        </span>

                        <h5><?= e($art['title']) ?></h5>

                        <p><?= e($art['description']) ?></p>

                        <?php if (!empty($art['image'])): ?>
                            <div class="mb-3">
                                <img src="../<?= e($art['image']) ?>" alt="<?= e($art['title']) ?>" class="img-fluid rounded-3" style="max-height: 140px; width: 100%; object-fit: cover;">
                            </div>
                        <?php endif; ?>

                        <div class="mt-auto d-flex justify-content-between align-items-center">
                            <a href="detail.php?id=<?= (int)$art['id'] ?>" class="education-read-btn">
                                Baca materi
                                <span>→</span>
                            </a>

                            <?php if (is_admin()): ?>
                                <a href="../admin/article-edit.php?id=<?= (int)$art['id'] ?>" class="badge text-bg-light text-decoration-none border p-2" title="Sunting artikel ini">
                                    ✏️ Edit
                                </a>
                            <?php endif; ?>
                        </div>
                    </article>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <!-- Footer note -->
    <section class="education-footer-note mt-4">
        <span>🌼</span>

        <p>
            Pengetahuan adalah langkah awal untuk lebih memahami
            tubuh dan menjaga kesehatan diri.
        </p>
    </section>

</div>

<?php require '../includes/footer.php'; ?>