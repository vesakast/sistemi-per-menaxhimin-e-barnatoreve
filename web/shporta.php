<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$cart = current_cart();
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $productId = filter_input(INPUT_POST, 'produkt_id', FILTER_VALIDATE_INT);
    if ($action === 'add' && $productId) {
        $quantity = filter_input(INPUT_POST, 'sasia', FILTER_VALIDATE_INT) ?: 1;
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
            flash_message('Produkti u shtua në shportë.');
            redirect('/web/shporta.php');
        }
    } elseif ($action === 'update') {
        $quantities = $_POST['sasia'] ?? [];
        if (!is_array($quantities)) {
            $error = 'Sasitë e shportës nuk janë të vlefshme.';
        } else {
            $products = fetch_cart_products($pdo, $cart);
            foreach ($quantities as $id => $quantityValue) {
                $id = filter_var($id, FILTER_VALIDATE_INT);
                $quantity = filter_var($quantityValue, FILTER_VALIDATE_INT);
                if (!$id || !$quantity || $quantity < 1 || $quantity > 99
                    || !isset($products[$id]) || $quantity > (int) $products[$id]['stoku_aktual']) {
                    $error = 'Kontrolloni sasitë; një produkt mund të ketë stok më të ulët.';
                    break;
                }
                $cart[$id] = $quantity;
            }
            if ($error === '') {
                $_SESSION['cart'] = $cart;
                flash_message('Shporta u përditësua.');
                redirect('/web/shporta.php');
            }
        }
    } elseif ($action === 'remove' && $productId) {
        unset($cart[$productId]);
        $_SESSION['cart'] = $cart;
        flash_message('Produkti u largua nga shporta.');
        redirect('/web/shporta.php');
    }
}

$cartProducts = fetch_cart_products($pdo, $cart);
$subtotal = 0.0;
foreach ($cart as $productId => $quantity) {
    if (isset($cartProducts[$productId])) {
        $subtotal += (float) $cartProducts[$productId]['cmimi'] * $quantity;
    }
}
$flash = $_SESSION['_flash'] ?? null;
unset($_SESSION['_flash']);

web_header('Shporta');
?>
<p class="eyebrow">Përzgjedhja juaj</p><h1 class="page-title">Shporta e blerjeve</h1>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($flash): ?><div class="alert" role="status"><?= e($flash['message']) ?></div><?php endif; ?>
<?php if ($cart === []): ?><section class="glass-card empty">Shporta juaj është bosh. <a href="<?= e(app_url('/web/produktet.php')) ?>">Shfletoni katalogun</a>.</section>
<?php else: ?>
<section class="glass-card"><form method="post">
    <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="update">
    <div class="table-wrap"><table><thead><tr><th>Produkti</th><th>Çmimi</th><th>Sasia</th><th>Nëntotali</th><th></th></tr></thead><tbody>
    <?php foreach ($cart as $productId => $quantity): $product = $cartProducts[$productId] ?? null; ?>
        <tr><td><?= e($product['emri'] ?? 'Produkt jo më i disponueshëm') ?></td>
            <td><?= $product ? e(number_format((float) $product['cmimi'], 2)) . ' €' : '—' ?></td>
            <td><?php if ($product): ?><input name="sasia[<?= e($productId) ?>]" type="number" min="1" max="<?= e(min(99, (int) $product['stoku_aktual'])) ?>" value="<?= e($quantity) ?>" aria-label="Sasia për <?= e($product['emri']) ?>" style="width:76px"><?php else: ?>—<?php endif; ?></td>
            <td><?= $product ? e(number_format((float) $product['cmimi'] * $quantity, 2)) . ' €' : '—' ?></td>
            <td><button class="btn secondary small" type="submit" form="remove-<?= e($productId) ?>">Largo</button></td></tr>
    <?php endforeach; ?>
    </tbody></table></div>
    <div class="section-heading"><span><strong>Nëntotali: <?= e(number_format($subtotal, 2)) ?> €</strong></span><div><button class="btn secondary" type="submit">Përditëso sasinë</button> <a class="btn" href="<?= e(app_url('/web/checkout.php')) ?>">Vazhdo te porosia</a></div></div>
</form>
<?php foreach ($cart as $productId => $_): ?><form id="remove-<?= e($productId) ?>" method="post"><input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="produkt_id" value="<?= e($productId) ?>"></form><?php endforeach; ?>
</section>
<?php endif; ?>
<?php web_footer(); ?>
