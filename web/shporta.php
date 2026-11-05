<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$cart = current_cart();
$bundleCart = current_bundle_cart();
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $productId = filter_input(INPUT_POST, 'produkt_id', FILTER_VALIDATE_INT);
    $offerId = filter_input(INPUT_POST, 'offer_id', FILTER_VALIDATE_INT);

    if ($action === 'add') {
        $quantity = filter_input(INPUT_POST, 'sasia', FILTER_VALIDATE_INT);

        if (
            !$productId
            || $productId < 1
            || $quantity === false
            || $quantity === null
            || $quantity < 1
            || $quantity > 99
        ) {
            $error = 'Produkti ose sasia nuk është e vlefshme.';
        } else {
            $stockQuery = $pdo->prepare(
                'SELECT stoku_aktual FROM me_produktet_stoku
                 WHERE id=? AND aktiv=1 AND i_dukshem_ne_web=1'
            );
            $stockQuery->execute([$productId]);
            $stock = $stockQuery->fetchColumn();
            $newQuantity = ($cart[$productId] ?? 0) + $quantity;

            if ($stock === false || $newQuantity > (int) $stock || $newQuantity > 99) {
                $error = 'Sasia e kërkuar nuk është e disponueshme.';
            } else {
                $cart[$productId] = $newQuantity;
                $_SESSION['cart'] = $cart;
                redirect(cart_return_path());
            }
        }
    } elseif ($action === 'add_bundle' && $offerId) {
        $offers = fetch_cart_offers($pdo, [$offerId => 1]);
        $offer = $offers[$offerId] ?? null;

        if (!$offer) {
            $error = 'Ky set nuk është më aktiv.';
        } else {
            foreach ($offer['artikujt'] as $item) {
                if ($item['stoku_aktual'] < $item['sasia'] * (($bundleCart[$offerId] ?? 0) + 1)) {
                    $error = 'Nuk ka stok të mjaftueshëm për këtë sasi të setit.';
                    break;
                }
            }

            if ($error === '') {
                $bundleCart[$offerId] = min(20, ($bundleCart[$offerId] ?? 0) + 1);
                $_SESSION['bundle_cart'] = $bundleCart;
                redirect(cart_return_path());
            }
        }
    } elseif ($action === 'update') {
        $quantities = $_POST['sasia'] ?? [];
        $bundleQuantities = $_POST['bundle_sasia'] ?? [];

        if (!is_array($quantities) || !is_array($bundleQuantities)) {
            $error = 'Sasitë e shportës nuk janë të vlefshme.';
        } else {
            $products = fetch_cart_products($pdo, $cart);
            $offers = fetch_cart_offers($pdo, $bundleCart);

            foreach ($quantities as $id => $quantityValue) {
                $id = filter_var($id, FILTER_VALIDATE_INT);
                $quantity = filter_var($quantityValue, FILTER_VALIDATE_INT);

                if (
                    !$id
                    || !$quantity
                    || $quantity < 1
                    || $quantity > 99
                    || !isset($products[$id])
                    || $quantity > (int) $products[$id]['stoku_aktual']
                ) {
                    $error = 'Kontrolloni sasitë; një produkt mund të ketë stok më të ulët.';
                    break;
                }

                $cart[$id] = $quantity;
            }

            if ($error === '') {
                foreach ($bundleQuantities as $id => $quantityValue) {
                    $id = filter_var($id, FILTER_VALIDATE_INT);
                    $quantity = filter_var($quantityValue, FILTER_VALIDATE_INT);

                    if (!$id || !$quantity || $quantity < 1 || $quantity > 20 || !isset($offers[$id])) {
                        $error = 'Një set nuk është më aktiv ose ka sasi të pavlefshme.';
                        break;
                    }

                    foreach ($offers[$id]['artikujt'] as $item) {
                        if ($item['stoku_aktual'] < $item['sasia'] * $quantity) {
                            $error = 'Sasia e një produkti në set nuk është më e disponueshme.';
                            break 2;
                        }
                    }

                    $bundleCart[$id] = $quantity;
                }
            }

            if ($error === '') {
                $_SESSION['cart'] = $cart;
                $_SESSION['bundle_cart'] = $bundleCart;
                flash_message('Shporta u përditësua.');
                redirect('/web/shporta.php');
            }
        }
    } elseif ($action === 'remove_bundle' && $offerId) {
        unset($bundleCart[$offerId]);
        $_SESSION['bundle_cart'] = $bundleCart;
        redirect('/web/shporta.php');
    } elseif ($action === 'remove' && $productId) {
        unset($cart[$productId]);
        $_SESSION['cart'] = $cart;
        flash_message('Produkti u largua nga shporta.');
        redirect('/web/shporta.php');
    }
}

$cartProducts = fetch_cart_products($pdo, $cart);
$cartOffers = fetch_cart_offers($pdo, $bundleCart);
$subtotal = 0.0;

foreach ($cart as $productId => $quantity) {
    if (isset($cartProducts[$productId])) {
        $subtotal += (float) $cartProducts[$productId]['cmimi'] * $quantity;
    }
}

$unavailableCartItems = count($cartProducts) !== count($cart)
    || count($cartOffers) !== count($bundleCart);

foreach ($cart as $productId => $quantity) {
    if (
        isset($cartProducts[$productId])
        && (int) $cartProducts[$productId]['stoku_aktual'] < $quantity
    ) {
        $unavailableCartItems = true;
    }
}

foreach ($bundleCart as $offerId => $quantity) {
    if (!isset($cartOffers[$offerId])) {
        continue;
    }

    $offer = $cartOffers[$offerId];
    $subtotal += (float) $offer['cmimi'] * $quantity;

    foreach ($offer['artikujt'] as $item) {
        if ($item['stoku_aktual'] < $item['sasia'] * $quantity) {
            $unavailableCartItems = true;
        }
    }
}

$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

web_header('Shporta');
?>

<section class="page-intro">
    <p class="eyebrow">Kontrollo përzgjedhjen</p>
    <h1 class="page-title">Shporta jote</h1>
    <p>Rishiko artikujt dhe sasitë para se të vazhdosh te të dhënat e dërgesës.</p>
</section>

<?php if ($error !== ''): ?>
    <div class="alert error" role="alert"><?= e($error) ?></div>
<?php endif; ?>

<?php if ($flash): ?>
    <div class="alert" role="status"><?= e($flash['message']) ?></div>
<?php endif; ?>

<?php if ($cart === [] && $bundleCart === []): ?>
    <section class="empty-state">
        <i class="bi bi-bag" aria-hidden="true"></i>
        <p>Shporta jote është bosh. Eksploro katalogun për të gjetur produkte që dëshiron të porositësh.</p>
        <a class="btn btn-forest" href="<?= e(app_url('/web/produktet.php')) ?>">Shfleto katalogun</a>
    </section>
<?php else: ?>
    <section class="content-grid cart-layout">
        <div class="content-card">
            <form method="post">
                <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                <input type="hidden" name="action" value="update">

                <div class="table-wrap">
                    <table>
                        <thead>
                            <tr>
                                <th>Produkti / seti</th>
                                <th>Çmimi</th>
                                <th>Sasia</th>
                                <th>Nëntotali</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($cart as $productId => $quantity): $product = $cartProducts[$productId] ?? null; ?>
                                <tr>
                                    <td><?= e($product['emri'] ?? 'Produkt jo më i disponueshëm') ?></td>
                                    <td><?= $product ? e(number_format((float) $product['cmimi'], 2)) . ' €' : '—' ?></td>
                                    <td>
                                        <?php if ($product): ?>
                                            <input
                                                name="sasia[<?= e($productId) ?>]"
                                                type="number"
                                                min="1"
                                                max="<?= e(min(99, (int) $product['stoku_aktual'])) ?>"
                                                value="<?= e($quantity) ?>"
                                                aria-label="Sasia për <?= e($product['emri']) ?>"
                                                style="width:76px"
                                            >
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $product ? e(number_format((float) $product['cmimi'] * $quantity, 2)) . ' €' : '—' ?></td>
                                    <td>
                                        <button class="btn secondary small" type="submit" form="remove-<?= e($productId) ?>">
                                            Largo
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>

                            <?php foreach ($bundleCart as $offerId => $quantity): $offer = $cartOffers[$offerId] ?? null; ?>
                                <tr>
                                    <td>
                                        <strong><?= e($offer['emri'] ?? 'Set jo më aktiv') ?></strong>
                                        <?php if ($offer): ?>
                                            <small class="cart-bundle-items">
                                                <?= e(implode(', ', array_map(
                                                    static fn(array $item): string => $item['emri'] . ' × ' . $item['sasia'],
                                                    $offer['artikujt']
                                                ))) ?>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?= $offer ? e(number_format((float) $offer['cmimi'], 2)) . ' €' : '—' ?>
                                        <?php if ($offer && (float) $offer['cmimi'] < (float) $offer['cmimi_i_rregullt']): ?>
                                            <small class="cart-old-price">
                                                <del><?= e(number_format((float) $offer['cmimi_i_rregullt'], 2)) ?> €</del>
                                            </small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($offer): ?>
                                            <input
                                                name="bundle_sasia[<?= e($offerId) ?>]"
                                                type="number"
                                                min="1"
                                                max="20"
                                                value="<?= e($quantity) ?>"
                                                aria-label="Sasia për setin <?= e($offer['emri']) ?>"
                                                style="width:76px"
                                            >
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $offer ? e(number_format((float) $offer['cmimi'] * $quantity, 2)) . ' €' : '—' ?></td>
                                    <td>
                                        <button class="btn btn-outline-forest btn-sm" type="submit" form="remove-bundle-<?= e($offerId) ?>">
                                            Largo
                                        </button>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <div class="cart-actions">
                    <button class="btn btn-outline-forest" type="submit">Përditëso sasitë</button>
                </div>
            </form>

            <?php foreach ($cart as $productId => $_): ?>
                <form id="remove-<?= e($productId) ?>" method="post">
                    <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="remove">
                    <input type="hidden" name="produkt_id" value="<?= e($productId) ?>">
                </form>
            <?php endforeach; ?>

            <?php foreach ($bundleCart as $offerId => $_): ?>
                <form id="remove-bundle-<?= e($offerId) ?>" method="post">
                    <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
                    <input type="hidden" name="action" value="remove_bundle">
                    <input type="hidden" name="offer_id" value="<?= e($offerId) ?>">
                </form>
            <?php endforeach; ?>
        </div>

        <aside class="content-card cart-summary">
            <p class="eyebrow">Përmbledhja</p>
            <h2>Totali yt</h2>
            <p>Nëntotali <strong><?= e(number_format($subtotal, 2)) ?> €</strong></p>
            <p class="help">Tarifa e dërgesës llogaritet në hapin e porosisë.</p>

            <?php if ($unavailableCartItems): ?>
                <div class="alert error">
                    Disa artikuj ose sete nuk janë më të disponueshme. Largoji ose përditëso sasitë para porosisë.
                </div>
            <?php endif; ?>

            <?php if ($unavailableCartItems): ?>
                <span class="btn btn-forest checkout-button disabled" aria-disabled="true">Përditëso shportën</span>
            <?php else: ?>
                <a class="btn btn-forest checkout-button" href="<?= e(app_url('/web/checkout.php')) ?>">
                    Vazhdo te porosia <i class="bi bi-arrow-right" aria-hidden="true"></i>
                </a>
            <?php endif; ?>

            <a class="continue-shopping" href="<?= e(app_url('/web/produktet.php')) ?>">Vazhdo blerjet</a>
        </aside>
    </section>
<?php endif; ?>

<section class="content-card cart-tracking">
    <p class="eyebrow">Porosi e bërë më herët?</p>
    <h2>Gjurmimi i porosisë</h2>
    <p>Shkruaj kodin që more pas porosisë për të parë statusin e dërgesës.</p>

    <form method="get" action="<?= e(app_url('/web/gjurmo.php')) ?>" class="form-grid">
        <div class="field">
            <label for="cart-tracking-code">Kodi i gjurmimit</label>
            <input
                id="cart-tracking-code"
                name="kodi_gjurmimit"
                type="text"
                maxlength="20"
                pattern="[A-Za-z0-9]{1,20}"
                placeholder="Shkruaj kodin e porosisë"
                autocomplete="off"
                required
            >
        </div>
        <button class="btn btn-forest" type="submit">Shfaq statusin</button>
    </form>
</section>

<?php web_footer(); ?>