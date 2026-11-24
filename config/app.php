
<?php

// ==========================================
// 1. SESSION
// ==========================================

if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');

    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax'
    ]);

    session_start();
}
// mengecek dan menjalankan session

// ==========================================
// 2. DATABASE CONNECTION
// ==========================================

require_once __DIR__ . '/database.php';


// ==========================================
// 3. ESCAPE HTML
// ==========================================

function e($value): string
{
    return htmlspecialchars(
        (string) ($value ?? ''),
        ENT_QUOTES,
        'UTF-8'
    );
}
//

// ==========================================
// 4. LOGIN & ADMIN PROTECTION
// ==========================================

function require_login(): void
{
    if (empty($_SESSION['user_id'])) {
        header('Location: ../auth/login.php');
        exit;
    }
}

function is_admin(): bool
{
    if (empty($_SESSION['user_id'])) {
        return false;
    }
    if (isset($_SESSION['user_role'])) {
        return $_SESSION['user_role'] === 'admin';
    }
    global $pdo;
    if ($pdo) {
        $stmt = $pdo->prepare('SELECT role FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        $role = $stmt->fetchColumn();
        $_SESSION['user_role'] = $role ?: 'user';
        return $_SESSION['user_role'] === 'admin';
    }
    return false;
}

function require_admin(): void
{
    require_login();
    if (!is_admin()) {
        flash('error', 'Akses ditolak. Halaman ini hanya dapat diakses oleh Administrator.');
        header('Location: ../dashboard/index.php');
        exit;
    }
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) {
        return null;
    }
    global $pdo;
    if ($pdo) {
        $stmt = $pdo->prepare('SELECT id, name, email, role, profile_picture, status, created_at FROM users WHERE id = ?');
        $stmt->execute([$_SESSION['user_id']]);
        return $stmt->fetch() ?: null;
    }
    return null;
}


// ==========================================
// 5. FLASH MESSAGE
// ==========================================

function flash(string $key, ?string $message = null): ?string
{
    // Menyimpan pesan
    if ($message !== null) {
        $_SESSION['flash'][$key] = $message;
        return null;
    }

    // Mengambil pesan
    $value = $_SESSION['flash'][$key] ?? null;

    // Menghapus pesan setelah diambil
    unset($_SESSION['flash'][$key]);

    return $value;
}


// ==========================================
// 6. CSRF TOKEN
// ==========================================

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf'];
}



// ==========================================
// 7. CSRF VALIDATION
// ==========================================

function verify_csrf(): void
{
    $session_token = $_SESSION['csrf'] ?? '';
    $form_token = $_POST['csrf'] ?? '';

    if (
        !is_string($session_token) ||
        !is_string($form_token) ||
        $session_token === '' ||
        $form_token === '' ||
        !hash_equals($session_token, $form_token)
    ) {
        http_response_code(419);

        exit('Permintaan tidak valid. Muat ulang halaman.');
    }
}

// ==========================================
// 8. PREDIKSI SIKLUS HAID (CALENDAR PREDICTION)
// ==========================================

function get_user_prediction(PDO $pdo, int $userId): ?array
{
    $today = new DateTimeImmutable('today');

    // Ambil data profil siklus
    $profStmt = $pdo->prepare('SELECT last_period_start, average_period_days, average_purity_days FROM cycle_profiles WHERE user_id = ?');
    $profStmt->execute([$userId]);
    $prof = $profStmt->fetch(PDO::FETCH_ASSOC) ?: [];

    // Ambil catatan riwayat terbaru
    $recStmt = $pdo->prepare('SELECT start_date, end_date FROM menstrual_records WHERE user_id = ? ORDER BY start_date DESC LIMIT 5');
    $recStmt->execute([$userId]);
    $records = $recStmt->fetchAll(PDO::FETCH_ASSOC);

    $lastStart = !empty($records[0]['start_date']) ? $records[0]['start_date'] : ($prof['last_period_start'] ?? null);
    if (!$lastStart) {
        return null;
    }

    $avgPeriod = !empty($prof['average_period_days']) ? (int)$prof['average_period_days'] : 7;
    $avgPurity = !empty($prof['average_purity_days']) ? (int)$prof['average_purity_days'] : 21;
    $cycleLength = $avgPeriod + $avgPurity;

    // Jika ada lebih dari 1 catatan riwayat, hitung rata-rata interval riil
    if (count($records) >= 2) {
        $intervals = [];
        for ($i = 0; $i < count($records) - 1; $i++) {
            try {
                $d1 = new DateTimeImmutable($records[$i]['start_date']);
                $d2 = new DateTimeImmutable($records[$i + 1]['start_date']);
                $diff = (int)$d2->diff($d1)->days;
                if ($diff >= 20 && $diff <= 50) {
                    $intervals[] = $diff;
                }
            } catch (Exception $e) {
            }
        }
        if (!empty($intervals)) {
            $cycleLength = (int)round(array_sum($intervals) / count($intervals));
        }
    }

    if ($cycleLength < 21 || $cycleLength > 45) {
        $cycleLength = 28;
    }
    if ($avgPeriod < 3 || $avgPeriod > 15) {
        $avgPeriod = 7;
    }

    try {
        $lastStartDate = new DateTimeImmutable($lastStart);
        $predictedStart = $lastStartDate->modify("+{$cycleLength} days");

        while ($predictedStart < $today) {
            $predictedStart = $predictedStart->modify("+{$cycleLength} days");
        }

        $predictedEnd = $predictedStart->modify('+' . ($avgPeriod - 1) . ' days');
        $daysUntil = (int)$today->diff($predictedStart)->format('%r%a');

        $pStartStr = $predictedStart->format('Y-m-d');
        $pEndStr = $predictedEnd->format('Y-m-d');

        // Simpan / perbarui ke tabel calendar_predictions
        $checkStmt = $pdo->prepare('SELECT id FROM calendar_predictions WHERE user_id = ? AND prediction_status = "upcoming" ORDER BY id DESC LIMIT 1');
        $checkStmt->execute([$userId]);
        $existingPredId = $checkStmt->fetchColumn();

        if ($existingPredId) {
            $updStmt = $pdo->prepare('UPDATE calendar_predictions SET predicted_start = ?, predicted_end = ?, calculated_at = NOW() WHERE id = ?');
            $updStmt->execute([$pStartStr, $pEndStr, $existingPredId]);
        } else {
            $insStmt = $pdo->prepare('INSERT INTO calendar_predictions (user_id, predicted_start, predicted_end, prediction_status, calculated_at) VALUES (?, ?, ?, "upcoming", NOW())');
            $insStmt->execute([$userId, $pStartStr, $pEndStr]);
        }

        return [
            'predicted_start' => $pStartStr,
            'predicted_end' => $pEndStr,
            'days_until' => $daysUntil,
            'cycle_length' => $cycleLength,
            'period_days' => $avgPeriod
        ];
    } catch (Exception $e) {
        return null;
    }
}
