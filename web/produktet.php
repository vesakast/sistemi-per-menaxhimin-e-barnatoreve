<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$requestedSlug = trim((string) ($_GET['slug'] ?? ''));
if ($requestedSlug !== '') {
    $detailQuery = $pdo->prepare(
        "SELECT p.id, p.slug, p.emri, p.marka, p.pershkrimi, p.cmimi, p.imazhi,
                c.emri AS kategoria, ps.stoku_aktual
         FROM me_produktet p JOIN me_produktet_stoku ps ON ps.id=p.id
         LEFT JOIN me_kategorite c ON c.id=p.kategoria_id
         WHERE p.slug=? AND p.aktiv=1 AND p.i_dukshem_ne_web=1
           AND (c.id IS NULL OR c.statusi='Aktiv') LIMIT 1"
    );
    $detailQuery->execute([$requestedSlug]);
    $product = $detailQuery->fetch();
    web_header($product ? (string) $product['emri'] : 'Produkt jo i gjetur');
    if (!$product) {
        http_response_code(404);
        echo '<section class="glass-card empty">Produkti nuk u gjet. <a href="' . e(app_url('/web/produktet.php')) . '">Kthehu te katalogu</a>.</section>';
    } else {
        ?>
        <section class="content-grid">
            <?php $image = product_image_path($product); ?>
            <div class="detail-product-image">
                <?php if ($image !== ''): ?><img src="<?= e($image) ?>" alt="<?= e($product['emri']) ?>"><?php else: ?>
                    <span class="image-placeholder" role="img" aria-label="<?= e('Fotografia e ' . $product['emri'] . ' do të shtohet') ?>"><i class="bi bi-flower1" aria-hidden="true"></i><span>Fotografia do të shtohet</span></span>
                <?php endif; ?>
            </div>
            <article class="content-card"><p class="eyebrow"><?= e($product['kategoria'] ?? 'Përzgjedhja HerbaBloom') ?></p>
                <h1 class="page-title"><?= e($product['emri']) ?></h1>
                <?php if ($product['marka']): ?><p><?= e($product['marka']) ?></p><?php endif; ?>
                <p class="product-use-note">Përbërjen, udhëzimet dhe paralajmërimet e sakta i gjen në etiketën e produktit. Për pyetje shëndetësore ose mbi përshtatshmërinë, këshillohu me profesionistin shëndetësor.</p>
                <p><strong class="detail-price"><?= e(number_format((float) $product['cmimi'], 2)) ?> €</strong></p>
                <?php if ((int) $product['stoku_aktual'] > 0): ?>
                    <form method="post" action="<?= e(app_url('/web/shporta.php')) ?>" class="form-grid">
                        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="produkt_id" value="<?= e($product['id']) ?>">
                        <input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '/web/produktet.php') ?>">
                        <div class="field"><label for="quantity">Sasia</label><input id="quantity" name="sasia" type="number" min="1" max="<?= e(min(99, (int) $product['stoku_aktual'])) ?>" value="1" required></div>
                        <button class="btn btn-forest" type="submit">Shto në shportë</button>
                    </form>
                <?php else: ?><p class="stock-out">Për momentin pa stok.</p><?php endif; ?>
                <form method="post" action="<?= e(app_url('/web/llogaria.php')) ?>" class="detail-favorite">
                    <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="favorite">
                    <input type="hidden" name="product_id" value="<?= e($product['id']) ?>"><input type="hidden" name="return_to" value="<?= e($_SERVER['REQUEST_URI'] ?? '') ?>">
                    <button class="btn btn-outline-forest" type="submit"><i class="bi bi-heart" aria-hidden="true"></i> Ruaje te të preferuarat</button>
                </form>
                <p><a href="<?= e(app_url('/web/produktet.php')) ?>">← Kthehu te të gjitha produktet</a></p>
            </article>
        </section>
        <?php
    }
    web_footer();
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$categorySlug = trim((string) ($_GET['kategoria'] ?? ''));
$sort = (string) ($_GET['sort'] ?? 'newest');
$page = max(1, min(1000, (int) ($_GET['page'] ?? 1)));
$perPage = 12;
$orderBy = [
    'newest' => 'p.data_krijimit DESC,p.id DESC',
    'price_asc' => 'p.cmimi ASC,p.id DESC',
    'price_desc' => 'p.cmimi DESC,p.id DESC',
    'name' => 'p.emri ASC,p.id DESC',
][$sort] ?? 'p.data_krijimit DESC,p.id DESC';
$where = ['p.aktiv=1', 'p.i_dukshem_ne_web=1', "(c.id IS NULL OR c.statusi='Aktiv')"];
$params = [];
if ($search !== '') {
    $where[] = '(p.emri LIKE :search_name OR p.marka LIKE :search_brand OR p.pershkrimi LIKE :search_description)';
    $pattern = '%' . $search . '%';
    $params += ['search_name' => $pattern, 'search_brand' => $pattern, 'search_description' => $pattern];
}
if ($categorySlug !== '') {
    $where[] = 'c.slug = :category_slug';
    $params['category_slug'] = $categorySlug;
}
$whereSql = implode(' AND ', $where);
$countQuery = $pdo->prepare(
    "SELECT COUNT(*) FROM me_produktet p LEFT JOIN me_kategorite c ON c.id=p.kategoria_id WHERE {$whereSql}"
);
$countQuery->execute($params);
$total = (int) $countQuery->fetchColumn();
$offset = ($page - 1) * $perPage;
$productQuery = $pdo->prepare(
    "SELECT p.id, p.slug, p.emri, p.pershkrimi, p.cmimi, p.imazhi, c.emri AS kategoria,
            ps.stoku_aktual,p.data_krijimit
     FROM me_produktet p JOIN me_produktet_stoku ps ON ps.id=p.id
     LEFT JOIN me_kategorite c ON c.id=p.kategoria_id
     WHERE {$whereSql} ORDER BY {$orderBy} LIMIT {$perPage} OFFSET {$offset}"
);
$productQuery->execute($params);
$products = $productQuery->fetchAll();
$categories = $pdo->query("SELECT emri, slug FROM me_kategorite WHERE statusi='Aktiv' ORDER BY emri")->fetchAll();

web_header('Produktet');
?>
<section class="page-intro" id="katalogu" data-reveal><p class="eyebrow">Katalogu HerbaBloom</p><h1 class="page-title">Gjej produktet për rutinën tënde</h1><p>Eksploro çajra, suplemente, vajra esencialë dhe kujdes për lëkurën e flokët. Kontrollo përshkrimin, çmimin dhe disponueshmërinë për secilin artikull.</p></section>
<section class="content-card catalog-filters" data-reveal>
    <form class="filters" method="get" action="<?= e(app_url('/web/produktet.php')) ?>#product-results" id="catalog-search" data-loading-label="Duke hapur rezultatet…">
        <div class="field"><label for="search">Kërko në katalog</label><div class="search-autocomplete">
            <input id="search" class="search-input" name="q" value="<?= e($search) ?>" placeholder="Shkruaj emrin, markën ose fjalë nga përshkrimi" autocomplete="off" role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="search-suggestions">
            <ul id="search-suggestions" class="search-suggestions" role="listbox" hidden></ul>
            <p id="search-status" class="search-status help" aria-live="polite"></p>
        </div></div>
        <div class="field"><label for="category">Kategoria</label><select id="category" name="kategoria"><option value="">Të gjitha kategoritë</option>
            <?php foreach ($categories as $category): ?><option value="<?= e($category['slug']) ?>" <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>><?= e($category['emri']) ?></option><?php endforeach; ?>
        </select></div>
        <div class="field"><label for="sort">Rendit sipas</label><select id="sort" name="sort">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Më të rejat</option>
            <option value="name" <?= $sort === 'name' ? 'selected' : '' ?>>Emri</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Çmimi: nga i ulëti</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Çmimi: nga i larti</option>
        </select></div>
        <button class="btn btn-forest" type="submit">Apliko</button>
    </form>
    <p class="help">U gjetën <?= e($total) ?> produkte që përputhen me kërkimin. Disponueshmëria mund të ndryshojë.</p>
</section>
<?php if ($products !== []): ?><div id="product-results" class="product-grid catalog-product-grid"><?php foreach ($products as $product) { web_product_card($product, (strtotime((string) $product['data_krijimit']) >= strtotime('-30 days')) ? 'E re' : ''); } ?></div>
<?php else: ?><div id="product-results" class="empty-state"><i class="bi bi-search" aria-hidden="true"></i><p>Nuk gjetëm produkte me këto kritere. Provo një fjalë tjetër ose pastro filtrat.</p><a class="text-link" href="<?= e(app_url('/web/produktet.php')) ?>">Shfaq të gjitha produktet</a></div><?php endif; ?>
<?php if ($total > $perPage): ?><nav class="section-heading" aria-label="Faqet">
    <?php if ($page > 1): ?><a class="btn btn-outline-forest" href="<?= e(app_url('/web/produktet.php?' . http_build_query(['q' => $search, 'kategoria' => $categorySlug, 'sort' => $sort, 'page' => $page - 1]))) ?>">← Para</a><?php endif; ?>
    <span class="help">Faqja <?= e($page) ?> / <?= e((int) ceil($total / $perPage)) ?></span>
    <?php if ($page * $perPage < $total): ?><a class="btn btn-outline-forest" href="<?= e(app_url('/web/produktet.php?' . http_build_query(['q' => $search, 'kategoria' => $categorySlug, 'sort' => $sort, 'page' => $page + 1]))) ?>">Më shumë →</a><?php endif; ?>
</nav><?php endif; ?>
<?php web_footer(); ?>