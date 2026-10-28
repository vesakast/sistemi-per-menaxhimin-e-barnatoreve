<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['emri'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $subject = trim((string) ($_POST['subjekti'] ?? ''));
    $message = trim((string) ($_POST['mesazhi'] ?? ''));
    $honeypot = trim((string) ($_POST['website'] ?? ''));
    $lastSubmission = (int) ($_SESSION['_last_contact_submission'] ?? 0);

    if ($honeypot !== '') {
        $error = 'Nuk u pranua kërkesa.';
    } elseif (time() - $lastSubmission < 30) {
        $error = 'Prisni pak para se të dërgoni një mesazh tjetër.';
    } elseif ($name === '' || strlen($name) > 100 || !filter_var($email, FILTER_VALIDATE_EMAIL)
        || strlen($email) > 100 || $subject === '' || strlen($subject) > 150
        || $message === '' || strlen($message) > 10000) {
        $error = 'Kontrolloni emrin, email-in, subjektin dhe mesazhin.';
    } else {
        $insert = $pdo->prepare(
            'INSERT INTO me_mesazhet (emri, email, subjekti, mesazhi) VALUES (?, ?, ?, ?)'
        );
        $insert->execute([$name, $email, $subject, $message]);
        $_SESSION['_last_contact_submission'] = time();
        $_SESSION['_contact_success'] = true;
        redirect('/web/kontakti.php');
    }
}
$success = (bool) ($_SESSION['_contact_success'] ?? false);
unset($_SESSION['_contact_success']);
$store = $pdo->query('SELECT emri_dyqanit, email, telefoni, adresa FROM me_cilesimet WHERE id=1')->fetch();

web_header('Kontakti');
?>
<p class="eyebrow">Jemi këtu për ju</p><h1 class="page-title">Na kontaktoni</h1>
<div class="content-grid">
    <section class="glass-card">
        <?php if ($success): ?><div class="alert" role="status">Mesazhi u dërgua me sukses. Do t'ju kontaktojmë së shpejti.</div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form-stack">
            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="field"><label for="name">Emri</label><input id="name" name="emri" maxlength="100" required></div>
            <div class="field"><label for="email">Email</label><input id="email" name="email" type="email" maxlength="100" required></div>
            <div class="field"><label for="subject">Subjekti</label><input id="subject" name="subjekti" maxlength="150" required></div>
            <div class="field"><label for="message">Mesazhi</label><textarea id="message" name="mesazhi" maxlength="10000" required></textarea></div>
            <div hidden aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <button class="btn" type="submit">Dërgo mesazhin</button>
        </form>
    </section>
    <aside class="glass-card"><h2>HerbaBloom</h2>
        <?php if ($store): ?><p><?= e($store['adresa'] ?? 'Na kontaktoni për adresën e dyqanit.') ?></p>
            <?php if ($store['email']): ?><p>Email: <a href="mailto:<?= e($store['email']) ?>"><?= e($store['email']) ?></a></p><?php endif; ?>
            <?php if ($store['telefoni']): ?><p>Tel: <?= e($store['telefoni']) ?></p><?php endif; ?>
        <?php else: ?><p>Na shkruani për pyetje rreth produkteve dhe porosive.</p><?php endif; ?>
    </aside>
</div>
<?php web_footer(); ?>
