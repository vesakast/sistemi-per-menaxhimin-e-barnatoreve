<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$cart = current_cart();
if ($cart === []) {
    redirect('/web/shporta.php');
}
$error = '';
$products = fetch_cart_products($pdo, $cart);
if (count($products) !== count($cart)) {
    $error = 'Disa produkte nuk janë më të disponueshme. Përditësoni shportën para porosisë.';
}
$subtotal = 0.0;
foreach ($cart as $productId => $quantity) {
    if (isset($products[$productId])) {
        $subtotal += (float) $products[$productId]['cmimi'] * $quantity;
    }
}
$settings = $pdo->query('SELECT tarifa_dorezimit, dergesa_falas_mbi FROM me_cilesimet WHERE id=1')->fetch();
if (!$settings) {
    $error = 'Dyqani nuk është konfiguruar për të pranuar porosi.';
}
$shipping = $settings && $settings['dergesa_falas_mbi'] !== null
    && $subtotal >= (float) $settings['dergesa_falas_mbi'] ? 0.0 : (float) ($settings['tarifa_dorezimit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $customer = trim((string) ($_POST['klienti'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['telefoni'] ?? ''));
    $city = trim((string) ($_POST['qyteti'] ?? ''));
    $address = trim((string) ($_POST['adresa'] ?? ''));
    $notes = trim((string) ($_POST['shenime'] ?? ''));
    if ($error === '' && ($customer === '' || strlen($customer) > 100
        || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) || strlen($email) > 100
        || $phone === '' || strlen($phone) > 30 || $city === '' || strlen($city) > 50
        || $address === '' || strlen($address) > 255 || strlen($notes) > 1000)) {
        $error = 'Plotësoni të dhënat e dërgesës saktë.';
    }

    if ($error === '') {
        try {
            $pdo->beginTransaction();
            $currentProducts = [];
            $subtotal = 0.0;
            foreach ($cart as $productId => $quantity) {
                $productQuery = $pdo->prepare(
                    'SELECT id, emri, cmimi FROM me_produktet
                     WHERE id=? AND aktiv=1 AND i_dukshem_ne_web=1 FOR UPDATE'
                );
                $productQuery->execute([$productId]);
                $product = $productQuery->fetch();
                if (!$product) {
                    throw new DomainException('Një produkt nuk është më i disponueshëm.');
                }
                $product['quantity'] = $quantity;
                $currentProducts[$productId] = $product;
                $subtotal += (float) $product['cmimi'] * $quantity;
            }
            $settingsQuery = $pdo->query('SELECT tarifa_dorezimit, dergesa_falas_mbi FROM me_cilesimet WHERE id=1');
            $currentSettings = $settingsQuery->fetch();
            if (!$currentSettings) {
                throw new DomainException('Cilësimet e dërgesës nuk janë konfiguruar.');
            }
            $shipping = $currentSettings['dergesa_falas_mbi'] !== null
                && $subtotal >= (float) $currentSettings['dergesa_falas_mbi']
                ? 0.0 : (float) $currentSettings['tarifa_dorezimit'];
            $trackingCode = generate_tracking_code($pdo);
            $insertOrder = $pdo->prepare(
                "INSERT INTO me_porosite_online
                 (kodi_gjurmimit, burimi, klienti, email, telefoni, qyteti, adresa, shenime,
                  nentotali, tarifa_dorezimit, totali)
                 VALUES (?, 'web', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insertOrder->execute([
                $trackingCode, $customer, $email ?: null, $phone, $city, $address, $notes ?: null,
                $subtotal, $shipping, $subtotal + $shipping,
            ]);
            $orderId = (int) $pdo->lastInsertId();
            $insertItem = $pdo->prepare(
                'INSERT INTO me_porosi_items (porosia_id, produkt_id, emri_produktit, cmimi, sasia) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($currentProducts as $productId => $product) {
                $insertItem->execute([$orderId, $productId, $product['emri'], $product['cmimi'], $product['quantity']]);
                reserve_stock($pdo, (int) $productId, (int) $product['quantity'], $orderId, null, true);
            }
            $pdo->prepare(
                'INSERT INTO me_porosi_statuset (porosia_id, statusi, shenim) VALUES (?, ?, ?)'
            )->execute([$orderId, 'Në pritje', 'Porosi e re nga dyqani online']);
            $pdo->prepare(
                "INSERT INTO me_njoftimet (lloji, titulli, mesazhi, rooli_target, ref_tipi, ref_id)
                 VALUES ('porosi', ?, ?, 'te_gjithe', 'porosi', ?)"
            )->execute(['Porosi e re online', "U regjistrua porosia {$trackingCode}.", $orderId]);
            $pdo->commit();
            $_SESSION['cart'] = [];
            redirect('/web/gjurmo.php?code=' . rawurlencode($trackingCode));
        } catch (DomainException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $error = $exception->getMessage();
        } catch (PDOException $exception) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            error_log('HerbaBloom checkout failed: ' . $exception->getMessage());
            $error = 'Porosia nuk u regjistrua. Kontrolloni shportën dhe provoni përsëri.';
        }
    }
}

web_header('Përfundo porosinë');
?>
<p class="eyebrow">Dërgesë e sigurt</p><h1 class="page-title">Përfundo porosinë</h1>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="content-grid">
    <section class="glass-card"><h2>Të dhënat e klientit</h2>
        <form method="post" class="form-stack">
            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="field"><label for="customer">Emri i plotë</label><input id="customer" name="klienti" maxlength="100" required></div>
            <div class="field"><label for="phone">Telefoni</label><input id="phone" name="telefoni" maxlength="30" required></div>
            <div class="field"><label for="email">Email (opsionale)</label><input id="email" name="email" type="email" maxlength="100"></div>
            <div class="field"><label for="city">Qyteti</label><input id="city" name="qyteti" maxlength="50" value="Prizren" required></div>
            <div class="field"><label for="address">Adresa e dërgesës</label><input id="address" name="adresa" maxlength="255" required></div>
            <div class="field"><label for="notes">Shënime (opsionale)</label><textarea id="notes" name="shenime" maxlength="1000"></textarea></div>
            <p class="notice">Pagesa pranohet me <strong>Cash në dorëzim</strong>.</p>
            <button class="btn" type="submit" <?= $error !== '' && count($products) !== count($cart) ? 'disabled' : '' ?>>Konfirmo porosinë</button>
        </form>
    </section>
    <section class="glass-card"><h2>Përmbledhja</h2>
        <?php foreach ($cart as $productId => $quantity): if (!isset($products[$productId])) { continue; } $product = $products[$productId]; ?>
            <p><?= e($product['emri']) ?> × <?= e($quantity) ?><strong style="float:right"><?= e(number_format((float) $product['cmimi'] * $quantity, 2)) ?> €</strong></p>
        <?php endforeach; ?>
        <hr><p>Nëntotali <strong style="float:right"><?= e(number_format($subtotal, 2)) ?> €</strong></p>
        <p>Dërgesa <strong style="float:right"><?= e(number_format($shipping, 2)) ?> €</strong></p>
        <p><strong>Totali</strong><strong style="float:right"><?= e(number_format($subtotal + $shipping, 2)) ?> €</strong></p>
    </section>
</div>
<?php web_footer(); ?>
