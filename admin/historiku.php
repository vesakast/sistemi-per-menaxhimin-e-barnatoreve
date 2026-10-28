<?php
declare(strict_types=1);

require_once __DIR__ . '/auth_check.php';

$canViewRevenue = $current_role === 'admin';
$sql = "SELECT o.id, o.kodi_gjurmimit, o.klienti, o.telefoni, o.qyteti, o.adresa, "
        . ($canViewRevenue ? 'o.totali, ' : '') . "
               o.statusi, o.arsyeja_anullimit, o.data_porosise, o.data_dorezimit, u.emri AS emri_kurierit
        FROM me_porosite_online o LEFT JOIN me_perdoruesit u ON u.id = o.kurier_id
        WHERE o.statusi IN ('Dorëzuar','Anuluar')";
if ($current_role === 'kurier') {
    $sql .= ' AND o.kurier_id = :courier_id';
}
$sql .= ' ORDER BY COALESCE(o.data_dorezimit, o.data_porosise) DESC LIMIT 300';
$query = $pdo->prepare($sql);
$query->execute($current_role === 'kurier' ? ['courier_id' => $current_user_id] : []);
$orders = $query->fetchAll();

admin_header('Historiku i porosive');
?>
<section class="panel">
    <h2>Porositë e dorëzuara dhe të anuluara</h2>
    <?php if ($orders === []): ?><p class="empty">Nuk ka porosi të mbyllura.</p>
    <?php else: ?><div class="table-wrap"><table><thead><tr><th>Gjurmimi</th><th>Klienti</th><th>Adresa</th><th>Kurieri</th><?php if ($canViewRevenue): ?><th>Totali</th><?php endif; ?><th>Statusi / arsyeja</th><th>Data</th></tr></thead><tbody>
        <?php foreach ($orders as $order): ?>
            <tr><td><?= e($order['kodi_gjurmimit']) ?></td>
                <td><?= e($order['klienti']) ?><br><span class="help"><?= e($order['telefoni']) ?></span></td>
                <td><?= e($order['qyteti']) ?> · <?= e($order['adresa']) ?></td>
                <td><?= e($order['emri_kurierit'] ?? '—') ?></td>
                <?php if ($canViewRevenue): ?><td><?= e(number_format((float) $order['totali'], 2)) ?> €</td><?php endif; ?>
                <td><span class="badge <?= $order['statusi'] === 'Anuluar' ? 'red' : 'green' ?>"><?= e($order['statusi']) ?></span>
                    <?php if ($order['arsyeja_anullimit']): ?><br><span class="help"><?= e($order['arsyeja_anullimit']) ?></span><?php endif; ?></td>
                <td><?= e($order['data_dorezimit'] ?? $order['data_porosise']) ?></td></tr>
        <?php endforeach; ?>
    </tbody></table></div><?php endif; ?>
</section>
<?php admin_footer(); ?>
