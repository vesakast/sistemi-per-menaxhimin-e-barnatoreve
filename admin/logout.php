<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    header('Allow: POST');
    exit('Përdorni formularin e daljes.');
}

verify_csrf();
$_SESSION = [];
$cookie = session_get_cookie_params();
setcookie(session_name(), '', [
    'expires' => time() - 42000,
    'path' => $cookie['path'],
    'domain' => $cookie['domain'],
    'secure' => $cookie['secure'],
    'httponly' => $cookie['httponly'],
    'samesite' => $cookie['samesite'] ?? 'Lax',
]);
session_destroy();
redirect('/admin/login.php');
