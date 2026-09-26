<?php
// views/pay.php
$order_id = $_GET['order_id'] ?? '';
if (empty($order_id)) die("Invalid Order ID");

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

if (!$order) die("Order not found or already processed.");

$amount = $order['amount'];
$merchant_name = $settings['merchant_name'];

// Submit ticket logic
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['utr_number'])) {
    $stmt = $pdo->prepare("INSERT INTO tickets (order_id, utr_number, message) VALUES (?, ?, ?)");
    $stmt->execute([$order_id, $_POST['utr_number'], $_POST['message'] ?? '']);
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
</head>
<body class="bg-[#0B0F19] flex items-center justify-center min-h-screen p-4 text-gray-300 font-sans">

    <div x-data="paymentGateway()" x-init="startTimer(); pollStatus();" class="bg-[#111827] w-full max-w-md rounded-2xl shadow-2xl overflow-hidden border border-[#1E293B]">
        
        <div class="bg-gradient-to-r from-blue-600 to-indigo-600 p-6 text-center text-white relative">
            <h2 class="text-xl font-bold tracking-wide"><?= htmlspecialchars($merchant_name) ?></h2>
            <p class="text-blue-100 text-sm mt-1 opacity-80 font-mono">ID: <?= htmlspecialchars($order_id) ?></p>
        </div>

        <div class="p-6">
            <?php if(isset($_GET['submitted'])): ?>
                <div class="bg-yellow-500/10 border border-yellow-500/50 text-yellow-400 p-4 rounded-xl text-center mb-6">
                    <p class="font-bold">UTR Submitted!</p>
                    <p class="text-sm mt-1">We are verifying it manually. Please wait.</p>
                </div>
            <?php else: ?>

            <div class="text-center mb-6">
                <p class="text-gray-500 text-sm font-medium tracking-widest uppercase mb-1">Amount to Pay</p>
                <div class="text-5xl font-extrabold text-white tracking-tight">₹<?= number_format($amount, 2) ?></div>
                <div class="mt-2 text-sm text-red-400 font-mono bg-red-500/10 inline-block px-3 py-1 rounded">
                    Expires in: <span x-text="formatTime(timeLeft)"></span>
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
                    <p class="font-bold text-lg">Payment Expired</p>
                </div>
            </template>

            <!-- PENDING STATE -->
            <div x-show="status === 'PENDING' || status === 'UNDER_REVIEW'">
                <div class="flex justify-center mb-6 bg-white p-3 rounded-xl mx-auto w-48 h-48 items-center border-4 border-indigo-500/30">
                    <div id="qrcode"></div>
                </div>

                <div class="space-y-3">
                    <div class="flex items-center text-gray-500 my-4">
                        <hr class="flex-grow border-[#1E293B]">
                        <span class="px-3 text-xs uppercase tracking-wider font-semibold">Pay directly via</span>
                        <hr class="flex-grow border-[#1E293B]">
                    </div>
                    <a href="<?= $upi_string ?>" class="w-full flex items-center justify-center bg-[#1E293B] hover:bg-gray-700 text-white font-semibold py-3 px-4 rounded-xl transition border border-gray-700">
                        PhonePe / GPay / Paytm
                    </a>
                </div>
                
                <!-- UTR FALLBACK -->
                <div x-data="{ expanded: false }" class="mt-6 border-t border-[#1E293B] pt-4">
                    <button @click="expanded = !expanded" class="w-full text-sm text-blue-400 hover:text-blue-300 font-medium pb-2">
                        Having issues? Submit UTR manually
                    </button>
                    <div x-show="expanded" x-collapse class="pt-2">
                        <form method="POST">
                            <input type="text" name="utr_number" placeholder="Enter 12-digit UTR" class="w-full bg-[#0B0F19] border border-[#1E293B] text-white text-sm rounded-lg p-3 mb-2 outline-none focus:border-blue-500" required pattern="\d{12}">
                            <input type="text" name="message" placeholder="Optional note" class="w-full bg-[#0B0F19] border border-[#1E293B] text-white text-sm rounded-lg p-3 mb-3 outline-none focus:border-blue-500">
                            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white font-medium py-2 rounded-lg transition">Submit Proof</button>
                        </form>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <script>
        new QRCode(document.getElementById("qrcode"), {
            text: "<?= $upi_string ?>",
            width: 160,
            height: 160,
            colorDark : "#000000",
            colorLight : "#ffffff",
            correctLevel : QRCode.CorrectLevel.H
        });

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
