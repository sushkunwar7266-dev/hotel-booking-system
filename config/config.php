<?php
declare(strict_types=1);

session_start();

const DB_HOST = 'localhost';
const DB_NAME = 'hotel_booking';
const DB_USER = 'root';
const DB_PASS = '';

const APP_NAME = 'StayEase Hotel Booking';
const CURRENCY = 'NPR';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        $pdo = new PDO($dsn, DB_USER, DB_PASS, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
    }
    return $pdo;
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    header("Location: $url");
    exit;
}

function flash(string $type, string $message): void {
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

function get_flash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function verify_csrf(): void {
    if (!hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'] ?? '')) {
        http_response_code(419);
        exit('Invalid CSRF token.');
    }
}

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    $stmt = db()->prepare("SELECT id,name,email,phone,role FROM users WHERE id=?");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch() ?: null;
}

function require_login(): array {
    $user = current_user();
    if (!$user) redirect('login.php');
    return $user;
}

function require_admin(): array {
    $user = require_login();
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('Forbidden');
    }
    return $user;
}

function nights(string $checkin, string $checkout): int {
    $a = new DateTime($checkin);
    $b = new DateTime($checkout);
    return max(0, (int)$a->diff($b)->days);
}

function is_room_available(int $roomId, string $checkin, string $checkout, ?int $ignoreBookingId = null): bool {
    $sql = "SELECT COUNT(*) FROM bookings
            WHERE room_id = ?
              AND status IN ('pending','confirmed','checked_in')
              AND check_in < ?
              AND check_out > ?";
    $params = [$roomId, $checkout, $checkin];
    if ($ignoreBookingId) {
        $sql .= " AND id <> ?";
        $params[] = $ignoreBookingId;
    }
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return (int)$stmt->fetchColumn() === 0;
}
