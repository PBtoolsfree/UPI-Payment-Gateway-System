<?php
// views/dashboard.php
requireAdmin();

$stats = [
    'today' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions_ledger WHERE DATE(created_at) = CURDATE() AND status='SUCCESS'")->fetchColumn(),
    'success' => $pdo->query("SELECT COUNT(*) FROM transactions_ledger WHERE status='SUCCESS'")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM transactions_ledger WHERE status='PENDING'")->fetchColumn()
];

$transactions = $pdo->query("SELECT * FROM transactions_ledger ORDER BY created_at DESC LIMIT 50")->fetchAll();
$gateways = $pdo->query("SELECT * FROM gateways")->fetchAll();
$tickets = $pdo->query("SELECT * FROM tickets ORDER BY created_at DESC")->fetchAll();

if (isset($_POST['action'])) {
    if ($_POST['action'] === 'approve_txn') {
        $stmt = $pdo->prepare("UPDATE transactions_ledger SET status='SUCCESS' WHERE order_id=?");
        $stmt->execute([$_POST['order_id']]);
    }
    if ($_POST['action'] === 'toggle_gateway') {
        $pdo->query("UPDATE gateways SET is_active=0");
        $stmt = $pdo->prepare("UPDATE gateways SET is_active=1 WHERE id=?");
        $stmt->execute([$_POST['gateway_id']]);
        $stmt = $pdo->prepare("UPDATE settings SET active_gateway_id=? WHERE id=1");
        $stmt->execute([$_POST['gateway_id']]);
    }
    header("Location: /");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My UPI Gateway</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-[#0B0F19] text-gray-300 font-sans min-h-screen" x-data="{ tab: 'dashboard' }">

    <nav class="bg-[#111827] border-b border-[#1E293B] sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center h-16">
            <div class="font-bold text-xl text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-indigo-400 to-purple-500">
                <?= htmlspecialchars($settings['merchant_name']) ?>
            </div>
            <div class="flex space-x-6 font-medium text-sm">
                <button @click="tab = 'dashboard'" :class="tab == 'dashboard' ? 'text-blue-400' : 'hover:text-white'">Dashboard</button>
                <button @click="tab = 'gateways'" :class="tab == 'gateways' ? 'text-blue-400' : 'hover:text-white'">Gateways</button>
                <button @click="tab = 'ledger'" :class="tab == 'ledger' ? 'text-blue-400' : 'hover:text-white'">Ledger</button>
                <button @click="tab = 'tickets'" :class="tab == 'tickets' ? 'text-blue-400' : 'hover:text-white'">Tickets</button>
                <a href="/?logout=1" class="text-red-400 hover:text-red-300">Logout</a>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- DASHBOARD TAB -->
        <div x-show="tab === 'dashboard'">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-[#111827] p-6 rounded-xl border border-[#1E293B]">
                    <div class="text-sm text-gray-500">Today's Collection</div>
                    <div class="text-3xl font-bold text-white mt-2">₹<?= number_format($stats['today'], 2) ?></div>
                </div>
                <div class="bg-[#111827] p-6 rounded-xl border border-[#1E293B]">
                    <div class="text-sm text-gray-500">Successful Txns</div>
                    <div class="text-3xl font-bold text-white mt-2"><?= $stats['success'] ?></div>
                </div>
                <div class="bg-[#111827] p-6 rounded-xl border border-[#1E293B]">
                    <div class="text-sm text-gray-500">Pending Txns</div>
                    <div class="text-3xl font-bold text-white mt-2"><?= $stats['pending'] ?></div>
                </div>
            </div>

            <div class="bg-[#111827] p-6 rounded-xl border border-[#1E293B]">
                <h3 class="text-lg font-bold text-white mb-4">Quick Link Generator</h3>
                <p class="text-sm mb-4">API Key: <span class="bg-gray-800 px-2 py-1 rounded text-green-400 select-all"><?= $settings['api_key'] ?></span></p>
                <form action="/api/create-order" method="POST" target="_blank" class="flex gap-4">
                    <input type="hidden" name="api_key" value="<?= $settings['api_key'] ?>">
                    <input type="number" name="amount" placeholder="Amount (₹)" class="bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white outline-none w-48" required>
                    <input type="text" name="customer_note" placeholder="Note (e.g. Test)" class="bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white outline-none flex-1">
                    <button type="submit" class="bg-blue-600 hover:bg-blue-700 text-white px-6 py-3 rounded-lg font-medium transition">Generate Link</button>
                </form>
            </div>
        </div>

        <!-- GATEWAYS TAB -->
        <div x-show="tab === 'gateways'" x-cloak>
            <h2 class="text-2xl font-bold text-white mb-6">My Gateways (10-in-1)</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach($gateways as $g): ?>
                <div class="bg-[#111827] p-6 rounded-xl border border-[#1E293B] flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="font-bold text-lg text-white"><?= htmlspecialchars($g['name']) ?></h3>
                        <?php if($g['is_active']): ?>
                            <span class="bg-green-500/20 text-green-400 text-xs px-2 py-1 rounded">Active</span>
                        <?php endif; ?>
                    </div>
                    <?php if($g['name'] === 'Personal UPI'): ?>
                        <div class="text-sm text-gray-400 mb-4">VPA: <?= htmlspecialchars($g['upi_id'] ?? 'Not set') ?></div>
                    <?php else: ?>
                        <div class="text-sm text-gray-400 mb-4">MID: <?= htmlspecialchars($g['mid'] ?? 'Not set') ?></div>
                    <?php endif; ?>
                    
                    <form method="POST" class="mt-auto">
                        <input type="hidden" name="action" value="toggle_gateway">
                        <input type="hidden" name="gateway_id" value="<?= $g['id'] ?>">
                        <?php if(!$g['is_active']): ?>
                        <button class="w-full bg-[#1E293B] hover:bg-gray-700 text-white py-2 rounded-lg transition text-sm">Activate</button>
                        <?php else: ?>
                        <button disabled class="w-full bg-blue-600/50 text-blue-200 py-2 rounded-lg text-sm cursor-not-allowed">Currently Active</button>
                        <?php endif; ?>
                    </form>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- LEDGER TAB -->
        <div x-show="tab === 'ledger'" x-cloak class="bg-[#111827] rounded-xl border border-[#1E293B] overflow-hidden">
            <div class="p-6 border-b border-[#1E293B] flex justify-between">
                <h2 class="text-xl font-bold text-white">Transaction Ledger</h2>
            </div>
            <div class="overflow-x-auto">
                <table class="w-full text-left text-sm whitespace-nowrap">
                    <thead class="bg-[#0B0F19] text-gray-400 border-b border-[#1E293B]">
                        <tr>
                            <th class="px-6 py-4">Order ID</th>
                            <th class="px-6 py-4">Amount</th>
                            <th class="px-6 py-4">Status</th>
                            <th class="px-6 py-4">UTR</th>
                            <th class="px-6 py-4">Date</th>
                            <th class="px-6 py-4">Action</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-[#1E293B]">
                        <?php foreach($transactions as $t): ?>
                        <tr class="hover:bg-[#1E293B]/30">
                            <td class="px-6 py-4 font-mono text-xs"><?= $t['order_id'] ?></td>
                            <td class="px-6 py-4 font-bold text-white">₹<?= $t['amount'] ?></td>
                            <td class="px-6 py-4">
                                <span class="<?= $t['status'] === 'SUCCESS' ? 'text-green-400' : ($t['status'] === 'FAILED' ? 'text-red-400' : 'text-yellow-400') ?>">
                                    <?= $t['status'] ?>
                                </span>
                            </td>
                            <td class="px-6 py-4 font-mono text-xs text-gray-500"><?= $t['utr_number'] ?? '-' ?></td>
                            <td class="px-6 py-4 text-gray-500"><?= date('M d, H:i', strtotime($t['created_at'])) ?></td>
                            <td class="px-6 py-4">
                                <?php if($t['status'] !== 'SUCCESS'): ?>
                                <form method="POST">
                                    <input type="hidden" name="action" value="approve_txn">
                                    <input type="hidden" name="order_id" value="<?= $t['order_id'] ?>">
                                    <button class="bg-green-600 hover:bg-green-500 text-white px-3 py-1 rounded text-xs">Approve</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- TICKETS TAB -->
        <div x-show="tab === 'tickets'" x-cloak class="bg-[#111827] rounded-xl border border-[#1E293B] overflow-hidden p-6">
            <h2 class="text-xl font-bold text-white mb-4">UTR Disputes & Tickets</h2>
            <div class="space-y-4">
                <?php foreach($tickets as $tk): ?>
                <div class="border border-[#1E293B] p-4 rounded-lg bg-[#0B0F19]">
                    <div class="flex justify-between">
                        <div>
                            <span class="text-blue-400 font-mono text-sm">Order: <?= $tk['order_id'] ?></span>
                            <span class="ml-4 text-gray-400 text-sm">UTR: <b class="text-white"><?= $tk['utr_number'] ?></b></span>
                        </div>
                        <span class="text-yellow-400 text-xs"><?= $tk['status'] ?></span>
                    </div>
                    <p class="mt-2 text-sm"><?= htmlspecialchars($tk['message']) ?></p>
                    <div class="mt-3 flex gap-2">
                        <form method="POST">
                            <input type="hidden" name="action" value="approve_txn">
                            <input type="hidden" name="order_id" value="<?= $tk['order_id'] ?>">
                            <button class="bg-blue-600 hover:bg-blue-500 px-3 py-1 rounded text-xs text-white">Approve Payment</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php if(empty($tickets)) echo "<p class='text-sm text-gray-500'>No tickets yet.</p>"; ?>
            </div>
        </div>

    </main>
</body>
</html>
