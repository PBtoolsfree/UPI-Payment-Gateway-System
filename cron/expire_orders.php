<?php
// cron/expire_orders.php
require_once __DIR__ . '/../config.php';

try {
    $stmt = $pdo->prepare("UPDATE transactions_ledger SET status = 'FAILED', updated_at = NOW() WHERE status = 'PENDING' AND created_at < (NOW() - INTERVAL 10 MINUTE)");
    $stmt->execute();
    echo "Expired " . $stmt->rowCount() . " orders.\n";
} catch (PDOException $e) {
    echo "DB Error\n";
}
