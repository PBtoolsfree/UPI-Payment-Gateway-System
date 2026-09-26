<?php
// views/landing.php
$settings = getWebsiteSettings($pdo);

// Fetch active plans
$plans = $pdo->query("SELECT * FROM plans ORDER BY price ASC")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($settings['site_title'] ?? 'UPI Gateway SaaS') ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Inter', sans-serif; background: #0f172a; color: #f8fafc; }
        .glass-panel { background: rgba(30, 41, 59, 0.7); backdrop-filter: blur(10px); border: 1px solid rgba(255,255,255,0.1); }
        .gradient-text { background: linear-gradient(to right, #3b82f6, #8b5cf6); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
    </style>
</head>
<body class="min-h-screen flex flex-col">
    <!-- Navbar -->
    <nav class="glass-panel fixed w-full z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex justify-between items-center h-16">
                <div class="flex-shrink-0 flex items-center">
                    <?php if(!empty($settings['site_logo'])): ?>
                        <img class="h-8 w-auto" src="<?= htmlspecialchars($settings['site_logo']) ?>" alt="Logo">
                    <?php else: ?>
                        <span class="font-bold text-xl tracking-wider text-white"><?= htmlspecialchars($settings['site_title']) ?></span>
                    <?php endif; ?>
                </div>
                <div class="hidden md:flex items-center space-x-4">
                    <a href="#features" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Features</a>
                    <a href="#pricing" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Pricing</a>
                    <a href="/login" class="text-gray-300 hover:text-white px-3 py-2 rounded-md text-sm font-medium">Login</a>
                    <a href="/register" class="bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-md text-sm font-medium transition">Get Started</a>
                </div>
            </div>
        </div>
    </nav>

    <!-- Hero Section -->
    <main class="flex-grow pt-24 pb-12 flex flex-col items-center justify-center text-center px-4">
        <h1 class="text-5xl md:text-7xl font-extrabold mb-6">
            The Ultimate <br> <span class="gradient-text">10-in-1 UPI Gateway</span>
        </h1>
        <p class="text-xl text-gray-400 max-w-2xl mb-10">
            Accept payments seamlessly via Paytm, PhonePe, GPay, BharatPe, and more. Zero coding required. Multi-webhook engine & Instant Settlement.
        </p>
        <div class="flex space-x-4">
            <a href="/register" class="bg-blue-600 hover:bg-blue-700 text-white px-8 py-4 rounded-full text-lg font-bold shadow-lg transition transform hover:scale-105">Start Free Trial</a>
            <a href="/checkout?demo=true" class="glass-panel text-white px-8 py-4 rounded-full text-lg font-bold hover:bg-gray-800 transition">View Demo</a>
        </div>
    </main>

    <!-- Pricing Section -->
    <section id="pricing" class="py-20 bg-slate-900">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="text-center mb-16">
                <h2 class="text-3xl md:text-4xl font-bold">Simple, Transparent Pricing</h2>
                <p class="text-gray-400 mt-4">Choose the plan that fits your business.</p>
            </div>
            <div class="grid grid-cols-1 md:grid-cols-3 gap-8">
                <?php foreach($plans as $plan): ?>
                <div class="glass-panel rounded-2xl p-8 flex flex-col items-center text-center hover:border-blue-500 transition duration-300">
                    <h3 class="text-2xl font-semibold mb-2"><?= htmlspecialchars($plan['name']) ?></h3>
                    <div class="text-4xl font-bold mb-6">₹<?= number_format($plan['price']) ?><span class="text-lg text-gray-400 font-normal">/<?= $plan['duration_days'] ?>d</span></div>
                    <ul class="text-gray-400 mb-8 space-y-3">
                        <li><?= $plan['transaction_fee_percent'] > 0 ? $plan['transaction_fee_percent'] . '% Txn Fee' : '0% Transaction Fee' ?></li>
                        <li><?= $plan['transaction_limit'] ? number_format($plan['transaction_limit']) . ' Transactions' : 'Unlimited Transactions' ?></li>
                        <li>All UPI Apps Supported</li>
                        <li>API & Webhook Access</li>
                    </ul>
                    <a href="/register?plan=<?= $plan['id'] ?>" class="w-full bg-slate-700 hover:bg-blue-600 text-white py-3 rounded-lg font-medium transition mt-auto">Choose Plan</a>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="glass-panel border-t-0 mt-auto py-12">
        <div class="max-w-7xl mx-auto px-4 text-center text-gray-400">
            <p class="mb-4">Contact us: <?= htmlspecialchars($settings['support_email'] ?? 'support@example.com') ?> | <?= htmlspecialchars($settings['support_phone'] ?? '') ?></p>
            <p class="mb-4"><?= htmlspecialchars($settings['office_address'] ?? '') ?></p>
            <p>&copy; <?= date('Y') ?> <?= htmlspecialchars($settings['site_title']) ?>. All rights reserved.</p>
        </div>
    </footer>
</body>
</html>
