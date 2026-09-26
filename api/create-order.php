<?php
// api/create-order.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(false, "Method not allowed", [], 405);
}

$headers = apache_request_headers();
$api_key = $headers['X-Api-Key'] ?? $_POST['api_key'] ?? '';

// Check API Key against global settings
$stmt = $pdo->query("SELECT api_key, active_gateway_id FROM settings WHERE id = 1");
$settings_api = $stmt->fetch();

if ($api_key !== $settings_api['api_key']) {
    jsonResponse(false, "Invalid API credentials", [], 401);
}

// Fetch active gateway
$stmt = $pdo->prepare("SELECT name FROM gateways WHERE id = ?");
$stmt->execute([$settings_api['active_gateway_id']]);
$gateway = $stmt->fetchColumn();

// Validate input
$amount = $_POST['amount'] ?? 0;
$order_id = $_POST['order_ref'] ?? 'ORD' . time() . rand(10, 99);
$note = $_POST['customer_note'] ?? 'Payment';
$redirect_url = $_POST['redirect_url'] ?? '';
$webhook_url = $_POST['webhook_url'] ?? '';

if ($amount <= 0) {
    jsonResponse(false, "Invalid amount", [], 400);
}

$client_ip = $_SERVER['REMOTE_ADDR'];

try {
    $stmt = $pdo->prepare("INSERT INTO transactions_ledger (order_id, gateway_name, amount, note, redirect_url, webhook_url, ip_address, status) VALUES (?, ?, ?, ?, ?, ?, ?, 'PENDING')");
    $stmt->execute([
        $order_id,
        $gateway,
        $amount,
        $note,
        $redirect_url,
        $webhook_url,
        $client_ip
    ]);
    
    $payment_url = "http://" . $_SERVER['HTTP_HOST'] . "/pay?order_id=" . urlencode($order_id);
    
    jsonResponse(true, "Order created", [
        'order_id' => $order_id,
        'payment_url' => $payment_url,
        'amount' => $amount
    ]);
} catch (PDOException $e) {
    if ($e->getCode() == 23000) {
        jsonResponse(false, "Order ID already exists", [], 400);
    }
    jsonResponse(false, "DB Error", [], 500);
}
