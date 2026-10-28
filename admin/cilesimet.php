<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

$error = '';
$mustChangePassword = (int) $authenticatedUser['duhet_ndryshim_fjalekalimi'] === 1;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $action = (string) ($_POST['action'] ?? '');
        if ($mustChangePassword && $action !== 'change_password') {
            throw new DomainException('Ndryshoni fjalëkalimin përpara se të përdorni cilësimet.');
        }

        if ($action === 'change_password') {
            $currentPassword = (string) ($_POST['current_password'] ?? '');
            $newPassword = (string) ($_POST['new_password'] ?? '');
            $confirmPassword = (string) ($_POST['confirm_password'] ?? '');
            $passwordQuery = $pdo->prepare('SELECT fjalekalimi FROM me_perdoruesit WHERE id=?');
            $passwordQuery->execute([$current_user_id]);
            $passwordHash = $passwordQuery->fetchColumn();
            if (!is_string($passwordHash) || !password_verify($currentPassword, $passwordHash)
                || strlen($newPassword) < 12 || strlen($newPassword) > 72
                || !hash_equals($newPassword, $confirmPassword)
                || password_verify($newPassword, $passwordHash)) {
                throw new DomainException('Fjalëkalimi aktual është i pasaktë ose fjalëkalimi i ri nuk i plotëson kushtet.');
            }
            $pdo->prepare(
                'UPDATE me_perdoruesit SET fjalekalimi=?, duhet_ndryshim_fjalekalimi=0 WHERE id=?'
            )->execute([password_hash($newPassword, PASSWORD_DEFAULT), $current_user_id]);
            audit_log($pdo, 'Ndryshoi fjalëkalimin');
            flash_message('Fjalëkalimi u ndryshua me sukses.');
            redirect('/admin/cilesimet.php');
        }

        if ($action === 'save_profile') {
            $name = trim((string) ($_POST['emri'] ?? ''));
            $email = trim((string) ($_POST['email'] ?? ''));
            $phone = trim((string) ($_POST['telefoni'] ?? ''));
            $city = trim((string) ($_POST['qyteti'] ?? ''));
            if ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
                || strlen($email) > 100 || strlen($phone) > 30 || strlen($city) > 50) {
                throw new DomainException('Të dhënat e profilit nuk janë të vlefshme.');
            }
            $pdo->prepare('UPDATE me_perdoruesit SET emri=?, email=?, telefoni=?, qyteti=? WHERE id=?')
                ->execute([$name, $email, $phone ?: null, $city ?: null, $current_user_id]);
            $_SESSION['name'] = $name;
            audit_log($pdo, 'Përditësoi profilin personal');
            flash_message('Profili personal u përditësua.');
            redirect('/admin/cilesimet.php');
        }

        if ($action !== 'save_settings' || $current_role !== 'admin') {
            http_response_code(403);
            throw new DomainException('Vetëm administratori mund të ndryshojë cilësimet globale.');
        }

        $storeName = trim((string) ($_POST['emri_dyqanit'] ?? ''));
        $owner = trim((string) ($_POST['pronari'] ?? ''));
        $contact = trim((string) ($_POST['personi_kontaktit'] ?? ''));
        $email = trim((string) ($_POST['email'] ?? ''));
        $otpEmail = trim((string) ($_POST['otp_email'] ?? ''));
        $phone = trim((string) ($_POST['telefoni'] ?? ''));
        $address = trim((string) ($_POST['adresa'] ?? ''));
        $shippingFee = filter_var($_POST['tarifa_dorezimit'] ?? null, FILTER_VALIDATE_FLOAT);
        $freeShipping = trim((string) ($_POST['dergesa_falas_mbi'] ?? ''));
        $stockThreshold = filter_var($_POST['pragu_stokut'] ?? null, FILTER_VALIDATE_INT);
        $warning1 = filter_var($_POST['paralajmerimi_1'] ?? null, FILTER_VALIDATE_INT);
        $warning2 = filter_var($_POST['paralajmerimi_2'] ?? null, FILTER_VALIDATE_INT);
        $freeShippingValue = $freeShipping === '' ? null : filter_var($freeShipping, FILTER_VALIDATE_FLOAT);
        if ($storeName === '' || strlen($storeName) > 100 || $owner === '' || strlen($owner) > 100
            || ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
            || !filter_var($otpEmail, FILTER_VALIDATE_EMAIL) || $shippingFee === false || $shippingFee < 0
            || ($freeShipping !== '' && ($freeShippingValue === false || $freeShippingValue < 0))
            || $stockThreshold === false || $stockThreshold < 0 || $warning1 === false || $warning1 < 0
            || $warning2 === false || $warning2 < 0 || strlen($phone) > 30) {
            throw new DomainException('Kontrolloni fushat e cilësimeve dhe vlerat numerike.');
        }
        $update = $pdo->prepare(
            'UPDATE me_cilesimet SET emri_dyqanit=?, pronari=?, personi_kontaktit=?, email=?,
             telefoni=?, adresa=?, otp_email=?, tarifa_dorezimit=?, dergesa_falas_mbi=?,
             pragu_stokut=?, paralajmerimi_1=?, paralajmerimi_2=? WHERE id=1'
        );
        $update->execute([
            $storeName, $owner, $contact ?: null, $email ?: null, $phone ?: null, $address ?: null,
            $otpEmail, $shippingFee, $freeShippingValue, $stockThreshold, $warning1, $warning2,
        ]);
        if ($update->rowCount() === 0 && !$pdo->query('SELECT 1 FROM me_cilesimet WHERE id=1')->fetchColumn()) {
            throw new DomainException('Rreshti bazë id=1 mungon në me_cilesimet. Ekzekutoni seed-in e skemës.');
        }
        audit_log($pdo, 'Përditësoi cilësimet globale të dyqanit');
        flash_message('Cilësimet e dyqanit u përditësuan.');
        redirect('/admin/cilesimet.php');
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('HerbaBloom settings update failed: ' . $exception->getMessage());
        $error = 'Cilësimet nuk u ruajtën. Kontrolloni të dhënat dhe provoni përsëri.';
    }
}

$settings = $pdo->query('SELECT * FROM me_cilesimet WHERE id = 1')->fetch();
$profileQuery = $pdo->prepare('SELECT emri, email, telefoni, qyteti FROM me_perdoruesit WHERE id = ?');
$profileQuery->execute([$current_user_id]);
$profile = $profileQuery->fetch();
$logs = $current_role === 'admin'
    ? $pdo->query('SELECT perdoruesi, veprimi, koha FROM me_audit_log ORDER BY id DESC LIMIT 20')->fetchAll()
    : [];

admin_header('Cilësimet & profili');
render_flash();
?>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<?php if ($mustChangePassword): ?>
    <div class="alert">Ndryshoni fjalëkalimin përpara se të përdorni funksionet e tjera të panelit.</div>
<?php else: ?>
    <section class="panel">
        <h2>Të dhënat personale</h2>
        <form method="post" class="form-grid">
            <?= csrf_field() ?><input type="hidden" name="action" value="save_profile">
            <div class="field"><label for="name">Emri</label><input id="name" name="emri" value="<?= e($profile['emri'] ?? '') ?>" maxlength="100" required></div>
            <div class="field"><label for="profile-email">Email</label><input id="profile-email" name="email" type="email" value="<?= e($profile['email'] ?? '') ?>" maxlength="100" required></div>
            <div class="field"><label for="profile-phone">Telefoni</label><input id="profile-phone" name="telefoni" value="<?= e($profile['telefoni'] ?? '') ?>" maxlength="30"></div>
            <div class="field"><label for="profile-city">Qyteti</label><input id="profile-city" name="qyteti" value="<?= e($profile['qyteti'] ?? '') ?>" maxlength="50"></div>
            <button class="btn" type="submit">Ruaj profilin</button>
        </form>
    </section>

    <?php if (!$settings): ?>
        <div class="alert error">Mungon rreshti id=1 te me_cilesimet. Ekzekutoni seed-in e skemës.</div>
    <?php else: ?>
        <section class="panel">
            <h2>Konfigurimi global i dyqanit</h2>
            <?php if ($current_role === 'admin'): ?>
                <form method="post" class="form-grid">
                    <?= csrf_field() ?><input type="hidden" name="action" value="save_settings">
                    <div class="field"><label for="store">Emri i dyqanit</label><input id="store" name="emri_dyqanit" value="<?= e($settings['emri_dyqanit']) ?>" maxlength="100" required></div>
                    <div class="field"><label for="owner">Pronari</label><input id="owner" name="pronari" value="<?= e($settings['pronari']) ?>" maxlength="100" required></div>
                    <div class="field"><label for="contact">Personi i kontaktit</label><input id="contact" name="personi_kontaktit" value="<?= e($settings['personi_kontaktit'] ?? '') ?>" maxlength="100"></div>
                    <div class="field"><label for="store-email">Email publik</label><input id="store-email" name="email" type="email" value="<?= e($settings['email'] ?? '') ?>" maxlength="100"></div>
                    <div class="field"><label for="otp">Email për OTP</label><input id="otp" name="otp_email" type="email" value="<?= e($settings['otp_email']) ?>" maxlength="100" required></div>
                    <div class="field"><label for="store-phone">Telefoni</label><input id="store-phone" name="telefoni" value="<?= e($settings['telefoni'] ?? '') ?>" maxlength="30"></div>
                    <div class="field"><label for="address">Adresa</label><input id="address" name="adresa" value="<?= e($settings['adresa'] ?? '') ?>"></div>
                    <div class="field"><label for="fee">Tarifa e dërgesës (€)</label><input id="fee" name="tarifa_dorezimit" type="number" min="0" step="0.01" value="<?= e($settings['tarifa_dorezimit']) ?>" required></div>
                    <div class="field"><label for="free">Dërgesë falas mbi (€); bosh = çaktivizuar</label><input id="free" name="dergesa_falas_mbi" type="number" min="0" step="0.01" value="<?= e($settings['dergesa_falas_mbi'] ?? '') ?>"></div>
                    <div class="field"><label for="threshold">Pragu i stokut</label><input id="threshold" name="pragu_stokut" type="number" min="0" value="<?= e($settings['pragu_stokut']) ?>" required></div>
                    <div class="field"><label for="warning1">Paralajmërimi i skadencës 1 (ditë)</label><input id="warning1" name="paralajmerimi_1" type="number" min="0" value="<?= e($settings['paralajmerimi_1']) ?>" required></div>
                    <div class="field"><label for="warning2">Paralajmërimi i skadencës 2 (ditë)</label><input id="warning2" name="paralajmerimi_2" type="number" min="0" value="<?= e($settings['paralajmerimi_2']) ?>" required></div>
                    <button class="btn" type="submit">Ruaj cilësimet</button>
                </form>
            <?php else: ?>
                <p class="help">Cilësimet globale janë vetëm për shikim; ndryshimet bëhen nga administratori.</p>
                <div class="table-wrap"><table><tbody>
                    <tr><th>Emri i dyqanit</th><td><?= e($settings['emri_dyqanit']) ?></td><th>Pronari</th><td><?= e($settings['pronari']) ?></td></tr>
                    <tr><th>Personi i kontaktit</th><td><?= e($settings['personi_kontaktit'] ?? '—') ?></td><th>Email publik</th><td><?= e($settings['email'] ?? '—') ?></td></tr>
                    <tr><th>Email OTP</th><td><?= e($settings['otp_email']) ?></td><th>Telefoni</th><td><?= e($settings['telefoni'] ?? '—') ?></td></tr>
                    <tr><th>Adresa</th><td><?= e($settings['adresa'] ?? '—') ?></td><th>Tarifa e dërgesës</th><td><?= e(number_format((float) $settings['tarifa_dorezimit'], 2)) ?> €</td></tr>
                    <tr><th>Dërgesë falas mbi</th><td><?= $settings['dergesa_falas_mbi'] === null ? '—' : e(number_format((float) $settings['dergesa_falas_mbi'], 2)) . ' €' ?></td><th>Pragu i stokut</th><td><?= e($settings['pragu_stokut']) ?></td></tr>
                    <tr><th>Paralajmërimi 1</th><td><?= e($settings['paralajmerimi_1']) ?> ditë</td><th>Paralajmërimi 2</th><td><?= e($settings['paralajmerimi_2']) ?> ditë</td></tr>
                </tbody></table></div>
            <?php endif; ?>
        </section>
    <?php endif; ?>
<?php endif; ?>
<?php if ($logs !== []): ?>
    <section class="panel"><h2>Auditimi i fundit</h2><div class="table-wrap"><table><thead><tr><th>Përdoruesi</th><th>Veprimi</th><th>Koha</th></tr></thead><tbody>
        <?php foreach ($logs as $log): ?><tr><td><?= e($log['perdoruesi']) ?></td><td><?= e($log['veprimi']) ?></td><td><?= e($log['koha']) ?></td></tr><?php endforeach; ?>
    </tbody></table></div></section>
<?php endif; ?>
<section class="panel">
    <h2>Siguria e llogarisë</h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="action" value="change_password">
        <div class="field"><label for="current-password">Fjalëkalimi aktual</label><input id="current-password" type="password" name="current_password" autocomplete="current-password" required></div>
        <div class="field"><label for="new-password">Fjalëkalimi i ri</label><input id="new-password" type="password" name="new_password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
        <div class="field"><label for="confirm-password">Përsërit fjalëkalimin e ri</label><input id="confirm-password" type="password" name="confirm_password" minlength="12" maxlength="72" autocomplete="new-password" required></div>
        <button class="btn" type="submit">Ndrysho fjalëkalimin</button>
    </form>
</section>
<?php admin_footer(); ?>
