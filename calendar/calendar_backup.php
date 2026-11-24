<?php

require_once '../config/app.php';
require_login();

$uid = $_SESSION['user_id'];

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

// Ambil riwayat menstruasi.
$q = $pdo->prepare(
    'SELECT start_date, end_date, status
     FROM menstrual_records
     WHERE user_id = ?
     ORDER BY start_date DESC'
);

$q->execute([$uid]);
$records = $q->fetchAll();

// Pengaturan halaman.
$pageTitle = 'Kalender';
$active = 'calendar';

$today = date('Y-m-d');

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
<div class="row g-4">

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

                    $statusTanggal = null;

                    foreach ($records as $r) {

                        $start = new DateTimeImmutable($r['start_date']);

                        $end = $r['end_date']
                            ? new DateTimeImmutable($r['end_date'])
                            : new DateTimeImmutable($today);

                        $current = new DateTimeImmutable($date);

                        if ($current < $start || $current > $end) {
                            continue;
                        }

                        $hariKe = (int) $start->diff($current)->days + 1;

                        if ($hariKe <= 15) {
                            $statusTanggal = 'haid';
                        } else {
                            $statusTanggal = 'istihadhoh';
                        }

                        break;
                    }

                    $isToday = $date === $today;
                    ?>

                    <div class="calendar-date
                        <?= $statusTanggal === 'haid' ? 'period' : '' ?>
                        <?= $statusTanggal === 'istihadhoh' ? 'istihadah' : '' ?>
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
                    Istihadhoh
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