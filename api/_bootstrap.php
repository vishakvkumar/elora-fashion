<?php
// Shared setup for Elora's PHP endpoints. Not meant to be opened directly.
declare(strict_types=1);
if (basename($_SERVER['SCRIPT_FILENAME'] ?? '') === basename(__FILE__)) { http_response_code(404); exit; }

$cfgFile = __DIR__ . '/config.php';
$CONFIG = is_file($cfgFile) ? (require $cfgFile) : [];
if (!is_array($CONFIG)) $CONFIG = [];
define('STRIPE_KEY', getenv('STRIPE_SECRET_KEY') ?: ($CONFIG['stripe_secret_key'] ?? ''));
define('ADMIN_PASSWORD', getenv('ELORA_ADMIN_PASSWORD') ?: ($CONFIG['admin_password'] ?? ''));
define('DATA_FILE', dirname(__DIR__) . '/data/store.json');
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads');

header('X-Content-Type-Options: nosniff');

function json_out($data, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
function fail(string $msg, int $status = 400): void { json_out(['error' => $msg], $status); }

function read_json_body(): array {
    $raw = file_get_contents('php://input') ?: '';
    $j = json_decode($raw, true);
    return is_array($j) ? $j : [];
}

function load_store(): array {
    if (!is_file(DATA_FILE)) fail('Store data is missing (data/store.json).', 500);
    $j = json_decode((string)file_get_contents(DATA_FILE), true);
    if (!is_array($j)) fail('Store data is unreadable.', 500);
    $j += ['settings' => [], 'categories' => [], 'products' => []];
    return $j;
}
/** Locks the store, lets $fn change it, then writes it back atomically. */
function update_store(callable $fn) {
    $fh = fopen(DATA_FILE, 'c+');
    if (!$fh || !flock($fh, LOCK_EX)) fail('Could not open store data for writing. Check that the data folder is writable.', 500);
    $store = json_decode((string)stream_get_contents($fh), true) ?: [];
    $store += ['settings' => [], 'categories' => [], 'products' => []];
    $result = $fn($store);
    $tmp = DATA_FILE . '.tmp';
    if (file_put_contents($tmp, json_encode($store, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)) === false || !rename($tmp, DATA_FILE)) {
        flock($fh, LOCK_UN); fclose($fh); fail('Could not save store data. Check that the data folder is writable.', 500);
    }
    flock($fh, LOCK_UN); fclose($fh);
    return $result;
}

function base_url(): string {
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $dir = rtrim(str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME'] ?? '/api/x.php'))), '/');
    return ($https ? 'https' : 'http') . '://' . $host . $dir;
}

function stripe(string $method, string $endpoint, array $params = []): array {
    if (STRIPE_KEY === '') fail("Payments aren't set up yet. Add the Stripe secret key to api/config.php.", 503);
    if (!function_exists('curl_init')) fail('The PHP curl extension is required for payments. Ask the host to enable it.', 500);
    $url = (getenv('ELORA_STRIPE_API') ?: 'https://api.stripe.com/v1/') . $endpoint; // override only used for local testing
    $query = http_build_query($params, '', '&', PHP_QUERY_RFC1738);
    $ch = curl_init();
    if ($method === 'GET' && $query !== '') $url .= '?' . $query;
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_USERPWD => STRIPE_KEY . ':',
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTPHEADER => ['Stripe-Version: 2024-06-20'],
    ]);
    if ($method === 'POST') { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $query); }
    $res = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err = curl_error($ch);
    curl_close($ch);
    if ($res === false) fail('Could not reach Stripe: ' . $err, 502);
    $j = json_decode($res, true) ?: [];
    if ($code >= 400) fail($j['error']['message'] ?? 'Stripe request failed.', 502);
    return $j;
}

function start_session(): void {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    session_name('elora_admin');
    session_set_cookie_params(['lifetime' => 0, 'path' => '/', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off', 'httponly' => true, 'samesite' => 'Strict']);
    session_start();
}
function is_admin(): bool { start_session(); return !empty($_SESSION['admin']); }
function require_admin(): void {
    if (!is_admin()) fail('Please log in to the admin panel.', 401);
    $sent = $_SERVER['HTTP_X_CSRF'] ?? '';
    if (!is_string($sent) || !hash_equals($_SESSION['csrf'] ?? '', $sent)) fail('Your session has expired. Please reload the page.', 403);
}

function str_clip($v, int $n): string { return mb_substr(trim((string)($v ?? '')), 0, $n); }
function valid_id($v): bool { return is_string($v) && preg_match('/^[a-z0-9][a-z0-9-]{0,59}$/', $v) === 1; }
function valid_img($v): bool { return is_string($v) && preg_match('#^(img|uploads)/[A-Za-z0-9._-]+$#', $v) === 1; }
