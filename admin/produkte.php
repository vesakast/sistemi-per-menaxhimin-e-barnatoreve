<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

$error = '';
$editProduct = null;
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$categories = $pdo->query("SELECT id, emri FROM me_kategorite WHERE statusi = 'Aktiv' ORDER BY emri")->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    try {
        if ($action === 'save_product') {
            $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
            $sku = trim((string) ($_POST['sku'] ?? ''));
            $name = trim((string) ($_POST['emri'] ?? ''));
            $brand = trim((string) ($_POST['marka'] ?? ''));
            $categoryId = filter_input(INPUT_POST, 'kategoria_id', FILTER_VALIDATE_INT) ?: null;
            $description = trim((string) ($_POST['pershkrimi'] ?? ''));
            $price = filter_var($_POST['cmimi'] ?? null, FILTER_VALIDATE_FLOAT);
            $minimumStock = filter_var($_POST['stoku_minimal'] ?? null, FILTER_VALIDATE_INT);
            $image = trim((string) ($_POST['imazhi'] ?? ''));
            $active = isset($_POST['aktiv']) ? 1 : 0;
            $visible = isset($_POST['i_dukshem_ne_web']) ? 1 : 0;
            $slugText = trim((string) ($_POST['slug'] ?? ''));
            $transliterated = function_exists('iconv') ? iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $slugText) : $slugText;
            $slug = strtolower(trim((string) $transliterated));
            $slug = trim((string) preg_replace('/[^a-z0-9]+/', '-', $slug), '-');

            if ($sku === '' || strlen($sku) > 50 || $name === '' || strlen($name) > 150
                || $slug === '' || strlen($slug) > 190 || $price === false || $price < 0
                || $minimumStock === false || $minimumStock < 0
                || ($categoryId !== null && $categoryId < 1)
                || ($image !== '' && !preg_match('~^https?://~i', $image))) {
                throw new DomainException('Plotësoni të dhënat e produktit me vlera të vlefshme.');
            }
            if ($categoryId !== null) {
                $categoryCheck = $pdo->prepare("SELECT 1 FROM me_kategorite WHERE id = ? AND statusi = 'Aktiv'");
                $categoryCheck->execute([$categoryId]);
                if (!$categoryCheck->fetchColumn()) {
                    throw new DomainException('Kategoria e zgjedhur nuk është aktive.');
                }
            }
            $values = [
                'sku' => $sku, 'slug' => $slug, 'name' => $name, 'brand' => $brand ?: null,
                'category_id' => $categoryId, 'description' => $description ?: null, 'price' => $price,
                'minimum_stock' => $minimumStock, 'image' => $image ?: null,
                'active' => $active, 'visible' => $visible,
            ];
            if ($id > 0) {
                $values['id'] = $id;
                $save = $pdo->prepare(
                    'UPDATE me_produktet SET sku=:sku, slug=:slug, emri=:name, marka=:brand,
                     kategoria_id=:category_id, pershkrimi=:description, cmimi=:price,
                     stoku_minimal=:minimum_stock, imazhi=:image, aktiv=:active,
                     i_dukshem_ne_web=:visible WHERE id=:id'
                );
            } else {
                $save = $pdo->prepare(
                    'INSERT INTO me_produktet
                     (sku, slug, emri, marka, kategoria_id, pershkrimi, cmimi, stoku_minimal, imazhi, aktiv, i_dukshem_ne_web)
                     VALUES (:sku,:slug,:name,:brand,:category_id,:description,:price,:minimum_stock,:image,:active,:visible)'
                );
            }
            $save->execute($values);
            audit_log($pdo, ($id > 0 ? 'Përditësoi produktin ' : 'Krijoi produktin ') . $sku);
            flash_message('Produkti u ruajt me sukses.');
            redirect('/admin/produkte.php');
        }

        if ($action === 'add_batch') {
            $productId = filter_input(INPUT_POST, 'produkt_id', FILTER_VALIDATE_INT);
            $batchNumber = trim((string) ($_POST['nr_batch'] ?? ''));
            $quantity = filter_input(INPUT_POST, 'sasia', FILTER_VALIDATE_INT);
            $expiryInput = trim((string) ($_POST['data_skadence'] ?? ''));
            $expiry = $expiryInput === '' ? null : valid_date_or_null($expiryInput);
            if (!$productId || $batchNumber === '' || strlen($batchNumber) > 50 || !$quantity || $quantity < 1
                || ($expiryInput !== '' && $expiry === null)) {
                throw new DomainException('Të dhënat e batch-it nuk janë të vlefshme.');
            }
            $pdo->beginTransaction();
            $batchFind = $pdo->prepare('SELECT id, data_skadence FROM me_stoku_batch WHERE produkt_id = ? AND nr_batch = ? FOR UPDATE');
            $batchFind->execute([$productId, $batchNumber]);
            $existingBatch = $batchFind->fetch();
            if ($existingBatch) {
                if ($existingBatch['data_skadence'] !== $expiry) {
                    throw new DomainException('Batch-i ekziston me datë tjetër skadence.');
                }
                $batchId = (int) $existingBatch['id'];
                $pdo->prepare('UPDATE me_stoku_batch SET sasia = sasia + ? WHERE id = ?')->execute([$quantity, $batchId]);
            } else {
                $insertBatch = $pdo->prepare(
                    'INSERT INTO me_stoku_batch (produkt_id, nr_batch, sasia, data_skadence) VALUES (?, ?, ?, ?)'
                );
                $insertBatch->execute([$productId, $batchNumber, $quantity, $expiry]);
                $batchId = (int) $pdo->lastInsertId();
            }
            $pdo->prepare(
                "INSERT INTO me_stoku_levizjet (produkt_id, batch_id, sasia, lloji, nga_user_id)
                 VALUES (?, ?, ?, 'shtim', ?)"
            )->execute([$productId, $batchId, $quantity, $current_user_id]);
            audit_log($pdo, "Shtoi {$quantity} njësi në batch-in {$batchNumber}");
            $pdo->commit();
            flash_message('Batch-i i stokut u regjistrua.');
            redirect('/admin/produkte.php');
        }

        if ($action === 'toggle_product') {
            $productId = filter_input(INPUT_POST, 'produkt_id', FILTER_VALIDATE_INT);
            if (!$productId) {
                throw new DomainException('Produkti nuk është i vlefshëm.');
            }
            $pdo->prepare('UPDATE me_produktet SET aktiv = 1 - aktiv WHERE id = ?')->execute([$productId]);
            audit_log($pdo, "Ndryshoi gjendjen e produktit #{$productId}");
            flash_message('Gjendja e produktit u përditësua.');
            redirect('/admin/produkte.php');
        }
    } catch (DomainException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('HerbaBloom product operation failed: ' . $exception->getMessage());
        $error = 'Ruajtja dështoi. Kontrolloni SKU/slug unike dhe provoni përsëri.';
    }
}

if ($editId) {
    $editQuery = $pdo->prepare('SELECT * FROM me_produktet WHERE id = ?');
    $editQuery->execute([$editId]);
    $editProduct = $editQuery->fetch() ?: null;
}
$products = $pdo->query(
    'SELECT id, sku, emri, cmimi, aktiv, i_dukshem_ne_web, stoku_aktual,
            stoku_minimal, statusi_stokut, skadenca_me_e_afert
     FROM me_produktet_stoku ORDER BY emri'
)->fetchAll();
$batches = $pdo->query(
    'SELECT b.nr_batch, b.sasia, b.data_skadence, p.emri
     FROM me_stoku_batch b JOIN me_produktet p ON p.id = b.produkt_id
     ORDER BY b.data_krijimit DESC, b.id DESC LIMIT 10'
)->fetchAll();
$formProduct = $editProduct ?? [];

admin_header('Produktet & stoku');
render_flash();
?>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<div class="grid-two">
    <section class="panel">
        <h2><?= $editProduct ? 'Përditëso produktin' : 'Shto produkt' ?></h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_product">
            <input type="hidden" name="id" value="<?= e($formProduct['id'] ?? '') ?>">
            <div class="field"><label for="sku">SKU</label><input id="sku" name="sku" maxlength="50" value="<?= e($formProduct['sku'] ?? '') ?>" required></div>
            <div class="field"><label for="emri">Emri</label><input id="emri" name="emri" maxlength="150" value="<?= e($formProduct['emri'] ?? '') ?>" required></div>
            <div class="field"><label for="slug">Slug unik</label><input id="slug" name="slug" maxlength="190" value="<?= e($formProduct['slug'] ?? '') ?>" required></div>
            <div class="field"><label for="marka">Marka</label><input id="marka" name="marka" maxlength="100" value="<?= e($formProduct['marka'] ?? '') ?>"></div>
            <div class="field"><label for="category">Kategoria</label><select id="category" name="kategoria_id"><option value="">Pa kategori</option>
                <?php foreach ($categories as $category): ?><option value="<?= e($category['id']) ?>" <?= (int) ($formProduct['kategoria_id'] ?? 0) === (int) $category['id'] ? 'selected' : '' ?>><?= e($category['emri']) ?></option><?php endforeach; ?>
            </select></div>
            <div class="field"><label for="cmimi">Çmimi (€)</label><input id="cmimi" name="cmimi" type="number" min="0" step="0.01" value="<?= e($formProduct['cmimi'] ?? '') ?>" required></div>
            <div class="field"><label for="minimum">Stoku minimal</label><input id="minimum" name="stoku_minimal" type="number" min="0" value="<?= e($formProduct['stoku_minimal'] ?? 5) ?>" required></div>
            <div class="field"><label for="imazhi">URL e imazhit (opsionale)</label><input id="imazhi" name="imazhi" type="url" maxlength="255" value="<?= e($formProduct['imazhi'] ?? '') ?>"></div>
            <div class="field"><label for="pershkrimi">Përshkrimi</label><textarea id="pershkrimi" name="pershkrimi"><?= e($formProduct['pershkrimi'] ?? '') ?></textarea></div>
            <div class="field"><label><input type="checkbox" name="aktiv" value="1" <?= !isset($formProduct['aktiv']) || $formProduct['aktiv'] ? 'checked' : '' ?>> Produkt aktiv</label>
                <label><input type="checkbox" name="i_dukshem_ne_web" value="1" <?= !isset($formProduct['i_dukshem_ne_web']) || $formProduct['i_dukshem_ne_web'] ? 'checked' : '' ?>> Shfaq në web</label></div>
            <div><button class="btn" type="submit">Ruaj produktin</button>
                <?php if ($editProduct): ?><a class="btn secondary" href="<?= e(app_url('/admin/produkte.php')) ?>">Anulo</a><?php endif; ?></div>
        </form>
    </section>
    <section class="panel">
        <h2>Shto ose plotëso batch stoku</h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="action" value="add_batch">
            <div class="field"><label for="batch-product">Produkti</label><select id="batch-product" name="produkt_id" required><option value="">Zgjidh produktin</option>
                <?php foreach ($products as $product): ?><option value="<?= e($product['id']) ?>"><?= e($product['emri']) ?> (<?= e($product['sku']) ?>)</option><?php endforeach; ?>
            </select></div>
            <div class="field"><label for="batch-number">Nr. batch</label><input id="batch-number" name="nr_batch" maxlength="50" required></div>
            <div class="field"><label for="batch-quantity">Sasia për shtim</label><input id="batch-quantity" name="sasia" type="number" min="1" required></div>
            <div class="field"><label for="expiry">Data e skadencës</label><input id="expiry" name="data_skadence" type="date"></div>
            <button class="btn" type="submit">Regjistro stokun</button>
        </form>
        <h3 style="margin-top:24px">Batch-et e fundit</h3>
        <div class="table-wrap"><table><thead><tr><th>Produkt</th><th>Batch</th><th>Sasia</th><th>Skadenca</th></tr></thead><tbody>
        <?php foreach ($batches as $batch): ?><tr><td><?= e($batch['emri']) ?></td><td><?= e($batch['nr_batch']) ?></td><td><?= e($batch['sasia']) ?></td><td><?= e($batch['data_skadence'] ?? '—') ?></td></tr><?php endforeach; ?>
        <?php if ($batches === []): ?><tr><td colspan="4" class="empty">Nuk ka batch-e të regjistruara.</td></tr><?php endif; ?>
        </tbody></table></div>
    </section>
</div>
<section class="panel">
    <h2>Katalogu dhe gjendja e stokut</h2>
    <div class="table-wrap"><table><thead><tr><th>SKU</th><th>Produkti</th><th>Çmimi</th><th>Stoku</th><th>Skadenca më e afërt</th><th>Web</th><th>Veprime</th></tr></thead><tbody>
    <?php foreach ($products as $product): ?>
        <tr><td><?= e($product['sku']) ?></td><td><?= e($product['emri']) ?><br><span class="badge <?= $product['statusi_stokut'] === 'Pa stok' ? 'red' : '' ?>"><?= e($product['statusi_stokut']) ?></span></td>
            <td><?= e(number_format((float) $product['cmimi'], 2)) ?> €</td><td><?= e($product['stoku_aktual']) ?> / <?= e($product['stoku_minimal']) ?> min.</td>
            <td><?= e($product['skadenca_me_e_afert'] ?? '—') ?></td><td><?= $product['i_dukshem_ne_web'] ? 'Po' : 'Jo' ?></td>
            <td><a class="btn secondary small" href="<?= e(app_url('/admin/produkte.php?edit=' . $product['id'])) ?>">Ndrysho</a>
                <form class="inline-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="produkt_id" value="<?= e($product['id']) ?>"><button class="btn small <?= $product['aktiv'] ? 'danger' : 'secondary' ?>" type="submit"><?= $product['aktiv'] ? 'Çaktivizo' : 'Aktivizo' ?></button></form></td></tr>
    <?php endforeach; ?>
    <?php if ($products === []): ?><tr><td colspan="7" class="empty">Katalogu është bosh. Shtoni produktin e parë.</td></tr><?php endif; ?>
    </tbody></table></div>
</section>
<?php admin_footer(); ?>
