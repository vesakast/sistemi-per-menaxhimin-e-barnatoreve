<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/db.php';
require_once dirname(__DIR__) . '/admin/_functions.php';
require_once dirname(__DIR__) . '/admin/_inventory.php';

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

function web_header(string $title): void
{
    $count = array_sum(current_cart());
    echo '<!doctype html><html lang="sq"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<title>' . e($title) . ' · HerbaBloom</title>';
    echo '<link rel="preconnect" href="https://fonts.googleapis.com">';
    echo '<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">';
    echo '<link rel="stylesheet" href="' . e(app_url('/web/style.css')) . '"></head><body>';
    echo '<header class="site-header"><a class="site-brand" href="' . e(app_url('/web/index.php')) . '"><span>✿</span><strong>HerbaBloom</strong></a>';
    echo '<nav aria-label="Navigimi"><a href="' . e(app_url('/web/produktet.php')) . '">Produktet</a><a href="' . e(app_url('/web/gjurmo.php')) . '">Gjurmimi</a><a href="' . e(app_url('/web/kontakti.php')) . '">Kontakti</a><a class="cart-link" href="' . e(app_url('/web/shporta.php')) . '">Shporta <b>' . e($count) . '</b></a></nav></header><main class="site-main">';
}

function web_footer(): void
{
    echo '</main><footer class="site-footer"><span>HerbaBloom</span><span>Mirëqenie bimore, me kujdes.</span></footer></body></html>';
}

function web_product_card(array $product): void
{
    ?>
    <article class="product-card">
        <a class="product-image" href="<?= e(app_url('/web/produktet.php?slug=' . rawurlencode((string) $product['slug']))) ?>">
            <?php if (!empty($product['imazhi'])): ?><img src="<?= e($product['imazhi']) ?>" alt="<?= e($product['emri']) ?>" loading="lazy">
            <?php else: ?><span aria-hidden="true">✿</span><?php endif; ?>
        </a>
        <div class="product-card-body">
            <p class="product-kicker"><?= e($product['kategoria'] ?? 'Herbal selection') ?></p>
            <h3><a href="<?= e(app_url('/web/produktet.php?slug=' . rawurlencode((string) $product['slug']))) ?>"><?= e($product['emri']) ?></a></h3>
            <p class="product-description"><?= e($product['pershkrimi'] ?? 'Produkt bimor i përzgjedhur me kujdes.') ?></p>
            <div class="product-card-bottom"><strong><?= e(number_format((float) $product['cmimi'], 2)) ?> €</strong>
                <?php if ((int) $product['stoku_aktual'] > 0): ?>
                    <form method="post" action="<?= e(app_url('/web/shporta.php')) ?>">
                        <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="add">
                        <input type="hidden" name="produkt_id" value="<?= e($product['id']) ?>"><input type="hidden" name="sasia" value="1">
                        <button class="btn small" type="submit">Shto</button>
                    </form>
                <?php else: ?><span class="stock-out">Pa stok</span><?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}
