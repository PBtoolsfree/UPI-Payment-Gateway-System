<?php
// views/pay.php
$order_id = $_GET['order_id'] ?? '';
if (empty($order_id)) {
    // Permanent Payment Page Link mode (if order_id is empty, show amount entry form)
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['pay_amount'])) {
        $amount = $_POST['pay_amount'];
        $new_order = 'PAY_' . time() . rand(10,99);
        $stmt = $pdo->prepare("INSERT INTO transactions_ledger (order_id, amount, status) VALUES (?, ?, 'PENDING')");
        $stmt->execute([$new_order, $amount]);
        header("Location: /pay?order_id=$new_order");
        exit;
    }
    
    // Show open payment form
    echo "<!DOCTYPE html><html lang='en'><head><title>Pay Now</title><script src='https://cdn.tailwindcss.com'></script></head>
    <body class='bg-[#0B0F19] flex items-center justify-center min-h-screen p-4'>
        <div class='bg-[#111827] p-8 rounded-xl border border-[#1E293B] shadow-2xl w-full max-w-sm text-center'>
            <h2 class='text-2xl font-bold text-white mb-6'>Enter Amount to Pay</h2>
            <form method='POST'>
                <input type='number' name='pay_amount' placeholder='₹ Amount' class='w-full bg-[#0B0F19] text-white border border-[#1E293B] rounded-lg p-3 mb-4 focus:ring-2 focus:ring-blue-500 outline-none text-center text-xl font-bold' required>
                <button type='submit' class='w-full bg-blue-600 hover:bg-blue-700 text-white font-bold py-3 rounded-lg'>Proceed to Pay</button>
            </form>
        </div>
    </body></html>";
    exit;
}

// Fetch settings
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch();

// Fetch active gateway upi_id
$stmt = $pdo->prepare("SELECT upi_id FROM gateways WHERE id = ?");
$stmt->execute([$settings['active_gateway_id']]);
$vpa = $stmt->fetchColumn() ?: 'test@upi';

// Fetch order
$stmt = $pdo->prepare("SELECT * FROM transactions_ledger WHERE order_id = ? AND status = 'PENDING' LIMIT 1");
$stmt->execute([$order_id]);
$order = $stmt->fetch();

if (!$order) {
    // Let's check if it's already successful or failed to show status
    $stmt = $pdo->prepare("SELECT status FROM transactions_ledger WHERE order_id = ? LIMIT 1");
    $stmt->execute([$order_id]);
    $existing = $stmt->fetchColumn();
    if ($existing) {
        $status_color = $existing === 'SUCCESS' ? 'text-green-500' : 'text-red-500';
        echo "<!DOCTYPE html><html lang='en'><head><title>Payment Status</title><script src='https://cdn.tailwindcss.com'></script></head>
        <body class='bg-[#0B0F19] flex items-center justify-center min-h-screen p-4'>
            <div class='bg-[#111827] p-8 rounded-xl border border-[#1E293B] shadow-2xl text-center text-white'>
                <h2 class='text-3xl font-bold $status_color mb-2'>$existing</h2>
                <p class='text-gray-400'>Order ID: $order_id</p>
            </div>
        </body></html>";
        exit;
    }
    die("Order not found.");
}

$amount = $order['amount'];
$merchant_name = $settings['merchant_name'];
$theme_color = $settings['theme_color'];

// Submit ticket logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['utr_number'])) {
    $screenshot_url = null;
    if (isset($_FILES['screenshot']) && $_FILES['screenshot']['error'] === UPLOAD_ERR_OK) {
        $ext = pathinfo($_FILES['screenshot']['name'], PATHINFO_EXTENSION);
        $filename = 'proof_' . $order_id . '_' . time() . '.' . $ext;
        $dest = __DIR__ . '/../public/uploads/' . $filename;
        if (move_uploaded_file($_FILES['screenshot']['tmp_name'], $dest)) {
            $screenshot_url = '/uploads/' . $filename;
        }
    }

    $stmt = $pdo->prepare("INSERT INTO tickets (order_id, utr_number, payer_mobile, screenshot_url) VALUES (?, ?, ?, ?)");
    $stmt->execute([$order_id, $_POST['utr_number'], $_POST['mobile'] ?? '', $screenshot_url]);
    $pdo->prepare("UPDATE transactions_ledger SET status='UNDER_REVIEW' WHERE order_id=?")->execute([$order_id]);
    header("Location: /pay?order_id=$order_id&submitted=1");
    exit;
}

$upi_string = "upi://pay?pa={$vpa}&pn=" . urlencode($merchant_name) . "&am={$amount}&tr={$order_id}&tn=" . urlencode('Payment');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Secure Payment</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
    <style>
        .custom-theme-bg { background-color: <?= $theme_color ?>; }
        .custom-theme-text { color: <?= $theme_color ?>; }
        .custom-theme-border { border-color: <?= $theme_color ?>; }
    </style>
</head>
<body class="bg-[#0B0F19] flex items-center justify-center min-h-screen p-4 text-gray-300 font-sans">

    <div x-data="paymentGateway()" x-init="startTimer(); pollStatus();" class="bg-[#111827] w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-[#1E293B] relative">
        
        <div class="custom-theme-bg p-6 text-center text-white shadow-md">
            <?php if($settings['logo_url']): ?>
                <img src="<?= htmlspecialchars($settings['logo_url']) ?>" alt="Logo" class="h-12 mx-auto mb-2 object-contain">
            <?php else: ?>
                <h2 class="text-2xl font-extrabold tracking-wide"><?= htmlspecialchars($merchant_name) ?></h2>
            <?php endif; ?>
            <p class="text-white/80 text-sm mt-1 font-mono">ID: <?= htmlspecialchars($order_id) ?></p>
        </div>

        <div class="p-6">
            <?php if(isset($_GET['submitted'])): ?>
                <div class="bg-yellow-500/10 border border-yellow-500/50 text-yellow-400 p-4 rounded-xl text-center mb-4">
                    <p class="font-bold">UTR Submitted Successfully!</p>
                    <p class="text-sm mt-1">We are verifying it manually. Your payment will be updated soon.</p>
                </div>
            <?php else: ?>

            <div class="text-center mb-6">
                <p class="text-gray-500 text-sm font-medium tracking-widest uppercase mb-1">Amount to Pay</p>
                <div class="text-5xl font-extrabold text-white tracking-tight">₹<?= number_format($amount, 2) ?></div>
                <div class="mt-3 text-sm text-red-400 font-mono bg-red-500/10 inline-flex items-center px-3 py-1.5 rounded-full border border-red-500/20">
                    <svg class="w-4 h-4 mr-1.5 animate-pulse" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    Expires in: <span class="ml-1 font-bold" x-text="formatTime(timeLeft)"></span>
                </div>
            </div>

            <!-- SUCCESS STATE -->
            <template x-if="status === 'SUCCESS'">
                <div class="bg-green-500/10 border border-green-500/50 text-green-400 p-6 rounded-xl text-center mb-6">
                    <svg class="w-16 h-16 mx-auto mb-3 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="font-bold text-xl">Payment Successful!</p>
                    <p class="text-sm mt-2 opacity-80">Redirecting back...</p>
                </div>
            </template>
            
            <template x-if="status === 'FAILED'">
                <div class="bg-red-500/10 border border-red-500/50 text-red-400 p-6 rounded-xl text-center mb-6">
                    <svg class="w-16 h-16 mx-auto mb-3 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 14l2-2m0 0l2-2m-2 2l-2-2m2 2l2 2m7-2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    <p class="font-bold text-lg">Payment Expired</p>
                </div>
            </template>

            <!-- PENDING STATE -->
            <div x-show="status === 'PENDING' || status === 'UNDER_REVIEW'">
                
                <?php if($settings['show_qr']): ?>
                <div class="flex justify-center mb-6 bg-white p-3 rounded-2xl mx-auto w-52 h-52 items-center custom-theme-border border-4 shadow-lg">
                    <div id="qrcode"></div>
                </div>
                <?php endif; ?>

                <?php if($settings['show_intent_buttons']): ?>
                <div class="space-y-3">
                    <div class="flex items-center text-gray-500 my-4">
                        <hr class="flex-grow border-[#1E293B]">
                        <span class="px-3 text-xs uppercase tracking-wider font-semibold">Pay directly via</span>
                        <hr class="flex-grow border-[#1E293B]">
                    </div>
                    
                    <div class="grid grid-cols-3 gap-3">
                        <a href="phonepe://pay?pa=<?= $vpa ?>&pn=<?= urlencode($merchant_name) ?>&am=<?= $amount ?>&tr=<?= $order_id ?>" class="flex flex-col items-center justify-center bg-[#1E293B] hover:bg-gray-700 p-3 rounded-xl border border-gray-700 transition">
                            <span class="text-white font-semibold text-xs mt-1">PhonePe</span>
                        </a>
                        <a href="paytmmp://pay?pa=<?= $vpa ?>&pn=<?= urlencode($merchant_name) ?>&am=<?= $amount ?>&tr=<?= $order_id ?>" class="flex flex-col items-center justify-center bg-[#1E293B] hover:bg-gray-700 p-3 rounded-xl border border-gray-700 transition">
                            <span class="text-white font-semibold text-xs mt-1">Paytm</span>
                        </a>
                        <a href="tez://upi/pay?pa=<?= $vpa ?>&pn=<?= urlencode($merchant_name) ?>&am=<?= $amount ?>&tr=<?= $order_id ?>" class="flex flex-col items-center justify-center bg-[#1E293B] hover:bg-gray-700 p-3 rounded-xl border border-gray-700 transition">
                            <span class="text-white font-semibold text-xs mt-1">GPay</span>
                        </a>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- UTR FALLBACK -->
                <div x-data="{ expanded: false }" class="mt-6 border-t border-[#1E293B] pt-4">
                    <button @click="expanded = !expanded" class="w-full text-sm custom-theme-text font-medium pb-2 flex justify-between items-center px-1">
                        <span>Paid but not updated? Submit UTR</span>
                        <svg class="w-4 h-4 transform transition-transform" :class="expanded ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                    </button>
                    <div x-show="expanded" x-collapse class="pt-3">
                        <form method="POST" enctype="multipart/form-data" class="bg-[#0B0F19] p-4 rounded-xl border border-[#1E293B]">
                            <input type="text" name="utr_number" placeholder="Enter 12-digit UTR Number" class="w-full bg-[#111827] border border-[#1E293B] text-white text-sm rounded-lg p-3 mb-3 outline-none focus:border-blue-500" required pattern="\d{12}" title="Must be 12 digits">
                            
                            <input type="text" name="mobile" placeholder="Mobile Number (Optional)" class="w-full bg-[#111827] border border-[#1E293B] text-white text-sm rounded-lg p-3 mb-3 outline-none focus:border-blue-500">
                            
                            <label class="block text-xs text-gray-400 mb-1 ml-1">Upload Screenshot (Optional)</label>
                            <input type="file" name="screenshot" accept="image/*" class="w-full bg-[#111827] border border-[#1E293B] text-gray-400 text-sm rounded-lg p-2 mb-4 outline-none file:mr-4 file:py-1 file:px-3 file:rounded-md file:border-0 file:text-xs file:font-semibold file:bg-[#1E293B] file:text-white hover:file:bg-gray-600">
                            
                            <button type="submit" class="w-full custom-theme-bg text-white font-medium py-2.5 rounded-lg transition hover:brightness-110 shadow-lg">Submit Ticket</button>
                        </form>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
        
        <?php if($settings['show_powered_by']): ?>
        <div class="text-center pb-4 text-xs text-gray-600 font-medium">
            Powered by <span class="custom-theme-text font-bold">10-in-1 UPI Gateway</span>
        </div>
        <?php endif; ?>
    </div>

    <script>
        <?php if($settings['show_qr']): ?>
        new QRCode(document.getElementById("qrcode"), {
            text: "<?= $upi_string ?>",
            width: 184,
            height: 184,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });
        <?php endif; ?>

        function paymentGateway() {
            return {
                status: '<?= $order['status'] ?>',
                timeLeft: 600, // 10 minutes
                timerInterval: null,
                pollInterval: null,

                startTimer() {
                    this.timerInterval = setInterval(() => {
                        if(this.timeLeft > 0) this.timeLeft--;
                        else {
                            clearInterval(this.timerInterval);
                            this.status = 'FAILED';
                        }
                    }, 1000);
                },

                formatTime(seconds) {
                    const m = Math.floor(seconds / 60).toString().padStart(2, '0');
                    const s = (seconds % 60).toString().padStart(2, '0');
                    return `${m}:${s}`;
                },

                async pollStatus() {
                    this.pollInterval = setInterval(async () => {
                        if (this.status !== 'PENDING') {
                            clearInterval(this.pollInterval);
                            return;
                        }
                        try {
                            const res = await fetch(`/api/status?order_id=<?= $order_id ?>`);
                            const data = await res.json();
                            if (data.status && data.data.status !== 'PENDING') {
                                this.status = data.data.status;
                                clearInterval(this.pollInterval);
                                clearInterval(this.timerInterval);
                                if(this.status === 'SUCCESS' && data.data.redirect_url) {
                                    setTimeout(() => window.location.href = data.data.redirect_url, 2000);
                                }
                            }
                        } catch (e) {}
                    }, 3000);
                }
            }
        }
    </script>
</body>
</html>
