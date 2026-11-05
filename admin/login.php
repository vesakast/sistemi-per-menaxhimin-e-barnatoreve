<?php
declare(strict_types=1);

require_once __DIR__ . '/_functions.php';

if (isset($_SESSION['user_id'])) {
    redirect('/admin/' . (($_SESSION['role'] ?? '') === 'kurier' ? 'shitjet.php' : 'dashboard.php'));
}

$error = '';
$stage = isset($_SESSION['pending_auth_user_id']) ? 'otp' : 'credentials';
$ip = substr((string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'), 0, 45);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'login');

    if ($action === 'verify_otp' && isset($_SESSION['pending_auth_user_id'])) {
        $userId = (int) $_SESSION['pending_auth_user_id'];
        $userQuery = $pdo->prepare(
            "SELECT id, username, emri, rooli, statusi FROM me_perdoruesit WHERE id = ? LIMIT 1"
        );
        $userQuery->execute([$userId]);
        $user = $userQuery->fetch();
        $otpQuery = $pdo->prepare(
            'SELECT id, kodi_hash, skadon_me, tentativa FROM me_otp_kodet
             WHERE perdorues_id = ? AND perdorur = 0 ORDER BY id DESC LIMIT 1'
        );
        $otpQuery->execute([$userId]);
        $otp = $otpQuery->fetch();
        $submittedCode = (string) ($_POST['otp_code'] ?? '');

        if ($user && $user['statusi'] === 'Aktiv' && $otp
            && (int) $otp['tentativa'] < 5
            && strtotime((string) $otp['skadon_me']) > time()
            && preg_match('/^\d{6}$/D', $submittedCode)
            && password_verify($submittedCode, $otp['kodi_hash'])) {
            $pdo->beginTransaction();
            $pdo->prepare('UPDATE me_otp_kodet SET perdorur = 1 WHERE id = ?')->execute([$otp['id']]);
            $pdo->prepare('UPDATE me_perdoruesit SET hyrja_e_fundit = NOW() WHERE id = ?')->execute([$userId]);
            if (isset($_SESSION['pending_attempt_id'])) {
                $pdo->prepare('UPDATE me_login_tentativa SET sukses = 1 WHERE id = ? AND username = ? AND ip = ?')
                    ->execute([$_SESSION['pending_attempt_id'], $user['username'], $ip]);
            }
            $pdo->prepare(
                'INSERT INTO me_audit_log (perdorues_id, perdoruesi, veprimi, ip) VALUES (?, ?, ?, ?)'
            )->execute([$user['id'], substr($user['username'], 0, 50), 'Hyrje e suksesshme', $ip]);
            unset($_SESSION['customer_id'], $_SESSION['customer_last_activity']);
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['name'] = $user['emri'];
            $_SESSION['role'] = $user['rooli'];
            $_SESSION['last_activity'] = time();
            unset($_SESSION['pending_auth_user_id'], $_SESSION['pending_attempt_id']);
            $pdo->commit();
            redirect('/admin/' . ($user['rooli'] === 'kurier' ? 'shitjet.php' : 'dashboard.php'));
        }

        if ($otp && (int) $otp['tentativa'] < 5) {
            $pdo->prepare('UPDATE me_otp_kodet SET tentativa = tentativa + 1 WHERE id = ?')->execute([$otp['id']]);
        }
        $pdo->prepare(
            'INSERT INTO me_login_tentativa (username, ip, sukses) VALUES (?, ?, 0)'
        )->execute([$user['username'] ?? 'unknown', $ip]);
        $error = 'Kodi është i pavlefshëm ose ka skaduar. Kërkoni hyrje të re.';
        if (!$otp || (int) ($otp['tentativa'] ?? 5) >= 4) {
            unset($_SESSION['pending_auth_user_id'], $_SESSION['pending_attempt_id']);
            $stage = 'credentials';
        }
    } elseif ($action === 'login') {
        $username = trim((string) ($_POST['username'] ?? ''));
        $password = (string) ($_POST['password'] ?? '');
        $rateQuery = $pdo->prepare(
            'SELECT COUNT(*) FROM me_login_tentativa
             WHERE sukses = 0 AND koha >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
               AND (username = ? OR ip = ?)'
        );
        $rateQuery->execute([$username, $ip]);

        if ((int) $rateQuery->fetchColumn() >= 5) {
            $error = 'Shumë tentativa hyrjeje. Prisni 15 minuta dhe provoni përsëri.';
        } else {
            $userQuery = $pdo->prepare(
                'SELECT id, username, emri, fjalekalimi, rooli, statusi
                 FROM me_perdoruesit WHERE username = ? LIMIT 1'
            );
            $userQuery->execute([$username]);
            $user = $userQuery->fetch();
            $validPassword = strlen($password) <= 72 && $user && $user['statusi'] === 'Aktiv'
                && password_verify($password, $user['fjalekalimi']);

            if (!$validPassword) {
                $pdo->prepare(
                    'INSERT INTO me_login_tentativa (username, ip, sukses) VALUES (?, ?, 0)'
                )->execute([$username !== '' ? $username : 'unknown', $ip]);
                $error = 'Emri i përdoruesit ose fjalëkalimi është i pasaktë.';
            } else {
                session_regenerate_id(true);
                $pdo->prepare(
                    'INSERT INTO me_login_tentativa (username, ip, sukses) VALUES (?, ?, 0)'
                )->execute([$username, $ip]);
                $attemptId = (int) $pdo->lastInsertId();
                $settingsQuery = $pdo->query('SELECT otp_email FROM me_cilesimet WHERE id = 1');
                $otpRecipient = (string) $settingsQuery->fetchColumn();
                $smtpHost = app_env('SMTP_HOST');
                $smtpUser = app_env('SMTP_USER');
                $smtpPassword = app_env('SMTP_PASSWORD');
                $smtpFrom = app_env('SMTP_FROM', $smtpUser);
                $autoload = dirname(__DIR__) . '/vendor/autoload.php';

                if ($smtpHost === '' || $smtpUser === '' || $smtpPassword === '' || $otpRecipient === '' || !is_file($autoload)) {
                    $error = 'Shërbimi i email-it për OTP nuk është konfiguruar. Kontaktoni administratorin.';
                } else {
                    $code = (string) random_int(100000, 999999);
                    $pdo->prepare(
                        'UPDATE me_otp_kodet SET perdorur = 1 WHERE perdorues_id = ? AND perdorur = 0'
                    )->execute([$user['id']]);
                    $pdo->prepare(
                        'INSERT INTO me_otp_kodet (perdorues_id, kodi_hash, skadon_me, ip)
                         VALUES (?, ?, DATE_ADD(NOW(), INTERVAL 5 MINUTE), ?)'
                    )->execute([$user['id'], password_hash($code, PASSWORD_DEFAULT), $ip]);

                    require_once $autoload;
                    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
                    try {
                        $mail->isSMTP();
                        $mail->Host = $smtpHost;
                        $mail->SMTPAuth = true;
                        $mail->Username = $smtpUser;
                        $mail->Password = $smtpPassword;
                        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
                        $mail->Port = (int) app_env('SMTP_PORT', '587');
                        $mail->setFrom($smtpFrom, 'HerbaBloom');
                        $mail->addAddress($otpRecipient);
                        $mail->Subject = 'Kodi i verifikimit HerbaBloom';
                        $mail->Body = "Kodi juaj i hyrjes është {$code}. Skadon pas 5 minutash.";
                        $mail->send();
                        $_SESSION['pending_auth_user_id'] = (int) $user['id'];
                        $_SESSION['pending_attempt_id'] = $attemptId;
                        $stage = 'otp';
                    } catch (PHPMailer\PHPMailer\Exception $exception) {
                        error_log('HerbaBloom OTP email delivery failed: ' . $exception->getMessage());
                        $pdo->prepare(
                            'UPDATE me_otp_kodet SET perdorur = 1 WHERE perdorues_id = ? AND perdorur = 0'
                        )->execute([$user['id']]);
                        $error = 'Kodi i verifikimit nuk mund të dërgohej. Provoni përsëri më vonë.';
                    }
                }
            }
        }
    }
}
?>
<!doctype html>
<html lang="sq">
<head>
    <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#344a38">
    <title>Hyrja e stafit · HerbaBloom</title>
    <link rel="icon" type="image/svg+xml" href="<?= e(app_url('/web-images/herbabloom-favicon.svg')) ?>">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=DM+Serif+Display&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(app_url('/admin/style.css')) ?>">
</head>
<body class="auth-page">
<main class="auth-card">
    <p class="eyebrow">Panel i sigurt për stafin</p>
    <h1><img class="login-brand-flower" src="<?= e(app_url('/web-images/herbabloom-favicon.svg')) ?>" alt="" aria-hidden="true"> HerbaBloom</h1>
    <p class="help"><?= $stage === 'otp' ? 'Shkruani kodin 6-shifror të dërguar në email-in e verifikimit.' : 'Hyni për të menaxhuar farmacinë.' ?></p>
    <?php if ($error !== ''): ?><div class="alert error" role="alert"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
        <?= csrf_field() ?>
        <?php if ($stage === 'otp'): ?>
            <input type="hidden" name="action" value="verify_otp">
            <div class="field"><label for="otp_code">Kodi i verifikimit</label><input id="otp_code" name="otp_code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autofocus></div>
            <button class="btn" type="submit">Verifiko kodin</button>
        <?php else: ?>
            <input type="hidden" name="action" value="login">
            <div class="field"><label for="username">Emri i përdoruesit</label><input id="username" name="username" maxlength="50" autocomplete="username" required autofocus></div>
            <div class="field"><label for="password">Fjalëkalimi</label><input id="password" type="password" name="password" maxlength="72" autocomplete="current-password" required></div>
            <button class="btn" type="submit">Hyr në panel</button>
        <?php endif; ?>
    </form>
</main>
</body>
</html>
