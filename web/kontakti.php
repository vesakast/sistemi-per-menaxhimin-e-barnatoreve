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
$store = $pdo->query('SELECT emri_dyqanit, email, telefoni FROM me_cilesimet WHERE id=1')->fetch();
$adminContact = $pdo->query(
    "SELECT email, telefoni FROM me_perdoruesit
     WHERE rooli='admin' AND statusi='Aktiv' ORDER BY id LIMIT 1"
)->fetch();
$contactEmail = trim((string) ($store['email'] ?? '')) ?: trim((string) ($adminContact['email'] ?? ''));
$contactPhone = trim((string) ($store['telefoni'] ?? '')) ?: trim((string) ($adminContact['telefoni'] ?? ''));

web_header('Kontakt');
?>
<section class="page-intro" data-reveal><p class="eyebrow">Ndihmë nga HerbaBloom</p><h1 class="page-title">Na shkruaj, jemi këtu për ty</h1><p>Ke pyetje për katalogun, porosinë apo dërgesën? Dërgo një mesazh dhe ekipi ynë do të të përgjigjet sa më shpejt që të jetë e mundur.</p></section>
<div class="content-grid">
    <section class="glass-card">
        <?php if ($success): ?><div class="alert" role="status">Faleminderit që na shkrove. Mesazhi u dërgua dhe do të të kontaktojmë sa më shpejt.</div><?php endif; ?>
        <?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="form-stack">
            <input type="hidden" name="_csrf_token" value="<?= e(csrf_token()) ?>">
            <div class="field"><label for="name">Emri yt</label><input id="name" name="emri" maxlength="100" autocomplete="name" required></div>
            <div class="field"><label for="email">Adresa e email-it</label><input id="email" name="email" type="email" maxlength="100" autocomplete="email" required></div>
            <div class="field"><label for="subject">Për çfarë dëshiron të na shkruash?</label><input id="subject" name="subjekti" maxlength="150" required></div>
            <div class="field"><label for="message">Mesazhi yt</label><textarea id="message" name="mesazhi" maxlength="10000" placeholder="Na trego si mund të të ndihmojmë." required></textarea></div>
            <div hidden aria-hidden="true"><label for="website">Website</label><input id="website" name="website" tabindex="-1" autocomplete="off"></div>
            <button class="btn" type="submit">Dërgo kërkesën</button>
        </form>
    </section>
    <aside class="glass-card"><h2>Si mund të të ndihmojmë?</h2>
        <p>HerbaBloom është dyqan online me produkte bimore, suplemente dhe artikuj për kujdes personal. Nuk ofrojmë vizita në adresë fizike.</p>
        <p>Mund të na kontaktosh para ose pas porosisë për sqarime rreth informacionit në katalog, pagesës në dorëzim apo përcjelljes së dërgesës.</p>
        <?php if ($contactEmail !== ''): ?><p>Email: <a href="mailto:<?= e($contactEmail) ?>"><?= e($contactEmail) ?></a></p><?php endif; ?>
        <?php if ($contactPhone !== ''): ?><p>Tel: <a href="tel:<?= e(preg_replace('/[^0-9+]/', '', $contactPhone) ?? '') ?>"><?= e($contactPhone) ?></a></p><?php endif; ?>
        <?php if ($contactEmail === '' && $contactPhone === ''): ?><p>Përdor formularin në të majtë dhe përfshi detajet që na ndihmojnë ta kuptojmë kërkesën tënde.</p><?php endif; ?>
    </aside>
</div>
<?php web_footer(); ?>
