<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

if (!isset($_SESSION['user_id']) || !is_int($_SESSION['user_id'])) {
    redirect('/admin/login.php');
}

if (isset($_SESSION['last_activity']) && time() - (int) $_SESSION['last_activity'] > 1800) {
    $_SESSION = [];
    session_regenerate_id(true);
    redirect('/admin/login.php?expired=1');
}

$statement = $pdo->prepare(
    "SELECT id, emri, username, rooli, statusi, duhet_ndryshim_fjalekalimi
     FROM me_perdoruesit WHERE id = :id LIMIT 1"
);
$statement->execute(['id' => $_SESSION['user_id']]);
$authenticatedUser = $statement->fetch();

if (!$authenticatedUser || $authenticatedUser['statusi'] !== 'Aktiv') {
    $_SESSION = [];
    session_regenerate_id(true);
    redirect('/admin/login.php');
}

$_SESSION['username'] = $authenticatedUser['username'];
$_SESSION['name'] = $authenticatedUser['emri'];
$_SESSION['role'] = $authenticatedUser['rooli'];
$_SESSION['last_activity'] = time();
$current_user_id = (int) $authenticatedUser['id'];
$current_role = $authenticatedUser['rooli'];
$current_name = $authenticatedUser['emri'];

$pagePermissions = [
    'dashboard.php' => ['admin', 'farmacist'],
    'produkte.php' => ['admin', 'farmacist'],
    'shitjet.php' => ['admin', 'farmacist', 'kurier'],
    'historiku.php' => ['admin', 'farmacist', 'kurier'],
    'raportet.php' => ['admin'],
    'mesazhet.php' => ['admin', 'farmacist'],
    'perdoruesit.php' => ['admin'],
    'cilesimet.php' => ['admin', 'farmacist', 'kurier'],
];
$requestedPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
$allowedRoles = $pagePermissions[$requestedPage] ?? [];

if ((int) $authenticatedUser['duhet_ndryshim_fjalekalimi'] === 1 && $requestedPage !== 'cilesimet.php') {
    redirect('/admin/cilesimet.php?change_password=1');
}

if (!in_array($current_role, $allowedRoles, true)) {
    http_response_code(403);
    $returnPage = $current_role === 'kurier' ? 'shitjet.php' : 'dashboard.php';
    echo '<!doctype html><html lang="sq"><meta charset="utf-8"><title>Qasje e ndaluar</title>';
    echo '<p style="font:16px sans-serif;margin:3rem">Nuk keni leje për këtë faqe. <a href="'
        . e(app_url('/admin/' . $returnPage)) . '">Kthehu</a></p></html>';
    exit;
}
