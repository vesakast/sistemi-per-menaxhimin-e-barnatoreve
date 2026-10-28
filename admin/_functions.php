<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf_token" value="' . e(csrf_token()) . '">';
}

function audit_log(PDO $pdo, string $action): void
{
    $statement = $pdo->prepare(
        'INSERT INTO me_audit_log (perdorues_id, perdoruesi, veprimi, ip)
         VALUES (:user_id, :username, :action, :ip)'
    );
    $statement->execute([
        'user_id' => $_SESSION['user_id'] ?? null,
        'username' => substr((string) ($_SESSION['username'] ?? 'system'), 0, 50),
        'action' => $action,
        'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);
}

function admin_header(string $title): void
{
    $pageTitle = e($title);
    echo '<!doctype html><html lang="sq"><head><meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . $pageTitle . ' · HerbaBloom</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . e(app_url('/admin/style.css')) . '"></head><body>';
    echo '<div class="app-shell">';
    require __DIR__ . '/sidebar.php';
    echo '<main class="main-content"><header class="page-heading"><div><p class="eyebrow">HerbaBloom · Paneli i kontrollit</p>';
    echo '<h1>' . $pageTitle . '</h1></div><div class="user-chip">' . e($_SESSION['name'] ?? '') . '</div></header>';
}

function admin_footer(): void
{
    echo '</main></div></body></html>';
}

function flash_message(string $message, string $type = 'success'): void
{
    $_SESSION['_flash'] = ['message' => $message, 'type' => $type];
}

function render_flash(): void
{
    if (!isset($_SESSION['_flash'])) {
        return;
    }

    $flash = $_SESSION['_flash'];
    unset($_SESSION['_flash']);
    echo '<div class="alert ' . e($flash['type']) . '" role="status">' . e($flash['message']) . '</div>';
}

function valid_date_or_null(string $value): ?string
{
    if ($value === '') {
        return null;
    }
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $value : null;
}

function generate_tracking_code(PDO $pdo): string
{
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $code = 'HB' . strtoupper(bin2hex(random_bytes(6)));
        $statement = $pdo->prepare('SELECT 1 FROM me_porosite_online WHERE kodi_gjurmimit = ?');
        $statement->execute([$code]);
        if (!$statement->fetchColumn()) {
            return $code;
        }
    }
    throw new RuntimeException('Nuk u krijua kodi unik i gjurmimit.');
}
