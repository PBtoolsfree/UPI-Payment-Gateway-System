<?php
// api/v1/check-status.php
// Requires: config.php

if (!isset($_GET['order_id'])) {
    jsonResponse(false, "Order ID is required", [], 400);
}

$order_id = $_GET['order_id'];

$stmt = $pdo->prepare("SELECT status, utr_number, amount, redirect_url FROM transactions_ledger WHERE order_id = ? LIMIT 1");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    jsonResponse(false, "Order not found", [], 404);
}

// In a real scenario, here you might verify with the underlying gateway (e.g., Paytm, PhonePe) 
// using their respective status APIs if status is still PENDING. 
// For this SaaS, we might rely on webhooks received from the provider or manual UTR entry.

jsonResponse(true, "Order status retrieved", [
    'order_id' => $order_id,
    'status' => $order['status'],
    'utr_number' => $order['utr_number'],
    'redirect_url' => $order['redirect_url']
]);
