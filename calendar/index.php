<?php

require_once '../config/app.php';
require_login();

$uid = (int) $_SESSION['user_id'];

$month = $_GET['month'] ?? date('Y-m');

// Validasi format bulan.
if (
    !preg_match('/^\d{4}-\d{2}$/', $month) ||
    !checkdate(
        (int) substr($month, 5, 2),
        1,
        (int) substr($month, 0, 4)
    )
) {
    $month = date('Y-m');
}

// Persiapan kalender.
$first = new DateTimeImmutable($month . '-01');
$days = (int) $first->format('t');
$offset = (int) $first->format('N') - 1;

$prev = $first->modify('-1 month')->format('Y-m');
$next = $first->modify('+1 month')->format('Y-m');

$today = date('Y-m-d');

// Ambil riwayat menstruasi.
$q = $pdo->prepare(
    'SELECT id, start_date, end_date, status
     FROM menstrual_records
     WHERE user_id = ?
     ORDER BY start_date DESC'
);

$q->execute([$uid]);
$records = $q->fetchAll();

// ==========================================
// MENENTUKAN STATUS SETIAP TANGGAL
// ==========================================

$calendarStatusByDate = [];

// Urutkan catatan dari yang paling lama ke paling baru.
$chronologicalRecords = array_reverse($records);

// Batas hari ke-15 dari rangkaian yang sedang dihitung.
$activeHaidUntil = null;

foreach ($chronologicalRecords as $record) {

    if (empty($record['start_date'])) {
        continue;
    }

    try {
        $start = new DateTimeImmutable($record['start_date']);

        $end = !empty($record['end_date'])
            ? new DateTimeImmutable($record['end_date'])
            : new DateTimeImmutable($today);

        if ($end < $start) {
            continue;
        }

        // Catatan pertama menjadi awal rangkaian.
        if ($activeHaidUntil === null) {
            $activeHaidUntil = $start->modify('+14 days');

            $currentDate = $start;

            while ($currentDate <= $end) {
                $dateString = $currentDate->format('Y-m-d');

                $calendarStatusByDate[$dateString] =
                    ($currentDate <= $activeHaidUntil)
                    ? 'haid'
                    : 'istihadhoh';

                $currentDate = $currentDate->modify('+1 day');
            }

            continue;
        }

        // Jika catatan baru dimulai setelah batas hari ke-15.
        if ($start > $activeHaidUntil) {

            // Hitung hari penuh di antara batas haid dan catatan baru.
            $cleanDays = max(
                (int) $activeHaidUntil->diff($start)->days - 1,
                0
            );
            // Periksa catatan sebelumnya.
            $previousRecordQuery = $pdo->prepare("
                SELECT start_date, end_date
                FROM menstrual_records
                WHERE user_id = ?
                AND start_date < ?
                AND end_date IS NOT NULL
                AND status = 'completed'
                ORDER BY start_date DESC
                LIMIT 1
            ");

            $previousRecordQuery->execute([
                $uid,
                $record['start_date']
            ]);

            $previousRecord = $previousRecordQuery->fetch(PDO::FETCH_ASSOC);

            if ($previousRecord) {

                $previousStart = new DateTimeImmutable(
                    $previousRecord['start_date']
                );

                $previousEnd = new DateTimeImmutable(
                    $previousRecord['end_date']
                );

                // Batasi akhir catatan sebelumnya sampai maksimal hari ke-15.
                $previousEffectiveEnd = $previousStart->modify('+14 days');

                if ($previousEnd < $previousEffectiveEnd) {
                    $previousEffectiveEnd = $previousEnd;
                }

                // Hitung hari penuh setelah batas efektif tersebut.
                $cleanDays = max(
                    (int) $previousEffectiveEnd->diff($start)->days - 1,
                    0
                );
            }
            // Jika masa suci sudah 15 hari penuh, mulai rangkaian baru.
            if ($cleanDays >= 15) {
                $activeHaidUntil = $start->modify('+14 days');

                $currentDate = $start;

                while ($currentDate <= $end) {
                    $dateString = $currentDate->format('Y-m-d');

                    $calendarStatusByDate[$dateString] =
                        ($currentDate <= $activeHaidUntil)
                        ? 'haid'
                        : 'istihadhoh';

                    $currentDate = $currentDate->modify('+1 day');
                }

                continue;
            }

            // Masa suci belum mencapai 15 hari.
            // Tanggal tercatat setelah batas haid sebelumnya perlu ditinjau.
            $gapStart = $activeHaidUntil->modify('+1 day');
            $gapEnd = $start->modify('-1 day');

            $gapDate = $gapStart;

            while ($gapDate <= $gapEnd) {
                $gapDateString = $gapDate->format('Y-m-d');

                // Ubah tanggal istihadhoh yang tercatat dalam rentang ini
                // menjadi perlu ditinjau.
                if (
                    isset($calendarStatusByDate[$gapDateString]) &&
                    $calendarStatusByDate[$gapDateString] === 'istihadhoh'
                ) {
                    $calendarStatusByDate[$gapDateString] = 'perlu_ditinjau';
                }

                $gapDate = $gapDate->modify('+1 day');
            }

            // Hitung berapa hari lagi sampai masa suci mencapai 15 hari.
            $remainingDays = 15 - $cleanDays;

            $reviewUntil = $start->modify(
                '+' . ($remainingDays - 1) . ' days'
            );

            $reviewEnd = ($reviewUntil < $end) ? $reviewUntil : $end;

            // Tandai awal catatan sebagai perlu ditinjau.
            $currentDate = $start;

            while ($currentDate <= $reviewEnd) {
                $calendarStatusByDate[$currentDate->format('Y-m-d')] = 'perlu_ditinjau';

                $currentDate = $currentDate->modify('+1 day');
            }

            // Jika catatan masih berlanjut setelah masa suci mencapai 15 hari,
            // bagian berikutnya menjadi awal rangkaian baru.
            if ($reviewUntil < $end) {
                $newHaidStart = $reviewUntil->modify('+1 day');
                $activeHaidUntil = $newHaidStart->modify('+14 days');

                $currentDate = $newHaidStart;

                while ($currentDate <= $end) {
                    $dateString = $currentDate->format('Y-m-d');

                    $calendarStatusByDate[$dateString] =
                        ($currentDate <= $activeHaidUntil)
                        ? 'haid'
                        : 'istihadhoh';

                    $currentDate = $currentDate->modify('+1 day');
                }
            }

            continue;
        }

        // Catatan baru dimulai sebelum atau tepat pada batas hari ke-15.
        // Bagian sampai batas tersebut ditandai Haid, sisanya Istihadhoh
        // sementara. Jika catatan berikutnya datang sebelum 15 hari suci,
        // bagian setelah batas akan diubah menjadi Perlu ditinjau.
        $currentDate = $start;

        while ($currentDate <= $end) {
            $dateString = $currentDate->format('Y-m-d');

            $calendarStatusByDate[$dateString] =
                ($currentDate <= $activeHaidUntil)
                ? 'haid'
                : 'istihadhoh';

            $currentDate = $currentDate->modify('+1 day');
        }
    } catch (Exception $e) {
        // Lewati catatan jika tanggal tidak dapat diproses.
        continue;
    }
}

// Pengaturan halaman.
$pageTitle = 'Kalender';
$active = 'calendar';

require '../includes/header.php';

?>

<!-- HEADER KALENDER -->
<section class="calendar-hero mb-4">
    <div>
        <span class="calendar-eyebrow">
            YOUR CYCLE JOURNAL 🌷
        </span>

        <h2>Kalender Siklus</h2>

        <p>
            Catat dan kenali pola siklusmu dengan lebih mudah.
        </p>
    </div>

    <div class="calendar-hero-icon">🗓️</div>
</section>

<!-- KALENDER DAN INFORMASI -->
<div class="row g-4 calendar-layout">

    <!-- KALENDER -->
    <div class="col-lg-8">
        <div class="calendar-panel">

            <!-- NAVIGASI BULAN -->
            <div class="calendar-heading">
                <div>
                    <span class="calendar-small-label">
                        KALENDER BULANAN
                    </span>

                    <h3>
                        <?= e($first->format('F Y')) ?>
                    </h3>
                </div>

                <div class="calendar-navigation">
                    <a
                        href="?month=<?= e($prev) ?>"
                        aria-label="Bulan sebelumnya">
                        ‹
                    </a>

                    <a
                        href="?month=<?= e(date('Y-m')) ?>"
                        class="calendar-today-button">
                        Hari ini
                    </a>

                    <a
                        href="?month=<?= e($next) ?>"
                        aria-label="Bulan berikutnya">
                        ›
                    </a>
                </div>
            </div>

            <!-- NAMA HARI -->
            <div class="calendar-grid calendar-weekdays">

                <?php foreach (['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'] as $d): ?>
                    <div><?= $d ?></div>
                <?php endforeach; ?>

            </div>

            <!-- TANGGAL -->
            <div class="calendar-grid calendar-dates">

                <?php for ($i = 0; $i < $offset; $i++): ?>
                    <div class="calendar-empty"></div>
                <?php endfor; ?>

                <?php for ($day = 1; $day <= $days; $day++): ?>

                    <?php
                    $date = $first
                        ->setDate(
                            (int) $first->format('Y'),
                            (int) $first->format('m'),
                            $day
                        )
                        ->format('Y-m-d');

                    $statusTanggal = $calendarStatusByDate[$date] ?? null;
                    $isToday = $date === $today;
                    ?>

                    <div class="calendar-date
                        <?= $statusTanggal === 'haid' ? 'period' : '' ?>
                        <?= $statusTanggal === 'istihadhoh' ? 'istihadah' : '' ?>
                        <?= $statusTanggal === 'perlu_ditinjau' ? 'needs-review' : '' ?>
                        <?= $isToday ? 'today' : '' ?>">

                        <span class="date-number">
                            <?= $day ?>
                        </span>

                        <?php if ($statusTanggal === 'haid'): ?>

                            <span class="date-indicator">
                                ● Haid
                            </span>

                        <?php elseif ($statusTanggal === 'istihadhoh'): ?>

                            <span class="date-indicator">
                                ● Istihadhoh
                            </span>

                        <?php elseif ($statusTanggal === 'perlu_ditinjau'): ?>

                            <span class="date-indicator">
                                ● Perlu ditinjau
                            </span>

                        <?php endif; ?>

                        <?php if ($isToday): ?>
                            <span class="today-label">
                                Hari ini
                            </span>
                        <?php endif; ?>

                    </div>

                <?php endfor; ?>

            </div>

            <!-- LEGENDA -->
            <div class="calendar-legend">

                <div>
                    <span class="legend-dot legend-period"></span>
                    Haid
                </div>

                <div>
                    <span class="legend-dot legend-istihadah"></span>
                    Istihadhah
                </div>

                <div>
                    <span class="legend-dot legend-review"></span>
                    Perlu ditinjau
                </div>

                <div>
                    <span class="legend-dot legend-today"></span>
                    Hari ini
                </div>

            </div>

        </div>
    </div>

    <!-- INFORMASI SIKLUS -->
    <div class="col-lg-4">

        <!-- KARTU CATATAN SIKLUS -->
        <div class="calendar-info-card mb-3">

            <div class="info-card-icon">🌸</div>

            <span class="calendar-small-label">
                CATATAN SIKLUS
            </span>

            <h4>Kenali pola tubuhmu</h4>

            <p>
                Catat tanggal mulai dan selesai menstruasi
                secara rutin untuk membantu mengenali pola siklusmu.
            </p>

            <a
                href="../history/index.php"
                class="calendar-info-link">
                Buka riwayat →
            </a>

        </div>

        <!-- KARTU INFORMASI -->
        <div class="calendar-note-card">

            <div class="info-card-icon">💡</div>

            <h5>Informasi kalender</h5>

            <p>
                Kalender ini menampilkan tanggal menstruasi
                yang sudah kamu catat. Tanggal yang belum tercatat
                tidak berarti bebas menstruasi.
            </p>

            <p>
                Klasifikasi Haid dan Istihadhoh menggunakan
                perhitungan sederhana dan belum mencakup seluruh
                ketentuan fiqih Syafi'i.
            </p>

            <p class="mb-0">
                Informasi kalender bukan diagnosis medis atau
                kepastian prediksi menstruasi.
            </p>

        </div>

    </div>

</div>

<?php require '../includes/footer.php'; ?>