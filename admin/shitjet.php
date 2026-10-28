<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/_inventory.php';

$error = '';
$isCourier = $current_role === 'kurier';
$canViewRevenue = $current_role === 'admin';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'create_manual' && !$isCourier) {
            $customer = trim((string) ($_POST['klienti'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['telefoni'] ?? ''));
            $city = trim((string) ($_POST['qyteti'] ?? ''));
            $address = trim((string) ($_POST['adresa'] ?? ''));
            $notes = trim((string) ($_POST['shenime'] ?? ''));
            $productIds = $_POST['produkt_id'] ?? [];
            $quantities = $_POST['sasia'] ?? [];
            if ($customer === '' || strlen($customer) > 100 || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
                || strlen($email) > 100 || $phone === '' || strlen($phone) > 30 || $city === ''
                || strlen($city) > 50 || $address === '' || strlen($address) > 255
                || !is_array($productIds) || !is_array($quantities) || $productIds === []
                || count($productIds) !== count($quantities)) {
                throw new DomainException('Plotësoni të dhënat e klientit dhe zgjidhni së paku një produkt.');
            }

            $lineItems = [];
            foreach ($productIds as $index => $productIdValue) {
                $productId = filter_var($productIdValue, FILTER_VALIDATE_INT);
                $quantity = filter_var($quantities[$index] ?? null, FILTER_VALIDATE_INT);
                if (!$productId || $productId < 1 || !$quantity || $quantity < 1) {
                    throw new DomainException('Kontrolloni produktet dhe sasitë e porosisë.');
                }
                $lineItems[$productId] = ($lineItems[$productId] ?? 0) + $quantity;
            }

            $pdo->beginTransaction();
            $productIdsToLoad = array_keys($lineItems);
            sort($productIdsToLoad, SORT_NUMERIC);
            $placeholders = implode(',', array_fill(0, count($productIdsToLoad), '?'));
            $productQuery = $pdo->prepare(
                "SELECT id, emri, cmimi FROM me_produktet WHERE aktiv = 1 AND id IN ({$placeholders}) ORDER BY id FOR UPDATE"
            );
            $productQuery->execute($productIdsToLoad);
            $availableProducts = [];
            foreach ($productQuery->fetchAll() as $product) {
                $availableProducts[(int) $product['id']] = $product;
            }
            if (count($availableProducts) !== count($lineItems)) {
                throw new DomainException('Një ose më shumë produkte nuk janë aktive.');
            }

            $subtotal = 0.0;
            foreach ($lineItems as $productId => $quantity) {
                $subtotal += (float) $availableProducts[$productId]['cmimi'] * $quantity;
            }
            $settings = $pdo->query('SELECT tarifa_dorezimit, dergesa_falas_mbi FROM me_cilesimet WHERE id = 1')->fetch();
            $shipping = $settings && $settings['dergesa_falas_mbi'] !== null
                && $subtotal >= (float) $settings['dergesa_falas_mbi'] ? 0.0 : (float) ($settings['tarifa_dorezimit'] ?? 0);
            $trackingCode = generate_tracking_code($pdo);
            $insert = $pdo->prepare(
                "INSERT INTO me_porosite_online
                 (kodi_gjurmimit, burimi, klienti, email, telefoni, qyteti, adresa, shenime,
                  nentotali, tarifa_dorezimit, totali)
                 VALUES (?, 'manual', ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insert->execute([$trackingCode, $customer, $email ?: null, $phone, $city, $address, $notes ?: null, $subtotal, $shipping, $subtotal + $shipping]);
            $orderId = (int) $pdo->lastInsertId();
            $insertItem = $pdo->prepare(
                'INSERT INTO me_porosi_items (porosia_id, produkt_id, emri_produktit, cmimi, sasia) VALUES (?, ?, ?, ?, ?)'
            );
            foreach ($lineItems as $productId => $quantity) {
                $product = $availableProducts[$productId];
                $insertItem->execute([$orderId, $productId, $product['emri'], $product['cmimi'], $quantity]);
            }
            $pdo->prepare(
                'INSERT INTO me_porosi_statuset (porosia_id, statusi, nga_user_id, shenim) VALUES (?, ?, ?, ?)'
            )->execute([$orderId, 'Në pritje', $current_user_id, 'Porosi manuale']);
            foreach ($lineItems as $productId => $quantity) {
                reserve_stock($pdo, (int) $productId, (int) $quantity, $orderId, $current_user_id);
            }
            audit_log($pdo, "Regjistroi porosinë manuale {$trackingCode}");
            $pdo->commit();
            flash_message("Porosia u regjistrua. Kodi i gjurmimit: {$trackingCode}");
            redirect('/admin/shitjet.php');
        }

        if ($action === 'assign_courier' && !$isCourier) {
            $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
            $courierId = filter_input(INPUT_POST, 'kurier_id', FILTER_VALIDATE_INT);
            if (!$orderId || !$courierId) {
                throw new DomainException('Zgjidhni porosinë dhe kurierin.');
            }
            $pdo->beginTransaction();
            $orderQuery = $pdo->prepare("SELECT statusi FROM me_porosite_online WHERE id = ? FOR UPDATE");
            $orderQuery->execute([$orderId]);
            $orderStatus = $orderQuery->fetchColumn();
            $courierQuery = $pdo->prepare(
                "SELECT id FROM me_perdoruesit WHERE id = ? AND rooli = 'kurier' AND statusi = 'Aktiv'"
            );
            $courierQuery->execute([$courierId]);
            if (!$orderStatus || in_array($orderStatus, ['Dorëzuar', 'Anuluar'], true) || !$courierQuery->fetchColumn()) {
                throw new DomainException('Porosia ose kurieri nuk mund të caktohet.');
            }
            $pdo->prepare('UPDATE me_porosite_online SET kurier_id = ? WHERE id = ?')->execute([$courierId, $orderId]);
            $pdo->prepare(
                'INSERT INTO me_porosi_statuset (porosia_id, statusi, nga_user_id, shenim) VALUES (?, ?, ?, ?)'
            )->execute([$orderId, $orderStatus, $current_user_id, 'U caktua kurieri']);
            audit_log($pdo, "Caktoi kurierin #{$courierId} në porosinë #{$orderId}");
            $pdo->commit();
            flash_message('Kurieri u caktua.');
            redirect('/admin/shitjet.php');
        }

        if ($action === 'update_status') {
            $orderId = filter_input(INPUT_POST, 'order_id', FILTER_VALIDATE_INT);
            $newStatus = (string) ($_POST['statusi'] ?? '');
            $reason = trim((string) ($_POST['arsyeja'] ?? ''));
            $allowedStatus = ['Në pritje', 'Në përgatitje', 'Në rrugë', 'Dorëzuar', 'Anuluar'];
            if (!$orderId || !in_array($newStatus, $allowedStatus, true) || ($newStatus === 'Anuluar' && $reason === '')) {
                throw new DomainException('Zgjidhni status të vlefshëm dhe shkruani arsyen e anulimit.');
            }

            $pdo->beginTransaction();
            $orderQuery = $pdo->prepare('SELECT statusi, kurier_id FROM me_porosite_online WHERE id = ? FOR UPDATE');
            $orderQuery->execute([$orderId]);
            $order = $orderQuery->fetch();
            if (!$order) {
                throw new DomainException('Porosia nuk u gjet.');
            }
            $currentStatus = $order['statusi'];
            $transitions = [
                'Në pritje' => ['Në përgatitje', 'Anuluar'],
                'Në përgatitje' => ['Në rrugë', 'Anuluar'],
                'Në rrugë' => ['Dorëzuar', 'Anuluar'],
            ];
            if (!in_array($newStatus, $transitions[$currentStatus] ?? [], true)
                || ($newStatus === 'Në rrugë' && $order['kurier_id'] === null)
                || ($isCourier && ((int) $order['kurier_id'] !== $current_user_id
                    || !in_array($currentStatus, ['Në përgatitje', 'Në rrugë'], true)
                    || !in_array($newStatus, ['Në rrugë', 'Dorëzuar', 'Anuluar'], true)))) {
                throw new DomainException('Ky ndryshim statusi nuk lejohet për këtë porosi.');
            }
            if ($newStatus === 'Anuluar') {
                restore_order_stock($pdo, $orderId, $current_user_id);
            }
            $cancelledBy = $newStatus === 'Anuluar' ? ($isCourier ? 'kurieri' : 'stafi') : null;
            $deliveryDate = $newStatus === 'Dorëzuar' ? date('Y-m-d H:i:s') : null;
            $pdo->prepare(
                'UPDATE me_porosite_online SET statusi = ?, data_dorezimit = ?,
                 anuluar_nga = ?, arsyeja_anullimit = ? WHERE id = ?'
            )->execute([$newStatus, $deliveryDate, $cancelledBy, $newStatus === 'Anuluar' ? substr($reason, 0, 255) : null, $orderId]);
            $pdo->prepare(
                'INSERT INTO me_porosi_statuset (porosia_id, statusi, nga_user_id, shenim) VALUES (?, ?, ?, ?)'
            )->execute([$orderId, $newStatus, $current_user_id, $newStatus === 'Anuluar' ? substr($reason, 0, 255) : null]);
            audit_log($pdo, "Ndryshoi porosinë #{$orderId} nga {$currentStatus} në {$newStatus}");
            $pdo->commit();
            flash_message('Statusi i porosisë u përditësua.');
            redirect('/admin/shitjet.php');
        }

        throw new DomainException('Veprimi nuk njihet.');
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('HerbaBloom order operation failed: ' . $exception->getMessage());
        $error = 'Veprimi nuk u ruajt. Rifreskoni faqen dhe provoni përsëri.';
    }
}

$financialColumns = $canViewRevenue ? 'o.totali,' : '';
$orderSql = "SELECT o.id,o.kodi_gjurmimit,o.klienti,o.telefoni,o.data_porosise,
                    o.qyteti,o.adresa,o.statusi,o.pagesa,o.kurier_id,{$financialColumns}
                    u.emri AS emri_kurierit FROM me_porosite_online o
             LEFT JOIN me_perdoruesit u ON u.id = o.kurier_id
             WHERE o.statusi NOT IN ('Dorëzuar','Anuluar')";
if ($isCourier) {
    $orderSql .= ' AND o.kurier_id = :courier_id';
}
$orderSql .= ' ORDER BY o.data_porosise ASC';
$orderQuery = $pdo->prepare($orderSql);
$orderQuery->execute($isCourier ? ['courier_id' => $current_user_id] : []);
$orders = $orderQuery->fetchAll();
$orderItems = [];
if ($orders !== []) {
    $placeholders = implode(',', array_fill(0, count($orders), '?'));
    $itemQuery = $pdo->prepare(
        "SELECT porosia_id, emri_produktit, cmimi, sasia FROM me_porosi_items WHERE porosia_id IN ({$placeholders}) ORDER BY id"
    );
    $itemQuery->execute(array_column($orders, 'id'));
    foreach ($itemQuery->fetchAll() as $item) {
        $orderItems[$item['porosia_id']][] = $item;
    }
}
$products = $pdo->query(
    "SELECT id, sku, emri, cmimi, stoku_aktual FROM me_produktet_stoku
     WHERE aktiv = 1 AND stoku_aktual > 0 ORDER BY emri"
)->fetchAll();
$couriers = $isCourier ? [] : $pdo->query(
    "SELECT id, emri FROM me_perdoruesit WHERE rooli = 'kurier' AND statusi = 'Aktiv' ORDER BY emri"
)->fetchAll();

admin_header($isCourier ? 'Dërgesat e mia' : 'Porositë & dërgesat');
render_flash();
?>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (!$isCourier): ?>
<section class="panel">
    <h2>Regjistro porosi manuale</h2>
    <form method="post" id="manual-order-form">
        <?= csrf_field() ?><input type="hidden" name="action" value="create_manual">
        <div class="form-grid">
            <div class="field"><label for="customer">Klienti</label><input id="customer" name="klienti" maxlength="100" required></div>
            <div class="field"><label for="phone">Telefoni</label><input id="phone" name="telefoni" maxlength="30" required></div>
            <div class="field"><label for="email">Email (opsionale)</label><input id="email" name="email" type="email" maxlength="100"></div>
            <div class="field"><label for="city">Qyteti</label><input id="city" name="qyteti" maxlength="50" value="Prizren" required></div>
            <div class="field"><label for="address">Adresa</label><input id="address" name="adresa" maxlength="255" required></div>
            <div class="field"><label for="notes">Shënime</label><input id="notes" name="shenime" maxlength="1000"></div>
        </div>
        <div class="order-lines">
            <div class="topline"><div><h3>Produktet e porosisë</h3><p class="help">Shtoni produkte dhe caktoni sasinë për secilin.</p></div>
                <button class="btn secondary" type="button" id="add-order-item">+ Shto produkt</button></div>
            <div id="order-items">
                <div class="order-item-row">
                    <div class="field"><label>Produkti</label><select name="produkt_id[]" required><option value="">Zgjidh produktin</option>
                        <?php foreach ($products as $product): ?><option value="<?= e($product['id']) ?>"><?= e($product['emri']) ?> · <?= e(number_format((float) $product['cmimi'], 2)) ?> € (stok <?= e($product['stoku_aktual']) ?>)</option><?php endforeach; ?>
                    </select></div>
                    <div class="field"><label>Sasia</label><input name="sasia[]" type="number" min="1" value="1" required></div>
                    <button class="btn danger small remove-order-item" type="button" aria-label="Largo produktin" disabled>Largo</button>
                </div>
            </div>
        </div>
        <button class="btn" type="submit">Krijo porosinë</button>
    </form>
</section>
<template id="order-item-template">
    <div class="order-item-row">
        <div class="field"><label>Produkti</label><select name="produkt_id[]" required><option value="">Zgjidh produktin</option>
            <?php foreach ($products as $product): ?><option value="<?= e($product['id']) ?>"><?= e($product['emri']) ?> · <?= e(number_format((float) $product['cmimi'], 2)) ?> € (stok <?= e($product['stoku_aktual']) ?>)</option><?php endforeach; ?>
        </select></div>
        <div class="field"><label>Sasia</label><input name="sasia[]" type="number" min="1" value="1" required></div>
        <button class="btn danger small remove-order-item" type="button">Largo</button>
    </div>
</template>
<script>
const orderItems = document.getElementById('order-items');
document.getElementById('add-order-item').addEventListener('click', () => {
    orderItems.append(document.getElementById('order-item-template').content.cloneNode(true));
});
orderItems.addEventListener('click', (event) => {
    if (event.target.closest('.remove-order-item') && orderItems.querySelectorAll('.order-item-row').length > 1) {
        event.target.closest('.order-item-row').remove();
    }
});
</script>
<?php endif; ?>
<section class="panel">
    <h2><?= $isCourier ? 'Porositë e caktuara për ju' : 'Porositë aktive' ?></h2>
    <?php if ($orders === []): ?><p class="empty">Nuk ka porosi aktive në këtë listë.</p>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th>Porosia / Klienti</th><th>Artikujt</th><th>Adresa</th><?php if ($canViewRevenue): ?><th>Totali</th><?php endif; ?><th>Kurieri</th><th>Statusi & veprimet</th></tr></thead><tbody>
    <?php foreach ($orders as $order): ?>
        <tr><td><strong><?= e($order['kodi_gjurmimit']) ?></strong><br><?= e($order['klienti']) ?><br><span class="help"><?= e($order['telefoni']) ?> · <?= e($order['data_porosise']) ?></span></td>
            <td><?php foreach ($orderItems[$order['id']] ?? [] as $item): ?><?= e($item['emri_produktit']) ?> × <?= e($item['sasia']) ?><br><?php endforeach; ?></td>
            <td><?= e($order['qyteti']) ?><br><?= e($order['adresa']) ?></td>
            <?php if ($canViewRevenue): ?><td><?= e(number_format((float) $order['totali'], 2)) ?> €<br><span class="help"><?= e($order['pagesa']) ?></span></td><?php endif; ?>
            <td>
                <?php if (!$isCourier): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="assign_courier"><input type="hidden" name="order_id" value="<?= e($order['id']) ?>">
                    <select name="kurier_id" aria-label="Cakto kurierin" required><option value="">Cakto kurier</option><?php foreach ($couriers as $courier): ?><option value="<?= e($courier['id']) ?>" <?= (int) $order['kurier_id'] === (int) $courier['id'] ? 'selected' : '' ?>><?= e($courier['emri']) ?></option><?php endforeach; ?></select><button class="btn secondary small" type="submit">Ruaj</button></form>
                <?php else: ?><?= e($order['emri_kurierit'] ?? $current_name) ?><?php endif; ?>
            </td>
            <td><span class="badge"><?= e($order['statusi']) ?></span>
                <form method="post" style="margin-top:8px;display:grid;gap:6px"><?= csrf_field() ?><input type="hidden" name="action" value="update_status"><input type="hidden" name="order_id" value="<?= e($order['id']) ?>">
                    <select name="statusi" required>
                        <?php foreach (($order['statusi'] === 'Në pritje' ? ['Në përgatitje','Anuluar'] : ($order['statusi'] === 'Në përgatitje' ? ['Në rrugë','Anuluar'] : ['Dorëzuar','Anuluar'])) as $status): ?>
                            <?php if ($isCourier && $status === 'Dorëzuar' && $order['statusi'] !== 'Në rrugë') { continue; } ?>
                            <option value="<?= e($status) ?>"><?= e($status) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input name="arsyeja" maxlength="255" placeholder="Arsyeja (e detyrueshme për anulim)">
                    <button class="btn small" type="submit">Përditëso</button>
                </form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php admin_footer(); ?>
