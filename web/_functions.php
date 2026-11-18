<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/admin/_functions.php';
require_once dirname(__DIR__) . '/admin/_inventory.php';
require_once __DIR__ . '/_design.php';

if (($_GET['visual_preview'] ?? '') === '1' && ($_SESSION['role'] ?? '') === 'admin') {
    header('X-Frame-Options: SAMEORIGIN');
}

function current_cart(): array
{
    $cart = [];
    $storedCart = $_SESSION['cart'] ?? [];
    if (!is_array($storedCart)) {
        $storedCart = [];
    }
    foreach ($storedCart as $id => $quantity) {
        $productId = filter_var($id, FILTER_VALIDATE_INT);
        $productQuantity = filter_var($quantity, FILTER_VALIDATE_INT);
        if ($productId && $productId > 0 && $productQuantity && $productQuantity > 0) {
            $cart[$productId] = min(99, (int) $productQuantity);
        }
    }
    $_SESSION['cart'] = $cart;
    ksort($cart);
    return $cart;
}

function current_bundle_cart(): array
{
    $cart = [];
    $storedCart = $_SESSION['bundle_cart'] ?? [];
    if (!is_array($storedCart)) {
        $storedCart = [];
    }
    foreach ($storedCart as $id => $quantity) {
        $offerId = filter_var($id, FILTER_VALIDATE_INT);
        $offerQuantity = filter_var($quantity, FILTER_VALIDATE_INT);
        if ($offerId && $offerId > 0 && $offerQuantity && $offerQuantity > 0) {
            $cart[$offerId] = min(20, (int) $offerQuantity);
        }
    }
    $_SESSION['bundle_cart'] = $cart;
    ksort($cart);
    return $cart;
}

function cart_item_count(): int
{
    return array_sum(current_cart()) + array_sum(current_bundle_cart());
}

function cart_return_path(): string
{
    $fallback = '/web/produktet.php?njoftim=shporta';
    $candidate = $_POST['return_to'] ?? '';
    if (!is_string($candidate)) {
        return $fallback;
    }

    $parts = parse_url($candidate);
    if (!is_array($parts) || isset($parts['scheme']) || isset($parts['host'])
        || isset($parts['user']) || isset($parts['pass'])) {
        return $fallback;
    }

    $route = basename((string) ($parts['path'] ?? ''));
    $allowedRoutes = ['index.php', 'produktet.php', 'bestseller.php', 'oferta.php'];
    if (!in_array($route, $allowedRoutes, true)
        || ($parts['path'] ?? '') !== app_url('/web/' . $route)) {
        return $fallback;
    }

    $query = [];
    parse_str((string) ($parts['query'] ?? ''), $submittedQuery);
    foreach (['q', 'kategoria', 'sort', 'page', 'slug'] as $key) {
        $value = $submittedQuery[$key] ?? null;
        if (is_string($value) && strlen($value) <= 200) {
            $query[$key] = $value;
        } elseif (is_scalar($value) && $value !== null) {
            $query[$key] = (string) $value;
        }
    }
    $query['njoftim'] = 'shporta';

    return '/web/' . $route . '?' . http_build_query($query);
}

function fetch_cart_products(PDO $pdo, array $cart): array
{
    if ($cart === []) {
        return [];
    }
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = $pdo->prepare(
        "SELECT id, slug, emri, cmimi, imazhi, stoku_aktual
         FROM me_produktet_stoku
         WHERE id IN ({$placeholders}) AND aktiv=1 AND i_dukshem_ne_web=1"
    );
    $query->execute($ids);
    $products = [];
    foreach ($query->fetchAll() as $product) {
        $products[(int) $product['id']] = $product;
    }
    return $products;
}

function fetch_cart_offers(PDO $pdo, array $cart): array
{
    if ($cart === [] || !customer_schema_ready($pdo)) {
        return [];
    }
    $ids = array_keys($cart);
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $query = $pdo->prepare(
        "SELECT id,slug,emri,pershkrimi,cmimi
         FROM me_ofertat
         WHERE id IN ({$placeholders}) AND aktiv=1
           AND (fillon_me IS NULL OR fillon_me<=NOW())
           AND (mbaron_me IS NULL OR mbaron_me>=NOW())"
    );
    $query->execute($ids);
    $offers = [];
    foreach ($query->fetchAll() as $offer) {
        $offers[(int) $offer['id']] = $offer + ['artikujt' => [], 'cmimi_i_rregullt' => 0.0];
    }
    if ($offers === []) {
        return [];
    }
    $offerIds = array_keys($offers);
    $itemPlaceholders = implode(',', array_fill(0, count($offerIds), '?'));
    $itemQuery = $pdo->prepare(
        "SELECT oi.oferta_id,oi.produkt_id,oi.sasia,p.emri,p.slug,p.imazhi,p.cmimi AS cmimi_produkti,
                ps.stoku_aktual
         FROM me_oferta_items oi
         JOIN me_produktet p ON p.id=oi.produkt_id AND p.aktiv=1 AND p.i_dukshem_ne_web=1
         JOIN me_produktet_stoku ps ON ps.id=p.id
         WHERE oi.oferta_id IN ({$itemPlaceholders})
         ORDER BY oi.oferta_id,p.emri"
    );
    $itemQuery->execute($offerIds);
    foreach ($itemQuery->fetchAll() as $item) {
        $offerId = (int) $item['oferta_id'];
        $item['sasia'] = (int) $item['sasia'];
        $item['stoku_aktual'] = (int) $item['stoku_aktual'];
        $offers[$offerId]['artikujt'][] = $item;
        $offers[$offerId]['cmimi_i_rregullt'] += (float) $item['cmimi_produkti'] * $item['sasia'];
    }
    foreach ($offers as $offerId => $offer) {
        if ($offer['artikujt'] === []) {
            unset($offers[$offerId]);
            continue;
        }
        $offers[$offerId]['cmimi_i_rregullt'] = round($offer['cmimi_i_rregullt'], 2);
    }
    return $offers;
}

function customer_schema_ready(PDO $pdo): bool
{
    $query = $pdo->query(
        "SELECT
            (SELECT COUNT(DISTINCT TABLE_NAME) FROM information_schema.TABLES
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME IN (
                 'me_klientet','me_klient_favorites','me_klient_preferencat',
                 'me_klient_login_tentativa','me_ofertat','me_oferta_items'
               )) AS table_count,
            (SELECT COUNT(*) FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA=DATABASE()
               AND TABLE_NAME='me_porosite_online' AND COLUMN_NAME='klient_id') AS order_owner_column"
    );
    $schema = $query->fetch();
    return (int) $schema['table_count'] === 6 && (int) $schema['order_owner_column'] === 1;
}

function current_customer(PDO $pdo): ?array
{
    $customerId = filter_var($_SESSION['customer_id'] ?? null, FILTER_VALIDATE_INT);
    if (!$customerId || !customer_schema_ready($pdo)) {
        return null;
    }
    if (isset($_SESSION['customer_last_activity'])
        && time() - (int) $_SESSION['customer_last_activity'] > 1800) {
        unset($_SESSION['customer_id'], $_SESSION['customer_last_activity']);
        return null;
    }
    $query = $pdo->prepare(
        "SELECT id, emri, email FROM me_klientet WHERE id=? AND statusi='Aktiv' LIMIT 1"
    );
    $query->execute([$customerId]);
    $customer = $query->fetch();
    if (!$customer) {
        unset($_SESSION['customer_id'], $_SESSION['customer_last_activity']);
        return null;
    }
    $_SESSION['customer_last_activity'] = time();
    return $customer;
}

function product_image_path(array $product): string
{
    $storedImage = trim((string) ($product['imazhi'] ?? ''));
    if ($storedImage !== '' && filter_var($storedImage, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string) parse_url($storedImage, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            return $storedImage;
        }
    }

    if ($storedImage !== '' && !preg_match('~^(?:https?:)?//~i', $storedImage)
        && !str_contains($storedImage, '..') && !str_contains($storedImage, "\0")) {
        $relativePath = ltrim(str_replace('\\', '/', $storedImage), '/');
        $candidate = dirname(__DIR__) . '/' . $relativePath;
        if (is_file($candidate)) {
            return app_url('/' . implode('/', array_map('rawurlencode', explode('/', $relativePath))));
        }
    }

    $slug = (string) ($product['slug'] ?? '');
    static $productImages = [
        'echinacea' => 'Echinacea.jpg',
        'ginkgo-biloba' => 'Ginkgo Biloba.jpg',
        'ginseng-panax' => 'Ginseng (Panax).jpg',
        'shafrani-i-indise-turmeric' => 'Turmeric.jpg',
        'silymarin-milk-thistle' => 'Silymarin (Milk Thistle).jpg',
        'valeriana' => 'Valerian.jpg',
        'krifsa-e-shengjergjit' => 'St. John’s Wort.jpg',
        'hudhra-ne-kapsula' => 'Garlic Capsules.jpg',
        'saw-palmetto' => 'Saw Palmetto.jpg',
        'ashwagandha' => 'Ashwagandha.jpg',
        'vaji-i-primales-evening-primrose' => 'Evening Primrose Oil.jpg',
        'glukomanan' => 'Glucomannan.jpg',
        'boronica-e-kuqe-cranberry' => 'Cranberry.jpg',
        'pelini-i-zi' => 'Black Wormwood.jpg',
        'xhinxher-ne-kapsula' => 'Ginger Capsules.jpg',
        'macca-root' => 'Maca Root.jpg',
        'aloe-vera-xhel-oral' => 'Aloe Vera Gel (Oral).jpg',
        'fenugreek' => 'Fenugreek.jpg',
        'cascara-sagrada' => 'Cascara Sagrada.jpg',
        'bromelain' => 'Bromelain.jpg',
        'melatonina' => 'Melatonin.jpg',
        'magnezum-citrat' => 'Magnesium Citrate.jpg',
        'kompleksi-i-vitaminave-b' => 'Vitamin B Complex.jpg',
        'vitamina-d3' => 'Vitamin D3.jpg',
        'vitamina-c-me-zinc' => 'Vitamin C with Zinc.jpg',
        'omega-3-vaj-peshku' => 'Omega-3 (Fish Oil).jpg',
        'koenzima-q10-coq10' => 'Coenzyme Q10 (CoQ10).jpg',
        'probiotiket' => 'Probiotics.jpg',
        '5-htp' => '5-HTP.jpg',
        'l-theanine' => 'L-Theanine.jpg',
        'vitamina-e' => 'Vitamin E.jpg',
        'hekur-bisglicinat' => 'Iron Bisglycinate.jpg',
        'kalcium-me-magnez-dhe-zink' => 'Calcium with Magnesium and Zinc.jpg',
        'glukozamine-dhe-kondroitine' => 'Glucosamine and Chondroitin.jpg',
        'kollagjen-hidrolizuar' => 'Hydrolyzed Collagen.jpg',
        'acid-folik-vitamina-b9' => 'Folic Acid (Vitamin B9).jpg',
        'kollostrum-kulloshter' => 'Colostrum.jpg',
        'l-carnitine' => 'L-Carnitine.jpg',
        'spirulina' => 'Spirulina.jpg',
        'pasiflora' => 'Passionflower.jpg',
        'caj-kamomili' => 'Chamomile Tea.jpg',
        'caj-mente' => 'Peppermint Tea.jpg',
        'caj-mali' => 'Mountain Tea.jpg',
        'caj-bliri' => 'Linden Tea.jpg',
        'caj-sherbele' => 'Sage Tea.jpg',
        'caj-uthi-hithre' => 'Nettle Tea.jpg',
        'caj-gjembgomari' => 'Milk Thistle Tea.jpg',
        'caj-trendafili-te-eger' => 'Rosehip Tea.jpg',
        'caj-xhinxheri-me-limon' => 'Ginger and Lemon Tea.jpg',
        'caj-hibiskusi' => 'Hibiscus Tea.jpg',
        'caj-anasi' => 'Anise Tea.jpg',
        'caj-finoku' => 'Fennel Tea.jpg',
        'caj-flete-ulliri' => 'Olive Leaf Tea.jpg',
        'caj-flete-boronice' => 'Blueberry Leaf Tea.jpg',
        'caj-rrenje-luleradhiqe' => 'Dandelion Root Tea.jpg',
        'caj-flete-mjedre' => 'Raspberry Leaf Tea.jpg',
        'caj-matcha' => 'Matcha Tea.jpg',
        'caj-rooibos' => 'Rooibos Tea.jpg',
        'caj-trumze' => 'Thyme Tea.jpg',
        'caj-flete-senna' => 'Senna Leaf Tea.jpg',
        'vaj-esencial-lavande' => 'Lavender Essential Oil.jpg',
        'vaj-esencial-te-pema-e-cajit' => 'Tea Tree Essential Oil.jpg',
        'vaj-esencial-mente' => 'Peppermint Essential Oil.jpg',
        'vaj-esencial-eukalipti' => 'Eucalyptus Essential Oil.jpg',
        'vaj-esencial-limoni' => 'Lemon Essential Oil.jpg',
        'vaj-esencial-rozmarine' => 'Rosemary Essential Oil.jpg',
        'vaj-esencial-portokalli' => 'Orange Essential Oil.jpg',
        'vaj-esencial-frankincense' => 'Frankincense Essential Oil.jpg',
        'vaj-esencial-ylang-ylang' => 'Ylang-Ylang Essential Oil.jpg',
        'vaj-esencial-oregano' => 'Oregano Essential Oil.jpg',
        'vaj-esencial-jasmine' => 'Jasmine Essential Oil.jpg',
        'vaj-esencial-grapefruit' => 'Grapefruit Essential Oil.jpg',
        'vaj-esencial-vetiver' => 'Vetiver Essential Oil.jpg',
        'vaj-esencial-karafili' => 'Clove Essential Oil.jpg',
        'vaj-esencial-cypress' => 'Cypress Essential Oil.jpg',
        'vaj-esencial-marjoram' => 'Marjoram Essential Oil.jpg',
        'vaj-esencial-myrrh' => 'Myrrh Essential Oil.jpg',
        'vaj-esencial-spearmint' => 'Spearmint Essential Oil.jpg',
        'vaj-esencial-citronella' => 'Citronella Essential Oil.jpg',
        'vaj-esencial-wintergreen' => 'Wintergreen Essential Oil.jpg',
        'krem-me-aloe-vera' => 'Aloe Vera Cream.jpg',
        'krem-me-calendula' => 'Calendula Cream.jpg',
        'krem-me-arnica' => 'Arnica Cream.jpg',
        'krem-me-acid-hialuronik' => 'Hyaluronic Acid Cream.jpg',
        'krem-me-retinol' => 'Retinol Cream.jpg',
        'krem-me-vitamine-c' => 'Vitamin C Cream.jpg',
        'krem-niacinamide' => 'Niacinamide Cream.jpg',
        'krem-me-propolis' => 'Propolis Cream.jpg',
        'krem-me-centella-asiatica-cica' => 'Centella Asiatica (Cica) Cream.jpg',
        'krem-me-gjalpe-shea' => 'Shea Butter Cream.jpg',
        'krem-me-ceramide' => 'Ceramide Cream.jpg',
        'krem-me-acid-salicilik' => 'Salicylic Acid Cream.jpg',
        'krem-me-bakuchiol' => 'Bakuchiol Cream.jpg',
        'krem-me-azelaic-acid' => 'Azelaic Acid Cream.jpg',
        'krem-me-peptide' => 'Peptide Cream.jpg',
        'krem-me-panthenol' => 'Panthenol Cream.jpg',
        'krem-me-vaj-neem' => 'Neem Oil Cream.jpg',
        'krem-me-zinc-oxide' => 'Zinc Oxide Cream.jpg',
        'krem-me-ure' => 'Urea Cream.jpg',
        'krem-me-ekstrakt-kafeine' => 'Caffeine Extract Cream.jpg',
    ];
    if (isset($productImages[$slug])) {
        $file = $productImages[$slug];
        $candidate = dirname(__DIR__) . '/web-images/' . $file;
        return is_file($candidate) ? app_url('/web-images/' . rawurlencode($file)) : '';
    }

    return '';
}

function offer_image_path(array $offer): string
{
    $storedImage = trim((string) ($offer['imazhi'] ?? ''));
    if ($storedImage !== '' && filter_var($storedImage, FILTER_VALIDATE_URL)) {
        $scheme = strtolower((string) parse_url($storedImage, PHP_URL_SCHEME));
        if (in_array($scheme, ['http', 'https'], true)) {
            return $storedImage;
        }
    }

    $slug = (string) ($offer['slug'] ?? '');
    static $offerImages = [
        'set-kujdesi-i-lekures' => 'offer-set-kujdesi-i-lekures.jpg',
        'set-bime-dhe-vitamina' => 'offer-set-bime-dhe-vitamina.jpg',
        'set-caj-dhe-vitamina' => 'offer-set-caj-dhe-vitamina.jpg',
        'set-perzgjedhje-herbabloom' => 'offer-set-perzgjedhje-herbabloom.jpg',
        'set-kujdes-i-perditeshem' => 'offer-set-kujdes-i-perditeshem.jpg',
        'set-caj-dhe-arome' => 'offer-set-caj-dhe-arome.jpg',
    ];
    if (!isset($offerImages[$slug])) {
        return '';
    }

    $file = $offerImages[$slug];
    if (!is_file(dirname(__DIR__) . '/web-images/' . $file)) {
        return '';
    }

    return app_url('/web-images/' . rawurlencode($file));
}

function web_header(string $title): void
{
    $count = cart_item_count();
    $customer = current_customer($GLOBALS['pdo']);
    $design = hb_design_public($GLOBALS['pdo']);
    $designPreview = isset($_GET['visual_preview'])
        && $_GET['visual_preview'] === '1'
        && ($_SESSION['role'] ?? '') === 'admin';
    $categories = $GLOBALS['pdo']->query(
        "SELECT emri, slug FROM me_kategorite WHERE statusi='Aktiv' ORDER BY emri"
    )->fetchAll();
    $base = app_url('/web');
    ?>
    <!doctype html>
    <html lang="sq">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#344a38">
        <title><?= e($title) ?> | HerbaBloom</title>
        <link rel="icon" type="image/svg+xml" href="<?= e(app_url('/web-images/herbabloom-favicon.svg')) ?>">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Allura&family=Cormorant+Garamond:wght@400;500;600;700&family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&family=Great+Vibes&family=Lora:wght@400;500;600;700&family=Playfair+Display:wght@400;500;600;700&display=swap" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
        <link rel="stylesheet" href="<?= e(app_url('/web/style.css?v=' . filemtime(__DIR__ . '/style.css'))) ?>">
        <style id="herbabloom-design-settings"><?= hb_design_css($design) ?></style>
    </head>
    <?php $bodyClass = basename((string) ($_SERVER['SCRIPT_NAME'] ?? '')) === 'shporta.php' ? ' class="cart-page"' : ''; ?>
    <body<?= $bodyClass ?><?= $designPreview ? ' data-visual-design-preview="authorized"' : '' ?>>
<div class="announcement-bar" aria-label="Njoftim i dyqanit">
    <div class="announcement-track">
        <span>Përzgjedhje bimore dhe kujdes personal <span aria-hidden="true">·</span> Pagesë në dorëzim</span>
        <span aria-hidden="true">Përzgjedhje bimore dhe kujdes personal <span>·</span> Pagesë në dorëzim</span>
        <span aria-hidden="true">Përzgjedhje bimore dhe kujdes personal <span>·</span> Pagesë në dorëzim</span>
    </div>
</div>
    <header class="store-header">
        <nav class="navbar navbar-expand-lg container-fluid store-navbar" aria-label="Navigimi kryesor">
            <a class="navbar-brand store-brand" href="<?= e($base . '/index.php') ?>"><i class="bi bi-flower1 brand-mark" aria-hidden="true"></i><span>HerbaBloom</span></a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#storeNavigation" aria-controls="storeNavigation" aria-expanded="false" aria-label="Hap navigimin">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="storeNavigation">
                <ul class="navbar-nav mx-auto align-items-lg-center">
                    <li class="nav-item"><a class="nav-link" href="<?= e($base . '/index.php') ?>">Ballina</a></li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="<?= e($base . '/produktet.php') ?>" role="button" data-bs-toggle="dropdown" aria-expanded="false">Produkte</a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?= e($base . '/produktet.php') ?>">Shfleto gjithë katalogun</a></li>
                            <?php foreach ($categories as $category): ?>
                                <li><a class="dropdown-item" href="<?= e($base . '/produktet.php?kategoria=' . rawurlencode($category['slug'])) ?>"><?= e($category['emri']) ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($base . '/bestseller.php') ?>">Bestseller</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($base . '/oferta.php') ?>">Oferta</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($base . '/rreth-nesh.php') ?>">Rreth nesh</a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e($base . '/kontakti.php') ?>">Kontakt</a></li>
                </ul>
                <div class="store-actions">
                    <button class="icon-link search-open-button" type="button" data-search-open aria-label="Kërko produkte në dyqan" aria-controls="storeSearch" aria-haspopup="dialog" aria-expanded="false">
                        <i class="bi bi-search" aria-hidden="true"></i>
                    </button>
                    <a class="icon-link" href="<?= e($base . '/llogaria.php') ?>" aria-label="<?= $customer ? 'Llogaria ime' : 'Hyr ose krijo llogari' ?>"><i class="bi bi-person" aria-hidden="true"></i></a>
                    <a class="icon-link cart-icon-link" href="<?= e($base . '/shporta.php') ?>" aria-label="Shporta, <?= e($count) ?> artikuj">
                        <i class="bi bi-bag" aria-hidden="true"></i><span class="cart-count"><?= e($count) ?></span>
                    </a>
                </div>
            </div>
        </nav>
    </header>
    <dialog class="store-search-dialog" id="storeSearch" aria-labelledby="storeSearchTitle">
        <div class="store-search-heading">
            <h2 id="storeSearchTitle">Kërko në HerbaBloom</h2>
            <button type="button" class="icon-link" data-search-close aria-label="Mbyll kërkimin">
                <i class="bi bi-x-lg" aria-hidden="true"></i>
            </button>
        </div>
        <form class="store-search-form" method="get" action="<?= e($base . '/produktet.php') ?>">
            <label for="storeSearchInput">Produkte, kategori ose fjalë kyçe</label>
            <div class="store-search-input-row">
                <input id="storeSearchInput" data-store-search-input type="search" name="q" placeholder="Provo: çaj, vaj esencial, vitaminë…" autocomplete="off">
                <button class="btn btn-forest" type="submit">Kërko</button>
            </div>
        </form>
        <p class="search-status" data-store-search-status aria-live="polite"></p>
        <ul class="store-search-results" data-store-search-results aria-label="Rezultatet e kërkimit"></ul>
        <div class="store-search-links">
            <a href="<?= e($base . '/produktet.php') ?>">Shfleto produktet dhe kategoritë</a>
            <a href="<?= e($base . '/oferta.php') ?>">Sete dhe oferta aktive</a>
            <a href="<?= e($base . '/rreth-nesh.php') ?>">Njihu me HerbaBloom</a>
            <?php foreach ($categories as $category): ?>
                <a href="<?= e($base . '/produktet.php?kategoria=' . rawurlencode($category['slug'])) ?>"><?= e($category['emri']) ?></a>
            <?php endforeach; ?>
        </div>
    </dialog>
    <main class="site-main">
    <?php
}

function web_footer(): void
{
    $base = app_url('/web');
    ?>
    </main>
    <footer class="store-footer">
        <div class="container store-footer-inner">
            <a class="store-brand footer-brand" href="<?= e($base . '/index.php') ?>"><i class="bi bi-flower1 brand-mark" aria-hidden="true"></i><span>HerbaBloom</span></a>
            <p>Produkte bimore dhe kujdes personal, të zgjedhura me vëmendje.</p>
            <div class="footer-links">
                <a href="<?= e($base . '/rreth-nesh.php') ?>">Rreth nesh</a>
                <a href="<?= e($base . '/kontakti.php') ?>">Kontakt</a>
                <a href="<?= e($base . '/llogaria.php') ?>">Llogaria</a>
            </div>
            <small>© <?= e(date('Y')) ?> HerbaBloom</small>
        </div>
    </footer>
    <div class="store-toast" role="status" aria-live="polite"></div>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= e(app_url('/web/store.js?v=' . filemtime(__DIR__ . '/store.js'))) ?>" defer></script>
    </body>
    </html>
    <?php
}

function web_product_card(array $product, string $badge = ''): void
{
    $image = product_image_path($product);
    $detailUrl = app_url('/web/produktet.php?slug=' . rawurlencode((string) $product['slug']));
    ?>
    <article class="product-card card h-100">
        <div class="product-card-image-wrap">
            <a class="product-image" href="<?= e($detailUrl) ?>" aria-label="<?= e($product['emri']) ?>">
                <?php if ($image !== ''): ?>
                    <img src="<?= e($image) ?>" alt="<?= e($product['emri']) ?>" loading="lazy">
                <?php else: ?>
                    <span class="image-placeholder" role="img" aria-label="<?= e('Fotografia e ' . $product['emri'] . ' do të shtohet') ?>"><i class="bi bi-flower1" aria-hidden="true"></i><span>Fotografia do të shtohet</span></span>
                <?php endif; ?>
            </a>
            <?php if ($badge !== ''): ?><span class="product-badge"><?= e($badge) ?></span><?php endif; ?>
            <form method="post" action="<?= e(app_url('/web/llogaria.php')) ?>" class="favorite-form">
                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="favorite">
                <input type="hidden" name="product_id" value="<?= e($product['id']) ?>">
                <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/web/index.php') ?>">
                <button class="favorite-button" type="submit" aria-label="Ruaj <?= e($product['emri']) ?> te të preferuarat">
                    <i class="bi bi-heart" aria-hidden="true"></i>
                </button>
            </form>
        </div>
        <div class="card-body product-card-body">
            <p class="product-kicker"><?= e($product['kategoria'] ?? 'Përzgjedhja HerbaBloom') ?></p>
            <h3 class="product-title"><a href="<?= e($detailUrl) ?>"><?= e($product['emri']) ?></a></h3>
            <div class="product-card-bottom">
                <strong class="product-price"><?= e(number_format((float) $product['cmimi'], 2)) ?> €</strong>
                <?php if ((int) $product['stoku_aktual'] > 0): ?>
                    <form method="post" action="<?= e(app_url('/web/shporta.php')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="produkt_id" value="<?= e($product['id']) ?>">
                        <input type="hidden" name="sasia" value="1">
                        <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/web/produktet.php') ?>">
                        <button class="btn btn-forest btn-sm" type="submit">Shto në shportë <i class="bi bi-plus" aria-hidden="true"></i></button>
                    </form>
                <?php else: ?><span class="stock-out">Përkohësisht pa stok</span><?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

function web_reveal(string $class = ''): string
{
    return ' data-reveal' . ($class !== '' ? '="' . e($class) . '"' : '');
}