<?php
require_once __DIR__ . '/../config/app.php';
require_admin();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

$stmt = $pdo->prepare("SELECT * FROM education_articles WHERE id = ?");
$stmt->execute([$id]);
$article = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$article) {
    flash('error', 'Artikel edukasi tidak ditemukan.');
    header('Location: articles.php');
    exit;
}

$error = '';

// Ambil daftar kategori yang ada
$existingCategories = $pdo->query("SELECT DISTINCT category FROM education_articles WHERE category IS NOT NULL AND category != '' ORDER BY category ASC")->fetchAll(PDO::FETCH_COLUMN);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $title = trim($_POST['title'] ?? '');
    $categoryCustom = trim($_POST['category_custom'] ?? '');
    $categorySelect = trim($_POST['category_select'] ?? '');
    $category = $categoryCustom !== '' ? $categoryCustom : $categorySelect;
    $icon = trim($_POST['icon'] ?? '🌸');
    $description = trim($_POST['description'] ?? '');
    $content = trim($_POST['content'] ?? '');
    $references = trim($_POST['references_text'] ?? '');
    $status = in_array($_POST['status'] ?? '', ['published', 'draft'], true) ? $_POST['status'] : 'published';
    $sendNotification = isset($_POST['send_notification']) && $_POST['send_notification'] === '1';

    if (strlen($title) < 3) {
        $error = 'Judul artikel minimal 3 karakter.';
    } elseif ($category === '') {
        $error = 'Pilih atau masukkan kategori artikel.';
    } elseif (strlen($description) < 10) {
        $error = 'Ringkasan artikel minimal 10 karakter.';
    } elseif (strlen($content) < 20) {
        $error = 'Isi materi artikel minimal 20 karakter.';
    } else {
        $imagePath = $article['image'];

        // Handle Hapus Banner
        if (isset($_POST['remove_image']) && $_POST['remove_image'] === '1') {
            if ($imagePath && file_exists(__DIR__ . '/../' . $imagePath)) {
                @unlink(__DIR__ . '/../' . $imagePath);
            }
            $imagePath = null;
        }
        // Handle Ganti Banner Baru
        elseif (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $fileMime = mime_content_type($file['tmp_name']);
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (in_array($fileMime, $allowedTypes, true) && in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                if ($file['size'] <= 5 * 1024 * 1024) {
                    $newFilename = 'article_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                    $targetDir = __DIR__ . '/../assets/uploads/articles/';
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0775, true);
                    }
                    if (move_uploaded_file($file['tmp_name'], $targetDir . $newFilename)) {
                        if ($imagePath && file_exists(__DIR__ . '/../' . $imagePath)) {
                            @unlink(__DIR__ . '/../' . $imagePath);
                        }
                        $imagePath = 'assets/uploads/articles/' . $newFilename;
                    }
                } else {
                    $error = 'Ukuran gambar banner maksimal 5MB.';
                }
            } else {
                $error = 'Format gambar tidak valid.';
            }
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();

                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));

                $updateStmt = $pdo->prepare("
                    UPDATE education_articles
                    SET title = ?, slug = ?, category = ?, icon = ?, image = ?, description = ?, 
                        content = ?, references_text = ?, status = ?, updated_at = NOW()
                    WHERE id = ?
                ");
                $updateStmt->execute([
                    $title,
                    $slug,
                    $category,
                    $icon,
                    $imagePath,
                    $description,
                    $content,
                    $references,
                    $status,
                    $id
                ]);

                // Kirim notifikasi jika dipilih
                $notifCount = 0;
                if ($sendNotification && $status === 'published') {
                    $usersStmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
                    $activeUserIds = $usersStmt->fetchAll(PDO::FETCH_COLUMN);

                    $notifStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, article_id, scheduled_at, status)
                        VALUES (?, ?, ?, 'new_article', ?, ?, NOW(), 'pending')
                    ");

                    $notifTitle = "📖 Pembaruan Materi: " . $title;
                    $notifMsg = "Materi '" . $title . "' telah diperbarui dengan informasi terbaru. Yuk simak kembali untuk menambah wawasanmu!";
                    $notifLink = "../education/detail.php?id=" . $id;

                    foreach ($activeUserIds as $uId) {
                        $notifStmt->execute([$uId, $notifTitle, $notifMsg, $notifLink, $id]);
                        $notifCount++;
                    }
                }

                $pdo->commit();

                $msg = "Materi edukasi '{$title}' berhasil diperbarui.";
                if ($notifCount > 0) {
                    $msg .= " Notifikasi pembaruan dikirimkan ke {$notifCount} pengguna aktif.";
                }
                flash('success', $msg);
                header("Location: article-edit.php?id=$id");
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($e->getMessage());
                $error = 'Gagal menyimpan perubahan ke database.';
            }
        }
    }
}

$pageTitle = 'Sunting Materi: ' . $article['title'];
$adminActive = 'articles';

require_once __DIR__ . '/includes/header.php';
?>

<!-- BANNER EDUKASI SERASI DENGAN TAMPILAN USER -->
<section class="education-hero mb-4">
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <span class="education-eyebrow">
                KONTRIBUTOR & REDAKSI 🌸
            </span>
            <h2>Sunting Materi Edukasi</h2>
            <p>
                <?= e($article['title']) ?> (Kategori: <?= e($article['category']) ?>)
            </p>
        </div>
        <div class="mt-2 d-flex flex-wrap gap-2">
            <a href="articles.php" class="btn btn-admin-soft">
                ← Kembali ke Daftar Materi
            </a>
            <a href="../education/detail.php?id=<?= $id ?>" target="_blank" class="btn btn-action-preview py-2 px-3">
                <i class="bi bi-eye-fill"></i> Pratinjau Tampilan User ↗
            </a>
        </div>
    </div>
    <div class="education-hero-icon"><?= !empty($article['icon']) ? e($article['icon']) : '✏️' ?></div>
</section>

<?php if ($error): ?>
    <div class="alert alert-danger rounded-4 shadow-sm mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="admin-card">
    <form method="POST" action="article-edit.php?id=<?= $id ?>" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <!-- Judul & Kategori -->
        <div class="row g-3 mb-3">
            <div class="col-md-7">
                <label class="form-label fw-semibold" style="color: #594765;">Judul Materi <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control form-control-lg" style="border-color: #e5d9ed; border-radius: 12px;" required value="<?= e($article['title']) ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="color: #594765;">Kategori <span class="text-danger">*</span></label>
                <select name="category_select" class="form-select form-select-lg" style="border-color: #e5d9ed; border-radius: 12px;" id="catSelect">
                    <?php foreach ($existingCategories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= $article['category'] === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
                    <?php endforeach; ?>
                    <option value="__new__">+ Kategori Baru...</option>
                </select>
                <div id="newCatWrap" class="mt-2" style="display: none;">
                    <input type="text" name="category_custom" id="catCustom" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" placeholder="Ketik nama kategori baru...">
                </div>
            </div>
            <div class="col-md-2">
                <label class="form-label fw-semibold" style="color: #594765;">Icon / Emoji</label>
                <select name="icon" class="form-select form-select-lg" style="border-color: #e5d9ed; border-radius: 12px;">
                    <?php
                    $icons = [
                        '🌸' => '🌸 Bunga Sakura',
                        '🌷' => '🌷 Bunga Tulip',
                        '📖' => '📖 Buku / Fikih',
                        '🌿' => '🌿 Daun / Suci',
                        '🩷' => '🩷 Hati Merah Muda',
                        '📝' => '📝 Catatan / Jurnal',
                        '💡' => '💡 Tips / Wawasan',
                        '🩺' => '🩺 Medis / Kesehatan',
                        '🌼' => '🌼 Melati'
                    ];
                    foreach ($icons as $val => $lbl):
                    ?>
                        <option value="<?= e($val) ?>" <?= ($article['icon'] ?? '') === $val ? 'selected' : '' ?>><?= e($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>

        <!-- Banner Sampul -->
        <div class="p-3 rounded-4 mb-3" style="background-color: #fdf8fb; border: 1px solid #f0e3f0;">
            <label class="form-label fw-bold mb-2" style="color: #594765;">Gambar Sampul / Banner</label>
            <div class="d-flex align-items-center gap-3 flex-wrap">
                <?php if (!empty($article['image']) && file_exists(__DIR__ . '/../' . $article['image'])): ?>
                    <div>
                        <img src="../<?= e($article['image']) ?>" alt="Banner" class="rounded-3 shadow-sm" style="width: 120px; height: 80px; object-fit: cover; border: 1px solid #f0e3f0;">
                    </div>
                <?php endif; ?>
                <div class="flex-grow-1" style="min-width: 220px;">
                    <label class="form-label small fw-semibold text-muted mb-1"><?= !empty($article['image']) ? 'Ganti Gambar Banner' : 'Unggah Gambar Banner' ?></label>
                    <input type="file" name="image" class="form-control form-control-sm" style="border-color: #e5d9ed; border-radius: 8px;" accept="image/png, image/jpeg, image/webp">
                    <small class="text-muted" style="font-size: 0.75rem;">Format JPG, PNG, WEBP. Maks 5MB.</small>
                </div>
                <?php if (!empty($article['image'])): ?>
                    <div>
                        <label class="btn btn-sm btn-outline-danger" style="border-radius: 8px;">
                            <input type="checkbox" name="remove_image" value="1" class="form-check-input me-1"> Hapus Gambar
                        </label>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Ringkasan / Deskripsi Singkat -->
        <div class="mb-3">
            <label class="form-label fw-semibold" style="color: #594765;">Ringkasan Singkat (Deskripsi) <span class="text-danger">*</span></label>
            <textarea name="description" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" rows="2" required><?= e($article['description']) ?></textarea>
        </div>

        <!-- Isi Materi Lengkap -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <label class="form-label fw-semibold mb-0" style="color: #594765;">Isi Lengkap Materi <span class="text-danger">*</span></label>
                <!-- Toolbar Format -->
                <div class="btn-group btn-group-sm">
                    <button type="button" class="btn btn-light border" style="border-color: #e5d9ed;" onclick="insertTag('heading')">
                        <i class="bi bi-type-h3"></i> Subjudul
                    </button>
                    <button type="button" class="btn btn-light border" style="border-color: #e5d9ed;" onclick="insertTag('paragraph')">
                        <i class="bi bi-paragraph"></i> Paragraf
                    </button>
                    <button type="button" class="btn btn-light border" style="border-color: #e5d9ed;" onclick="insertTag('list')">
                        <i class="bi bi-list-ul"></i> Daftar Poin
                    </button>
                    <button type="button" class="btn btn-light border" style="border-color: #e5d9ed;" onclick="insertTag('note')">
                        <i class="bi bi-info-circle"></i> Kotak Catatan
                    </button>
                    <button type="button" class="btn btn-light border" style="border-color: #e5d9ed;" onclick="insertTag('example')">
                        <i class="bi bi-lightbulb"></i> Kotak Contoh
                    </button>
                </div>
            </div>
            <textarea name="content" id="articleContent" class="form-control font-monospace" style="border-color: #e5d9ed; border-radius: 12px;" rows="14" required><?= e($article['content']) ?></textarea>
            <small class="text-muted">Mendukung format HTML standar (&lt;h4&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;, dll).</small>
        </div>

        <!-- Referensi & Rujukan -->
        <div class="mb-4">
            <label class="form-label fw-semibold" style="color: #594765;">Referensi & Rujukan</label>
            <textarea name="references_text" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" rows="3"><?= e($article['references_text'] ?? '') ?></textarea>
            <small class="text-muted">Pisahkan setiap rujukan kitab atau referensi dengan baris baru (Enter).</small>
        </div>

        <!-- Status & Push Notification Check -->
        <div class="p-3 rounded-4 mb-4" style="background-color: #fdf8fb; border: 1px solid #f0e3f0;">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label class="form-label fw-semibold mb-1" style="color: #594765;">Status Publikasi</label>
                    <select name="status" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;">
                        <option value="published" <?= $article['status'] === 'published' ? 'selected' : '' ?>>Publikasikan (Terbit)</option>
                        <option value="draft" <?= $article['status'] === 'draft' ? 'selected' : '' ?>>Simpan Sebagai Draft</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="send_notification" value="1" id="sendNotifCheck">
                        <label class="form-check-label fw-bold" for="sendNotifCheck" style="color: #a36f91;">
                            📢 Kirim notifikasi pembaruan materi ini ke semua pengguna aktif
                        </label>
                        <div class="small text-muted">
                            Centang jika terdapat penambahan penting agar pengguna mendapatkan notifikasi pembaruan.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOMBOL AKSI DENGAN STYLE DAN KETERANGAN -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-3 border-top">
            <div class="small text-muted">
                <i class="bi bi-info-circle me-1"></i> Perubahan materi akan langsung diterapkan pada halaman baca pengguna.
            </div>
            <div class="d-flex gap-2">
                <a href="articles.php" class="btn btn-light border px-4" style="border-radius: 12px;">
                    <i class="bi bi-x-circle me-1"></i> Batal
                </a>
                <button type="submit" class="btn btn-admin-primary px-4 py-2">
                    <i class="bi bi-check2-circle me-1"></i> Simpan Perubahan Materi
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    const catSelect = document.getElementById('catSelect');
    const newCatWrap = document.getElementById('newCatWrap');
    catSelect.addEventListener('change', function() {
        if (this.value === '__new__') {
            newCatWrap.style.display = 'block';
            document.getElementById('catCustom').focus();
        } else {
            newCatWrap.style.display = 'none';
        }
    });

    function insertTag(type) {
        const textarea = document.getElementById('articleContent');
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        const selected = textarea.value.substring(start, end);
        let replacement = '';

        if (type === 'heading') {
            replacement = `\n<h4>${selected || 'Judul Bagian'}</h4>\n`;
        } else if (type === 'paragraph') {
            replacement = `\n<p>${selected || 'Tuliskan teks paragraf di sini...'}</p>\n`;
        } else if (type === 'list') {
            replacement = `\n<ul>\n  <li>${selected || 'Poin pertama'}</li>\n  <li>Poin kedua</li>\n</ul>\n`;
        } else if (type === 'note') {
            replacement = `\n<div class="alert alert-light border mt-4">\n  <strong>Catatan Penting</strong>\n  <p class="mb-0 mt-2">${selected || 'Catatan penjelasan di sini...'}</p>\n</div>\n`;
        } else if (type === 'example') {
            replacement = `\n<div class="alert alert-light border mt-3">\n  <strong>Contoh:</strong>\n  <p class="mb-0 mt-2">${selected || 'Contoh kasus atau perhitungan...'}</p>\n</div>\n`;
        }

        textarea.setRangeText(replacement, start, end, 'end');
        textarea.focus();
    }
</script>

<?php
require_once __DIR__ . '/includes/footer.php';
?>