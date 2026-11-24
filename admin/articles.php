<?php
require_once __DIR__ . '/../config/app.php';
require_admin();

// Handle Hapus Artikel
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_article') {
    verify_csrf();
    $artId = (int)($_POST['article_id'] ?? 0);

    // Ambil info gambar untuk dihapus dari disk
    $stmt = $pdo->prepare("SELECT image, title FROM education_articles WHERE id = ?");
    $stmt->execute([$artId]);
    $art = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($art) {
        if (!empty($art['image']) && file_exists(__DIR__ . '/../' . $art['image'])) {
            @unlink(__DIR__ . '/../' . $art['image']);
        }
        $del = $pdo->prepare("DELETE FROM education_articles WHERE id = ?");
        $del->execute([$artId]);
        flash('success', "Artikel edukasi '" . e($art['title']) . "' berhasil dihapus.");
    }
    header('Location: articles.php');
    exit;
}

// Handle Push Notifikasi Langsung untuk Artikel Tertentu
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'push_article_notif') {
    verify_csrf();
    $artId = (int)($_POST['article_id'] ?? 0);

    $stmt = $pdo->prepare("SELECT id, title, description FROM education_articles WHERE id = ?");
    $stmt->execute([$artId]);
    $art = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($art) {
        // Ambil semua pengguna aktif
        $usersStmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
        $activeUserIds = $usersStmt->fetchAll(PDO::FETCH_COLUMN);

        $notifStmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, message, type, link, article_id, scheduled_at, status)
            VALUES (?, ?, ?, 'new_article', ?, ?, NOW(), 'pending')
        ");

        $title = "📖 Materi Edukasi: " . $art['title'];
        $message = "Materi baru telah hadir di NISAYA: '" . $art['title'] . "'. Pelajari selengkapnya untuk menambah pemahamanmu!";
        $link = "../education/detail.php?id=" . $art['id'];

        $count = 0;
        foreach ($activeUserIds as $uId) {
            $notifStmt->execute([$uId, $title, $message, $link, $art['id']]);
            $count++;
        }

        flash('success', "Push notifikasi artikel '" . e($art['title']) . "' berhasil dikirimkan ke $count pengguna aktif.");
    }
    header('Location: articles.php');
    exit;
}

// Filter & Pencarian
$search = trim($_GET['search'] ?? '');
$categoryFilter = trim($_GET['category'] ?? '');
$statusFilter = trim($_GET['status'] ?? '');

$sql = "SELECT a.*, u.name as author_name 
        FROM education_articles a
        LEFT JOIN users u ON u.id = a.author_id
        WHERE 1=1";
$params = [];

if ($search !== '') {
    $sql .= " AND (a.title LIKE ? OR a.description LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

if ($categoryFilter !== '') {
    $sql .= " AND a.category = ?";
    $params[] = $categoryFilter;
}

if ($statusFilter !== '') {
    $sql .= " AND a.status = ?";
    $params[] = $statusFilter;
}

$sql .= " ORDER BY a.id ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$articles = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ambil daftar kategori unik
$categories = $pdo->query("SELECT DISTINCT category FROM education_articles ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

$pageTitle = 'Kelola Materi Edukasi';
$adminActive = 'articles';

require_once __DIR__ . '/includes/header.php';
?>

<!-- HERO BANNER EDUKASI SERASI DENGAN TAMPILAN USER -->
<section class="education-hero mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <span class="education-eyebrow">
                PUSAT KENDALI EDUKASI 🌸
            </span>

            <h2>Materi & Artikel Edukasi</h2>

            <p>
                Kelola konten edukasi seputar menstruasi, fikih, dan kesehatan yang dibaca oleh pengguna di aplikasi NISAYA.
            </p>

            <div class="education-total">
                <span>📚</span>
                <?= count($articles) ?> materi terdaftar
            </div>
        </div>

        <div class="mt-2 d-flex flex-wrap gap-2">
            <a href="article-create.php" class="btn btn-admin-primary shadow-sm">
                <i class="bi bi-journal-plus me-1"></i> Tambah Materi Baru
            </a>
            <a href="../education/index.php" target="_blank" class="btn btn-admin-soft">
                <i class="bi bi-eye me-1"></i> Lihat Halaman Edukasi User ↗
            </a>
        </div>
    </div>

    <div class="education-hero-icon">📖</div>
</section>

<!-- PANDUAN & KETERANGAN LENGKAP TOMBOL AKSI -->
<div class="action-legend-card mb-4">
    <div class="d-flex align-items-center gap-2 mb-2">
        <span class="fs-6">💡</span>
        <strong style="color: #594765; font-size: 13px;">Panduan & Keterangan Tombol Aksi Materi:</strong>
    </div>
    <div class="row g-2">
        <div class="col-sm-6 col-xl-3">
            <div class="action-legend-item">
                <span class="btn-action-preview py-1 px-2"><i class="bi bi-eye-fill"></i> Lihat</span>
                <span>Buka tampilan artikel di halaman pengguna</span>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="action-legend-item">
                <span class="btn-action-edit py-1 px-2"><i class="bi bi-pencil-square"></i> Edit</span>
                <span>Ubah judul, naskah isi, kategori & cover materi</span>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="action-legend-item">
                <span class="btn-action-push py-1 px-2"><i class="bi bi-bell-fill"></i> Push Notif</span>
                <span>Siarkan notifikasi langsung ke semua user aktif</span>
            </div>
        </div>
        <div class="col-sm-6 col-xl-3">
            <div class="action-legend-item">
                <span class="btn-action-delete py-1 px-2"><i class="bi bi-trash3-fill"></i> Hapus</span>
                <span>Hapus materi beserta file cover terkait</span>
            </div>
        </div>
    </div>
</div>

<!-- FILTER CARD -->
<div class="admin-card mb-4 p-3">
    <form method="GET" action="articles.php" class="row g-2 align-items-center">
        <div class="col-md-5">
            <div class="input-group">
                <span class="input-group-text bg-white border-end-0 text-muted" style="border-color: #e5d9ed; border-radius: 12px 0 0 12px;"><i class="bi bi-search"></i></span>
                <input type="text" name="search" class="form-control border-start-0" style="border-color: #e5d9ed; border-radius: 0 12px 12px 0;" placeholder="Cari judul materi atau isi ringkasan..." value="<?= e($search) ?>">
            </div>
        </div>
        <div class="col-md-3">
            <select name="category" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;">
                <option value="">Semua Kategori</option>
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= e($cat) ?>" <?= $categoryFilter === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-2">
            <select name="status" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;">
                <option value="">Semua Status</option>
                <option value="published" <?= $statusFilter === 'published' ? 'selected' : '' ?>>Terbit (Published)</option>
                <option value="draft" <?= $statusFilter === 'draft' ? 'selected' : '' ?>>Draft</option>
            </select>
        </div>
        <div class="col-md-2 d-flex gap-2">
            <button type="submit" class="btn btn-admin-primary w-100 justify-content-center">
                Filter
            </button>
            <?php if ($search !== '' || $categoryFilter !== '' || $statusFilter !== ''): ?>
                <a href="articles.php" class="btn btn-light border" style="border-radius: 12px;" title="Reset Filter">
                    <i class="bi bi-x-lg"></i>
                </a>
            <?php endif; ?>
        </div>
    </form>
</div>

<!-- TABEL ARTIKEL -->
<div class="admin-card">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div class="fw-bold" style="color: #493347;">
            Total: <?= count($articles) ?> Artikel Edukasi
        </div>
    </div>

    <div class="admin-table-wrap">
        <table class="admin-table">
            <thead>
                <tr>
                    <th style="width: 45px;">Ikon</th>
                    <th>Judul & Ringkasan</th>
                    <th>Kategori</th>
                    <th>Status</th>
                    <th>Penulis</th>
                    <th>Tgl Publikasi</th>
                    <th class="text-end" style="min-width: 320px;">Aksi & Pengelolaan</th>
                </tr>
            </thead>
            <tbody>
                <?php if (empty($articles)): ?>
                    <tr>
                        <td colspan="7" class="text-center py-5 text-muted">
                            <span class="fs-1 d-block mb-2">🌸</span>
                            Tidak ada artikel edukasi yang sesuai dengan kriteria pencarian.
                        </td>
                    </tr>
                <?php else: ?>
                    <?php foreach ($articles as $art): ?>
                        <tr>
                            <td class="text-center">
                                <div class="fs-4"><?= !empty($art['icon']) ? e($art['icon']) : '🌸' ?></div>
                            </td>
                            <td>
                                <div class="d-flex align-items-center gap-3">
                                    <?php if (!empty($art['image']) && file_exists(__DIR__ . '/../' . $art['image'])): ?>
                                        <img src="../<?= e($art['image']) ?>" alt="Cover" class="rounded-3 shadow-sm" style="width: 55px; height: 42px; object-fit: cover; border: 1px solid #f0e3f0;">
                                    <?php endif; ?>
                                    <div>
                                        <a href="article-edit.php?id=<?= (int)$art['id'] ?>" class="fw-bold text-decoration-none" style="color: #594765; font-size: 0.98rem;">
                                            <?= e($art['title']) ?>
                                        </a>
                                        <small class="text-muted d-block text-truncate" style="max-width: 300px;">
                                            <?= e($art['description']) ?>
                                        </small>
                                    </div>
                                </div>
                            </td>
                            <td>
                                <span class="badge" style="background: #faf4fb; color: #a36f91; border: 1px solid #f2e3ed; border-radius: 20px; font-weight: 650; font-size: 11px; padding: 5px 10px;">
                                    <?= e($art['category']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($art['status'] === 'published'): ?>
                                    <span class="badge-status-active">
                                        ● Terbit
                                    </span>
                                <?php else: ?>
                                    <span class="badge-status-inactive">
                                        ● Draft
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <small class="text-muted"><?= e($art['author_name'] ?? 'Admin') ?></small>
                            </td>
                            <td>
                                <small class="text-muted"><?= date('d M Y', strtotime($art['created_at'])) ?></small>
                            </td>
                            <td class="text-end text-nowrap">
                                <div class="action-btn-group justify-content-end">
                                    <!-- Tombol Pratinjau di Sisi Pengguna -->
                                    <a href="../education/detail.php?id=<?= (int)$art['id'] ?>" target="_blank" class="btn-action-preview" title="Pratinjau: Buka artikel ini seperti yang dilihat oleh pengguna">
                                        <i class="bi bi-eye-fill"></i>
                                        <span>Lihat</span>
                                    </a>

                                    <!-- Tombol Sunting Konten -->
                                    <a href="article-edit.php?id=<?= (int)$art['id'] ?>" class="btn-action-edit" title="Sunting: Perbarui judul, narasi isi, rujukan, cover & kategori">
                                        <i class="bi bi-pencil-square"></i>
                                        <span>Edit</span>
                                    </a>


                                    <!-- TOMBOL PUSH NOTIFIKASI SIARAN -->
                                    <button type="button"
                                        class="btn-action-push border-0"
                                        data-bs-toggle="modal"
                                        data-bs-target="#pushArticleModal<?= (int)$art['id'] ?>"
                                        title="Kirim notifikasi siaran">
                                        <i class="bi bi-bell-fill"></i>
                                        <span>Push Notif</span>
                                    </button>

                                    <!-- TOMBOL HAPUS ARTIKEL -->
                                    <button type="button"
                                        class="btn-action-delete border-0"
                                        data-bs-toggle="modal"
                                        data-bs-target="#deleteArticleModal<?= (int)$art['id'] ?>"
                                        title="Hapus artikel">
                                        <i class="bi bi-trash3-fill"></i>
                                        <span>Hapus</span>
                                    </button>

                                </div>
                            </td>


                            <!-- MODAL PUSH NOTIFIKASI -->
                            <div class="modal fade"
                                id="pushArticleModal<?= (int)$art['id'] ?>"
                                tabindex="-1"
                                aria-hidden="true">

                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow"
                                        style="border-radius:20px;">

                                        <div class="modal-body text-center p-4">

                                            <div class="logout-icon mb-3">
                                                <i class="bi bi-bell-fill"></i>
                                            </div>

                                            <h5 class="fw-bold">Konfirmasi Push Notifikasi</h5>

                                            <p class="text-muted">
                                                Apakah Anda yakin ingin mengirimkan push notifikasi
                                                artikel ini ke seluruh anggota aktif?
                                            </p>

                                            <form method="POST" action="articles.php">

                                                <input type="hidden" name="csrf"
                                                    value="<?= e(csrf_token()) ?>">

                                                <input type="hidden" name="action"
                                                    value="push_article_notif">

                                                <input type="hidden" name="article_id"
                                                    value="<?= (int)$art['id'] ?>">

                                                <div class="d-flex justify-content-center gap-2 mt-4">

                                                    <button type="button"
                                                        class="btn btn-secondary px-4"
                                                        data-bs-dismiss="modal">
                                                        Batal
                                                    </button>

                                                    <button type="submit"
                                                        class="btn btn-danger px-4">
                                                        Ya, Kirim
                                                    </button>

                                                </div>
                                            </form>

                                        </div>
                                    </div>
                                </div>
                            </div>


                            <!-- MODAL HAPUS ARTIKEL -->
                            <div class="modal fade"
                                id="deleteArticleModal<?= (int)$art['id'] ?>"
                                tabindex="-1"
                                aria-hidden="true">

                                <div class="modal-dialog modal-dialog-centered">
                                    <div class="modal-content border-0 shadow"
                                        style="border-radius:20px;">

                                        <div class="modal-body text-center p-4">

                                            <div class="logout-icon mb-3">
                                                <i class="bi bi-exclamation-circle"></i>
                                            </div>

                                            <h5 class="fw-bold">Konfirmasi Hapus Artikel</h5>

                                            <p class="text-muted">
                                                Apakah Anda yakin ingin menghapus artikel ini?
                                                File gambar banner terkait juga akan dihapus.
                                                Tindakan ini tidak dapat dibatalkan.
                                            </p>

                                            <form method="POST" action="articles.php">

                                                <input type="hidden" name="csrf"
                                                    value="<?= e(csrf_token()) ?>">

                                                <input type="hidden" name="action"
                                                    value="delete_article">

                                                <input type="hidden" name="article_id"
                                                    value="<?= (int)$art['id'] ?>">

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