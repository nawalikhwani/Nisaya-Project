<?php require_once '../config/app.php';
require_login();

$uid = $_SESSION['user_id'];
$rid = (int)($_GET['record_id'] ?? 0);
$q = $pdo->prepare('SELECT * FROM menstrual_records WHERE id=? AND user_id=?');
$q->execute([$rid, $uid]);
$record = $q->fetch();
if (!$record) {
    flash('error', 'Catatan tidak ditemukan.');
    header('Location: ../history/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $action = $_POST['action'] ?? 'add';

    // HAPUS CATATAN PENGAMATAN
    if ($action === 'delete') {
        $eventId = (int) ($_POST['event_id'] ?? 0);

        $delete = $pdo->prepare(
            'DELETE be
     FROM bleeding_events AS be
     INNER JOIN menstrual_records AS mr
        ON mr.id = be.record_id
     WHERE be.id = ?
       AND be.record_id = ?
       AND mr.user_id = ?'
        );

        $delete->execute([$eventId, $rid, $uid]);
        if ($delete->rowCount() > 0) {
            flash('success', 'Pengamatan berhasil dihapus.');
        } else {
            flash('error', 'Pengamatan tidak ditemukan.');
        }
    } else {
        // TAMBAH CATATAN PENGAMATAN
        $start = $_POST['started_at'] ?? '';
        $end = $_POST['ended_at'] ?: null;
        $obs = $_POST['observation'] ?? '';

        if (
            !$start ||
            !in_array($obs, ['darah', 'bercak', 'berhenti']) ||
            ($end && $end < $start)
        ) {
            flash('error', 'Periksa waktu dan jenis pengamatan.');
        } else {
            $pdo->prepare(
                'INSERT INTO bleeding_events
                (record_id, started_at, ended_at, observation, notes)
                VALUES (?, ?, ?, ?, ?)'
            )->execute([
                $rid,
                $start,
                $end,
                $obs,
                trim($_POST['notes'] ?? '')
            ]);

            flash('success', 'Pengamatan tersimpan.');
        }
    }

    header('Location: ?record_id=' . $rid);
    exit;
}

$q = $pdo->prepare(
    'SELECT be.*
     FROM bleeding_events AS be
     INNER JOIN menstrual_records AS mr
        ON mr.id = be.record_id
     WHERE be.record_id = ?
       AND mr.user_id = ?
     ORDER BY be.started_at'
);

$q->execute([$rid, $uid]);
$events = $q->fetchAll();

$pageTitle = 'Catatan Pengamatan';
require '../includes/header.php';
?>

<h2>Catatan Pengamatan</h2>
<p class="muted">
    Untuk catatan mulai <?= e($record['start_date']) ?>
</p>

<!-- FORM TAMBAH PENGAMATAN -->
<div class="soft-card p-4 mb-3">
    <form method="post">
        <input type="hidden" name="csrf"
            value="<?= e(csrf_token()) ?>">

        <label class="form-label">Mulai</label>
        <input class="form-control mb-3"
            type="datetime-local"
            name="started_at"
            required>

        <label class="form-label">Selesai (opsional)</label>
        <input class="form-control mb-3"
            type="datetime-local"
            name="ended_at">

        <label class="form-label">Pengamatan</label>
        <select class="form-select mb-3" name="observation">
            <option value="darah">Darah</option>
            <option value="bercak">Bercak</option>
            <option value="berhenti">Berhenti</option>
        </select>

        <label class="form-label">Catatan</label>
        <textarea class="form-control mb-3"
            name="notes"></textarea>

        <button class="btn btn-primary">Simpan</button>
    </form>
</div>

<!-- RIWAYAT PENGAMATAN -->
<div class="soft-card p-3">
    <h5>Riwayat Pengamatan</h5>

    <?php if (!$events): ?>
        <p class="muted">Belum ada pengamatan.</p>
    <?php else: ?>

        <?php foreach ($events as $ev): ?>
            <div class="py-3 border-bottom">

                <div class="d-flex justify-content-between align-items-center flex-wrap gap-2 mb-2">
                    <strong><?= e($ev['started_at']) ?></strong>

                    <span class="badge bg-light text-dark">
                        <?= e(ucfirst($ev['observation'])) ?>
                    </span>
                </div>

                <?php if (!empty($ev['ended_at'])): ?>
                    <p class="muted mb-2">
                        Selesai: <?= e($ev['ended_at']) ?>
                    </p>
                <?php endif; ?>

                <?php if (!empty($ev['notes'])): ?>
                    <p class="mb-2"><?= e($ev['notes']) ?></p>
                <?php endif; ?>

                <form method="post"
                    onsubmit="return confirm('Yakin ingin menghapus pengamatan ini?')">

                    <input type="hidden" name="csrf"
                        value="<?= e(csrf_token()) ?>">

                    <input type="hidden" name="action" value="delete">

                    <input type="hidden" name="event_id"
                        value="<?= (int) $ev['id'] ?>">

                    <button type="submit"
                        class="btn btn-sm btn-outline-danger">
                        Hapus
                    </button>
                </form>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>
</div>

<?php require '../includes/footer.php'; ?>