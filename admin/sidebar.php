<?php
$role = $_SESSION['role'] ?? '';
$navigation = [
    'admin' => [
        ['dashboard.php', '⌂', 'Dashboard'],
        ['produkte.php', '✿', 'Produktet & stoku'],
        ['shitjet.php', '↗', 'Porositë aktive'],
        ['historiku.php', '◷', 'Historiku'],
        ['raportet.php', '▤', 'Raportet'],
        ['ofertat.php', '◇', 'Setet & ofertat'],
        ['mesazhet.php', '✉', 'Mesazhet'],
        ['perdoruesit.php', '♙', 'Përdoruesit'],
        ['cilesimet.php', '⚙', 'Cilësimet'],
    ],
    'farmacist' => [
        ['dashboard.php', '⌂', 'Dashboard'],
        ['produkte.php', '✿', 'Produktet & stoku'],
        ['shitjet.php', '↗', 'Porositë aktive'],
        ['historiku.php', '◷', 'Historiku'],
        ['mesazhet.php', '✉', 'Mesazhet'],
        ['cilesimet.php', '⚙', 'Cilësimet'],
    ],
    'kurier' => [
        ['shitjet.php', '↗', 'Dërgesat e mia'],
        ['historiku.php', '◷', 'Historiku'],
        ['cilesimet.php', '⚙', 'Profili im'],
    ],
];
$currentPage = basename($_SERVER['SCRIPT_NAME'] ?? '');
?>
<aside class="sidebar">
    <a class="brand" href="<?= e(app_url('/admin/' . ($role === 'kurier' ? 'shitjet.php' : 'dashboard.php'))) ?>">
        <img class="brand-flower" src="<?= e(app_url('/web-images/herbabloom-favicon.svg')) ?>" alt="" aria-hidden="true">
        <span><strong>HerbaBloom</strong><small>Barnatorja bimore</small></span>
    </a>
    <nav aria-label="Navigimi kryesor">
        <?php foreach ($navigation[$role] ?? [] as [$file, $icon, $label]): ?>
            <a class="nav-link <?= $currentPage === $file ? 'active' : '' ?>" href="<?= e(app_url('/admin/' . $file)) ?>">
                <span aria-hidden="true"><?= e($icon) ?></span><?= e($label) ?>
            </a>
        <?php endforeach; ?>
    </nav>
    <form method="post" action="<?= e(app_url('/admin/logout.php')) ?>" class="logout-form">
        <?= csrf_field() ?>
        <button class="nav-link logout-button" type="submit"><span aria-hidden="true">↪</span>Dil nga sistemi</button>
    </form>
</aside>
