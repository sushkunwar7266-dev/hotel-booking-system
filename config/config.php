<?php
declare(strict_types=1);

// Secure session configuration
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_secure', '0'); // Set to '1' if using HTTPS
ini_set('session.cookie_samesite', 'Lax'); // Lax so the session survives the redirect back from Khalti
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_lifetime', '0'); // Session cookie expires when browser closes
ini_set('session.gc_maxlifetime', '7200'); // 2 hours

session_start();

// Regenerate session ID periodically to prevent session fixation
if (!isset($_SESSION['created'])) {
    $_SESSION['created'] = time();
} elseif (time() - $_SESSION['created'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['created'] = time();
}

// Security headers
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
// Uncomment for production with HTTPS:
// header('Strict-Transport-Security: max-age=31536000; includeSubDomains');

// Content Security Policy
$csp = "default-src 'self'; " .
       "script-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
       "style-src 'self' 'unsafe-inline' https://cdnjs.cloudflare.com; " .
       "font-src 'self' https://cdnjs.cloudflare.com; " .
       "img-src 'self' data:; " .
       "connect-src 'self'; " .
       "frame-ancestors 'self'";
header("Content-Security-Policy: $csp");

// Disable error display in production (errors are logged instead)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
error_reporting(E_ALL);
ini_set('log_errors', '1');
ini_set('error_log', __DIR__ . '/../logs/php_errors.log');

// Custom error handler to prevent information disclosure
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Log the actual error
    error_log("Error [$errno]: $errstr in $errfile on line $errline");
    
    // Don't expose details to user - show generic message
    if (!(error_reporting() & $errno)) {
        return false; // Error was suppressed with @
    }
    
    // For fatal errors, show generic message
    if (in_array($errno, [E_ERROR, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
        http_response_code(500);
        exit('An error occurred. Please try again later.');
    }
    
    return true; // Continue with normal error handling
});

// Ensure logs directory exists
if (!is_dir(__DIR__ . '/../logs')) {
    @mkdir(__DIR__ . '/../logs', 0750, true);
}

const DB_HOST = 'localhost';
const DB_NAME = 'hotel_booking';
const DB_USER = 'root';
const DB_PASS = '';

const APP_NAME = 'StayEase Hotel Booking';
const CURRENCY = 'NPR';

// Base URL of the site, used to build the Khalti return URL
const APP_URL = 'http://localhost/hotel';

// Khalti ePayment (KPG-2). Sandbox: https://dev.khalti.com/api/v2/  Production: https://khalti.com/api/v2/
// Use the "live_secret_key" from test-admin.khalti.com (sandbox) or admin.khalti.com (production).
// The key below is Khalti's public sandbox sample key; replace it with your own merchant key.
const KHALTI_BASE_URL = 'https://dev.khalti.com/api/v2/';
const KHALTI_SECRET_KEY = '05bf95cc57244045b8df5fad06748dab';

function db(): PDO {
    static $pdo = null;
    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4';
        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } catch (PDOException $e) {
            // Log error securely without exposing to user
            error_log('Database connection failed: ' . $e->getMessage());
            http_response_code(503);
            exit('Service temporarily unavailable. Please try again later.');
        }
    }
    return $pdo;
}

function e(?string $value): string {
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function js(?string $value): string {
    return json_encode($value ?? '', JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
}

function attr(?string $value): string {
    // For HTML attributes - same as e() but explicit
    return htmlspecialchars($value ?? '', ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): never {
    // Prevent open redirect vulnerabilities - only allow relative URLs
    if (strpos($url, '://') !== false || strpos($url, '//') === 0) {
        // Absolute URL detected - reject to prevent open redirects
        $url = 'index.php';
    }
    
    // Remove any null bytes
    $url = str_replace("\0", '', $url);
    
    header("Location: $url", true, 302);
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
    $stmt = db()->prepare("SELECT id,name,email,phone,role,avatar FROM users WHERE id=?");
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

// Rate limiting functions for brute force protection
function get_rate_limit_key(string $action, string $identifier): string {
    return 'rate_limit_' . $action . '_' . hash('sha256', $identifier);
}

function check_rate_limit(string $action, string $identifier, int $max_attempts, int $window_seconds): bool {
    $key = get_rate_limit_key($action, $identifier);
    
    if (!isset($_SESSION[$key])) {
        $_SESSION[$key] = ['attempts' => 0, 'reset_time' => time() + $window_seconds];
    }
    
    // Reset if window expired
    if (time() > $_SESSION[$key]['reset_time']) {
        $_SESSION[$key] = ['attempts' => 0, 'reset_time' => time() + $window_seconds];
    }
    
    // Check if limit exceeded
    if ($_SESSION[$key]['attempts'] >= $max_attempts) {
        return false; // Rate limit exceeded
    }
    
    return true; // Within limit
}

function increment_rate_limit(string $action, string $identifier): void {
    $key = get_rate_limit_key($action, $identifier);
    if (isset($_SESSION[$key])) {
        $_SESSION[$key]['attempts']++;
    }
}

function get_rate_limit_wait_time(string $action, string $identifier): int {
    $key = get_rate_limit_key($action, $identifier);
    if (isset($_SESSION[$key]) && time() < $_SESSION[$key]['reset_time']) {
        return $_SESSION[$key]['reset_time'] - time();
    }
    return 0;
}

function reset_rate_limit(string $action, string $identifier): void {
    $key = get_rate_limit_key($action, $identifier);
    unset($_SESSION[$key]);
}

function nights(string $checkin, string $checkout): int {
    $a = new DateTime($checkin);
    $b = new DateTime($checkout);
    return max(0, (int)$a->diff($b)->days);
}

function get_pending_booking_count(): int {
    $stmt = db()->prepare("SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
    $stmt->execute();
    return (int)$stmt->fetchColumn();
}

function is_room_available(int $roomId, string $checkin, string $checkout, ?int $ignoreBookingId = null): bool {
    // First check if room itself is available (not unavailable or in maintenance)
    $roomCheck = db()->prepare("SELECT status FROM rooms WHERE id = ?");
    $roomCheck->execute([$roomId]);
    $roomStatus = $roomCheck->fetchColumn();
    
    if($roomStatus !== 'available') {
        return false;
    }
    
    // Then check for booking conflicts
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

/**
 * Call a Khalti ePayment API endpoint (e.g. 'epayment/initiate/' or 'epayment/lookup/').
 * Returns ['code' => HTTP status, 'body' => decoded JSON array or null].
 */
function khalti_request(string $endpoint, array $payload): array {
    $ch = curl_init(KHALTI_BASE_URL . $endpoint);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => [
            'Authorization: Key ' . KHALTI_SECRET_KEY,
            'Content-Type: application/json',
        ],
        CURLOPT_POSTFIELDS => json_encode($payload),
    ]);
    $raw = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if ($raw === false) {
        error_log('Khalti request failed: ' . curl_error($ch));
    }
    curl_close($ch);
    return ['code' => $code, 'body' => $raw ? json_decode($raw, true) : null];
}

/**
 * Start a Khalti payment for a pending booking owned by $user.
 * Records the pending payment (linked by pidx) and returns Khalti's payment_url, or null with $error set.
 */
function khalti_start(int $bookingId, array $user, ?string &$error = null): ?string {
    $pdo = db();
    $s = $pdo->prepare("SELECT b.*, r.room_number, rt.name type_name FROM bookings b JOIN rooms r ON r.id=b.room_id JOIN room_types rt ON rt.id=r.room_type_id WHERE b.id=? AND b.user_id=?");
    $s->execute([$bookingId, $user['id']]);
    $b = $s->fetch();
    if (!$b || $b['status'] !== 'pending' || $b['payment_status'] === 'paid') {
        $error = 'This booking cannot be paid.';
        return null;
    }

    $amountPaisa = (int)round((float)$b['total_amount'] * 100); // Khalti expects paisa
    if ($amountPaisa < 1000) {
        $error = 'Khalti requires a minimum payment of NPR 10.';
        return null;
    }

    $payload = [
        'return_url' => APP_URL . '/khalti_return.php',
        'website_url' => APP_URL . '/',
        'amount' => $amountPaisa,
        'purchase_order_id' => $b['booking_code'],
        'purchase_order_name' => 'Room ' . $b['room_number'] . ' (' . $b['type_name'] . ')',
        'customer_info' => array_filter([
            'name' => $user['name'],
            'email' => $user['email'],
            'phone' => $user['phone'] ?? '',
        ]),
        'product_details' => [[
            'identity' => (string)$b['room_id'],
            'name' => $b['type_name'] . ' - Room ' . $b['room_number'],
            'total_price' => $amountPaisa,
            'quantity' => 1,
            'unit_price' => $amountPaisa,
        ]],
        'merchant_booking_id' => (string)$b['id'],
    ];

    $res = khalti_request('epayment/initiate/', $payload);
    $body = $res['body'] ?? [];
    $host = parse_url($body['payment_url'] ?? '', PHP_URL_HOST) ?: '';
    if ($res['code'] !== 200 || empty($body['pidx']) || !preg_match('/(^|\.)khalti\.com$/', $host)) {
        error_log('Khalti initiate failed: HTTP ' . $res['code'] . ' ' . json_encode($body));
        $error = 'Could not start Khalti payment. Please try again.';
        return null;
    }

    $existing = $pdo->prepare("SELECT id FROM payments WHERE booking_id=? AND status IN ('pending','failed') ORDER BY id DESC LIMIT 1");
    $existing->execute([$bookingId]);
    $pid = $existing->fetchColumn();
    if ($pid) {
        $pdo->prepare("UPDATE payments SET method='khalti', pidx=?, amount=?, status='pending', transaction_id=NULL, paid_at=NULL WHERE id=?")
            ->execute([$body['pidx'], $b['total_amount'], $pid]);
    } else {
        $pdo->prepare("INSERT INTO payments (booking_id, pidx, amount, method, status) VALUES (?, ?, ?, 'khalti', 'pending')")
            ->execute([$bookingId, $body['pidx'], $b['total_amount']]);
    }
    return $body['payment_url'];
}
