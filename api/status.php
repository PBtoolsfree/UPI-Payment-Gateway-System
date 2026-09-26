<?php
// api/status.php
if (!isset($_GET['order_id'])) {
    jsonResponse(false, "Order ID required", [], 400);
}

$stmt = $pdo->prepare("SELECT status, utr_number, amount, redirect_url FROM transactions_ledger WHERE order_id = ?");
$stmt->execute([$_GET['order_id']]);
$order = $stmt->fetch();

if (!$order) {
    jsonResponse(false, "Not found", [], 404);
}

jsonResponse(true, "Status fetched", $order);
