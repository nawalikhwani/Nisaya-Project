<?php
require_once __DIR__ . '/../config/app.php';
require_admin();

$error = '';

// Ambil daftar kategori yang sudah ada
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
        // Upload Banner Gambar (opsional)
        $imagePath = null;
        if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['image'];
            $allowedTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
            $fileMime = mime_content_type($file['tmp_name']);
            $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

            if (in_array($fileMime, $allowedTypes, true) && in_array($fileExt, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true)) {
                if ($file['size'] <= 5 * 1024 * 1024) { // Max 5MB
                    $newFilename = 'article_' . time() . '_' . bin2hex(random_bytes(4)) . '.' . $fileExt;
                    $targetDir = __DIR__ . '/../assets/uploads/articles/';
                    if (!is_dir($targetDir)) {
                        mkdir($targetDir, 0775, true);
                    }
                    if (move_uploaded_file($file['tmp_name'], $targetDir . $newFilename)) {
                        $imagePath = 'assets/uploads/articles/' . $newFilename;
                    }
                } else {
                    $error = 'Ukuran gambar banner artikel maksimal 5MB.';
                }
            } else {
                $error = 'Format gambar tidak valid (gunakan JPG, PNG, atau WEBP).';
            }
        }

        if (!$error) {
            try {
                $pdo->beginTransaction();

                // Generate slug unik
                $slug = strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title), '-'));
                $checkSlug = $pdo->prepare("SELECT id FROM education_articles WHERE slug = ?");
                $checkSlug->execute([$slug]);
                if ($checkSlug->fetch()) {
                    $slug .= '-' . time();
                }

                $stmt = $pdo->prepare("
                    INSERT INTO education_articles 
                    (title, slug, category, icon, image, description, content, references_text, status, author_id, created_at, updated_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
                ");
                $stmt->execute([
                    $title,
                    $slug,
                    $category,
                    $icon,
                    $imagePath,
                    $description,
                    $content,
                    $references,
                    $status,
                    $_SESSION['user_id']
                ]);
                $articleId = $pdo->lastInsertId();

                // Jika dicentang untuk push notifikasi & status published
                $notifCount = 0;
                if ($sendNotification && $status === 'published') {
                    $usersStmt = $pdo->query("SELECT id FROM users WHERE status = 'active'");
                    $activeUserIds = $usersStmt->fetchAll(PDO::FETCH_COLUMN);

                    $notifStmt = $pdo->prepare("
                        INSERT INTO notifications (user_id, title, message, type, link, article_id, scheduled_at, status)
                        VALUES (?, ?, ?, 'new_article', ?, ?, NOW(), 'pending')
                    ");

                    $notifTitle = "📖 Artikel Edukasi Baru: " . $title;
                    $notifMsg = "Materi edukasi baru telah hadir di NISAYA: '" . $title . "'. Yuk luangkan waktu untuk membaca dan memperluas pengetahuanmu!";
                    $notifLink = "../education/detail.php?id=" . $articleId;

                    foreach ($activeUserIds as $uId) {
                        $notifStmt->execute([$uId, $notifTitle, $notifMsg, $notifLink, $articleId]);
                        $notifCount++;
                    }
                }

                $pdo->commit();

                $msg = "Artikel edukasi '{$title}' berhasil ditambahkan.";
                if ($notifCount > 0) {
                    $msg .= " Push notifikasi otomatis dikirimkan ke {$notifCount} pengguna aktif.";
                }
                flash('success', $msg);
                header('Location: articles.php');
                exit;
            } catch (PDOException $e) {
                if ($pdo->inTransaction()) $pdo->rollBack();
                error_log($e->getMessage());
                $error = 'Gagal menyimpan artikel ke database: ' . $e->getMessage();
            }
        }
    }
}

$pageTitle = 'Tambah Materi Edukasi Baru';
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
            <h2>Tambah Materi Edukasi Baru</h2>
            <p>
                Publikasikan materi baru tentang fikih wanita, menstruasi, dan kesehatan reproduksi untuk seluruh anggota NISAYA.
            </p>
        </div>
        <div class="mt-2">
            <a href="articles.php" class="btn btn-admin-soft">
                ← Kembali ke Daftar Materi
            </a>
        </div>
    </div>
    <div class="education-hero-icon">✍️</div>
</section>

<?php if ($error): ?>
    <div class="alert alert-danger rounded-4 shadow-sm mb-4">
        <i class="bi bi-exclamation-triangle-fill me-2"></i> <?= e($error) ?>
    </div>
<?php endif; ?>

<div class="admin-card">
    <form method="POST" action="article-create.php" enctype="multipart/form-data">
        <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">

        <!-- Judul & Kategori -->
        <div class="row g-3 mb-3">
            <div class="col-md-7">
                <label class="form-label fw-semibold" style="color: #594765;">Judul Materi <span class="text-danger">*</span></label>
                <input type="text" name="title" class="form-control form-control-lg" style="border-color: #e5d9ed; border-radius: 12px;" required placeholder="Contoh: Tanda-tanda Berakhirnya Haid" value="<?= e($_POST['title'] ?? '') ?>">
            </div>
            <div class="col-md-3">
                <label class="form-label fw-semibold" style="color: #594765;">Kategori <span class="text-danger">*</span></label>
                <select name="category_select" class="form-select form-select-lg" style="border-color: #e5d9ed; border-radius: 12px;" id="catSelect">
                    <option value="">-- Pilih Kategori --</option>
                    <?php foreach ($existingCategories as $cat): ?>
                        <option value="<?= e($cat) ?>" <?= ($_POST['category_select'] ?? '') === $cat ? 'selected' : '' ?>><?= e($cat) ?></option>
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
                    <option value="🌸">🌸 Bunga Sakura</option>
                    <option value="🌷" selected>🌷 Bunga Tulip</option>
                    <option value="📖">📖 Buku / Fikih</option>
                    <option value="🌿">🌿 Daun / Suci</option>
                    <option value="🩷">🩷 Hati Merah Muda</option>
                    <option value="📝">📝 Catatan / Jurnal</option>
                    <option value="💡">💡 Tips / Wawasan</option>
                    <option value="🩺">🩺 Medis / Kesehatan</option>
                    <option value="🌼">🌼 Melati</option>
                </select>
            </div>
        </div>

        <!-- Banner Gambar (Cover) -->
        <div class="mb-3">
            <label class="form-label fw-semibold" style="color: #594765;">Gambar Sampul / Banner (Opsional)</label>
            <input type="file" name="image" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" accept="image/png, image/jpeg, image/webp">
            <small class="text-muted">Ukuran maksimal 5MB. Format disarankan lanskap (16:9 atau 4:3).</small>
        </div>

        <!-- Ringkasan / Deskripsi Singkat -->
        <div class="mb-3">
            <label class="form-label fw-semibold" style="color: #594765;">Ringkasan Singkat (Deskripsi) <span class="text-danger">*</span></label>
            <textarea name="description" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" rows="2" required placeholder="Tuliskan ringkasan 1-2 kalimat pengantar materi ini..."><?= e($_POST['description'] ?? '') ?></textarea>
            <small class="text-muted">Ditampilkan di kartu daftar materi dan pengantar bacaan.</small>
        </div>

        <!-- Isi Materi (Konten Lengkap) dengan Toolbar Format -->
        <div class="mb-3">
            <div class="d-flex justify-content-between align-items-center mb-2 flex-wrap gap-2">
                <label class="form-label fw-semibold mb-0" style="color: #594765;">Isi Lengkap Materi <span class="text-danger">*</span></label>
                <!-- Quick insert toolbar -->
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
            <textarea name="content" id="articleContent" class="form-control font-monospace" style="border-color: #e5d9ed; border-radius: 12px;" rows="12" required placeholder="Tuliskan isi materi lengkap di sini..."><?= e($_POST['content'] ?? '') ?></textarea>
            <small class="text-muted">Mendukung format HTML standar seperti &lt;h4&gt;, &lt;p&gt;, &lt;ul&gt;, &lt;li&gt;, &lt;strong&gt;, dan &lt;blockquote&gt;.</small>
        </div>

        <!-- Referensi / Rujukan -->
        <div class="mb-4">
            <label class="form-label fw-semibold" style="color: #594765;">Referensi & Rujukan (Opsional)</label>
            <textarea name="references_text" class="form-control" style="border-color: #e5d9ed; border-radius: 12px;" rows="3" placeholder="Kitab Fath al-Qarib, bab haid.&#10;Kitab Al-Majmu' karya Imam An-Nawawi.&#10;Kementerian Kesehatan RI."><?= e($_POST['references_text'] ?? '') ?></textarea>
            <small class="text-muted">Pisahkan setiap rujukan kitab atau sumber dengan baris baru (Enter).</small>
        </div>

        <!-- Status & Opsi Push Notifikasi -->
        <div class="p-3 rounded-4 mb-4" style="background-color: #fdf8fb; border: 1px solid #f0e3f0;">
            <div class="row g-3 align-items-center">
                <div class="col-md-4">
                    <label class="form-label fw-semibold mb-1" style="color: #594765;">Status Publikasi</label>
                    <select name="status" class="form-select" style="border-color: #e5d9ed; border-radius: 12px;">
                        <option value="published" selected>Publikasikan Sekarang (Terbit)</option>
                        <option value="draft">Simpan Sebagai Draft</option>
                    </select>
                </div>
                <div class="col-md-8">
                    <div class="form-check mt-3">
                        <input class="form-check-input" type="checkbox" name="send_notification" value="1" id="sendNotifCheck" checked>
                        <label class="form-check-label fw-bold" for="sendNotifCheck" style="color: #a36f91;">
                            📢 Kirim push notifikasi langsung ke semua pengguna aktif
                        </label>
                        <div class="small text-muted">
                            Pengguna akan menerima notifikasi "Materi Baru: [Judul]" dengan tautan langsung ke artikel ini.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- TOMBOL AKSI DENGAN STYLE DAN KETERANGAN -->
        <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 pt-3 border-top">
            <div class="small text-muted">
                <i class="bi bi-info-circle me-1"></i> Materi yang diterbitkan dapat langsung dibaca oleh pengguna di NISAYA.
            </div>
            <div class="d-flex gap-2">
                <a href="articles.php" class="btn btn-light border px-4" style="border-radius: 12px;">
                    <i class="bi bi-x-circle me-1"></i> Batal
                </a>
                <button type="submit" class="btn btn-admin-primary px-4 py-2">
                    <i class="bi bi-cloud-arrow-up-fill me-1"></i> Simpan & Publikasikan
                </button>
            </div>
        </div>
    </form>
</div>

<script>
    // Toggle input kategori kustom
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

    // Helper untuk menyisipkan tag formatting ke textarea
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