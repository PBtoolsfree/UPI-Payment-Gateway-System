<?php
// cron/expire_orders.php
// Run this via crontab every minute

require_once __DIR__ . '/../config.php';

echo "Running expire_orders cron job at " . date('Y-m-d H:i:s') . "\n";

try {
    // Find PENDING orders older than 10 minutes
    $stmt = $pdo->prepare("
        UPDATE transactions_ledger 
        SET status = 'FAILED', updated_at = NOW() 
        WHERE status = 'PENDING' 
        AND created_at < (NOW() - INTERVAL 10 MINUTE)
    ");
    
    $stmt->execute();
    $rowCount = $stmt->rowCount();
    
    echo "Expired $rowCount orders.\n";
    
    // In a full implementation, you would trigger webhooks for these failed orders here.
    
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage() . "\n";
}
