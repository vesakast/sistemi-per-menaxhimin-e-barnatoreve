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
<?php if (($_GET['created'] ?? '') === '1' && $order): ?>
    <section class="order-confirmed" role="status"><i class="bi bi-check-circle" aria-hidden="true"></i><div><p class="eyebrow">Porosia u regjistrua</p><h1>Faleminderit për porosinë!</h1><p>Mbaje kodin e gjurmimit që të mund ta kontrollosh statusin e porosisë kur të duash.</p></div></section>
<?php else: ?>
    <section class="page-intro"><p class="eyebrow">Përcille porosinë tënde</p><h1 class="page-title">Ku është porosia?</h1><p>Shkruaj kodin e gjurmimit që more pas porosisë për të parë statusin, artikujt dhe përditësimet e dërgesës.</p></section>
<?php endif; ?>
<section class="content-card">
    <form method="get" class="form-grid">
        <div class="field"><label for="tracking">Kodi i porosisë</label><input id="tracking" name="kodi_gjurmimit" maxlength="20" value="<?= e($trackingCode) ?>" placeholder="Shkruaj kodin e gjurmimit" required></div>
        <button class="btn" type="submit">Shfaq statusin</button>
    </form>
</section>
<?php if ($trackingCode !== '' && !$order): ?><div class="alert error" role="alert">Nuk gjetëm porosi me këtë kod. Kontrollo shkronjat dhe numrat, pastaj provo sërish.</div><?php endif; ?>
<?php if ($order): ?>
    <section class="content-card">
        <div class="section-heading"><div><p class="eyebrow">Kodi i porosisë</p><span class="tracking-code"><?= e($order['kodi_gjurmimit']) ?></span></div>
            <span class="badge <?= $order['statusi'] === 'Dorëzuar' ? 'green' : ($order['statusi'] === 'Anuluar' ? 'red' : '') ?>"><?= e($order['statusi']) ?></span></div>
        <p>Porositur më <?= e($order['data_porosise']) ?><?php if ($order['data_dorezimit']): ?> · Dorëzuar më <?= e($order['data_dorezimit']) ?><?php endif; ?></p>
        <?php if ($order['statusi'] === 'Anuluar' && $order['arsyeja_anullimit']): ?><p class="notice">Arsyeja e anulimit: <?= e($order['arsyeja_anullimit']) ?></p><?php endif; ?>
        <h2>Artikujt</h2><div class="table-wrap"><table><thead><tr><th>Produkti</th><th>Sasia</th><th>Çmimi</th></tr></thead><tbody>
            <?php foreach ($items as $item): ?><tr><td><?= e($item['emri_produktit']) ?></td><td><?= e($item['sasia']) ?></td><td><?= e(number_format((float) $item['cmimi'] * (int) $item['sasia'], 2)) ?> €</td></tr><?php endforeach; ?>
        </tbody></table></div>
        <p>Nëntotali <?= e(number_format((float) $order['totali'] - (float) $order['tarifa_dorezimit'], 2)) ?> € · Dërgesa <?= e(number_format((float) $order['tarifa_dorezimit'], 2)) ?> € · <strong>Totali <?= e(number_format((float) $order['totali'], 2)) ?> €</strong></p>
        <h2>Përditësimet e porosisë</h2><ol><?php foreach ($history as $event): ?><li><strong><?= e($event['statusi']) ?></strong> · <?= e($event['koha']) ?><?php if ($event['shenim']): ?> — <?= e($event['shenim']) ?><?php endif; ?></li><?php endforeach; ?></ol>
    </section>
<?php endif; ?>
<?php web_footer(); ?>
