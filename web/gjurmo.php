<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$trackingCode = strtoupper(trim((string) ($_GET['code'] ?? $_GET['kodi_gjurmimit'] ?? '')));
$order = null;
$items = [];
$history = [];
if ($trackingCode !== '' && preg_match('/^[A-Z0-9]{1,20}$/D', $trackingCode)) {
    $query = $pdo->prepare(
        'SELECT id, kodi_gjurmimit, statusi, totali, tarifa_dorezimit, data_porosise, data_dorezimit, arsyeja_anullimit
         FROM me_porosite_online WHERE kodi_gjurmimit = ? LIMIT 1'
    );
    $query->execute([$trackingCode]);
    $order = $query->fetch() ?: null;
    if ($order) {
        $itemQuery = $pdo->prepare('SELECT emri_produktit, cmimi, sasia FROM me_porosi_items WHERE porosia_id = ? ORDER BY id');
        $itemQuery->execute([$order['id']]);
        $items = $itemQuery->fetchAll();
        $historyQuery = $pdo->prepare(
            'SELECT statusi, shenim, koha FROM me_porosi_statuset WHERE porosia_id = ? ORDER BY koha, id'
        );
        $historyQuery->execute([$order['id']]);
        $history = $historyQuery->fetchAll();
    }
}

web_header('Gjurmimi i porosisë');
?>
<p class="eyebrow">Informohu në çdo hap</p><h1 class="page-title">Gjurmimi i porosisë</h1>
<section class="glass-card">
    <form method="get" class="form-grid">
        <div class="field"><label for="tracking">Kodi i gjurmimit</label><input id="tracking" name="kodi_gjurmimit" maxlength="20" value="<?= e($trackingCode) ?>" placeholder="p.sh. HB0123456789" required></div>
        <button class="btn" type="submit">Gjurmo porosinë</button>
    </form>
</section>
<?php if ($trackingCode !== '' && !$order): ?><div class="alert error" role="alert">Nuk u gjet porosi me këtë kod.</div><?php endif; ?>
<?php if ($order): ?>
    <section class="glass-card">
        <div class="section-heading"><div><p class="eyebrow">Kodi i porosisë</p><span class="tracking-code"><?= e($order['kodi_gjurmimit']) ?></span></div>
            <span class="badge <?= $order['statusi'] === 'Dorëzuar' ? 'green' : ($order['statusi'] === 'Anuluar' ? 'red' : '') ?>"><?= e($order['statusi']) ?></span></div>
        <p>Porositur më <?= e($order['data_porosise']) ?><?php if ($order['data_dorezimit']): ?> · Dorëzuar më <?= e($order['data_dorezimit']) ?><?php endif; ?></p>
        <?php if ($order['statusi'] === 'Anuluar' && $order['arsyeja_anullimit']): ?><p class="notice">Arsyeja e anulimit: <?= e($order['arsyeja_anullimit']) ?></p><?php endif; ?>
        <h2>Artikujt</h2><div class="table-wrap"><table><thead><tr><th>Produkti</th><th>Sasia</th><th>Çmimi</th></tr></thead><tbody>
            <?php foreach ($items as $item): ?><tr><td><?= e($item['emri_produktit']) ?></td><td><?= e($item['sasia']) ?></td><td><?= e(number_format((float) $item['cmimi'] * (int) $item['sasia'], 2)) ?> €</td></tr><?php endforeach; ?>
        </tbody></table></div>
        <p>Nëntotali <?= e(number_format((float) $order['totali'] - (float) $order['tarifa_dorezimit'], 2)) ?> € · Dërgesa <?= e(number_format((float) $order['tarifa_dorezimit'], 2)) ?> € · <strong>Totali <?= e(number_format((float) $order['totali'], 2)) ?> €</strong></p>
        <h2>Historiku i statusit</h2><ol><?php foreach ($history as $event): ?><li><strong><?= e($event['statusi']) ?></strong> · <?= e($event['koha']) ?><?php if ($event['shenim']): ?> — <?= e($event['shenim']) ?><?php endif; ?></li><?php endforeach; ?></ol>
    </section>
<?php endif; ?>
<?php web_footer(); ?>
