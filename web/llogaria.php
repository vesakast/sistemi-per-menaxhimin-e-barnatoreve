<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$error = '';
$notice = '';
$ready = customer_schema_ready($pdo);
$customer = $ready ? current_customer($pdo) : null;
$mode = (string) ($_GET['pas'] ?? 'hyrje');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    if (!$ready) {
        $error = 'Për llogaritë e klientëve duhet të zbatohet migrimi SQL 001 nga dosja database/migrations.';
    } elseif ($action === 'favorite') {
        $customer = current_customer($pdo);
        $productId = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);
        if (!$customer) {
            $returnTo = (string) ($_POST['return_to'] ?? '/web/index.php');
            $mode = 'hyrje';
            $error = 'Hyr në llogarinë tënde për t’i ruajtur produktet e preferuara.';
            $_SESSION['_favorite_return_to'] = $returnTo;
        } elseif (!$productId || $productId < 1) {
            $error = 'Produkti i zgjedhur nuk është i vlefshëm.';
        } else {
            $validProduct = $pdo->prepare(
                'SELECT id FROM me_produktet WHERE id=? AND aktiv=1 AND i_dukshem_ne_web=1'
            );
            $validProduct->execute([$productId]);
            if (!$validProduct->fetchColumn()) {
                $error = 'Ky produkt nuk është më i disponueshëm.';
            } else {
                $favorite = $pdo->prepare(
                    'SELECT 1 FROM me_klient_favorites WHERE klient_id=? AND produkt_id=?'
                );
                $favorite->execute([$customer['id'], $productId]);
                if ($favorite->fetchColumn()) {
                    $pdo->prepare('DELETE FROM me_klient_favorites WHERE klient_id=? AND produkt_id=?')
                        ->execute([$customer['id'], $productId]);
                    $_SESSION['_store_notice'] = 'Produkti u largua nga të preferuarat.';
                } else {
                    $pdo->prepare('INSERT INTO me_klient_favorites (klient_id,produkt_id) VALUES (?,?)')
                        ->execute([$customer['id'], $productId]);
                    $_SESSION['_store_notice'] = 'Produkti u ruajt te të preferuarat.';
                }
                $returnTo = (string) ($_POST['return_to'] ?? '/web/index.php');
                $parsed = parse_url($returnTo);
                $returnPath = (string) ($parsed['path'] ?? '/web/index.php');
                $webPrefix = app_url('/web/');
                if (!str_starts_with($returnPath, $webPrefix) || str_contains($returnPath, '..')) {
                    $returnPath = app_url('/web/index.php');
                }
                $query = (string) ($parsed['query'] ?? '');
                $returnUrl = $returnPath . ($query !== '' ? '?' . $query . '&' : '?') . 'njoftim=favorite';
                header('Location: ' . $returnUrl, true, 303);
                exit;
            }
        }
    } elseif ($action === 'logout') {
        unset($_SESSION['customer_id'], $_SESSION['customer_last_activity']);
        session_regenerate_id(true);
        $_SESSION['_store_notice'] = 'Dole nga llogaria.';
        redirect('/web/llogaria.php');
    } elseif ($action === 'save_preferences') {
        if (!$customer) {
            $error = 'Hyr për të përditësuar preferencat.';
        } else {
            $newProducts = isset($_POST['njoftime_produkte_te_reja']) ? 1 : 0;
            $discounts = isset($_POST['njoftime_zbritje']) ? 1 : 0;
            $offers = isset($_POST['njoftime_oferta']) ? 1 : 0;
            $consented = $newProducts === 1 || $discounts === 1 || $offers === 1;
            $pdo->prepare(
                'INSERT INTO me_klient_preferencat
                 (klient_id,njoftime_produkte_te_reja,njoftime_zbritje,njoftime_oferta,pelqim_dhen_me)
                 VALUES (?,?,?,?,?) ON DUPLICATE KEY UPDATE
                 njoftime_produkte_te_reja=VALUES(njoftime_produkte_te_reja),
                 njoftime_zbritje=VALUES(njoftime_zbritje),
                 njoftime_oferta=VALUES(njoftime_oferta),
                 pelqim_dhen_me=VALUES(pelqim_dhen_me)'
            )->execute([$customer['id'], $newProducts, $discounts, $offers, $consented ? date('Y-m-d H:i:s') : null]);
            $_SESSION['_store_notice'] = 'Preferencat e njoftimeve u ruajtën.';
            redirect('/web/llogaria.php?pas=preferencat');
        }
    } elseif ($action === 'register' || $action === 'login') {
        $email = strtolower(trim((string) ($_POST['email'] ?? '')));
        $password = (string) ($_POST['password'] ?? '');
        if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100 || $password === '') {
            $error = 'Shkruaj email të vlefshëm dhe fjalëkalimin.';
        } elseif ($action === 'register') {
            $name = trim((string) ($_POST['name'] ?? ''));
            $confirmation = (string) ($_POST['password_confirmation'] ?? '');
            if ($name === '' || mb_strlen($name, 'UTF-8') > 100) {
                $error = 'Emri është i detyrueshëm dhe nuk mund të kalojë 100 karaktere.';
            } elseif (strlen($password) < 12 || strlen($password) > 72) {
                $error = 'Fjalëkalimi duhet të jetë 12–72 byte.';
            } elseif (!hash_equals($password, $confirmation)) {
                $error = 'Fjalëkalimet nuk përputhen.';
            } else {
                try {
                    $pdo->beginTransaction();
                    $pdo->prepare(
                        "INSERT INTO me_klientet (emri,email,fjalekalimi_hash) VALUES (?,?,?)"
                    )->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
                    $customerId = (int) $pdo->lastInsertId();
                    $pdo->prepare('INSERT INTO me_klient_preferencat (klient_id) VALUES (?)')
                        ->execute([$customerId]);
                    $newProducts = isset($_POST['njoftime_produkte_te_reja']) ? 1 : 0;
                    $discounts = isset($_POST['njoftime_zbritje']) ? 1 : 0;
                    $offers = isset($_POST['njoftime_oferta']) ? 1 : 0;
                    if ($newProducts || $discounts || $offers) {
                        $pdo->prepare(
                            'UPDATE me_klient_preferencat
                             SET njoftime_produkte_te_reja=?,njoftime_zbritje=?,njoftime_oferta=?,pelqim_dhen_me=NOW()
                             WHERE klient_id=?'
                        )->execute([$newProducts, $discounts, $offers, $customerId]);
                    }
                    $pdo->commit();
                    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['name'], $_SESSION['role'],
                        $_SESSION['last_activity'], $_SESSION['pending_auth_user_id'], $_SESSION['pending_attempt_id']);
                    session_regenerate_id(true);
                    $_SESSION['customer_id'] = $customerId;
                    $_SESSION['customer_last_activity'] = time();
                    redirect('/web/llogaria.php?pas=porosite');
                } catch (PDOException $exception) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    if (($exception->errorInfo[0] ?? null) === '23000') {
                        $error = 'Ky email ka tashmë një llogari. Provo të hysh.';
                    } else {
                        throw $exception;
                    }
                }
            }
            $mode = 'regjistrohu';
        } else {
            $ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);
            $attemptQuery = $pdo->prepare(
                'SELECT COUNT(*) FROM me_klient_login_tentativa WHERE sukses=0
                 AND koha>=DATE_SUB(NOW(),INTERVAL 15 MINUTE) AND (email=? OR ip=?)'
            );
            $attemptQuery->execute([$email, $ip]);
            if ((int) $attemptQuery->fetchColumn() >= 5) {
                $error = 'Shumë përpjekje hyrjeje. Provo përsëri pas 15 minutash.';
            } else {
                $query = $pdo->prepare(
                    "SELECT id,emri,email,fjalekalimi_hash FROM me_klientet
                     WHERE email=? AND statusi='Aktiv' LIMIT 1"
                );
                $query->execute([$email]);
                $found = $query->fetch();
                if (!$found || strlen($password) > 72 || !password_verify($password, $found['fjalekalimi_hash'])) {
                    $pdo->prepare(
                        'INSERT INTO me_klient_login_tentativa (email,ip,sukses) VALUES (?,?,0)'
                    )->execute([$email, $ip]);
                    $error = 'Email-i ose fjalëkalimi nuk është i saktë.';
                } else {
                    $pdo->prepare(
                        'INSERT INTO me_klient_login_tentativa (email,ip,sukses) VALUES (?,?,1)'
                    )->execute([$email, $ip]);
                    unset($_SESSION['user_id'], $_SESSION['username'], $_SESSION['name'], $_SESSION['role'],
                        $_SESSION['last_activity'], $_SESSION['pending_auth_user_id'], $_SESSION['pending_attempt_id']);
                    session_regenerate_id(true);
                    $_SESSION['customer_id'] = (int) $found['id'];
                    $_SESSION['customer_last_activity'] = time();
                    $returnTo = (string) ($_SESSION['_favorite_return_to'] ?? '');
                    unset($_SESSION['_favorite_return_to']);
                    if ($returnTo !== '') {
                        $parsed = parse_url($returnTo);
                        $path = (string) ($parsed['path'] ?? '/web/index.php');
                        $webPrefix = app_url('/web/');
                        if (!str_starts_with($path, $webPrefix) || str_contains($path, '..')) {
                            $path = app_url('/web/index.php');
                        }
                        $queryString = (string) ($parsed['query'] ?? '');
                        $returnUrl = $path . ($queryString !== '' ? '?' . $queryString . '&' : '?') . 'njoftim=favorite';
                        header('Location: ' . $returnUrl, true, 303);
                        exit;
                    }
                    redirect('/web/llogaria.php?pas=porosite');
                }
            }
            $mode = 'hyrje';
        }
    }
}

$customer = $ready ? current_customer($pdo) : null;
$notice = (string) ($_SESSION['_store_notice'] ?? '');
unset($_SESSION['_store_notice']);
$favoriteProducts = [];
$orders = [];
$preferences = [
    'njoftime_produkte_te_reja' => 0,
    'njoftime_zbritje' => 0,
    'njoftime_oferta' => 0,
];
if ($customer) {
    $favoriteQuery = $pdo->prepare(
        "SELECT p.id,p.slug,p.emri,p.pershkrimi,p.cmimi,p.imazhi,c.emri AS kategoria,ps.stoku_aktual
         FROM me_klient_favorites f
         JOIN me_produktet_stoku ps ON ps.id=f.produkt_id
         JOIN me_produktet p ON p.id=ps.id AND p.aktiv=1 AND p.i_dukshem_ne_web=1
         LEFT JOIN me_kategorite c ON c.id=p.kategoria_id
         WHERE f.klient_id=? ORDER BY f.data_krijimit DESC"
    );
    $favoriteQuery->execute([$customer['id']]);
    $favoriteProducts = $favoriteQuery->fetchAll();
    $ordersQuery = $pdo->prepare(
        'SELECT id,kodi_gjurmimit,statusi,data_porosise,totali
         FROM me_porosite_online WHERE klient_id=? ORDER BY data_porosise DESC LIMIT 50'
    );
    $ordersQuery->execute([$customer['id']]);
    $orders = $ordersQuery->fetchAll();
    $preferencesQuery = $pdo->prepare('SELECT * FROM me_klient_preferencat WHERE klient_id=?');
    $preferencesQuery->execute([$customer['id']]);
    $preferences = $preferencesQuery->fetch() ?: $preferences;
}

web_header('Llogaria');
?>
<section class="page-intro account-intro" data-reveal>
    <p class="eyebrow">Hapësira jote personale</p>
    <h1 class="page-title">Llogaria HerbaBloom</h1>
    <p>Hyr ose krijo një llogari për të parë porositë, për të ruajtur produktet e preferuara dhe për të menaxhuar njoftimet.</p>
</section><?php if ($notice !== ''): ?><div class="alert" role="status"><?= e($notice) ?></div><?php endif; ?>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if (!$ready): ?>
    <div class="notice" role="status">Llogaritë e klientëve kërkojnë migrimin e veçantë <code>database/migrations/001_customers_and_offers.sql</code>. Ai nuk është ekzekutuar automatikisht.</div>
<?php elseif (!$customer): ?>
    <section class="auth-panel content-card" data-reveal>
        <p class="auth-intro">Përdor email-in për t’u identifikuar, ose krijo llogari për t’i mbajtur porositë dhe preferencat në një vend.</p>
        <div class="auth-tabs" role="tablist">
            <a class="<?= $mode !== 'regjistrohu' ? 'is-active' : '' ?>" href="<?= e(app_url('/web/llogaria.php?pas=hyrje')) ?>">Hyr</a>
            <a class="<?= $mode === 'regjistrohu' ? 'is-active' : '' ?>" href="<?= e(app_url('/web/llogaria.php?pas=regjistrohu')) ?>">Krijo llogari</a>
        </div>
        <form method="post" class="form-stack" data-loading-label="<?= $mode === 'regjistrohu' ? 'Duke krijuar llogarinë…' : 'Duke verifikuar hyrjen…' ?>">
            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
            <input type="hidden" name="action" value="<?= $mode === 'regjistrohu' ? 'register' : 'login' ?>">
            <?php if ($mode === 'regjistrohu'): ?>
                <div class="field"><label for="customer-name">Emri</label><input id="customer-name" name="name" maxlength="100" autocomplete="name" required></div>
            <?php endif; ?>
            <div class="field"><label for="customer-email">Email</label><input id="customer-email" name="email" type="email" maxlength="100" autocomplete="email" required></div>
            <div class="field"><label for="customer-password">Fjalëkalimi</label><input id="customer-password" name="password" type="password" minlength="<?= $mode === 'regjistrohu' ? '12' : '1' ?>" maxlength="72" autocomplete="<?= $mode === 'regjistrohu' ? 'new-password' : 'current-password' ?>" required>
                <?php if ($mode === 'regjistrohu'): ?><small class="help">Përdor të paktën 12 karaktere.</small><?php endif; ?>
            </div>
            <?php if ($mode === 'regjistrohu'): ?>
                <div class="field"><label for="customer-password-confirm">Përsërit fjalëkalimin</label><input id="customer-password-confirm" name="password_confirmation" type="password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
                <fieldset class="consent-fields"><legend>Njoftime me zgjedhje</legend>
                    <label><input type="checkbox" name="njoftime_produkte_te_reja" value="1"> Produkte të reja</label>
                    <label><input type="checkbox" name="njoftime_zbritje" value="1"> Zbritje</label>
                    <label><input type="checkbox" name="njoftime_oferta" value="1"> Oferta dhe sete</label>
                    <small class="help">Nuk regjistrohesh për asnjë njoftim nëse nuk i zgjedh vetë kutitë.</small>
                </fieldset>
            <?php endif; ?>
            <button class="btn btn-forest" type="submit"><?= $mode === 'regjistrohu' ? 'Krijo llogari' : 'Hyr në llogari' ?></button>
        </form>
    </section>
<?php else: ?>
    <section class="account-welcome content-card" data-reveal>
        <div><p class="eyebrow">Profili</p><h2><?= e($customer['emri']) ?></h2><p><?= e($customer['email']) ?></p></div>
        <form method="post"><input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="logout"><button type="submit" class="btn btn-outline-forest">Dil nga llogaria</button></form>
    </section>
    <section class="store-section" data-reveal>
        <div class="section-heading"><div><p class="eyebrow">Blerjet e tua</p><h2>Porositë e mia</h2></div></div>
        <?php if ($orders === []): ?><div class="empty-state"><p>Nuk ka ende porosi të lidhura me llogarinë tënde.</p><a class="text-link" href="<?= e(app_url('/web/produktet.php')) ?>">Eksploro katalogun</a></div>
        <?php else: ?><div class="table-wrap account-orders"><table><thead><tr><th>Porosia</th><th>Data</th><th>Statusi</th><th>Totali</th><th></th></tr></thead><tbody>
            <?php foreach ($orders as $order): ?><tr><td><?= e($order['kodi_gjurmimit']) ?></td><td><?= e($order['data_porosise']) ?></td><td><?= e($order['statusi']) ?></td><td><?= e(number_format((float) $order['totali'], 2)) ?> €</td><td><a class="text-link" href="<?= e(app_url('/web/gjurmo.php?code=' . rawurlencode($order['kodi_gjurmimit']))) ?>">Gjurmo</a></td></tr><?php endforeach; ?>
        </tbody></table></div><?php endif; ?>
    </section>
    <section class="store-section" data-reveal>
        <div class="section-heading"><div><p class="eyebrow">Të ruajtura</p><h2>Të preferuarat</h2></div></div>
        <?php if ($favoriteProducts !== []): ?><div class="product-grid"><?php foreach ($favoriteProducts as $product) { web_product_card($product); } ?></div>
        <?php else: ?><div class="empty-state"><p>Nuk ke ruajtur ende produkte të preferuara.</p></div><?php endif; ?>
    </section>
    <section class="content-card preferences-card" data-reveal>
        <p class="eyebrow">Zgjedhjet e tua</p><h2>Preferencat e njoftimeve</h2>
        <form method="post" class="form-stack">
            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>"><input type="hidden" name="action" value="save_preferences">
            <label><input type="checkbox" name="njoftime_produkte_te_reja" value="1" <?= (int) $preferences['njoftime_produkte_te_reja'] === 1 ? 'checked' : '' ?>> Produkte të reja</label>
            <label><input type="checkbox" name="njoftime_zbritje" value="1" <?= (int) $preferences['njoftime_zbritje'] === 1 ? 'checked' : '' ?>> Zbritje</label>
            <label><input type="checkbox" name="njoftime_oferta" value="1" <?= (int) $preferences['njoftime_oferta'] === 1 ? 'checked' : '' ?>> Oferta dhe sete</label>
            <small class="help">Preferencat ruhen vetëm për llogarinë tënde. Çaktivizo kutitë për të hequr pëlqimin përkatës.</small>
            <button class="btn btn-forest" type="submit">Ruaj preferencat</button>
        </form>
    </section>
<?php endif; ?>
<?php web_footer(); ?>
