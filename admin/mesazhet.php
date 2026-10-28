<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $messageId = filter_input(INPUT_POST, 'message_id', FILTER_VALIDATE_INT);
    if (!$messageId) {
        http_response_code(400);
        exit('Mesazhi nuk është i vlefshëm.');
    }
    $update = $pdo->prepare('UPDATE me_mesazhet SET i_trajtuar = 1 - i_trajtuar WHERE id = ?');
    $update->execute([$messageId]);
    audit_log($pdo, "Ndryshoi gjendjen e mesazhit #{$messageId}");
    flash_message('Gjendja e mesazhit u përditësua.');
    redirect('/admin/mesazhet.php');
}

$messages = $pdo->query('SELECT id, emri, email, subjekti, mesazhi, i_trajtuar, data_dergimit FROM me_mesazhet ORDER BY i_trajtuar ASC, data_dergimit DESC LIMIT 200')->fetchAll();

admin_header('Mesazhet e klientëve');
render_flash();
?>
<section class="panel">
    <h2>Kërkesat nga formulari i kontaktit</h2>
    <?php if ($messages === []): ?><p class="empty">Nuk ka mesazhe.</p>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th>Klienti</th><th>Subjekti & mesazhi</th><th>Data</th><th>Statusi</th><th></th></tr></thead><tbody>
    <?php foreach ($messages as $message): ?>
        <tr><td><?= e($message['emri']) ?><br><a href="mailto:<?= e($message['email']) ?>"><?= e($message['email']) ?></a></td>
            <td><strong><?= e($message['subjekti']) ?></strong><br><?= nl2br(e($message['mesazhi'])) ?></td><td><?= e($message['data_dergimit']) ?></td>
            <td><span class="badge <?= $message['i_trajtuar'] ? 'green' : '' ?>"><?= $message['i_trajtuar'] ? 'Trajtuar' : 'E re' ?></span></td>
            <td><form method="post"><?= csrf_field() ?><input type="hidden" name="message_id" value="<?= e($message['id']) ?>"><button class="btn secondary small" type="submit"><?= $message['i_trajtuar'] ? 'Shëno të patrajtuar' : 'Shëno të trajtuar' ?></button></form></td></tr>
    <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php admin_footer(); ?>
