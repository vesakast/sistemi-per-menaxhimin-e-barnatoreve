<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

$canViewRevenue = $current_role === 'admin';
$stats = [
    'orders' => (int) $pdo->query("SELECT COUNT(*) FROM me_porosite_online WHERE statusi NOT IN ('Dorëzuar','Anuluar')")->fetchColumn(),
    'products' => (int) $pdo->query('SELECT COUNT(*) FROM me_produktet WHERE aktiv = 1')->fetchColumn(),
    'messages' => (int) $pdo->query('SELECT COUNT(*) FROM me_mesazhet WHERE i_trajtuar = 0')->fetchColumn(),
];
if ($canViewRevenue) {
    $stats['revenue'] = (float) $pdo->query(
        "SELECT COALESCE(SUM(totali), 0) FROM me_porosite_online WHERE statusi = 'Dorëzuar'"
    )->fetchColumn();
}
$expiryWarningDays = (int) ($pdo->query('SELECT paralajmerimi_1 FROM me_cilesimet WHERE id=1')->fetchColumn() ?: 30);
$stockAlerts = $pdo->query(
    "SELECT id, emri, stoku_aktual, stoku_minimal, statusi_stokut, skadenca_me_e_afert
     FROM me_produktet_stoku
     WHERE aktiv = 1 AND (stoku_aktual <= stoku_minimal
       OR skadenca_me_e_afert <= DATE_ADD(CURDATE(), INTERVAL {$expiryWarningDays} DAY))
     ORDER BY skadenca_me_e_afert IS NULL, skadenca_me_e_afert, stoku_aktual
     LIMIT 10"
)->fetchAll();
$orders = $pdo->query(
    "SELECT id, kodi_gjurmimit, klienti, qyteti, " . ($canViewRevenue ? 'totali, ' : '') . "statusi, data_porosise
     FROM me_porosite_online WHERE statusi NOT IN ('Dorëzuar','Anuluar')
     ORDER BY data_porosise DESC LIMIT 8"
)->fetchAll();

admin_header('Dashboard');
render_flash();
?>
<section class="stats-grid">
    <article class="stat-card"><span>Porosi aktive</span><strong><?= e($stats['orders']) ?></strong></article>
    <?php if ($canViewRevenue): ?><article class="stat-card"><span>Të ardhura të dorëzuara</span><strong><?= e(number_format($stats['revenue'], 2)) ?> €</strong></article><?php endif; ?>
    <article class="stat-card"><span>Produkte aktive</span><strong><?= e($stats['products']) ?></strong></article>
    <article class="stat-card"><span>Mesazhe të patrajtuara</span><strong><?= e($stats['messages']) ?></strong></article>
</section>
<div class="grid-two">
    <section class="panel">
        <div class="topline"><h2>Stoku & skadencat urgjente</h2><a href="<?= e(app_url('/admin/produkte.php')) ?>">Menaxho stokun</a></div>
        <?php if ($stockAlerts === []): ?><p class="empty">Nuk ka alarme urgjente për momentin.</p>
        <?php else: ?><div class="table-wrap"><table><thead><tr><th>Produkti</th><th>Sasia</th><th>Gjendja</th><th>Skadenca</th></tr></thead><tbody>
            <?php foreach ($stockAlerts as $alert): ?>
                <tr><td><?= e($alert['emri']) ?></td><td><?= e($alert['stoku_aktual']) ?></td>
                    <td><span class="badge <?= $alert['statusi_stokut'] === 'Pa stok' ? 'red' : '' ?>"><?= e($alert['statusi_stokut']) ?></span></td>
                    <td><?= e($alert['skadenca_me_e_afert'] ?? '—') ?></td></tr>
            <?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
    <section class="panel">
        <div class="topline"><h2>Porositë e fundit aktive</h2><a href="<?= e(app_url('/admin/shitjet.php')) ?>">Shiko të gjitha</a></div>
        <?php if ($orders === []): ?><p class="empty">Nuk ka porosi aktive.</p>
        <?php else: ?><div class="table-wrap"><table><thead><tr><th>Porosia</th><th>Klienti</th><?php if ($canViewRevenue): ?><th>Totali</th><?php endif; ?><th>Statusi</th></tr></thead><tbody>
            <?php foreach ($orders as $order): ?>
                <tr><td><strong><?= e($order['kodi_gjurmimit']) ?></strong><br><span class="help"><?= e($order['data_porosise']) ?></span></td>
                    <td><?= e($order['klienti']) ?><br><span class="help"><?= e($order['qyteti']) ?></span></td>
                    <?php if ($canViewRevenue): ?><td><?= e(number_format((float) $order['totali'], 2)) ?> €</td><?php endif; ?>
                    <td><span class="badge"><?= e($order['statusi']) ?></span></td></tr>
            <?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
</div>
<?php admin_footer(); ?>
