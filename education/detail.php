<?php
require_once '../config/app.php';
require_login();

$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

try {
    $stmt = $pdo->prepare("SELECT * FROM education_articles WHERE id = ?");
    $stmt->execute([$id]);
    $article = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    error_log($e->getMessage());
    $article = null;
}

if (!$article || ($article['status'] !== 'published' && !is_admin())) {
    http_response_code(404);
    echo '<!DOCTYPE html><html lang="id"><head><meta charset="utf-8"><title>Materi Tidak Ditemukan</title><link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"><link href="../assets/style.css" rel="stylesheet"></head><body class="p-5 text-center"><div class="soft-card p-5 mx-auto" style="max-width:500px;"><h3>Materi tidak ditemukan</h3><p class="text-muted">Artikel edukasi yang kamu cari tidak tersedia atau belum dipublikasikan.</p><a href="index.php" class="btn btn-primary">Kembali ke Edukasi</a></div></body></html>';
    exit;
}

// Previous & Next navigation
try {
    $stmtPrev = $pdo->prepare("SELECT id FROM education_articles WHERE id < ? AND status = 'published' ORDER BY id DESC LIMIT 1");
    $stmtPrev->execute([$id]);
    $prevId = $stmtPrev->fetchColumn();

    $stmtNext = $pdo->prepare("SELECT id FROM education_articles WHERE id > ? AND status = 'published' ORDER BY id ASC LIMIT 1");
    $stmtNext->execute([$id]);
    $nextId = $stmtNext->fetchColumn();
} catch (PDOException $e) {
    $prevId = null;
    $nextId = null;
}

$pageTitle = $article['title'];
$active = 'education';

require '../includes/header.php';
?>

<div class="soft-card p-4 p-md-5 mb-5">

    <div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
        <div>
            <!-- KATEGORI -->
            <span class="badge text-bg-light border px-3 py-2 fs-6">
                <?= !empty($article['icon']) ? e($article['icon']) . ' ' : '' ?><?= e($article['category']) ?>
            </span>
            <?php if ($article['status'] === 'draft'): ?>
                <span class="badge text-bg-warning px-2 py-1 ms-2">Draft</span>
            <?php endif; ?>
        </div>

        <div class="d-flex align-items-center gap-2">
            <?php if (is_admin()): ?>
                <a href="../admin/article-edit.php?id=<?= (int)$article['id'] ?>" class="btn-action-edit py-1 px-3">
                    <i class="bi bi-pencil-square"></i> Edit Materi
                </a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-sm btn-light border" style="border-radius: 8px;">
                ← Kembali
            </a>
        </div>
    </div>

    <!-- JUDUL -->
    <h1 class="fw-bold mb-3" style="color: #4a344d; font-size: 2rem;">
        <?= e($article['title']) ?>
    </h1>

    <!-- TANGGAL & PENULIS -->
    <div class="d-flex align-items-center gap-3 text-muted small mb-4 pb-3 border-bottom">
        <span>📅 <?= date('d M Y', strtotime($article['created_at'])) ?></span>
        <span>•</span>
        <span>📖 Pusat Edukasi NISAYA</span>
    </div>

    <!-- GAMBAR BANNER (JIKA ADA) -->
    <?php if (!empty($article['image'])): ?>
        <div class="mb-4">
            <img src="../<?= e($article['image']) ?>" alt="<?= e($article['title']) ?>" class="img-fluid rounded-4 shadow-sm w-100" style="max-height: 380px; object-fit: cover;">
        </div>
    <?php endif; ?>

    <!-- DESKRIPSI -->
    <div class="p-3 rounded-3 mb-4" style="background: #fdf5f8; border-left: 4px solid #d991b1; color: #6d4f64; line-height: 1.8;">
        <strong>Ringkasan:</strong> <?= e($article['description']) ?>
    </div>

    <!-- ISI MATERI -->
    <div class="article-content mt-4" style="line-height: 1.9; font-size: 1.05rem; color: #3d3343;">
        <?php
        // Cek apakah konten mengandung format HTML atau teks biasa
        $content = $article['content'];
        if (strip_tags($content) === $content) {
            // Konten teks biasa, konversi line break
            echo nl2br(e($content));
        } else {
            // Konten HTML (sudah diformat oleh admin / sistem)
            // Izinkan tag umum aman
            echo strip_tags($content, '<h4><h3><h2><h5><h6><p><ul><ol><li><strong><em><b><i><br><hr><blockquote><div><span><img><table><thead><tbody><tr><th><td>');
        }
        ?>
    </div>

    <!-- REFERENSI -->
    <?php if (!empty(trim($article['references_text'] ?? ''))): ?>
        <div class="mt-5 pt-4 border-top">
            <h5 class="fw-bold mb-3" style="color: #5d4359;">
                📚 Referensi Materi
            </h5>
            <ol style="line-height: 1.8; color: #695d6e;">
                <?php
                $refs = explode("\n", trim($article['references_text']));
                foreach ($refs as $reference):
                    $ref = trim($reference);
                    if ($ref !== ''):
                ?>
                        <li><?= e($ref) ?></li>
                <?php
                    endif;
                endforeach;
                ?>
            </ol>
        </div>
    <?php endif; ?>

    <!-- CATATAN EDUKASI -->
    <div class="alert alert-light border mt-4" style="border-radius: 12px; background-color: #faf7fc;">
        🌸 <strong>Catatan Edukasi:</strong> Materi ini merupakan edukasi umum dan bukan fatwa untuk menentukan hukum suatu kasus secara pribadi. Untuk kasus khusus, konsultasikan kepada guru fikih yang kompeten atau tenaga medis kesehatan reproduksi.
    </div>

    <!-- NAVIGASI -->
    <div class="d-flex justify-content-between align-items-center mt-5 pt-3 border-top">
        <?php if (!empty($prevId)): ?>
            <a href="detail.php?id=<?= (int)$prevId ?>" class="text-decoration-none fw-semibold" style="color: #AB6E90;">
                ← Materi sebelumnya
            </a>
        <?php else: ?>
            <a href="index.php" class="text-decoration-none text-muted">
                ← Daftar materi
            </a>
        <?php endif; ?>

        <?php if (!empty($nextId)): ?>
            <a href="detail.php?id=<?= (int)$nextId ?>" class="text-decoration-none fw-semibold" style="color: #AB6E90;">
                Materi berikutnya →
            </a>
        <?php else: ?>
            <a href="index.php" class="text-decoration-none fw-semibold" style="color: #AB6E90;">
                Selesai (Kembali ke Daftar) 🌸
            </a>
        <?php endif; ?>
    </div>

</div>

<?php require '../includes/footer.php'; ?>