<?php
// views/checkout.php
// Requires: config.php

$is_demo = isset($_GET['demo']);
$order_id = $_GET['order_id'] ?? '';

if (!$is_demo && empty($order_id)) {
    die("Invalid Order ID");
}

$merchant = null;
$amount = 10.00; // default for demo
$vpa = 'demo@upi';
$merchant_name = 'Demo Merchant';
$theme_color = '#3b82f6';
$show_qr = true;
$show_intent = true;
$show_footer = true;
$logo = '';

if (!$is_demo) {
    // Fetch order and merchant details
    $stmt = $pdo->prepare("
        SELECT t.*, m.upi_id, m.theme_color, m.logo_url, m.show_qr, m.show_intent_buttons, m.show_powered_by, u.name as merchant_name 
        FROM transactions_ledger t 
        JOIN merchants m ON t.merchant_id = m.id 
        JOIN users u ON m.user_id = u.id
        WHERE t.order_id = ? AND t.status = 'PENDING' LIMIT 1
    ");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        die("Order not found or already processed.");
    }
    
    $amount = $order['amount'];
    $vpa = $order['upi_id'] ?? 'default@upi';
    $merchant_name = $order['merchant_name'];
    $theme_color = $order['theme_color'] ?? '#3b82f6';
    $show_qr = $order['show_qr'];
    $show_intent = $order['show_intent_buttons'];
    $show_footer = $order['show_powered_by'];
    $logo = $order['logo_url'];
} else {
    $order_id = 'DEMO_' . time();
}

// Generate standard NPCI UPI String
$upi_string = "upi://pay?pa={$vpa}&pn=" . urlencode($merchant_name) . "&am={$amount}&tr={$order_id}&tn=" . urlencode('Payment for ' . $order_id);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout - <?= htmlspecialchars($merchant_name) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <!-- Alpine.js for reactivity -->
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <style>
        :root { --theme-color: <?= htmlspecialchars($theme_color) ?>; }
        .theme-bg { background-color: var(--theme-color); }
        .theme-text { color: var(--theme-color); }
        body { font-family: 'Inter', sans-serif; background-color: #f3f4f6; }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center p-4">
    
    <div x-data="checkoutData()" x-init="initPolling()" class="bg-white rounded-2xl shadow-xl w-full max-w-md overflow-hidden border border-gray-100">
        
        <!-- Header -->
        <div class="theme-bg p-6 text-center text-white relative">
            <?php if($logo): ?>
                <img src="<?= htmlspecialchars($logo) ?>" alt="Logo" class="h-12 w-auto mx-auto mb-3 bg-white rounded p-1">
            <?php else: ?>
                <div class="w-16 h-16 bg-white bg-opacity-20 rounded-full flex items-center justify-center mx-auto mb-3 text-2xl font-bold">
                    <?= substr($merchant_name, 0, 1) ?>
                </div>
            <?php endif; ?>
            <h2 class="text-xl font-semibold"><?= htmlspecialchars($merchant_name) ?></h2>
            <p class="text-white text-opacity-80 text-sm mt-1">Order ID: <?= htmlspecialchars($order_id) ?></p>
        </div>

        <!-- Body -->
        <div class="p-6">
            <div class="text-center mb-6">
                <p class="text-gray-500 text-sm mb-1">Amount to Pay</p>
                <div class="text-4xl font-extrabold text-gray-800">₹<?= number_format($amount, 2) ?></div>
            </div>

            <!-- Status Indicator -->
            <template x-if="status === 'SUCCESS'">
                <div class="bg-green-50 border border-green-200 text-green-700 p-4 rounded-xl text-center mb-6">
                    <svg class="w-12 h-12 mx-auto mb-2 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="font-bold text-lg">Payment Successful!</p>
                    <p class="text-sm mt-1">Redirecting...</p>
                </div>
            </template>
            
            <template x-if="status === 'FAILED'">
                <div class="bg-red-50 border border-red-200 text-red-700 p-4 rounded-xl text-center mb-6">
                    <p class="font-bold">Payment Failed or Expired.</p>
                </div>
            </template>

            <div x-show="status === 'PENDING'">
                <?php if($show_qr): ?>
                    <div class="flex justify-center mb-6 bg-gray-50 p-4 rounded-xl border border-gray-100">
                        <div id="qrcode" class="p-2 bg-white rounded-lg shadow-sm"></div>
                    </div>
                    <p class="text-center text-sm text-gray-500 mb-6">Scan QR with any UPI App</p>
                <?php endif; ?>

                <?php if($show_intent): ?>
                    <div class="space-y-3">
                        <div class="flex items-center text-gray-400 my-4">
                            <hr class="flex-grow border-gray-200">
                            <span class="px-3 text-xs uppercase tracking-wider font-semibold">Or Pay Via</span>
                            <hr class="flex-grow border-gray-200">
                        </div>
                        <a href="<?= $upi_string ?>" class="w-full flex items-center justify-center bg-indigo-600 hover:bg-indigo-700 text-white font-medium py-3 px-4 rounded-xl transition shadow-sm">
                            PhonePe / GPay / Paytm
                        </a>
                    </div>
                <?php endif; ?>
                
                <!-- Manual UTR Entry Fallback -->
                <div class="mt-8 border-t border-gray-100 pt-6">
                    <p class="text-xs text-gray-500 mb-2 text-center">Payment done but not updating?</p>
                    <div class="flex">
                        <input type="text" x-model="utrInput" placeholder="Enter 12-digit UTR No." class="flex-grow bg-gray-50 border border-gray-200 text-gray-700 text-sm rounded-l-lg focus:ring-blue-500 focus:border-blue-500 block p-2.5 outline-none">
                        <button @click="submitUtr" class="theme-bg text-white px-4 rounded-r-lg text-sm font-medium hover:opacity-90 transition">Submit</button>
                    </div>
                </div>
            </div>
        </div>

        <?php if($show_footer): ?>
        <div class="bg-gray-50 p-3 text-center text-xs text-gray-400 border-t border-gray-100">
            Powered by <span class="font-semibold text-gray-600">UPI Gateway</span>
        </div>
        <?php endif; ?>
    </div>

    <script>
        // Generate QR
        <?php if($show_qr): ?>
        new QRCode(document.getElementById("qrcode"), {
            text: "<?= $upi_string ?>",
            width: 200,
            height: 200,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
        <?php endif; ?>

        function checkoutData() {
            return {
                status: 'PENDING',
                orderId: '<?= htmlspecialchars($order_id) ?>',
                isDemo: <?= $is_demo ? 'true' : 'false' ?>,
                utrInput: '',
                pollInterval: null,

                initPolling() {
                    if(this.isDemo) return; // Don't poll for demo
                    
                    this.pollInterval = setInterval(() => {
                        this.checkStatus();
                    }, 3000); // Poll every 3 seconds
                },

                async checkStatus() {
                    if (this.status !== 'PENDING') {
                        clearInterval(this.pollInterval);
                        return;
                    }
                    try {
                        const res = await fetch(`/api/v1/check-status?order_id=${this.orderId}`);
                        const data = await res.json();
                        
                        if (data.status && data.data.status !== 'PENDING') {
                            this.status = data.data.status;
                            clearInterval(this.pollInterval);
                            
                            if(this.status === 'SUCCESS' && data.data.redirect_url) {
                                setTimeout(() => {
                                    window.location.href = data.data.redirect_url;
                                }, 2000);
                            }
                        }
                    } catch (e) {
                        console.error('Polling error', e);
                    }
                },
                
                async submitUtr() {
                    if(this.utrInput.length !== 12) {
                        alert("Please enter a valid 12-digit UTR number.");
                        return;
                    }
                    alert("UTR Submitted! Waiting for merchant approval.");
                    // In a real app, send POST request to /api/v1/submit-utr
                }
            }
        }
    </script>
</body>
</html>
