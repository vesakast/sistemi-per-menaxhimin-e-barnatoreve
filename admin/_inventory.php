<?php
declare(strict_types=1);

function reserve_stock(PDO $pdo, int $productId, int $quantity, int $orderId, ?int $userId, bool $webOnly = false): void
{
    $productQuery = $pdo->prepare(
        'SELECT id FROM me_produktet WHERE id = ? AND aktiv = 1'
        . ($webOnly ? ' AND i_dukshem_ne_web = 1' : '')
        . ' FOR UPDATE'
    );
    $productQuery->execute([$productId]);
    if (!$productQuery->fetchColumn()) {
        throw new DomainException('Një produkt nuk është më i disponueshëm.');
    }

    $batchQuery = $pdo->prepare(
        'SELECT id, sasia FROM me_stoku_batch
         WHERE produkt_id = ? AND sasia > 0 AND (data_skadence IS NULL OR data_skadence >= CURDATE())
         ORDER BY data_skadence IS NULL, data_skadence, id FOR UPDATE'
    );
    $batchQuery->execute([$productId]);
    $remaining = $quantity;

    foreach ($batchQuery->fetchAll() as $batch) {
        if ($remaining === 0) {
            break;
        }
        $deduct = min($remaining, (int) $batch['sasia']);
        $pdo->prepare('UPDATE me_stoku_batch SET sasia = sasia - ? WHERE id = ? AND sasia >= ?')
            ->execute([$deduct, $batch['id'], $deduct]);
        if ($deduct > 0) {
            $pdo->prepare(
                "INSERT INTO me_stoku_levizjet
                 (produkt_id, batch_id, sasia, lloji, porosia_id, nga_user_id)
                 VALUES (?, ?, ?, 'shitje', ?, ?)"
            )->execute([$productId, $batch['id'], -$deduct, $orderId, $userId]);
            $remaining -= $deduct;
        }
    }

    if ($remaining > 0) {
        throw new DomainException('Nuk ka stok të mjaftueshëm për një nga produktet.');
    }
}

function restore_order_stock(PDO $pdo, int $orderId, ?int $userId): void
{
    $movementsQuery = $pdo->prepare(
        "SELECT id, produkt_id, batch_id, -sasia AS sasia
         FROM me_stoku_levizjet
         WHERE porosia_id = ? AND lloji = 'shitje' AND sasia < 0
         ORDER BY id FOR UPDATE"
    );
    $movementsQuery->execute([$orderId]);
    $movements = $movementsQuery->fetchAll();
    if ($movements === []) {
        throw new DomainException('Nuk u gjet regjistrimi i stokut për këtë porosi.');
    }

    foreach ($movements as $movement) {
        if ($movement['batch_id'] === null) {
            throw new DomainException('Batch-i origjinal i stokut nuk është më i disponueshëm.');
        }
        $batchLock = $pdo->prepare('SELECT id FROM me_stoku_batch WHERE id = ? FOR UPDATE');
        $batchLock->execute([$movement['batch_id']]);
        if (!$batchLock->fetchColumn()) {
            throw new DomainException('Batch-i origjinal i stokut nuk është më i disponueshëm.');
        }
        $pdo->prepare('UPDATE me_stoku_batch SET sasia = sasia + ? WHERE id = ?')
            ->execute([$movement['sasia'], $movement['batch_id']]);
        $pdo->prepare(
            "INSERT INTO me_stoku_levizjet
             (produkt_id, batch_id, sasia, lloji, porosia_id, nga_user_id)
             VALUES (?, ?, ?, 'anullim', ?, ?)"
        )->execute([
            $movement['produkt_id'],
            $movement['batch_id'],
            $movement['sasia'],
            $orderId,
            $userId,
        ]);
    }
}
