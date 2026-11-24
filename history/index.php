<?php
require_once '../config/app.php';
require_login();

$uid = (int) $_SESSION['user_id'];

/**
 * Sinkronisasi notifikasi berdasarkan riwayat menstruasi terbaru.
 */
function refreshNisayaNotifications(PDO $pdo, int $uid): void
{
    // Hapus notifikasi yang catatan sumbernya sudah tidak tersedia.
    $q = $pdo->prepare("
        DELETE n
        FROM notifications n
        LEFT JOIN menstrual_records m
            ON m.id = n.record_id
            AND m.user_id = n.user_id
        WHERE n.user_id = ?
          AND n.record_id IS NOT NULL
          AND m.id IS NULL
    ");
    $q->execute([$uid]);

    // Hapus notifikasi pemeriksaan lama yang tidak terhubung ke catatan.
    // Notifikasi akan dibuat ulang berdasarkan riwayat terbaru.
    $q = $pdo->prepare("
        DELETE FROM notifications
        WHERE user_id = ?
          AND record_id IS NULL
          AND title IN (
              'Pengingat peninjauan haid dan istihadhah',
              'Peringatan kemungkinan istihadhah'
          )
    ");
    $q->execute([$uid]);

    // Buat kembali notifikasi sesuai data riwayat terbaru.
    require_once __DIR__ . '/../notifications/generate.php';
}

/**
 * Proses tambah, edit, dan hapus catatan.
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? '';
    $returnPage = max(1, (int) ($_POST['page'] ?? 1));

    // Hapus catatan.
    if ($action === 'delete') {
        $id = (int) ($_POST['id'] ?? 0);

        $q = $pdo->prepare(
            'DELETE FROM menstrual_records
             WHERE id = ? AND user_id = ?'
        );
        $q->execute([$id, $uid]);

        $deleted = $q->rowCount() > 0;

        if ($deleted) {
            refreshNisayaNotifications($pdo, $uid);
        }

        flash(
            $deleted ? 'success' : 'error',
            $deleted
                ? 'Catatan berhasil dihapus.'
                : 'Catatan tidak ditemukan.'
        );

        header('Location: index.php?page=' . $returnPage . '#riwayat');
        exit;
    }

    // Tambah atau edit catatan.
    if (in_array($action, ['add', 'edit'], true)) {
        $id = (int) ($_POST['id'] ?? 0);
        $start = trim($_POST['start_date'] ?? '');
        $end = trim($_POST['end_date'] ?? '');
        $end = $end !== '' ? $end : null;
        $notes = trim($_POST['notes'] ?? '');

        $validStart = DateTime::createFromFormat('!Y-m-d', $start);
        $validEnd = $end === null
            ? true
            : (DateTime::createFromFormat('!Y-m-d', $end) !== false);

        if (
            !$validStart ||
            $validStart->format('Y-m-d') !== $start ||
            !$validEnd ||
            ($end !== null && $end < $start)
        ) {
            flash(
                'error',
                'Tanggal tidak valid. Periksa kembali tanggal mulai dan selesai.'
            );
            header('Location: index.php?page=' . $returnPage . '#riwayat');
            exit;
        }

        $status = $end !== null ? 'completed' : 'ongoing';

        if ($action === 'edit') {
            // Pastikan catatan benar-benar milik pengguna yang sedang login.
            $check = $pdo->prepare(
                'SELECT id
                 FROM menstrual_records
                 WHERE id = ? AND user_id = ?
                 LIMIT 1'
            );
            $check->execute([$id, $uid]);

            if (!$check->fetchColumn()) {
                flash(
                    'error',
                    'Catatan tidak ditemukan atau bukan milik akunmu.'
                );
                header('Location: index.php?page=' . $returnPage);
                exit;
            }

            $q = $pdo->prepare(
                'UPDATE menstrual_records
                 SET start_date = ?,
                     end_date = ?,
                     status = ?,
                     notes = ?,
                     updated_at = NOW()
                 WHERE id = ? AND user_id = ?'
            );
            $q->execute([$start, $end, $status, $notes, $id, $uid]);

            flash('success', 'Catatan berhasil diperbarui.');
        } else {
            $q = $pdo->prepare(
                'INSERT INTO menstrual_records
                    (user_id, start_date, end_date, status, notes)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $q->execute([$uid, $start, $end, $status, $notes]);

            flash('success', 'Catatan berhasil disimpan.');
            $returnPage = 1;
        }

        refreshNisayaNotifications($pdo, $uid);

        header('Location: index.php?page=' . $returnPage);
        exit;
    }

    flash('error', 'Aksi tidak dikenali.');
    header('Location: index.php');
    exit;
}

/**
 * Ambil data catatan yang akan diedit, jika ada.
 */
$editRecord = null;

if (isset($_GET['edit'])) {
    $editId = (int) $_GET['edit'];

    if ($editId <= 0) {
        flash('error', 'ID catatan tidak valid.');
        header('Location: index.php');
        exit;
    }

    $q = $pdo->prepare(
        'SELECT *
         FROM menstrual_records
         WHERE id = ? AND user_id = ?
         LIMIT 1'
    );
    $q->execute([$editId, $uid]);
    $editRecord = $q->fetch(PDO::FETCH_ASSOC);

    if (!$editRecord) {
        flash('error', 'Catatan tidak ditemukan.');
        header('Location: index.php');
        exit;
    }
}

/* Statistik seluruh riwayat. */
$q = $pdo->prepare(
    "SELECT
        COUNT(*) AS total,
        COALESCE(SUM(CASE WHEN status = 'ongoing' THEN 1 ELSE 0 END), 0) AS ongoing_count,
        COALESCE(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END), 0) AS completed_count
     FROM menstrual_records
     WHERE user_id = ?"
);
$q->execute([$uid]);
$stats = $q->fetch(PDO::FETCH_ASSOC) ?: [];

$totalRecords = (int) ($stats['total'] ?? 0);
$ongoingCount = (int) ($stats['ongoing_count'] ?? 0);
$completedCount = (int) ($stats['completed_count'] ?? 0);

/* Pagination. */
$perPage = 5;
$totalPages = max(1, (int) ceil($totalRecords / $perPage));
$page = max(1, (int) ($_GET['page'] ?? 1));
$page = min($page, $totalPages);
$offset = ($page - 1) * $perPage;

/* Ambil catatan sesuai halaman. */
$q = $pdo->prepare(
    'SELECT *
     FROM menstrual_records
     WHERE user_id = ?
     ORDER BY start_date DESC, id DESC
     LIMIT ? OFFSET ?'
);
$q->bindValue(1, $uid, PDO::PARAM_INT);
$q->bindValue(2, $perPage, PDO::PARAM_INT);
$q->bindValue(3, $offset, PDO::PARAM_INT);
$q->execute();
$records = $q->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = 'Riwayat Menstruasi';
$active = 'history';

require '../includes/header.php';
?>

<div class="history-page">

    <!-- HEADER -->
    <section class="history-hero mb-4">
        <div>
            <span class="history-eyebrow">CATATAN SIKLUSMU</span>
            <h2>Riwayat Menstruasi 🌷</h2>
            <p>
                Setiap catatan membantu kamu mengenali pola siklus
                menstruasimu dari waktu ke waktu.
            </p>
        </div>

        <div class="history-hero-icon">🩷</div>
    </section>

    <!-- STATISTIK -->
    <div class="row g-3 mb-4">

        <div class="col-md-4">
            <div class="history-stat stat-pink">
                <div class="history-stat-icon">📖</div>
                <div>
                    <div class="history-stat-label">Total catatan</div>
                    <div class="history-stat-value"><?= $totalRecords ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="history-stat stat-purple">
                <div class="history-stat-icon">🌸</div>
                <div>
                    <div class="history-stat-label">Selesai</div>
                    <div class="history-stat-value"><?= $completedCount ?></div>
                </div>
            </div>
        </div>

        <div class="col-md-4">
            <div class="history-stat stat-peach">
                <div class="history-stat-icon">🌱</div>
                <div>
                    <div class="history-stat-label">Berlangsung</div>
                    <div class="history-stat-value"><?= $ongoingCount ?></div>
                </div>
            </div>
        </div>

    </div>

    <!-- FORM TAMBAH DAN EDIT -->
    <section class="history-form-card mb-4" id="add-form">

        <div class="history-section-heading">
            <div>
                <span class="section-kicker">
                    <?= $editRecord ? 'PERBARUI SIKLUSMU' : 'CATAT SIKLUSMU' ?>
                </span>

                <h5>
                    <?= $editRecord
                        ? 'Edit catatan menstruasi ✏️'
                        : 'Tambah catatan baru' ?>
                </h5>

                <p>
                    <?= $editRecord
                        ? 'Perbarui tanggal menstruasi sesuai catatan terbarumu.'
                        : 'Isi tanggal menstruasimu untuk menyimpan riwayat.' ?>
                </p>
            </div>

            <div class="section-icon">
                <?= $editRecord ? '♡' : '✎' ?>
            </div>
        </div>

        <form method="post" action="index.php" class="row g-3">

            <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
            <input
                type="hidden"
                name="action"
                value="<?= $editRecord ? 'edit' : 'add' ?>">
            <input
                type="hidden"
                name="id"
                value="<?= $editRecord ? (int) $editRecord['id'] : '' ?>">
            <input type="hidden" name="page" value="<?= $page ?>">

            <div class="col-md-6">
                <label class="form-label">Tanggal mulai</label>
                <input
                    type="date"
                    name="start_date"
                    class="form-control"
                    value="<?= e($editRecord['start_date'] ?? '') ?>"
                    required>
            </div>

            <div class="col-md-6">
                <label class="form-label">
                    Tanggal selesai
                    <span class="optional-label">(opsional)</span>
                </label>
                <input
                    type="date"
                    name="end_date"
                    class="form-control"
                    value="<?= e($editRecord['end_date'] ?? '') ?>">
                <small class="text-muted">
                    Kosongkan jika menstruasi masih berlangsung.
                </small>
            </div>

            <div class="col-12">
                <label class="form-label">Catatan tambahan</label>
                <textarea
                    name="notes"
                    class="form-control"
                    rows="3"
                    placeholder="Contoh: kondisi tubuh atau keluhan yang ingin dicatat..."><?= e($editRecord['notes'] ?? '') ?></textarea>
            </div>

            <div class="col-12 d-flex justify-content-end gap-2 flex-wrap">

                <?php if ($editRecord): ?>
                    <a
                        href="index.php?page=<?= $page ?>"
                        class="btn btn-light">
                        Batal
                    </a>
                <?php endif; ?>

                <button type="submit" class="btn history-save-btn">
                    <?php if ($editRecord): ?>
                        Simpan perubahan
                    <?php else: ?>
                        <span>＋</span> Simpan catatan
                    <?php endif; ?>
                </button>

            </div>

        </form>
    </section>

    <!-- TABEL RIWAYAT -->
    <section class="history-table-card" id="riwayat">
        <div class="history-section-heading">
            <div>
                <span class="section-kicker">PERJALANAN SIKLUS</span>
                <h5>Catatan menstruasimu</h5>
                <p>Daftar riwayat yang sudah kamu simpan.</p>
            </div>

            <div class="section-icon">♡</div>
        </div>

        <div class="table-responsive">
            <table class="table history-table align-middle">

                <thead>
                    <tr>
                        <th>No.</th>
                        <th>Tanggal mulai</th>
                        <th>Tanggal selesai</th>
                        <th>Status</th>
                        <th>Catatan</th>
                        <th>Aksi</th>
                    </tr>
                </thead>

                <tbody>

                    <?php if ($records): ?>

                        <?php foreach ($records as $index => $r): ?>
                            <tr>

                                <td>
                                    <span class="history-number">
                                        <?= $offset + $index + 1 ?>
                                    </span>
                                </td>

                                <td>
                                    <strong><?= e($r['start_date']) ?></strong>
                                </td>

                                <td>
                                    <?= e($r['end_date'] ?: '—') ?>
                                </td>

                                <td>
                                    <?php if ($r['status'] === 'ongoing'): ?>
                                        <span class="status-pill status-ongoing">
                                            ● Berlangsung
                                        </span>
                                    <?php else: ?>
                                        <span class="status-pill status-completed">
                                            ✓ Selesai
                                        </span>
                                    <?php endif; ?>
                                </td>

                                <td>
                                    <span class="history-notes">
                                        <?= e($r['notes'] ?: 'Tidak ada catatan') ?>
                                    </span>
                                </td>

                                <td>
                                    <div class="history-actions">

                                        <a
                                            class="action-btn action-observe"
                                            href="../bleeding-events/index.php?record_id=<?= (int) $r['id'] ?>">
                                            Pengamatan
                                        </a>

                                        <a
                                            class="action-btn action-edit"
                                            href="index.php?edit=<?= (int) $r['id'] ?>&page=<?= $page ?>#add-form">
                                            Edit
                                        </a>

                                        <button
                                            type="button"
                                            class="action-btn action-delete"
                                            data-bs-toggle="modal"
                                            data-bs-target="#deleteRecordModal<?= (int) $r['id'] ?>">
                                            Hapus
                                        </button>
                                        </form>

                                    </div>
                                </td>

                            </tr>
                        <?php endforeach; ?>

                    <?php else: ?>
                        <tr>
                            <td colspan="6">
                                <div class="history-empty">
                                    <div>🌷</div>
                                    <h6>Belum ada riwayat menstruasi</h6>
                                    <p>
                                        Catatan yang kamu simpan akan muncul
                                        di bagian ini.
                                    </p>
                                </div>
                            </td>
                        </tr>
                    <?php endif; ?>

                </tbody>
            </table>
        </div>

        <!-- MODAL HAPUS CATATAN -->
        <?php foreach ($records as $r): ?>

            <div
                class="modal fade"
                id="deleteRecordModal<?= (int) $r['id'] ?>"
                tabindex="-1"
                aria-hidden="true">
                <div class="modal-dialog modal-dialog-centered">
                    <div class="modal-content border-0 shadow">

                        <div class="modal-header">
                            <h5 class="modal-title">Hapus Catatan</h5>
                            <button
                                type="button"
                                class="btn-close"
                                data-bs-dismiss="modal"></button>
                        </div>

                        <div class="modal-body text-center py-4">
                            <div class="fs-1 mb-3">🗑️</div>
                            <h6>Yakin ingin menghapus catatan ini?</h6>
                            <p class="text-muted small mb-0">
                                Catatan yang sudah dihapus tidak dapat dikembalikan.
                            </p>
                        </div>

                        <div class="modal-footer justify-content-center">

                            <button
                                type="button"
                                class="btn btn-light"
                                data-bs-dismiss="modal">
                                Batal
                            </button>

                            <form method="post" action="index.php">
                                <input
                                    type="hidden"
                                    name="csrf"
                                    value="<?= e(csrf_token()) ?>">
                                <input type="hidden" name="action" value="delete">
                                <input
                                    type="hidden"
                                    name="id"
                                    value="<?= (int) $r['id'] ?>">
                                <input
                                    type="hidden"
                                    name="page"
                                    value="<?= (int) $page ?>">

                                <button type="submit" class="btn btn-danger">
                                    Ya, Hapus
                                </button>
                            </form>

                        </div>
                    </div>
                </div>
            </div>

        <?php endforeach; ?>

        <!-- PAGINATION -->
        <?php if ($totalPages > 1): ?>
            <nav
                class="history-pagination"
                aria-label="Navigasi halaman riwayat">

                <span class="history-pagination-info">
                    Menampilkan
                    <?= $offset + 1 ?>–<?= min($offset + count($records), $totalRecords) ?>
                    dari <?= $totalRecords ?> catatan
                </span>

                <div class="history-pagination-links">

                    <?php if ($page > 1): ?>
                        <a
                            href="?page=<?= $page - 1 ?>"
                            aria-label="Halaman sebelumnya">‹</a>
                    <?php else: ?>
                        <span class="disabled" aria-disabled="true">‹</span>
                    <?php endif; ?>

                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <?php if ($p === $page): ?>
                            <span class="current" aria-current="page">
                                <?= $p ?>
                            </span>
                        <?php else: ?>
                            <a href="?page=<?= $p ?>">
                                <?= $p ?>
                            </a>
                        <?php endif; ?>
                    <?php endfor; ?>

                    <?php if ($page < $totalPages): ?>
                        <a
                            href="?page=<?= $page + 1 ?>"
                            aria-label="Halaman berikutnya">›</a>
                    <?php else: ?>
                        <span class="disabled" aria-disabled="true">›</span>
                    <?php endif; ?>

                </div>
            </nav>
        <?php endif; ?>

    </section>


    <!-- REMINDER -->
    <div class="history-reminder mt-4">
        <span>💗</span>
        <p>
            Setiap siklus bisa berbeda. Catat perubahan yang kamu alami
            agar kamu lebih mudah mengenali pola tubuhmu.
        </p>
    </div>

</div>



<?php require '../includes/footer.php'; ?>