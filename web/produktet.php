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
    web_header($product['emri'] ?? 'Produkt');
    if (!$product) {
        http_response_code(404);
        echo '<section class="glass-card empty">Produkti nuk u gjet. <a href="' . e(app_url('/web/produktet.php')) . '">Kthehu te katalogu</a>.</section>';
    } else {
        ?>
        <section class="content-grid">
            <div class="glass-card product-image" style="height:360px;border-radius:22px">
                <?php if ($product['imazhi']): ?><img src="<?= e($product['imazhi']) ?>" alt="<?= e($product['emri']) ?>"><?php else: ?><span aria-hidden="true">✿</span><?php endif; ?>
            </div>
            <article class="glass-card"><p class="eyebrow"><?= e($product['kategoria'] ?? 'Përzgjedhja HerbaBloom') ?></p>
                <h1 class="page-title"><?= e($product['emri']) ?></h1>
                <?php if ($product['marka']): ?><p><?= e($product['marka']) ?></p><?php endif; ?>
                <p><?= nl2br(e($product['pershkrimi'] ?? '')) ?></p>
                <p><strong style="font-size:24px;color:#880e4f"><?= e(number_format((float) $product['cmimi'], 2)) ?> €</strong></p>
                <?php if ((int) $product['stoku_aktual'] > 0): ?>
                    <form method="post" action="<?= e(app_url('/web/shporta.php')) ?>" class="form-grid">
                        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add"><input type="hidden" name="produkt_id" value="<?= e($product['id']) ?>">
                        <div class="field"><label for="quantity">Sasia</label><input id="quantity" name="sasia" type="number" min="1" max="<?= e(min(99, (int) $product['stoku_aktual'])) ?>" value="1" required></div>
                        <button class="btn" type="submit">Shto në shportë</button>
                    </form>
                <?php else: ?><p class="stock-out">Për momentin pa stok.</p><?php endif; ?>
                <p><a href="<?= e(app_url('/web/produktet.php')) ?>">← Kthehu te katalogu</a></p>
            </article>
        </section>
        <?php
    }
    web_footer();
    exit;
}

$search = trim((string) ($_GET['q'] ?? ''));
$categorySlug = trim((string) ($_GET['kategoria'] ?? ''));
$page = max(1, min(1000, (int) ($_GET['page'] ?? 1)));
$perPage = 12;
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
            ps.stoku_aktual
     FROM me_produktet p JOIN me_produktet_stoku ps ON ps.id=p.id
     LEFT JOIN me_kategorite c ON c.id=p.kategoria_id
     WHERE {$whereSql} ORDER BY p.emri LIMIT {$perPage} OFFSET {$offset}"
);
$productQuery->execute($params);
$products = $productQuery->fetchAll();
$categories = $pdo->query("SELECT emri, slug FROM me_kategorite WHERE statusi='Aktiv' ORDER BY emri")->fetchAll();

web_header('Produktet bimore');
?>
<p class="eyebrow">Katalogu HerbaBloom</p><h1 class="page-title">Produkte bimore për mirëqenien tuaj</h1>
<section class="glass-card" style="margin-top:20px">
    <form class="filters" method="get" action="<?= e(app_url('/web/produktet.php')) ?>">
        <div class="field"><label for="search">Kërko produkt</label><input id="search" class="search-input" name="q" value="<?= e($search) ?>" placeholder="Emri, marka ose përshkrimi" autocomplete="off"></div>
        <div class="field"><label for="category">Kategoria</label><select id="category" name="kategoria"><option value="">Të gjitha kategoritë</option>
            <?php foreach ($categories as $category): ?><option value="<?= e($category['slug']) ?>" <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>><?= e($category['emri']) ?></option><?php endforeach; ?>
        </select></div>
        <button class="btn" type="submit">Filtro</button>
    </form>
    <p class="help"><?= e($total) ?> produkt(e) u gjetën.</p>
</section>
<div class="product-grid"><?php foreach ($products as $product) { web_product_card($product); } ?></div>
<?php if ($products === []): ?><section class="glass-card empty">Nuk u gjet asnjë produkt me këto kritere.</section><?php endif; ?>
<?php if ($total > $perPage): ?><nav class="section-heading" aria-label="Faqet">
    <?php if ($page > 1): ?><a class="btn secondary" href="<?= e(app_url('/web/produktet.php?' . http_build_query(['q' => $search, 'kategoria' => $categorySlug, 'page' => $page - 1]))) ?>">← Para</a><?php endif; ?>
    <span class="help">Faqja <?= e($page) ?> / <?= e((int) ceil($total / $perPage)) ?></span>
    <?php if ($page * $perPage < $total): ?><a class="btn secondary" href="<?= e(app_url('/web/produktet.php?' . http_build_query(['q' => $search, 'kategoria' => $categorySlug, 'page' => $page + 1]))) ?>">Më shumë →</a><?php endif; ?>
</nav><?php endif; ?>
<script>
let searchTimer;
document.getElementById('search').addEventListener('input', () => {
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => document.getElementById('search').form.requestSubmit(), 500);
});
</script>
<?php web_footer(); ?>
