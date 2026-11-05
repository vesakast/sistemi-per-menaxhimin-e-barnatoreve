<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$offers = customer_schema_ready($pdo) ? $pdo->query(
    "SELECT id,slug,emri,pershkrimi,imazhi,cmimi
     FROM me_ofertat
     WHERE aktiv=1 AND (fillon_me IS NULL OR fillon_me<=NOW())
       AND (mbaron_me IS NULL OR mbaron_me>=NOW())
     ORDER BY id DESC"
)->fetchAll() : [];
$offerItems = [];
$offerIds = array_map(static fn(array $offer): int => (int) $offer['id'], $offers);
if ($offerIds !== []) {
    $placeholders = implode(',', array_fill(0, count($offerIds), '?'));
    $query = $pdo->prepare(
        "SELECT oi.oferta_id,oi.sasia,p.id,p.slug,p.emri,p.cmimi,p.imazhi,ps.stoku_aktual
         FROM me_oferta_items oi
         JOIN me_produktet p ON p.id=oi.produkt_id AND p.aktiv=1 AND p.i_dukshem_ne_web=1
         JOIN me_produktet_stoku ps ON ps.id=p.id
         WHERE oi.oferta_id IN ({$placeholders})
         ORDER BY oi.oferta_id,p.emri"
    );
    $query->execute($offerIds);
    foreach ($query->fetchAll() as $item) {
        $offerItems[(int) $item['oferta_id']][] = $item;
    }
}

web_header('Ofertat');
?>
<section class="page-intro" data-reveal>
    <p class="eyebrow">Përzgjedhje të kombinuara</p>
    <h1 class="page-title">Sete dhe oferta aktive</h1>
    <p>Shiko produktet që përfshin secili set dhe çmimin e publikuar. Përbërja e disponueshme mund të ndryshojë sipas stokut.</p>
</section>
<?php if ($offers === []): ?>
    <div class="empty-state"><i class="bi bi-gift" aria-hidden="true"></i><p>Nuk ka sete aktive për momentin. Ndërkohë, mund të eksplorosh produktet një nga një.</p><a class="btn btn-forest" href="<?= e(app_url('/web/produktet.php')) ?>">Shfleto katalogun</a></div>
<?php else: ?>
    <div class="offer-list">
        <?php foreach ($offers as $offer):
            $items = $offerItems[(int) $offer['id']] ?? [];
            $offerImage = offer_image_path($offer);
            $regularTotal = round(array_sum(array_map(
                static fn(array $item): float => (float) $item['cmimi'] * (int) $item['sasia'],
                $items
            )), 2);
            $hasDiscount = $regularTotal > (float) $offer['cmimi'];
            ?>
            <article id="<?= e($offer['slug']) ?>" class="offer-detail content-card" data-reveal>
                <div class="offer-detail-image">
                    <?php if ($offerImage !== ''): ?>
                        <img src="<?= e($offerImage) ?>" alt="<?= e('Foto e ' . $offer['emri']) ?>" loading="lazy">
                    <?php else: ?>
                        <span class="image-placeholder offer-image-placeholder" role="img" aria-label="<?= e('Ilustrim për ' . $offer['emri']) ?>">
                            <i class="bi bi-gift" aria-hidden="true"></i><span>Foto e setit do të shtohet</span>
                        </span>
                    <?php endif; ?>
                </div>
                <div class="offer-detail-heading">
                    <div><p class="eyebrow"><?= $hasDiscount ? 'Çmim seti i konfiguruar' : 'Set i përzgjedhur' ?></p><h2><?= e($offer['emri']) ?></h2></div>
                    <div class="offer-prices">
                        <?php if ($hasDiscount): ?><del><?= e(number_format($regularTotal, 2)) ?> €</del><?php endif; ?>
                        <strong><?= e(number_format((float) $offer['cmimi'], 2)) ?> €</strong>
                    </div>
                </div>
                <?php if (!empty($offer['pershkrimi'])): ?><p><?= e($offer['pershkrimi']) ?></p><?php endif; ?>
                <h3>Çfarë përfshin ky set</h3>
                <?php if ($items === []): ?><p class="help">Përbërja nuk është publikuar.</p>
                <?php else: ?>
                    <ul class="offer-components">
                        <?php foreach ($items as $item): ?>
                            <li>
                                <span><?= e($item['emri']) ?> <small>× <?= e($item['sasia']) ?></small></span>
                                <span><?= e(number_format((float) $item['cmimi'] * (int) $item['sasia'], 2)) ?> €</span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php $available = array_reduce($items, static fn(bool $ok, array $item): bool => $ok && (int) $item['stoku_aktual'] >= (int) $item['sasia'], true); ?>
                    <?php if ($available): ?>
                        <form method="post" action="<?= e(app_url('/web/shporta.php')) ?>">
                            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                            <input type="hidden" name="action" value="add_bundle">
                            <input type="hidden" name="offer_id" value="<?= e($offer['id']) ?>">
                            <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/web/oferta.php') ?>">
                            <button class="btn btn-forest" type="submit">Shto setin në shportë</button>
                        </form>
                    <?php else: ?><span class="stock-out">Disa produkte të setit nuk janë në stok</span><?php endif; ?>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php web_footer(); ?>
