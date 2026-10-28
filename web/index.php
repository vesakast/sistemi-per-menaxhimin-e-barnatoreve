<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$categories = $pdo->query(
    "SELECT id, emri, slug, pershkrimi FROM me_kategorite WHERE statusi = 'Aktiv' ORDER BY emri"
)->fetchAll();
$featured = $pdo->query(
    "SELECT p.id, p.slug, p.emri, p.pershkrimi, p.cmimi, p.imazhi, c.emri AS kategoria,
            ps.stoku_aktual
     FROM me_produktet_stoku ps JOIN me_produktet p ON p.id=ps.id
     LEFT JOIN me_kategorite c ON c.id=p.kategoria_id
     WHERE p.aktiv=1 AND p.i_dukshem_ne_web=1 AND ps.stoku_aktual>0
       AND (c.id IS NULL OR c.statusi='Aktiv')
     ORDER BY ps.stoku_aktual DESC, p.id DESC LIMIT 8"
)->fetchAll();
$store = $pdo->query('SELECT emri_dyqanit FROM me_cilesimet WHERE id=1')->fetchColumn() ?: 'HerbaBloom';

web_header((string) $store);
?>
<section class="hero">
    <div><p class="eyebrow">Kujdes natyral · Përzgjedhur me dashuri</p>
        <h1>Pak më shumë natyrë në çdo ditë.</h1>
        <p>Eksploroni përzgjedhjen tonë të produkteve bimore, çajrave, vajrave esenciale dhe kujdesit të butë.</p>
        <a class="btn" href="<?= e(app_url('/web/produktet.php')) ?>">Zbulo koleksionin</a></div>
    <div class="hero-flower" aria-hidden="true">✿</div>
</section>
<section>
    <div class="section-heading"><div><p class="eyebrow">Koleksionet</p><h2>Eksploro sipas kategorisë</h2></div></div>
    <div class="category-grid">
        <?php foreach ($categories as $category): ?><a class="category-card" href="<?= e(app_url('/web/produktet.php?kategoria=' . rawurlencode($category['slug']))) ?>">
            <strong><?= e($category['emri']) ?></strong><span><?= e($category['pershkrimi'] ?? 'Zbulo produktet në këtë kategori.') ?></span>
        </a><?php endforeach; ?>
    </div>
</section>
<section>
    <div class="section-heading"><div><p class="eyebrow">Të përzgjedhura për ju</p><h2>Produktet e preferuara</h2></div><a href="<?= e(app_url('/web/produktet.php')) ?>">Shiko katalogun →</a></div>
    <div class="product-grid"><?php foreach ($featured as $product) { web_product_card($product); } ?></div>
    <?php if ($featured === []): ?><div class="glass-card empty">Produktet e disponueshme do të shfaqen së shpejti.</div><?php endif; ?>
</section>
<?php web_footer(); ?>
