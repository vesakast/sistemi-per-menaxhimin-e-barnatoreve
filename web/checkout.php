<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$cart = current_cart();
$bundleCart = current_bundle_cart();
if ($cart === [] && $bundleCart === []) {
    redirect('/web/shporta.php');
}
$error = '';
$products = fetch_cart_products($pdo, $cart);
if (count($products) !== count($cart)) {
    $error = 'Disa produkte nuk janë më të disponueshme. Përditësoni shportën para porosisë.';
}
foreach ($cart as $productId => $quantity) {
    if (isset($products[$productId]) && $products[$productId]['stoku_aktual'] < $quantity) {
        $error = 'Një produkt në shportë nuk ka stok të mjaftueshëm.';
    }
}
$offers = fetch_cart_offers($pdo, $bundleCart);
if (count($offers) !== count($bundleCart)) {
    $error = 'Një set në shportë nuk është më aktiv. Kthehuni te shporta dhe përditësojeni.';
}
$subtotal = 0.0;
foreach ($cart as $productId => $quantity) {
    if (isset($products[$productId])) {
        $subtotal += (float) $products[$productId]['cmimi'] * $quantity;
    }
}
foreach ($bundleCart as $offerId => $quantity) {
    if (isset($offers[$offerId])) {
        $subtotal += (float) $offers[$offerId]['cmimi'] * $quantity;
        foreach ($offers[$offerId]['artikujt'] as $item) {
            if ($item['stoku_aktual'] < $item['sasia'] * $quantity) {
                $error = 'Një produkt brenda setit nuk ka stok të mjaftueshëm.';
            }
        }
    }
}
$cartCustomer = customer_schema_ready($pdo) ? current_customer($pdo) : null;
$settings = $pdo->query('SELECT tarifa_dorezimit, dergesa_falas_mbi FROM me_cilesimet WHERE id=1')->fetch();
if (!$settings) {
    $error = 'Dyqani nuk është konfiguruar për të pranuar porosi.';
}
$shipping = $settings && $settings['dergesa_falas_mbi'] !== null
    && $subtotal >= (float) $settings['dergesa_falas_mbi'] ? 0.0 : (float) ($settings['tarifa_dorezimit'] ?? 0);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $customer = trim((string) ($_POST['klienti'] ?? $cartCustomer['emri'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? $cartCustomer['email'] ?? ''));
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
            $currentOffers = fetch_cart_offers($pdo, $bundleCart);
            if (count($currentOffers) !== count($bundleCart)) {
                throw new DomainException('Një ofertë nuk është më aktive. Përditësoni shportën.');
            }
            foreach ($bundleCart as $offerId => $bundleQuantity) {
                $lockedOfferQuery = $pdo->prepare(
                    'SELECT id,emri,cmimi FROM me_ofertat
                     WHERE id=? AND aktiv=1 AND (fillon_me IS NULL OR fillon_me<=NOW())
                       AND (mbaron_me IS NULL OR mbaron_me>=NOW()) FOR UPDATE'
                );
                $lockedOfferQuery->execute([$offerId]);
                $lockedOffer = $lockedOfferQuery->fetch();
                if (!$lockedOffer) {
                    throw new DomainException('Një ofertë nuk është më aktive.');
                }
                foreach ($cart as $productId => $quantity) {
                    if (!isset($products[$productId]) || $products[$productId]['stoku_aktual'] < $quantity) {
                        $cartIsAvailable = false;
                    }
                }
                $currentOffers[$offerId]['emri'] = $lockedOffer['emri'];
                $currentOffers[$offerId]['cmimi'] = $lockedOffer['cmimi'];
                $subtotal += (float) $lockedOffer['cmimi'] * $bundleQuantity;
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
            if ($cartCustomer) {
                $insertOrder = $pdo->prepare(
                    "INSERT INTO me_porosite_online
                     (kodi_gjurmimit,burimi,klienti,klient_id,email,telefoni,qyteti,adresa,shenime,
                      nentotali,tarifa_dorezimit,totali)
                     VALUES (?,'web',?,?,?,?,?,?,?,?,?,?)"
                );
                $insertOrder->execute([
                    $trackingCode,$customer,$cartCustomer['id'],$email ?: null,$phone,$city,$address,
                    $notes ?: null,$subtotal,$shipping,$subtotal+$shipping,
                ]);
            } else {
                $insertOrder = $pdo->prepare(
                    "INSERT INTO me_porosite_online
                     (kodi_gjurmimit,burimi,klienti,email,telefoni,qyteti,adresa,shenime,
                      nentotali,tarifa_dorezimit,totali)
                     VALUES (?,'web',?,?,?,?,?,?,?,?,?)"
                );
                $insertOrder->execute([
                    $trackingCode,$customer,$email ?: null,$phone,$city,$address,$notes ?: null,
                    $subtotal,$shipping,$subtotal+$shipping,
                ]);
            }
            $orderId = (int) $pdo->lastInsertId();
            $insertItem = $pdo->prepare(
                'INSERT INTO me_porosi_items (porosia_id, produkt_id, emri_produktit, cmimi, sasia) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($currentProducts as $productId => $product) {
                $insertItem->execute([$orderId, $productId, $product['emri'], $product['cmimi'], $product['quantity']]);
                reserve_stock($pdo, (int) $productId, (int) $product['quantity'], $orderId, null, true);
            }
            foreach ($bundleCart as $offerId => $bundleQuantity) {
                $offer = $currentOffers[$offerId];
                $components = $offer['artikujt'];
                $names = [];
                foreach ($components as $item) {
                    $names[] = $item['emri'] . ' × ' . $item['sasia'];
                }
                $bundleName = mb_strimwidth('Set: ' . $offer['emri'] . ' (' . implode(', ', $names) . ')', 0, 150, '…', 'UTF-8');
                $insertItem->execute([$orderId, null, $bundleName, $offer['cmimi'], $bundleQuantity]);
                foreach ($components as $item) {
                    reserve_stock(
                        $pdo,
                        (int) $item['produkt_id'],
                        (int) $item['sasia'] * $bundleQuantity,
                        $orderId,
                        null,
                        true
                    );
                }
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
            $_SESSION['bundle_cart'] = [];
            redirect('/web/gjurmo.php?code=' . rawurlencode($trackingCode) . '&created=1');
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
$cartIsAvailable = count($products) === count($cart) && count($offers) === count($bundleCart);
foreach ($bundleCart as $offerId => $bundleQuantity) {
    if (!isset($offers[$offerId])) {
        $cartIsAvailable = false;
        continue;
    }
    foreach ($offers[$offerId]['artikujt'] as $item) {
        if ($item['stoku_aktual'] < $item['sasia'] * $bundleQuantity) {
            $cartIsAvailable = false;
        }
    }
}

web_header('Përfundo porosinë');
?>
<section class="page-intro"><p class="eyebrow">Edhe një hap</p><h1 class="page-title">Të dhënat e porosisë</h1><p>Plotëso kontaktin dhe adresën e dërgesës. Pagesa bëhet në dorëzim.</p></section>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="content-grid">
    <section class="content-card"><h2>Ku ta dërgojmë porosinë?</h2>
        <form method="post" class="form-stack" data-loading-label="Duke regjistruar porosinë…">
            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="field"><label for="customer">Emri i plotë</label><input id="customer" name="klienti" maxlength="100" value="<?= e($_POST['klienti'] ?? $cartCustomer['emri'] ?? '') ?>" required></div>
            <div class="field"><label for="phone">Telefoni</label><input id="phone" name="telefoni" maxlength="30" value="<?= e($_POST['telefoni'] ?? '') ?>" required></div>
            <div class="field"><label for="email">Email (opsionale)</label><input id="email" name="email" type="email" maxlength="100" value="<?= e($_POST['email'] ?? $cartCustomer['email'] ?? '') ?>"></div>
            <div class="field"><label for="city">Qyteti</label><input id="city" name="qyteti" maxlength="50" value="<?= e($_POST['qyteti'] ?? 'Prizren') ?>" required></div>
            <div class="field"><label for="address">Adresa e plotë</label><input id="address" name="adresa" maxlength="255" value="<?= e($_POST['adresa'] ?? '') ?>" required></div>
            <div class="field"><label for="notes">Udhëzime për dërgesën (opsionale)</label><textarea id="notes" name="shenime" maxlength="1000" placeholder="P.sh. orientim për adresën."><?= e($_POST['shenime'] ?? '') ?></textarea></div>
            <p class="notice">Pagesa bëhet në dorëzim, kur porosia të mbërrijë.</p>
            <button class="btn btn-forest" type="submit" <?= !$cartIsAvailable || !$settings ? 'disabled' : '' ?>>Dërgo porosinë</button>
        </form>
    </section>
    <section class="content-card"><h2>Përmbledhja</h2>
        <?php foreach ($cart as $productId => $quantity): if (!isset($products[$productId])) { continue; } $product = $products[$productId]; ?>
            <p><?= e($product['emri']) ?> × <?= e($quantity) ?><strong style="float:right"><?= e(number_format((float) $product['cmimi'] * $quantity, 2)) ?> €</strong></p>
        <?php endforeach; ?>
        <?php foreach ($bundleCart as $offerId => $quantity): if (!isset($offers[$offerId])) { continue; } $offer = $offers[$offerId]; ?>
            <p><strong><?= e($offer['emri']) ?> × <?= e($quantity) ?></strong><strong style="float:right"><?= e(number_format((float) $offer['cmimi'] * $quantity, 2)) ?> €</strong></p>
        <?php endforeach; ?>
        <hr><p>Nëntotali <strong class="summary-price"><?= e(number_format($subtotal, 2)) ?> €</strong></p>
        <p>Dërgesa <strong class="summary-price"><?= e(number_format($shipping, 2)) ?> €</strong></p>
        <p><strong>Totali</strong><strong class="summary-price"><?= e(number_format($subtotal + $shipping, 2)) ?> €</strong></p>
    </section>
</div>
<?php web_footer(); ?>
