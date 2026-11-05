<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$products = $pdo->query(
    "SELECT p.id,p.slug,p.emri,p.pershkrimi,p.cmimi,p.imazhi,c.emri AS kategoria,
            ps.stoku_aktual,SUM(-sm.sasia) AS units_sold
     FROM me_stoku_levizjet sm
     JOIN me_porosite_online o ON o.id=sm.porosia_id AND o.statusi='Dorëzuar'
     JOIN me_produktet_stoku ps ON ps.id=sm.produkt_id
     JOIN me_produktet p ON p.id=ps.id
     LEFT JOIN me_kategorite c ON c.id=p.kategoria_id
     WHERE sm.lloji='shitje' AND sm.sasia<0
       AND p.aktiv=1 AND p.i_dukshem_ne_web=1 AND ps.stoku_aktual>0
       AND (c.id IS NULL OR c.statusi='Aktiv')
     GROUP BY p.id
     ORDER BY units_sold DESC,p.id DESC LIMIT 24"
)->fetchAll();

web_header('Produktet më të porositura');
?>
<section class="page-intro" data-reveal>
    <p class="eyebrow">Zgjedhje të njohura në katalog</p>
    <h1 class="page-title">Produktet më të porositura</h1>
    <p>Kjo renditje përllogaritet nga sasitë e produkteve në porositë e dorëzuara. Nuk përdorim vlerësime apo komente të sajuara.</p>
</section>
<?php if ($products !== []): ?>
    <div class="product-grid"><?php foreach ($products as $product) { web_product_card($product, 'Bestseller'); } ?></div>
<?php else: ?>
    <div class="empty-state"><i class="bi bi-stars" aria-hidden="true"></i><p>Renditja do të shfaqet pasi të regjistrohen porosi të dorëzuara.</p><a class="btn btn-forest" href="<?= e(app_url('/web/produktet.php')) ?>">Eksploro katalogun</a></div>
<?php endif; ?>
<?php web_footer(); ?>
