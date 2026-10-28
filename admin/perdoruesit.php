<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

$error = '';
$editId = filter_input(INPUT_GET, 'edit', FILTER_VALIDATE_INT);
$editQuery = $pdo->prepare(
    'SELECT id, emri, username, email, rooli, telefoni, qyteti, statusi FROM me_perdoruesit WHERE id = ?'
);
if ($editId) {
    $editQuery->execute([$editId]);
    $editingUser = $editQuery->fetch() ?: null;
} else {
    $editingUser = null;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) ?: 0;
    $name = trim((string) ($_POST['emri'] ?? ''));
    $username = trim((string) ($_POST['username'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $phone = trim((string) ($_POST['telefoni'] ?? ''));
    $city = trim((string) ($_POST['qyteti'] ?? ''));
    $role = (string) ($_POST['rooli'] ?? '');
    $status = (string) ($_POST['statusi'] ?? '');
    $password = (string) ($_POST['fjalekalimi'] ?? '');

    try {
        if ($name === '' || strlen($name) > 100 || !preg_match('/^[A-Za-z0-9_.-]{3,50}$/', $username)
            || !filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 100 || strlen($phone) > 30
            || strlen($city) > 50 || !in_array($role, ['admin', 'farmacist', 'kurier'], true)
            || !in_array($status, ['Aktiv', 'Bllokuar'], true)
            || ($id === 0 && (strlen($password) < 12 || strlen($password) > 72))
            || ($password !== '' && (strlen($password) < 12 || strlen($password) > 72))) {
            throw new DomainException('Plotësoni të dhënat saktë; fjalëkalimi i ri duhet të ketë së paku 12 karaktere.');
        }
        if ($id === $current_user_id && ($role !== $current_role || $status !== 'Aktiv')) {
            throw new DomainException('Nuk mund të ndryshoni rolin ose të bllokoni llogarinë tuaj.');
        }
        if ($id > 0) {
            if ($password !== '') {
                $save = $pdo->prepare(
                    'UPDATE me_perdoruesit SET emri=?, username=?, email=?, telefoni=?, qyteti=?,
                     rooli=?, statusi=?, fjalekalimi=?, duhet_ndryshim_fjalekalimi=1 WHERE id=?'
                );
                $save->execute([$name, $username, $email, $phone ?: null, $city ?: null, $role, $status, password_hash($password, PASSWORD_DEFAULT), $id]);
            } else {
                $save = $pdo->prepare(
                    'UPDATE me_perdoruesit SET emri=?, username=?, email=?, telefoni=?, qyteti=?, rooli=?, statusi=? WHERE id=?'
                );
                $save->execute([$name, $username, $email, $phone ?: null, $city ?: null, $role, $status, $id]);
            }
            audit_log($pdo, "Përditësoi llogarinë e stafit {$username}");
        } else {
            $save = $pdo->prepare(
                'INSERT INTO me_perdoruesit
                 (emri, username, email, fjalekalimi, rooli, telefoni, qyteti, duhet_ndryshim_fjalekalimi)
                 VALUES (?, ?, ?, ?, ?, ?, ?, 1)'
            );
            $save->execute([$name, $username, $email, password_hash($password, PASSWORD_DEFAULT), $role, $phone ?: null, $city ?: null]);
            audit_log($pdo, "Krijoi llogarinë e stafit {$username}");
        }
        flash_message('Llogaria e stafit u ruajt. Përdoruesi duhet të ndryshojë fjalëkalimin në hyrjen e radhës.');
        redirect('/admin/perdoruesit.php');
    } catch (DomainException $exception) {
        $error = $exception->getMessage();
    } catch (PDOException $exception) {
        error_log('HerbaBloom staff operation failed: ' . $exception->getMessage());
        $error = 'Ruajtja dështoi. Emri i përdoruesit mund të jetë përdorur tashmë.';
    }
}

$users = $pdo->query(
    'SELECT id, emri, username, email, rooli, telefoni, qyteti, statusi, hyrja_e_fundit
     FROM me_perdoruesit ORDER BY FIELD(rooli, "admin","farmacist","kurier"), emri'
)->fetchAll();
$formUser = $editingUser ?? [];

admin_header('Menaxhimi i stafit');
render_flash();
?>
<?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
<section class="panel">
    <h2><?= $editingUser ? 'Përditëso llogarinë' : 'Krijo llogari stafi' ?></h2>
    <form method="post" class="form-grid">
        <?= csrf_field() ?><input type="hidden" name="id" value="<?= e($formUser['id'] ?? '') ?>">
        <div class="field"><label for="name">Emri i plotë</label><input id="name" name="emri" maxlength="100" value="<?= e($formUser['emri'] ?? '') ?>" required></div>
        <div class="field"><label for="username">Username</label><input id="username" name="username" minlength="3" maxlength="50" pattern="[A-Za-z0-9_.-]+" value="<?= e($formUser['username'] ?? '') ?>" required></div>
        <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="100" value="<?= e($formUser['email'] ?? '') ?>" required></div>
        <div class="field"><label for="phone">Telefoni</label><input id="phone" name="telefoni" maxlength="30" value="<?= e($formUser['telefoni'] ?? '') ?>"></div>
        <div class="field"><label for="city">Qyteti</label><input id="city" name="qyteti" maxlength="50" value="<?= e($formUser['qyteti'] ?? 'Prizren') ?>"></div>
        <div class="field"><label for="role">Roli</label><select id="role" name="rooli" required>
            <?php foreach (['admin' => 'Administrator', 'farmacist' => 'Farmacist', 'kurier' => 'Kurier'] as $value => $label): ?>
                <option value="<?= e($value) ?>" <?= ($formUser['rooli'] ?? 'farmacist') === $value ? 'selected' : '' ?>><?= e($label) ?></option>
            <?php endforeach; ?>
        </select></div>
        <div class="field"><label for="status">Statusi</label><select id="status" name="statusi"><option value="Aktiv" <?= ($formUser['statusi'] ?? 'Aktiv') === 'Aktiv' ? 'selected' : '' ?>>Aktiv</option><option value="Bllokuar" <?= ($formUser['statusi'] ?? '') === 'Bllokuar' ? 'selected' : '' ?>>Bllokuar</option></select></div>
        <div class="field"><label for="password">Fjalëkalimi <?= $editingUser ? '(lëre bosh për ta ruajtur)' : '' ?></label><input id="password" type="password" name="fjalekalimi" minlength="12" maxlength="72" <?= $editingUser ? '' : 'required' ?> autocomplete="new-password"></div>
        <p class="help">Përdorni së paku 12 karaktere. Fjalëkalimi ruhet vetëm si hash.</p>
        <div><button class="btn" type="submit">Ruaj përdoruesin</button><?php if ($editingUser): ?> <a class="btn secondary" href="<?= e(app_url('/admin/perdoruesit.php')) ?>">Anulo</a><?php endif; ?></div>
    </form>
</section>
<section class="panel">
    <h2>Llogaritë ekzistuese</h2>
    <div class="table-wrap"><table><thead><tr><th>Emri</th><th>Username</th><th>Kontakti</th><th>Roli</th><th>Statusi</th><th>Hyrja e fundit</th><th></th></tr></thead><tbody>
    <?php foreach ($users as $user): ?><tr><td><?= e($user['emri']) ?></td><td><?= e($user['username']) ?></td><td><?= e($user['email']) ?><br><?= e($user['telefoni'] ?? '') ?></td><td><?= e($user['rooli']) ?></td><td><span class="badge <?= $user['statusi'] === 'Aktiv' ? 'green' : 'red' ?>"><?= e($user['statusi']) ?></span></td><td><?= e($user['hyrja_e_fundit'] ?? '—') ?></td><td><a class="btn secondary small" href="<?= e(app_url('/admin/perdoruesit.php?edit=' . $user['id'])) ?>">Ndrysho</a></td></tr><?php endforeach; ?>
    </tbody></table></div>
</section>
<?php admin_footer(); ?>
