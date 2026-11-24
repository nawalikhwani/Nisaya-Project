
<?php

/**
 * Generator notifikasi NISAYA.
 * Dipanggil dari dashboard/halaman notifikasi.
 */

if (!isset($pdo, $uid)) {
    return;
}

$today = new DateTimeImmutable('today');

// Ambil riwayat menstruasi.
$q = $pdo->prepare("
    SELECT id, start_date, end_date, status
    FROM menstrual_records
    WHERE user_id = ?
    ORDER BY start_date DESC
");
$q->execute([$uid]);
$records = $q->fetchAll(PDO::FETCH_ASSOC);


/**
 * Simpan atau perbarui notifikasi.
 * Jika isi berubah, notifikasi dianggap baru.
 */
function createNisayaNotification(
    PDO $pdo,
    int $uid,
    string $title,
    string $message,
    string $scheduledAt,
    ?int $recordId = null,
    ?string $predictionSource = null
): void {

    $check = $pdo->prepare("
        SELECT id, message, is_deleted
        FROM notifications
        WHERE user_id = ?
          AND title = ?
          AND prediction_source <=> ?
          AND record_id <=> ?
        LIMIT 1
    ");

    $check->execute([
        $uid,
        $title,
        $predictionSource,
        $recordId
    ]);

    $existing = $check->fetch(PDO::FETCH_ASSOC);

    if ($existing) {

        // Periksa apakah isi notifikasi berubah
        // atau sebelumnya sudah disembunyikan.
        $changed =
            $existing['message'] !== $message ||
            (int) $existing['is_deleted'] === 1;

        if ($changed) {
            $update = $pdo->prepare("
                UPDATE notifications
                SET message = ?,
                    scheduled_at = ?,
                    status = 'pending',
                    is_read = 0,
                    is_deleted = 0
                WHERE id = ?
                  AND user_id = ?
            ");

            $update->execute([
                $message,
                $scheduledAt,
                $existing['id'],
                $uid
            ]);
        }
    } else {

        $insert = $pdo->prepare("
            INSERT INTO notifications
                (user_id, record_id, title, message,
                 prediction_source, scheduled_at, status)
            VALUES (?, ?, ?, ?, ?, ?, 'pending')
        ");

        $insert->execute([
            $uid,
            $recordId,
            $title,
            $message,
            $predictionSource,
            $scheduledAt
        ]);
    }
}


/**
 * Sembunyikan notifikasi yang sudah tidak berlaku.
 */
function hideNisayaNotification(
    PDO $pdo,
    int $uid,
    string $title,
    int $recordId
): void {

    $hide = $pdo->prepare("
        UPDATE notifications
        SET is_deleted = 1
        WHERE user_id = ?
          AND title = ?
          AND record_id = ?
          AND is_deleted = 0
    ");

    $hide->execute([
        $uid,
        $title,
        $recordId
    ]);
}


// ==========================================
// 1. PENGINGAT PENINJAUAN HAID / ISTIHADHAH
// ==========================================

foreach ($records as $record) {

    if (empty($record['start_date'])) {
        continue;
    }

    try {
        $start = new DateTimeImmutable($record['start_date']);

        if (
            $record['status'] === 'completed' &&
            !empty($record['end_date'])
        ) {
            $end = new DateTimeImmutable($record['end_date']);
        } elseif ($record['status'] === 'ongoing') {
            $end = $today;
        } else {
            continue;
        }

        if ($end < $start) {
            continue;
        }

        $duration = (int) $start->diff($end)->days + 1;

        if ($duration > 15) {

            createNisayaNotification(
                $pdo,
                $uid,
                'Pengingat peninjauan haid dan istihadhah',

                'Periode yang ditinjau: ' .
                    $start->format('d-m-Y') . ' s.d. ' .
                    $end->format('d-m-Y') . '. ' .

                    'Catatan menstruasimu berlangsung lebih dari 15 hari. ' .
                    'Menurut ketentuan Mazhab Syafi’i, masa haid maksimum ' .
                    'adalah 15 hari 15 malam. Kondisi ini perlu ditinjau ' .
                    'lebih lanjut untuk menentukan status darah dan ' .
                    'kemungkinan istihadhah. Perhatikan kebiasaan haid ' .
                    'dan rincian fikih lainnya, serta konsultasikan ' .
                    'kepada guru fikih yang kompeten.',

                date('Y-m-d H:i:s'),
                (int) $record['id']
            );
        } else {

            // Hapus peringatan lama jika catatan sudah tidak lebih dari 15 hari.
            hideNisayaNotification(
                $pdo,
                $uid,
                'Pengingat peninjauan haid dan istihadhah',
                (int) $record['id']
            );
        }
    } catch (Exception $e) {
        error_log('NISAYA notification error: ' . $e->getMessage());
        continue;
    }
}


// ==========================================
// 2. PEMERIKSAAN MASA SUCI ANTAR CATATAN
// ==========================================

for ($i = 0; $i < count($records) - 1; $i++) {

    $newer = $records[$i];
    $older = $records[$i + 1];

    if (
        empty($older['start_date']) ||
        empty($older['end_date']) ||
        empty($newer['start_date'])
    ) {
        continue;
    }

    try {
        $olderStart = new DateTimeImmutable($older['start_date']);
        $olderEnd = new DateTimeImmutable($older['end_date']);
        $newerStart = new DateTimeImmutable($newer['start_date']);

        if (
            $newer['status'] === 'completed' &&
            !empty($newer['end_date'])
        ) {
            $newerEnd = new DateTimeImmutable($newer['end_date']);
        } elseif ($newer['status'] === 'ongoing') {
            $newerEnd = $today;
        } else {
            continue;
        }

        if (
            $newerStart <= $olderEnd ||
            $newerEnd < $newerStart
        ) {
            continue;
        }

        $olderDuration = (int) $olderStart->diff($olderEnd)->days + 1;

        if ($olderDuration > 15) {
            $effectiveHaidEnd = $olderStart->modify('+14 days');
        } else {
            $effectiveHaidEnd = $olderEnd;
        }

        $cleanDays = max(
            (int) $effectiveHaidEnd->diff($newerStart)->days - 1,
            0
        );

        $haidUntil = $olderStart->modify('+14 days');

        $needsReview =
            $cleanDays < 15 &&
            $newerEnd > $haidUntil;

        if ($needsReview) {

            createNisayaNotification(
                $pdo,
                $uid,
                'Peringatan kemungkinan istihadhah',

                'Periode yang ditinjau: ' .
                    $olderStart->format('d-m-Y') . ' s.d. ' .
                    $newerEnd->format('d-m-Y') . '. ' .

                    'Darah tercatat kembali setelah ' . $cleanDays .
                    ' hari suci. Masa suci belum mencapai 15 hari penuh. ' .

                    'Tanggal ' . $olderStart->format('d-m-Y') .
                    ' sampai ' . $haidUntil->format('d-m-Y') .
                    ' berada dalam rentang perhitungan hari ke-15. ' .

                    'Sementara tanggal ' .
                    $haidUntil->modify('+1 day')->format('d-m-Y') .
                    ' sampai ' . $newerEnd->format('d-m-Y') .
                    ' memerlukan peninjauan lebih lanjut untuk menentukan status darah. ' .

                    'Perhatikan kebiasaan haid dan rincian ketentuan Mazhab Syafi’i. ' .
                    'Konsultasikan kepada guru fikih yang kompeten.',

                date('Y-m-d H:i:s'),
                (int) $newer['id']
            );
        } else {

            hideNisayaNotification(
                $pdo,
                $uid,
                'Peringatan kemungkinan istihadhah',
                (int) $newer['id']
            );
        }
    } catch (Exception $e) {
        error_log('NISAYA notification error: ' . $e->getMessage());
        continue;
    }
}


// ==========================================
// 3. PENGINGAT ESTIMASI SIKLUS MENDATANG
// ==========================================

$pred = get_user_prediction($pdo, $uid);
if ($pred && $pred['days_until'] >= 0 && $pred['days_until'] <= 5) {
    $predStartFmt = date('d M Y', strtotime($pred['predicted_start']));
    $dayText = $pred['days_until'] === 0 ? 'hari ini' : ($pred['days_until'] === 1 ? 'besok' : "sekitar {$pred['days_until']} hari lagi ({$predStartFmt})");

    createNisayaNotification(
        $pdo,
        $uid,
        '🌸 Pengingat Datangnya Siklus Haid',
        "Halo! Berdasarkan pola catatan siklusmu di NISAYA, perkiraan waktu haid berikutnya tiba {$dayText}. Jangan lupa persiapkan kebutuhan pribadi dan pantau terus kalender siklusmu.",
        date('Y-m-d H:i:s'),
        null,
        'prediction_' . $pred['predicted_start']
    );
}
