<?php
// api/v1/create-order.php
// Requires: config.php

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, "Method not allowed", [], 405);
}

// Get headers for authentication
$headers = apache_request_headers();
$api_key = $headers['X-Api-Key'] ?? $_POST['api_key'] ?? '';
$secret_key = $headers['X-Secret-Key'] ?? $_POST['secret_key'] ?? '';

if (empty($api_key) || empty($secret_key)) {
    jsonResponse(false, "Authentication credentials missing", [], 401);
}

// Authenticate Merchant
$stmt = $pdo->prepare("SELECT * FROM merchants WHERE api_key = ? AND secret_key = ? LIMIT 1");
$stmt->execute([$api_key, $secret_key]);
$merchant = $stmt->fetch();

if (!$merchant) {
    jsonResponse(false, "Invalid API credentials", [], 401);
}

// Check IP Whitelisting
$client_ip = $_SERVER['REMOTE_ADDR'];
$allowed_ips = $merchant['allowed_ips'];

if ($allowed_ips !== '*' && !empty($allowed_ips)) {
    $ip_list = array_map('trim', explode(',', $allowed_ips));
    if (!in_array($client_ip, $ip_list)) {
        jsonResponse(false, "IP address not whitelisted", [], 403);
    }
}

// Validate input
$amount = $_POST['amount'] ?? 0;
$order_id = $_POST['order_id'] ?? 'ORD' . time() . rand(100, 999);
$note = $_POST['note'] ?? 'Payment for Order ' . $order_id;
$redirect_url = $_POST['redirect_url'] ?? '';

if ($amount <= 0) {
    jsonResponse(false, "Invalid amount", [], 400);
}

// Create Ledger Entry
try {
    $stmt = $pdo->prepare("INSERT INTO transactions_ledger (order_id, merchant_id, gateway_type, amount, note, redirect_url, ip_address, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')");
    $stmt->execute([
        $order_id,
        $merchant['id'],
        $merchant['active_gateway'],
        $amount,
        $note,
        $redirect_url,
        $client_ip
    ]);
    
    $checkout_url = "http://" . $_SERVER['HTTP_HOST'] . "/checkout?order_id=" . urlencode($order_id);
    
    jsonResponse(true, "Order created successfully", [
        'order_id' => $order_id,
        'checkout_url' => $checkout_url,
        'amount' => $amount
    ]);
    
} catch (PDOException $e) {
    // Handle duplicate order_id
    if ($e->getCode() == 23000) {
        jsonResponse(false, "Order ID already exists", [], 400);
    }
    jsonResponse(false, "Database error: " . $e->getMessage(), [], 500);
}
