<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$design = hb_design_public($pdo);
$categories = $pdo->query(
    "SELECT id, emri, slug, pershkrimi FROM me_kategorite
     WHERE statusi='Aktiv' ORDER BY emri"
)->fetchAll();

$categoryImages = [
    'barna-dhe-medikamente' => 'barna-dhe-medikamente.jpg',
    'cajra-bimore' => 'qajra-bimore.jpg',
    'kremra-salca-bimore' => 'kremra-dhe-salca-bimore.jpg',
    'vajra-esenciale' => 'vajra-esenciale.jpg',
];
$categoryCopy = [
    'barna-dhe-medikamente' => 'Shfleto produktet e kësaj kategorie dhe kontrollo gjithmonë etiketën dhe udhëzimet përkatëse.',
    'cajra-bimore' => 'Zgjedhje çajrash për t’i përfshirë sipas dëshirës në rutinën tënde të përditshme.',
    'kremra-salca-bimore' => 'Produkte për kujdesin e lëkurës, të përdorura sipas përbërjes dhe udhëzimeve të etiketës.',
    'vajra-esenciale' => 'Vajra aromatikë për përdorim të kujdesshëm dhe sipas udhëzimeve të produktit.',
];
foreach ($categories as &$category) {
    $imageFile = $categoryImages[$category['slug']] ?? 'category-' . $category['slug'] . '.jpg';
    $categoryImagePath = dirname(__DIR__) . '/web-images/' . $imageFile;
    $category['image'] = is_file($categoryImagePath)
        ? app_url('/web-images/' . rawurlencode($imageFile))
        : '';
    $category['tagline'] = $categoryCopy[$category['slug']] ?? trim((string) ($category['pershkrimi'] ?? ''));
}
unset($category);

$featuredQuery = $pdo->query(
    "SELECT p.id,p.slug,p.emri,p.pershkrimi,p.cmimi,p.imazhi,c.emri AS kategoria,ps.stoku_aktual
     FROM me_produktet_stoku ps
     JOIN me_produktet p ON p.id=ps.id
     LEFT JOIN me_kategorite c ON c.id=p.kategoria_id
     WHERE p.aktiv=1 AND p.i_dukshem_ne_web=1 AND ps.stoku_aktual>0
       AND (c.id IS NULL OR c.statusi='Aktiv') AND p.data_krijimit>=DATE_SUB(NOW(),INTERVAL 30 DAY)
     ORDER BY p.data_krijimit DESC,p.id DESC LIMIT 8"
);
$newProducts = $featuredQuery->fetchAll();

$bestSellersQuery = $pdo->query(
    "SELECT p.id,p.slug,p.emri,p.pershkrimi,p.cmimi,p.imazhi,c.emri AS kategoria,ps.stoku_aktual,
            SUM(-sm.sasia) AS units_sold
     FROM me_stoku_levizjet sm
     JOIN me_porosite_online o ON o.id=sm.porosia_id AND o.statusi='Dorëzuar'
     JOIN me_produktet_stoku ps ON ps.id=sm.produkt_id
     JOIN me_produktet p ON p.id=ps.id
     LEFT JOIN me_kategorite c ON c.id=p.kategoria_id
     WHERE sm.lloji='shitje' AND sm.sasia<0
       AND p.aktiv=1 AND p.i_dukshem_ne_web=1 AND ps.stoku_aktual>0
       AND (c.id IS NULL OR c.statusi='Aktiv')
     GROUP BY p.id
     ORDER BY units_sold DESC,p.id DESC LIMIT 4"
);
$bestSellers = $bestSellersQuery->fetchAll();
$storeName = (string) ($pdo->query('SELECT emri_dyqanit FROM me_cilesimet WHERE id=1')->fetchColumn() ?: 'HerbaBloom');
$slides = [
    [
        'eyebrow' => 'Kujdes i frymëzuar nga natyra',
        'title' => 'Zgjedhje të kujdesshme për rutinën tënde.',
        'text' => 'Eksploro çajra, suplemente, vajra dhe produkte për kujdesin e lëkurës e flokëve.',
        'image' => 'banner-natural-care.jpg',
        'video' => 'hero-slide-1.mp4',
    ],
    [
        'eyebrow' => 'Gjej atë që të përshtatet',
        'title' => 'Kujdes personal, sipas ritmit tënd.',
        'text' => 'Krahaso përshkrimet dhe përbërjet për të zgjedhur çfarë i përshtatet rutinës sate.',
        'image' => 'about-herbabloom.jpg',
        'video' => 'hero-slide-2.mp4',
    ],
    [
        'eyebrow' => 'Mirë se vjen në HerbaBloom',
        'title' => 'Zbulo produkte për mirëqenie dhe kujdes.',
        'text' => 'Një katalog online me përzgjedhje bimore, vitamina, suplemente dhe kujdes personal.',
        'image' => 'background-image.jpg',
        'video' => 'hero-slide-3.mp4',
    ],
];
if ($design['hero']['enabled']) {
    $slides[0]['eyebrow'] = $design['hero']['eyebrow'];
    $slides[0]['title'] = $design['hero']['title'];
    $slides[0]['text'] = $design['hero']['description'];
    $slides[0]['button_text'] = $design['hero']['button_text'];
    $slides[0]['button_url'] = $design['hero']['button_url'];
    if ($design['hero']['background_image'] !== '') {
        $slides[0]['image'] = basename((string) parse_url($design['hero']['background_image'], PHP_URL_PATH));
        $slides[0]['configured_image'] = $design['hero']['background_image'];
    }
    if ($design['hero']['video_url'] !== '') {
        $slides[0]['video'] = basename((string) parse_url($design['hero']['video_url'], PHP_URL_PATH));
        $slides[0]['configured_video'] = $design['hero']['video_url'];
    } else {
        $slides[0]['video'] = '';
    }
    if ($design['hero']['poster'] !== '') {
        $slides[0]['image'] = basename((string) parse_url($design['hero']['poster'], PHP_URL_PATH));
        $slides[0]['configured_image'] = $design['hero']['poster'];
    }
}
foreach ($slides as &$slide) {
    $imagePath = dirname(__DIR__) . '/web-images/' . $slide['image'];
    $slide['image_url'] = $slide['configured_image'] ?? (is_file($imagePath)
        ? app_url('/web-images/' . rawurlencode($slide['image']))
        : '');
    $videoPath = dirname(__DIR__) . '/web-images/' . $slide['video'];
    $slide['video_url'] = $slide['configured_video'] ?? (is_file($videoPath)
        ? app_url('/web-images/' . rawurlencode($slide['video']))
        : '');
    $slide['video_type'] = strtolower(pathinfo($slide['video'], PATHINFO_EXTENSION)) === 'webm'
        ? 'video/webm'
        : 'video/mp4';
}
unset($slide);

$activeOffers = [];
if (customer_schema_ready($pdo)) {
    $activeOffers = $pdo->query(
        "SELECT id,slug,emri,pershkrimi,imazhi,cmimi
         FROM me_ofertat
         WHERE aktiv=1 AND (fillon_me IS NULL OR fillon_me<=NOW())
           AND (mbaron_me IS NULL OR mbaron_me>=NOW())
         ORDER BY id DESC LIMIT 4"
    )->fetchAll();
}

web_header('Ballina');
?>
<section class="hero-slider<?= !$design['hero']['enabled'] ? ' design-hero-hidden' : '' ?>" aria-roledescription="karusel" data-design-hero>
    <?php foreach ($slides as $index => $slide): ?>
        <article class="hero-slide <?= $index === 0 ? 'is-active' : '' ?>" aria-hidden="<?= $index === 0 ? 'false' : 'true' ?>" data-hero-slide>
            <div class="hero-copy">
                <p class="eyebrow" data-design-hero-eyebrow><?= e($slide['eyebrow']) ?></p>
                <h1 data-design-hero-title><?= e($slide['title']) ?></h1>
                <p data-design-hero-description><?= e($slide['text']) ?></p>
                <a class="btn btn-forest btn-lg" href="<?= e($index === 0 ? ($slide['button_url'] ?? app_url('/web/produktet.php')) : app_url('/web/produktet.php')) ?>" data-design-hero-button><?= e($index === 0 ? ($slide['button_text'] ?? 'Shfleto katalogun') : 'Shfleto katalogun') ?> <i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
            </div>
            <div class="hero-visual <?= $slide['image_url'] === '' && $slide['video_url'] === '' ? 'has-placeholder' : '' ?>">
                <?php if ($slide['video_url'] !== ''): ?>
                    <?php if ($slide['image_url'] !== ''): ?><img class="hero-video-poster" src="<?= e($slide['image_url']) ?>" alt="" aria-hidden="true"><?php endif; ?>
                    <video<?= in_array($slide['video'], ['hero-slide-1.mp4', 'hero-slide-2.mp4', 'hero-slide-3.mp4'], true) ? ' class="hero-video-fill hero-video-slide-' . e((string) ($index + 1)) . '"' : '' ?> muted playsinline preload="metadata"<?= $slide['image_url'] !== '' ? ' poster="' . e($slide['image_url']) . '"' : '' ?> aria-label="Video e produkteve bimore">
                        <source src="<?= e($slide['video_url']) ?>" type="<?= e($slide['video_type']) ?>">
                    </video>
                <?php elseif ($slide['image_url'] !== ''): ?>
                    <img src="<?= e($slide['image_url']) ?>" alt="Përzgjedhje produktesh bimore HerbaBloom" fetchpriority="<?= $index === 0 ? 'high' : 'auto' ?>">
                <?php else: ?>
                    <div class="image-placeholder hero-placeholder" role="img" aria-label="Pamja e videos së HerbaBloom do të shtohet">
                        <i class="bi bi-flower1" aria-hidden="true"></i><span>Videoja do të shtohet</span>
                    </div>
                <?php endif; ?>
                <span class="hero-visual-caption"><?= e($storeName) ?> · Kujdes bimor</span>
            </div>
        </article>
    <?php endforeach; ?>
    <div class="hero-controls">
        <button type="button" class="hero-arrow" data-hero-prev aria-label="Slide i mëparshëm"><i class="bi bi-arrow-left" aria-hidden="true"></i></button>
        <div class="hero-dots" aria-label="Zgjidh slide-in">
            <?php foreach ($slides as $index => $_): ?>
                <button type="button" class="hero-dot <?= $index === 0 ? 'is-active' : '' ?>" data-hero-dot="<?= e($index) ?>" aria-label="Shfaq slide-in <?= e($index + 1) ?>" aria-current="<?= $index === 0 ? 'true' : 'false' ?>"></button>
            <?php endforeach; ?>
        </div>
        <button type="button" class="hero-arrow" data-hero-next aria-label="Slide i radhës"><i class="bi bi-arrow-right" aria-hidden="true"></i></button>
    </div>
</section>

<section class="benefit-strip" aria-label="Shërbimet e dyqanit" data-reveal>
    <div><i class="bi bi-cash-coin" aria-hidden="true"></i><span><strong>Pagesë në dorëzim</strong><small>Paguaj kur të mbërrijë porosia</small></span></div>
    <div><i class="bi bi-box-seam" aria-hidden="true"></i><span><strong>Porosi të përcjellshme</strong><small>Kontrollo statusin me kodin tënd</small></span></div>
    <div><i class="bi bi-chat-heart" aria-hidden="true"></i><span><strong>Na pyet kur të duhet</strong><small>Kontakto ekipin për ndihmë</small></span></div>
</section>

<section class="store-section" data-reveal>
    <div class="section-heading">
        <div><p class="eyebrow">Nis nga ajo që po kërkon</p><h2>Eksploro kategoritë</h2></div>
        <a class="text-link" href="<?= e(app_url('/web/produktet.php')) ?>">Shfleto gjithë katalogun <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <div class="category-grid">
        <?php foreach ($categories as $category): ?>
            <a class="category-card" href="<?= e(app_url('/web/produktet.php?kategoria=' . rawurlencode($category['slug']))) ?>">
                <div class="category-image">
                    <?php if ($category['image'] !== ''): ?>
                        <img src="<?= e($category['image']) ?>" alt="" loading="lazy">
                    <?php else: ?>
                        <span class="image-placeholder" role="img" aria-label="Fotografia e kategorisë do të shtohet">
                            <i class="bi bi-flower1" aria-hidden="true"></i><span>Fotografia do të shtohet</span>
                        </span>
                    <?php endif; ?>
                </div>
                <span class="category-name"><?= e($category['emri']) ?></span>
                <?php if ($category['tagline'] !== ''): ?><span class="category-description"><?= e($category['tagline']) ?></span><?php endif; ?>
                <i class="bi bi-arrow-up-right category-arrow" aria-hidden="true"></i>
            </a>
        <?php endforeach; ?>
    </div>
</section>

<section class="store-section" data-reveal>
    <div class="section-heading">
        <div><p class="eyebrow">Shtuar së fundmi në katalog</p><h2>Produkte të reja</h2></div>
        <a class="text-link" href="<?= e(app_url('/web/produktet.php?sort=newest')) ?>">Shiko produktet e fundit <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <?php if ($newProducts !== []): ?>
        <div class="product-grid"><?php foreach ($newProducts as $product) { web_product_card($product, 'E re'); } ?></div>
    <?php else: ?>
        <div class="empty-state"><i class="bi bi-flower1" aria-hidden="true"></i><p>Produktet që janë aktualisht në stok do të shfaqen këtu.</p></div>
    <?php endif; ?>
</section>

<section class="store-section" data-reveal>
    <div class="section-heading">
        <div><p class="eyebrow">Sipas porosive të dorëzuara</p><h2>Produktet më të porositura</h2></div>
        <a class="text-link" href="<?= e(app_url('/web/bestseller.php')) ?>">Shiko renditjen <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <?php if ($bestSellers !== []): ?>
        <div class="product-grid"><?php foreach ($bestSellers as $product) { web_product_card($product, 'Bestseller'); } ?></div>
    <?php else: ?>
        <div class="empty-state"><i class="bi bi-stars" aria-hidden="true"></i><p>Renditja do të përditësohet sapo të ketë porosi të dorëzuara.</p></div>
    <?php endif; ?>
</section>

<section class="store-section" data-reveal>
    <div class="section-heading">
        <div><p class="eyebrow">Produkte të kombinuara në sete</p><h2>Oferta aktive</h2></div>
        <a class="text-link" href="<?= e(app_url('/web/oferta.php')) ?>">Shiko përbërjen e seteve <i class="bi bi-arrow-right" aria-hidden="true"></i></a>
    </div>
    <?php if ($activeOffers !== []): ?>
        <div class="offer-grid">
            <?php foreach ($activeOffers as $offer): ?>
                <a class="offer-teaser" href="<?= e(app_url('/web/oferta.php#' . rawurlencode($offer['slug']))) ?>">
                    <?php $offerImage = offer_image_path($offer); ?>
                    <?php if ($offerImage !== ''): ?><img class="offer-teaser-image" src="<?= e($offerImage) ?>" alt="" loading="lazy">
                    <?php else: ?><span class="offer-icon" role="img" aria-label="<?= e('Fotografia e ' . $offer['emri'] . ' do të shtohet') ?>"><i class="bi bi-gift" aria-hidden="true"></i></span><?php endif; ?>
                    <span><strong><?= e($offer['emri']) ?></strong><small><?= e(mb_strimwidth((string) $offer['pershkrimi'], 0, 82, '…', 'UTF-8')) ?></small></span>
                    <b><?= e(number_format((float) $offer['cmimi'], 2)) ?> €</b>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else: ?>
        <div class="empty-state"><i class="bi bi-gift" aria-hidden="true"></i><p>Kur të ketë sete aktive, do t’i gjesh këtu me çmimin dhe përbërjen përkatëse.</p></div>
    <?php endif; ?>
</section>

<section class="natural-banner" data-reveal>
    <div class="natural-banner-image">
        <?php $bannerPath = dirname(__DIR__) . '/web-images/banner-natural-care.jpg'; ?>
        <?php if (is_file($bannerPath)): ?><img src="<?= e(app_url('/web-images/banner-natural-care.jpg')) ?>" alt="Përzgjedhje e kujdesit bimor" loading="lazy">
        <?php else: ?><span class="image-placeholder" role="img" aria-label="Pamje ilustruese për përzgjedhjen bimore"><i class="bi bi-leaf" aria-hidden="true"></i><span>Një rutinë e frymëzuar nga natyra</span></span><?php endif; ?>
    </div>
    <div class="natural-banner-copy"><p class="eyebrow">Përbërës me bazë bimore</p><h2>Kujdes që gjen vend në rutinën tënde.</h2><p>Lexo përshkrimet e produkteve dhe zgjidh me kujdes sipas preferencave dhe nevojave të tua.</p><a class="btn btn-light-outline" href="<?= e(app_url('/web/produktet.php')) ?>">Eksploro katalogun</a></div>
</section>

<section class="values-strip" data-reveal>
    <div><i class="bi bi-flower2" aria-hidden="true"></i><span>Produkte me përbërës me bazë bimore</span></div>
    <div><i class="bi bi-heart" aria-hidden="true"></i><span>Informacion i qartë për blerjen</span></div>
    <div><i class="bi bi-box2-heart" aria-hidden="true"></i><span>Kujdes në çdo porosi</span></div>
</section>
<?php web_footer(); ?>
