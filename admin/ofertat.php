<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';
require_once __DIR__ . '/_functions.php';

$migrationQuery = $pdo->query(
    "SELECT COUNT(DISTINCT TABLE_NAME) FROM information_schema.TABLES
     WHERE TABLE_SCHEMA=DATABASE()
       AND TABLE_NAME IN ('me_ofertat','me_oferta_items')"
);
$imageColumnQuery = $pdo->query(
    "SELECT COUNT(*) FROM information_schema.COLUMNS
     WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='me_ofertat' AND COLUMN_NAME='imazhi'"
);
if ((int) $migrationQuery->fetchColumn() !== 2 || (int) $imageColumnQuery->fetchColumn() !== 1) {
    admin_header('Setet & ofertat');
    echo '<div class="alert error">Nëse migrimi 001 nuk është aplikuar, ekzekutoje fillimisht; pastaj ekzekuto një herë <code>database/migrations/002_offer_images.sql</code>. Nëse 001 është aplikuar më herët, nevojitet vetëm migrimi 002. Migrimet nuk ekzekutohen automatikisht.</div>';
    admin_footer();
    exit;
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if ($action === 'create') {
        $name = trim((string) ($_POST['emri'] ?? ''));
        $slug = strtolower(trim((string) ($_POST['slug'] ?? '')));
        $description = trim((string) ($_POST['pershkrimi'] ?? ''));
        $image = trim((string) ($_POST['imazhi'] ?? ''));
        $price = filter_var($_POST['cmimi'] ?? null, FILTER_VALIDATE_FLOAT);
        $startDate = trim((string) ($_POST['fillon_me'] ?? ''));
        $endDate = trim((string) ($_POST['mbaron_me'] ?? ''));
        $parseDateTime = static function (string $value): ?DateTimeImmutable {
            if ($value === '') {
                return null;
            }
            $date = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i', $value);
            $errors = DateTimeImmutable::getLastErrors();
            if (!$date || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
                || $date->format('Y-m-d\TH:i') !== $value) {
                return null;
            }
            return $date;
        };
        $startAt = $parseDateTime($startDate);
        $endAt = $parseDateTime($endDate);
        $productIds = $_POST['produkt_id'] ?? [];
        $quantities = $_POST['sasia'] ?? [];
        $isActive = isset($_POST['aktiv']) ? 1 : 0;
        if ($name === '' || mb_strlen($name, 'UTF-8') > 150
            || $description === '' || mb_strlen($description, 'UTF-8') > 2000
            || $price === false || $price < 0
            || ($image !== '' && (!filter_var($image, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string) parse_url($image, PHP_URL_SCHEME)), ['http', 'https'], true)))
            || strlen($slug) > 140 || !preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/D', $slug)
            || !is_array($productIds) || !is_array($quantities) || count($productIds) < 1
            || count($productIds) > 20 || count($productIds) !== count($quantities)
            || ($startDate !== '' && $startAt === null)
            || ($endDate !== '' && $endAt === null)
            || ($startAt !== null && $endAt !== null && $endAt < $startAt)) {
            $error = 'Kontrolloni emrin, slug-un, URL-në e imazhit, çmimin, periudhën dhe përbërjen e setit.';
        } else {
            $items = [];
            foreach ($productIds as $index => $productIdValue) {
                $productId = filter_var($productIdValue, FILTER_VALIDATE_INT);
                $quantity = filter_var($quantities[$index] ?? null, FILTER_VALIDATE_INT);
                if (!$productId || $productId < 1 || !$quantity || $quantity < 1 || $quantity > 99
                    || isset($items[$productId])) {
                    $error = 'Çdo produkt i setit duhet të jetë unik dhe të ketë sasi 1–99.';
                    break;
                }
                $items[$productId] = $quantity;
            }
            if ($error === '') {
                try {
                    $pdo->beginTransaction();
                    $ids = array_keys($items);
                    sort($ids, SORT_NUMERIC);
                    $placeholders = implode(',', array_fill(0, count($ids), '?'));
                    $productQuery = $pdo->prepare(
                        "SELECT id,cmimi FROM me_produktet
                         WHERE id IN ({$placeholders}) AND aktiv=1 AND i_dukshem_ne_web=1 FOR UPDATE"
                    );
                    $productQuery->execute($ids);
                    $products = [];
                    foreach ($productQuery->fetchAll() as $product) {
                        $products[(int) $product['id']] = $product;
                    }
                    if (count($products) !== count($items)) {
                        throw new DomainException('Një ose më shumë produkte nuk janë aktive për dyqanin.');
                    }
                    $pdo->prepare(
                        'INSERT INTO me_ofertat (slug,emri,pershkrimi,imazhi,cmimi,aktiv,fillon_me,mbaron_me)
                         VALUES (?,?,?,?,?,?,?,?)'
                    )->execute([
                        $slug,$name,$description,$image ?: null,$price,$isActive,
                        $startAt?->format('Y-m-d H:i:s'),
                        $endAt?->format('Y-m-d H:i:s'),
                    ]);
                    $offerId = (int) $pdo->lastInsertId();
                    $insertItem = $pdo->prepare(
                        'INSERT INTO me_oferta_items (oferta_id,produkt_id,sasia) VALUES (?,?,?)'
                    );
                    foreach ($items as $productId => $quantity) {
                        $insertItem->execute([$offerId,$productId,$quantity]);
                    }
                    audit_log($pdo, "Krijoi setin/ofertën #{$offerId}");
                    $pdo->commit();
                    flash_message('Seti u ruajt.');
                    redirect('/admin/ofertat.php');
                } catch (DomainException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    $error = $exception->getMessage();
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if (($exception->errorInfo[0] ?? null) === '23000') {
                        $error = 'Ky slug përdoret nga një set tjetër.';
                    } else {
                        throw $exception;
                    }
                }
            }
        }
    } elseif ($action === 'save_image') {
        $offerId = filter_var($_POST['offer_id'] ?? null, FILTER_VALIDATE_INT);
        $image = trim((string) ($_POST['imazhi'] ?? ''));
        if (!$offerId || $offerId < 1 || strlen($image) > 255
            || ($image !== '' && (!filter_var($image, FILTER_VALIDATE_URL)
                || !in_array(strtolower((string) parse_url($image, PHP_URL_SCHEME)), ['http', 'https'], true)))) {
            $error = 'Kontrolloni URL-në e imazhit të ofertës.';
        } else {
            $update = $pdo->prepare('UPDATE me_ofertat SET imazhi=? WHERE id=?');
            $update->execute([$image ?: null, $offerId]);
            $exists = $pdo->prepare('SELECT 1 FROM me_ofertat WHERE id=?');
            $exists->execute([$offerId]);
            if ($exists->fetchColumn()) {
                audit_log($pdo, "Përditësoi imazhin e ofertës #{$offerId}");
                flash_message('Imazhi i ofertës u ruajt.');
                redirect('/admin/ofertat.php');
            }
            $error = 'Oferta nuk u gjet.';
        }
    } elseif ($action === 'toggle') {
        $offerId = filter_var($_POST['offer_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$offerId || $offerId < 1) {
            $error = 'Seti i zgjedhur nuk është i vlefshëm.';
        } else {
            $update = $pdo->prepare('UPDATE me_ofertat SET aktiv=1-aktiv WHERE id=?');
            $update->execute([$offerId]);
            if ($update->rowCount() > 0) {
                audit_log($pdo, "Ndryshoi publikimin e ofertës #{$offerId}");
                flash_message('Gjendja e setit u përditësua.');
                redirect('/admin/ofertat.php');
            }
            $error = 'Seti nuk u gjet ose nuk ndryshoi.';
        }
    }
}

$products = $pdo->query(
    "SELECT p.id,p.emri,p.cmimi,ps.stoku_aktual
     FROM me_produktet_stoku ps JOIN me_produktet p ON p.id=ps.id
     WHERE p.aktiv=1 AND p.i_dukshem_ne_web=1 ORDER BY p.emri"
)->fetchAll();
$offers = $pdo->query(
    'SELECT o.id,o.slug,o.emri,o.pershkrimi,o.imazhi,o.cmimi,o.aktiv,o.fillon_me,o.mbaron_me,
            GROUP_CONCAT(CONCAT(p.emri," × ",oi.sasia) ORDER BY p.emri SEPARATOR ", ") AS perberja,
            SUM(p.cmimi*oi.sasia) AS cmimi_i_rregullt
     FROM me_ofertat o
     LEFT JOIN me_oferta_items oi ON oi.oferta_id=o.id
     LEFT JOIN me_produktet p ON p.id=oi.produkt_id
     GROUP BY o.id ORDER BY o.id DESC'
)->fetchAll();

admin_header('Setet & ofertat');
render_flash();
?>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="panel">
    <h2>Krijo set ose ofertë</h2>
    <p>Çmimi i ofertës shënohet vetëm nga administratori. Sistemi e krahason me shumën aktuale të produkteve dhe shfaq çmimin e mëparshëm vetëm kur oferta është realisht më e lirë.</p>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="create">
        <div class="field"><label for="offer-name">Emri</label><input id="offer-name" name="emri" maxlength="150" required></div>
        <div class="field"><label for="offer-slug">Slug</label><input id="offer-slug" name="slug" pattern="[a-z0-9]+(-[a-z0-9]+)*" maxlength="140" required></div>
        <div class="field"><label for="offer-price">Çmimi i setit (€)</label><input id="offer-price" name="cmimi" type="number" min="0" step="0.01" required></div>
        <div class="field"><label for="offer-image">URL e imazhit (opsionale)</label><input id="offer-image" name="imazhi" type="url" maxlength="255"></div>
        <div class="field"><label for="offer-start">Fillon më (opsionale)</label><input id="offer-start" name="fillon_me" type="datetime-local"></div>
        <div class="field"><label for="offer-end">Përfundon më (opsionale)</label><input id="offer-end" name="mbaron_me" type="datetime-local"></div>
        <div class="field" style="grid-column:1/-1"><label for="offer-description">Përshkrimi</label><textarea id="offer-description" name="pershkrimi" maxlength="2000" required></textarea></div>
        <fieldset class="offer-admin-products"><legend>Përbërja (1–20 produkte)</legend>
            <div id="offer-product-list">
                <div class="offer-product-row">
                    <select name="produkt_id[]" required><option value="">Zgjidh produkt real</option>
                        <?php foreach ($products as $product): ?><option value="<?= e($product['id']) ?>"><?= e($product['emri']) ?> · <?= e(number_format((float) $product['cmimi'], 2)) ?> € · stok <?= e($product['stoku_aktual']) ?></option><?php endforeach; ?>
                    </select>
                    <input type="number" name="sasia[]" min="1" max="99" value="1" aria-label="Sasia e produktit në set" required>
                </div>
            </div>
            <button type="button" class="btn secondary small" id="add-offer-product">Shto produkt tjetër</button>
        </fieldset>
        <label class="offer-active"><input type="checkbox" name="aktiv" value="1"> Publikoje pasi të ruhet</label>
        <button class="btn" type="submit">Ruaj setin</button>
    </form>
</section>
<section class="panel">
    <h2>Setet e konfiguruara</h2>
    <?php if ($offers === []): ?><p class="empty">Nuk ka sete të konfiguruara.</p>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th>Seti</th><th>Përbërja</th><th>Çmimi i produkteve</th><th>Çmimi i setit</th><th>Publikimi</th><th>Foto</th><th></th></tr></thead><tbody>
        <?php foreach ($offers as $offer): ?><tr>
            <td><strong><?= e($offer['emri']) ?></strong><br><small><?= e($offer['slug']) ?></small></td>
            <td><?= e($offer['perberja'] ?? 'Pa produkte') ?></td>
            <td><?= e(number_format((float) $offer['cmimi_i_rregullt'], 2)) ?> €</td>
            <td><?= e(number_format((float) $offer['cmimi'], 2)) ?> €</td>
            <td><?= $offer['aktiv'] ? 'Aktiv' : 'Joaktiv' ?></td>
            <td><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="action" value="save_image"><input type="hidden" name="offer_id" value="<?= e($offer['id']) ?>"><input type="url" name="imazhi" maxlength="255" value="<?= e($offer['imazhi'] ?? '') ?>" aria-label="<?= e('URL e imazhit për ' . $offer['emri']) ?>"><button class="btn secondary small" type="submit">Ruaj foton</button></form></td>
            <td><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="offer_id" value="<?= e($offer['id']) ?>"><button class="btn secondary small" type="submit"><?= $offer['aktiv'] ? 'Çaktivizo' : 'Aktivizo' ?></button></form></td>
        </tr><?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<script>
document.getElementById('add-offer-product').addEventListener('click', () => {
    const list = document.getElementById('offer-product-list');
    if (list.children.length >= 20) return;
    const row = list.firstElementChild.cloneNode(true);
    row.querySelector('select').value = '';
    row.querySelector('input').value = '1';
    list.append(row);
});
</script>
<?php admin_footer(); ?>
