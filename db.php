<?php
declare(strict_types=1);

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('herbabloom_session');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('X-Frame-Options: DENY');

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function app_env(string $name, string $default = ''): string
{
    $value = getenv($name);
    if ($value === false || $value === '') {
        $value = $_SERVER[$name] ?? $_ENV[$name] ?? $default;
    }

    return is_string($value) ? $value : $default;
}

function app_url(string $path = ''): string
{
    $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/'));
    $base = preg_replace('~/(admin|web)$~', '', $scriptDirectory) ?? '';
    $base = $base === '/' ? '' : rtrim($base, '/');

    return $base . '/' . ltrim($path, '/');
}

function csrf_token(): string
{
    if (!isset($_SESSION['_csrf_token'])) {
        $_SESSION['_csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['_csrf_token'];
}

function verify_csrf(): void
{
    $submitted = $_POST['_csrf_token'] ?? '';
    if (!is_string($submitted) || !hash_equals(csrf_token(), $submitted)) {
        http_response_code(419);
        exit('Kërkesa ka skaduar. Rifreskoni faqen dhe provoni përsëri.');
    }
}

function redirect(string $path): never
{
    header('Location: ' . app_url($path), true, 303);
    exit;
}

$dbHost = getenv('DB_HOST') ?: '127.0.0.1';
$dbName = getenv('DB_NAME') ?: 'herbabloom_db';
$dbUser = getenv('DB_USER') ?: 'root';
$dbPassword = getenv('DB_PASSWORD') ?: '';

try {
    $pdo = new PDO(
        "mysql:host={$dbHost};dbname={$dbName};charset=utf8mb4",
        $dbUser,
        $dbPassword,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    $pdo->exec('SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci');
} catch (PDOException $exception) {
    error_log('HerbaBloom database connection failed: ' . $exception->getMessage());
    http_response_code(503);
    exit('Shërbimi nuk është i disponueshëm. Kontrolloni konfigurimin e databazës.');
}
