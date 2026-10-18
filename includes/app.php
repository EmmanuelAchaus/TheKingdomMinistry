<?php

declare(strict_types=1);

date_default_timezone_set('Africa/Nairobi');

if (in_array(PHP_SAPI, ['cli', 'cli-server'], true) && !is_dir((string) ini_get('session.save_path'))) {
    session_save_path(sys_get_temp_dir());
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off'),
        'samesite' => 'Lax',
        'path' => '/',
    ]);
    session_start();
}

require_once __DIR__ . '/db.php';

function app_base_path(): string
{
    $configured = trim((string) (getenv('APP_BASE_PATH') ?: ''));
    if ($configured !== '') {
        return '/' . trim($configured, '/');
    }

    $script = str_replace('\\', '/', $_SERVER['SCRIPT_NAME'] ?? '');
    foreach (['/portal/', '/admin/'] as $marker) {
        $position = strpos($script, $marker);
        if ($position !== false) {
            return rtrim(substr($script, 0, $position), '/');
        }
    }

    return rtrim(str_replace('\\', '/', dirname($script)), '/.');
}

function app_url(string $path = ''): string
{
    return app_base_path() . '/' . ltrim($path, '/');
}

function h(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    header('Location: ' . (str_starts_with($path, '/') ? $path : app_url($path)));
    exit;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . h(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = (string) ($_POST['csrf_token'] ?? '');
    if ($token === '' || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
        http_response_code(419);
        throw new RuntimeException('Your session expired. Refresh the page and try again.');
    }
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function pull_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

function current_user(): ?array
{
    global $pdo;
    static $loadedFor = null;
    static $cached = null;

    $id = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : 0;
    if ($id < 1) {
        return null;
    }
    if ($loadedFor === $id) {
        return $cached;
    }

    $stmt = $pdo->prepare(
        'SELECT u.*, m.id AS member_id, m.membership_number, m.full_name, m.email,
                m.date_joined, m.residential_address, m.membership_status, m.first_contribution_month
         FROM users u
         LEFT JOIN members m ON m.user_id = u.id
         WHERE u.id = ? LIMIT 1'
    );
    $stmt->execute([$id]);
    $cached = $stmt->fetch() ?: null;
    $loadedFor = $id;
    return $cached;
}

function require_user(): array
{
    $user = current_user();
    if (!$user || $user['account_status'] !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['admin_logged_in'], $_SESSION['admin_id'], $_SESSION['admin_username']);
        flash('error', 'Please sign in to continue.');
        redirect('portal/login.php');
    }
    if (($user['membership_status'] ?? '') !== 'Active') {
        $_SESSION = [];
        flash('error', 'Your membership is not currently active.');
        redirect('portal/login.php');
    }
    return $user;
}

function require_admin(): array
{
    $user = require_user();
    if ((int) $user['must_change_password'] === 1) {
        flash('warning', 'Change your temporary password before continuing.');
        redirect('portal/index.php?page=security');
    }
    if ($user['role'] !== 'admin') {
        http_response_code(403);
        exit('You do not have permission to access this page.');
    }
    return $user;
}

function json_response(bool $success, string $message, array $data = [], array $errors = []): never
{
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(compact('success', 'message', 'data', 'errors'), JSON_UNESCAPED_SLASHES);
    exit;
}

function request_is_json(): bool
{
    return str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json')
        || ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest';
}

function action_result(bool $success, string $message, string $redirectTo, array $data = [], array $errors = []): never
{
    if (request_is_json()) {
        json_response($success, $message, $data, $errors);
    }
    flash($success ? 'success' : 'error', $message);
    redirect($redirectTo);
}

function normalize_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', trim($phone));
    if (str_starts_with($digits, '0') && strlen($digits) === 10) {
        return '+254' . substr($digits, 1);
    }
    if (str_starts_with($digits, '254') && strlen($digits) === 12) {
        return '+' . $digits;
    }
    return str_starts_with($phone, '+') ? '+' . $digits : $digits;
}

function money_to_cents(string|int|float $value): int
{
    $normalized = preg_replace('/[^0-9.\-]/', '', (string) $value);
    if (!preg_match('/^-?\d+(?:\.\d{1,2})?$/', $normalized)) {
        throw new InvalidArgumentException('Enter a valid amount with no more than two decimal places.');
    }
    $negative = str_starts_with($normalized, '-');
    $normalized = ltrim($normalized, '-');
    [$whole, $decimal] = array_pad(explode('.', $normalized, 2), 2, '');
    $cents = ((int) $whole * 100) + (int) str_pad($decimal, 2, '0');
    return $negative ? -$cents : $cents;
}

function cents_to_decimal(int $cents): string
{
    $negative = $cents < 0 ? '-' : '';
    $cents = abs($cents);
    return $negative . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function kes(string|int|float|null $amount): string
{
    return 'KES ' . number_format((float) ($amount ?? 0), 2);
}

function private_encrypt(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $secret = getenv('APP_ENCRYPTION_KEY') ?: '';
    if ($secret === '') {
        throw new RuntimeException('APP_ENCRYPTION_KEY must be configured before protected identity data can be saved.');
    }
    $key = hash('sha256', $secret, true);
    $iv = random_bytes(12);
    $tag = '';
    $ciphertext = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);
    if ($ciphertext === false) {
        throw new RuntimeException('Could not protect the private value.');
    }
    return base64_encode($iv . $tag . $ciphertext);
}

function private_fingerprint(?string $value): ?string
{
    $value = trim((string) $value);
    if ($value === '') {
        return null;
    }
    $secret = getenv('APP_ENCRYPTION_KEY') ?: '';
    if ($secret === '') {
        throw new RuntimeException('APP_ENCRYPTION_KEY must be configured before protected identity data can be saved.');
    }
    return hash_hmac('sha256', strtolower($value), $secret);
}

function audit(string $event, string $subjectType, ?int $subjectId, array $details = [], ?int $actorId = null): void
{
    global $pdo;
    $actorId ??= current_user()['id'] ?? null;
    $stmt = $pdo->prepare(
        'INSERT INTO audit_logs (actor_user_id, event_type, subject_type, subject_id, details_json, ip_address)
         VALUES (?, ?, ?, ?, ?, ?)'
    );
    $stmt->execute([
        $actorId,
        $event,
        $subjectType,
        $subjectId,
        json_encode($details, JSON_UNESCAPED_SLASHES),
        substr($_SERVER['REMOTE_ADDR'] ?? 'cli', 0, 45),
    ]);
}

function notify_member(int $memberId, string $type, string $title, string $message, ?string $link = null): void
{
    global $pdo;
    $stmt = $pdo->prepare(
        'INSERT INTO notifications (member_id, type, title, message, link_url) VALUES (?, ?, ?, ?, ?)'
    );
    $stmt->execute([$memberId, $type, $title, $message, $link]);
}
