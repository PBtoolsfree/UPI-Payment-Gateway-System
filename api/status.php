<?php
// api/status.php
if (!isset($_GET['order_id'])) {
    jsonResponse(false, "Order ID required", [], 400);
}
$order_id = $_GET['order_id'];

// 1. Fetch current status
$stmt = $pdo->prepare("SELECT status, utr_number, amount, redirect_url, gateway_name FROM transactions_ledger WHERE order_id = ?");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    jsonResponse(false, "Not found", [], 404);
}

// 2. If it's already successful, return it
if ($order['status'] !== 'PENDING') {
    jsonResponse(true, "Status fetched", $order);
}

// 3. Backend Auto-Verification Logic (Triggered during polling)
// This hits the official gateway APIs to verify the transaction in real-time.

// Fetch Active Gateway Credentials
$stmt = $pdo->query("SELECT * FROM gateways WHERE name = '{$order['gateway_name']}' LIMIT 1");
$gateway = $stmt->fetch();

if ($gateway && $gateway['is_active']) {
    
    // ======== PAYTM AUTO-VERIFICATION WORKFLOW ========
    if ($gateway['name'] === 'Paytm Business' && !empty($gateway['mid'])) {
        /*
        // Real implementation using Paytm's Secure Gateway API
        $paytmParams = array();
        $paytmParams["body"] = array(
            "mid" => $gateway['mid'],
            "orderId" => $order_id,
        );
        // Note: Real implementation requires generating a ChecksumHash using merchant_key
        // $checksum = PaytmChecksum::generateSignature(json_encode($paytmParams["body"]), $gateway['api_token']);
        // $paytmParams["head"] = array("signature" => $checksum);

        $postData = json_encode($paytmParams);
        $ch = curl_init("https://securegw.paytm.in/v3/order/status");
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postData);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Content-Type: application/json"));
        $response = curl_exec($ch);
        $resJson = json_decode($response, true);

        if (isset($resJson['body']['resultInfo']['resultStatus']) && $resJson['body']['resultInfo']['resultStatus'] === 'TXN_SUCCESS') {
            $bankTxnId = $resJson['body']['bankTxnId']; // 12-digit UTR
            $txnAmount = $resJson['body']['txnAmount'];
            
            // Mark Success in Database
            $stmt = $pdo->prepare("UPDATE transactions_ledger SET status='SUCCESS', utr_number=? WHERE order_id=?");
            $stmt->execute([$bankTxnId, $order_id]);
            
            // Fire Webhooks
            fireWebhooks($order_id, $bankTxnId, $txnAmount);
            
            $order['status'] = 'SUCCESS';
            $order['utr_number'] = $bankTxnId;
        }
        */
        
        // --- SIMULATION MODE (For demonstration purposes) ---
        // If the developer manually sets the transaction to success in the dashboard, 
        // the next poll will pick it up at Step 2 above.
    }
    
    // ======== PHONEPE AUTO-VERIFICATION WORKFLOW ========
    else if ($gateway['name'] === 'PhonePe Business' && !empty($gateway['mid'])) {
        /*
        // https://api.phonepe.com/apis/hermes/pg/v1/status/{merchantId}/{transactionId}
        // Requires X-VERIFY header hash
        */
    }
}


function fireWebhooks($order_id, $utr, $amount) {
    global $pdo;
    $webhooks = $pdo->query("SELECT url FROM webhooks")->fetchAll(PDO::FETCH_COLUMN);
    if (empty($webhooks)) return;
    
    $payload = json_encode([
        'order_id' => $order_id,
        'utr' => $utr,
        'amount' => $amount,
        'status' => 'SUCCESS'
    ]);
    
    foreach ($webhooks as $url) {
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 3);
        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        $stmt = $pdo->prepare("INSERT INTO webhook_logs (order_id, webhook_url, payload, http_status) VALUES (?, ?, ?, ?)");
        $stmt->execute([$order_id, $url, $payload, $code]);
    }
}

jsonResponse(true, "Status fetched", $order);
