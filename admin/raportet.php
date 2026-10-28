<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

$monthly = $pdo->query(
    "SELECT DATE_FORMAT(data_dorezimit, '%Y-%m') AS muaji, COALESCE(SUM(totali),0) AS te_ardhura,
            COUNT(*) AS porosi
     FROM me_porosite_online
     WHERE statusi = 'Dorëzuar' AND data_dorezimit >= DATE_SUB(CURDATE(), INTERVAL 11 MONTH)
     GROUP BY DATE_FORMAT(data_dorezimit, '%Y-%m') ORDER BY muaji"
)->fetchAll();
$topProducts = $pdo->query(
    "SELECT i.emri_produktit, SUM(i.sasia) AS sasi, SUM(i.cmimi * i.sasia) AS te_ardhura
     FROM me_porosi_items i JOIN me_porosite_online o ON o.id = i.porosia_id
     WHERE o.statusi = 'Dorëzuar' GROUP BY i.emri_produktit
     ORDER BY sasi DESC, te_ardhura DESC LIMIT 10"
)->fetchAll();
$totals = $pdo->query(
    "SELECT COALESCE(SUM(totali),0) AS te_ardhura, COUNT(*) AS porosi,
            COALESCE(AVG(totali),0) AS mesatarja
     FROM me_porosite_online WHERE statusi = 'Dorëzuar'"
)->fetch();
$chartLabels = array_column($monthly, 'muaji');
$chartRevenue = array_map(static fn(array $row): float => (float) $row['te_ardhura'], $monthly);

admin_header('Raportet financiare');
?>
<section class="stats-grid">
    <article class="stat-card"><span>Të ardhura të dorëzuara</span><strong><?= e(number_format((float) $totals['te_ardhura'], 2)) ?> €</strong></article>
    <article class="stat-card"><span>Porosi të dorëzuara</span><strong><?= e($totals['porosi']) ?></strong></article>
    <article class="stat-card"><span>Vlera mesatare e porosisë</span><strong><?= e(number_format((float) $totals['mesatarja'], 2)) ?> €</strong></article>
</section>
<section class="panel"><h2>Të ardhurat mujore · 12 muajt e fundit</h2><canvas id="revenueChart" height="100" aria-label="Grafiku i të ardhurave"></canvas></section>
<section class="panel">
    <h2>Produktet më të shitura</h2>
    <?php if ($topProducts === []): ?><p class="empty">Nuk ka shitje të dorëzuara për raportim.</p>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th>Produkti</th><th>Sasia e shitur</th><th>Të ardhurat</th></tr></thead><tbody>
        <?php foreach ($topProducts as $product): ?><tr><td><?= e($product['emri_produktit']) ?></td><td><?= e($product['sasi']) ?></td><td><?= e(number_format((float) $product['te_ardhura'], 2)) ?> €</td></tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
const revenueLabels = <?= json_encode($chartLabels, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
const revenueValues = <?= json_encode($chartRevenue, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
new Chart(document.getElementById('revenueChart'), {
    type: 'line',
    data: { labels: revenueLabels, datasets: [{ label: 'Të ardhura (€)', data: revenueValues, borderColor: '#ad1457', backgroundColor: 'rgba(236,64,122,.16)', fill: true, tension: .35 }] },
    options: { responsive: true, scales: { y: { beginAtZero: true } } }
});
</script>
<?php admin_footer(); ?>
