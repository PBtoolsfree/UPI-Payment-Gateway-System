<?php
// views/dashboard.php
requireAdmin();

// Handle Form Submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];

    if ($action === 'update_customizer') {
        $stmt = $pdo->prepare("UPDATE settings SET theme_color=?, logo_url=?, show_qr=?, show_intent_buttons=?, show_powered_by=? WHERE id=1");
        $stmt->execute([
            $_POST['theme_color'],
            $_POST['logo_url'],
            isset($_POST['show_qr']) ? 1 : 0,
            isset($_POST['show_intent_buttons']) ? 1 : 0,
            isset($_POST['show_powered_by']) ? 1 : 0
        ]);
    }
    
    if ($action === 'add_ledger_entry') {
        $stmt = $pdo->prepare("INSERT INTO transactions_ledger (order_id, gateway_name, amount, utr_number, note, status, is_manual) VALUES (?, ?, ?, ?, ?, ?, 1)");
        $stmt->execute([
            'MAN_' . time(),
            'Manual Entry',
            $_POST['amount'],
            $_POST['utr_number'],
            $_POST['note'],
            $_POST['status']
        ]);
    }
    
    if ($action === 'approve_txn') {
        $stmt = $pdo->prepare("UPDATE transactions_ledger SET status='SUCCESS', utr_number=COALESCE(utr_number, ?) WHERE order_id=?");
        $stmt->execute([$_POST['utr_number'] ?? null, $_POST['order_id']]);
        $pdo->prepare("UPDATE tickets SET status='RESOLVED' WHERE order_id=?")->execute([$_POST['order_id']]);
        // Note: Webhook triggering logic would be here
    }

    if ($action === 'fail_txn') {
        $stmt = $pdo->prepare("UPDATE transactions_ledger SET status='FAILED' WHERE order_id=?");
        $stmt->execute([$_POST['order_id']]);
        $pdo->prepare("UPDATE tickets SET status='REJECTED' WHERE order_id=?")->execute([$_POST['order_id']]);
    }

    if ($action === 'set_active_gateway') {
        $pdo->query("UPDATE gateways SET is_active=0");
        $stmt = $pdo->prepare("UPDATE gateways SET is_active=1 WHERE id=?");
        $stmt->execute([$_POST['gateway_id']]);
        $stmt = $pdo->prepare("UPDATE settings SET active_gateway_id=? WHERE id=1");
        $stmt->execute([$_POST['gateway_id']]);
    }
    
    if ($action === 'manual_verify_gateway') {
        $stmt = $pdo->prepare("UPDATE gateways SET mid=?, upi_id=?, api_token=?, session_cookie=? WHERE id=?");
        $stmt->execute([
            $_POST['mid'] ?? null,
            $_POST['upi_id'] ?? null,
            $_POST['api_token'] ?? null,
            $_POST['session_cookie'] ?? null,
            $_POST['gateway_id']
        ]);
    }

    if ($action === 'add_webhook') {
        $stmt = $pdo->prepare("INSERT INTO webhooks (url) VALUES (?)");
        $stmt->execute([$_POST['url']]);
    }
    
    if ($action === 'delete_webhook') {
        $stmt = $pdo->prepare("DELETE FROM webhooks WHERE id=?");
        $stmt->execute([$_POST['id']]);
    }

    if ($action === 'update_api_settings') {
        $stmt = $pdo->prepare("UPDATE settings SET api_key=?, api_secret=?, ip_whitelist=? WHERE id=1");
        $stmt->execute([$_POST['api_key'], $_POST['api_secret'], $_POST['ip_whitelist']]);
    }

    header("Location: /");
    exit;
}

// Export CSV Logic
if (isset($_GET['export_csv'])) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=ledger_export.csv');
    $output = fopen('php://output', 'w');
    fputcsv($output, ['Order ID', 'Date', 'Gateway', 'Amount', 'UTR', 'Payer VPA', 'Status', 'Note']);
    $rows = $pdo->query("SELECT order_id, created_at, gateway_name, amount, utr_number, payer_vpa, status, note FROM transactions_ledger ORDER BY created_at DESC")->fetchAll();
    foreach ($rows as $row) fputcsv($output, $row);
    exit;
}

// Fetch Data
$stats = [
    'today' => $pdo->query("SELECT COALESCE(SUM(amount),0) FROM transactions_ledger WHERE DATE(created_at) = CURDATE() AND status='SUCCESS'")->fetchColumn(),
    'success' => $pdo->query("SELECT COUNT(*) FROM transactions_ledger WHERE status='SUCCESS'")->fetchColumn(),
    'pending' => $pdo->query("SELECT COUNT(*) FROM transactions_ledger WHERE status='PENDING'")->fetchColumn()
];

// Ledger Filters
$where = "1=1";
$params = [];
if (!empty($_GET['status'])) {
    $where .= " AND status = ?";
    $params[] = $_GET['status'];
}
if (!empty($_GET['date'])) {
    $where .= " AND DATE(created_at) = ?";
    $params[] = $_GET['date'];
}

$stmt = $pdo->prepare("SELECT * FROM transactions_ledger WHERE $where ORDER BY created_at DESC LIMIT 100");
$stmt->execute($params);
$transactions = $stmt->fetchAll();

$gateways = $pdo->query("SELECT * FROM gateways")->fetchAll();
$tickets = $pdo->query("SELECT * FROM tickets ORDER BY created_at DESC")->fetchAll();
$webhooks = $pdo->query("SELECT * FROM webhooks")->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My UPI Gateway - Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
</head>
<body class="bg-[#0B0F19] text-gray-300 font-sans min-h-screen" x-data="{ tab: 'dashboard', modalOpen: false, currentGateway: null }">

    <!-- Navbar (No Logout) -->
    <nav class="bg-[#111827] border-b border-[#1E293B] sticky top-0 z-50">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center h-16">
            <div class="font-bold text-xl text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-indigo-400 to-purple-500">
                <?= htmlspecialchars($settings['merchant_name']) ?>
            </div>
            <div class="flex space-x-6 font-medium text-sm overflow-x-auto">
                <button @click="tab = 'dashboard'" :class="tab == 'dashboard' ? 'text-blue-400' : 'hover:text-white'">Dashboard</button>
                <button @click="tab = 'gateways'" :class="tab == 'gateways' ? 'text-blue-400' : 'hover:text-white'">My Gateways</button>
                <button @click="tab = 'ledger'" :class="tab == 'ledger' ? 'text-blue-400' : 'hover:text-white'">Ledger Entries</button>
                <button @click="tab = 'tickets'" :class="tab == 'tickets' ? 'text-blue-400' : 'hover:text-white'">UTR Tickets</button>
                <button @click="tab = 'api'" :class="tab == 'api' ? 'text-blue-400' : 'hover:text-white'">API & Webhooks</button>
            </div>
        </div>
    </nav>

    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-8">
        
        <!-- DASHBOARD TAB -->
        <div x-show="tab === 'dashboard'">
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
                <div class="bg-[#111827] p-6 rounded-xl border border-[#1E293B] shadow-sm">
                    <div class="text-sm text-gray-500">Today's Collection</div>
                    <div class="text-3xl font-bold text-white mt-2">₹<?= number_format($stats['today'], 2) ?></div>
                </div>
                <div class="bg-[#111827] p-6 rounded-xl border border-[#1E293B] shadow-sm">
                    <div class="text-sm text-gray-500">Successful Txns</div>
                    <div class="text-3xl font-bold text-white mt-2"><?= $stats['success'] ?></div>
                </div>
                <div class="bg-[#111827] p-6 rounded-xl border border-[#1E293B] shadow-sm">
                    <div class="text-sm text-gray-500">Pending Entries</div>
                    <div class="text-3xl font-bold text-white mt-2"><?= $stats['pending'] ?></div>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Links Generator -->
                <div class="bg-[#111827] p-6 rounded-xl border border-[#1E293B]">
                    <h3 class="text-lg font-bold text-white mb-4">Payment Link Generator</h3>
                    <form action="/api/create-order" method="POST" target="_blank" class="space-y-4">
                        <input type="hidden" name="api_key" value="<?= $settings['api_key'] ?>">
                        <div>
                            <label class="text-xs text-gray-500 uppercase">Amount</label>
                            <input type="number" name="amount" placeholder="Amount (e.g. 10.00)" class="w-full bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white outline-none" required>
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase">Order Note</label>
                            <input type="text" name="customer_note" placeholder="Order Note" class="w-full bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white outline-none">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 uppercase">Redirect URL (Optional)</label>
                            <input type="url" name="redirect_url" placeholder="https://" class="w-full bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white outline-none">
                        </div>
                        <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-medium transition">Generate Instant Link</button>
                    </form>

                    <div class="mt-6 pt-6 border-t border-[#1E293B]">
                        <h4 class="text-sm font-bold text-white mb-2">Permanent Payment Page (Open Amount)</h4>
                        <a href="/pay" target="_blank" class="block w-full text-center bg-gray-800 hover:bg-gray-700 text-white py-3 rounded-lg font-medium transition">Open Permanent Link</a>
                    </div>
                </div>

                <!-- Customizer -->
                <div class="bg-[#111827] p-6 rounded-xl border border-[#1E293B]">
                    <h3 class="text-lg font-bold text-white mb-4">Checkout Page Customizer</h3>
                    <form method="POST" class="space-y-4">
                        <input type="hidden" name="action" value="update_customizer">
                        
                        <div>
                            <label class="text-xs text-gray-500 uppercase block mb-1">Theme Color</label>
                            <input type="color" name="theme_color" value="<?= $settings['theme_color'] ?>" class="h-10 w-full rounded cursor-pointer border border-[#1E293B] bg-[#0B0F19]">
                        </div>
                        
                        <div>
                            <label class="text-xs text-gray-500 uppercase block mb-1">Logo URL</label>
                            <input type="url" name="logo_url" value="<?= htmlspecialchars($settings['logo_url'] ?? '') ?>" placeholder="https://..." class="w-full bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white outline-none">
                        </div>

                        <div class="flex items-center justify-between p-3 bg-[#0B0F19] rounded-lg border border-[#1E293B]">
                            <span class="text-sm">Show QR Code</span>
                            <input type="checkbox" name="show_qr" <?= $settings['show_qr'] ? 'checked' : '' ?> class="w-5 h-5 accent-blue-600">
                        </div>

                        <div class="flex items-center justify-between p-3 bg-[#0B0F19] rounded-lg border border-[#1E293B]">
                            <span class="text-sm">Show Intent Deep-Links</span>
                            <input type="checkbox" name="show_intent_buttons" <?= $settings['show_intent_buttons'] ? 'checked' : '' ?> class="w-5 h-5 accent-blue-600">
                        </div>

                        <div class="flex items-center justify-between p-3 bg-[#0B0F19] rounded-lg border border-[#1E293B]">
                            <span class="text-sm">Show "Powered By" Footer</span>
                            <input type="checkbox" name="show_powered_by" <?= $settings['show_powered_by'] ? 'checked' : '' ?> class="w-5 h-5 accent-blue-600">
                        </div>

                        <button type="submit" class="w-full bg-indigo-600 hover:bg-indigo-700 text-white py-3 rounded-lg font-medium transition">Save Appearance</button>
                    </form>
                </div>
            </div>
        </div>

        <!-- GATEWAYS TAB (10-in-1) -->
        <div x-show="tab === 'gateways'" x-cloak>
            <h2 class="text-2xl font-bold text-white mb-6">10-in-1 Connect Merchant & Verification System</h2>
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                <?php foreach($gateways as $g): ?>
                <div class="bg-[#111827] p-6 rounded-xl border <?= $g['is_active'] ? 'border-blue-500 shadow-[0_0_15px_rgba(37,99,235,0.2)]' : 'border-[#1E293B]' ?> flex flex-col">
                    <div class="flex justify-between items-start mb-4">
                        <h3 class="font-bold text-lg text-white"><?= htmlspecialchars($g['name']) ?></h3>
                        <?php if($g['is_active']): ?>
                            <span class="bg-blue-600 text-white text-xs px-2 py-1 rounded shadow">DEFAULT ACTIVE</span>
                        <?php endif; ?>
                    </div>
                    
                    <div class="text-sm text-gray-400 mb-2">VPA: <span class="text-gray-200"><?= htmlspecialchars($g['upi_id'] ?? 'Not set') ?></span></div>
                    <div class="text-sm text-gray-400 mb-6">MID: <span class="text-gray-200"><?= htmlspecialchars($g['mid'] ?? 'Not set') ?></span></div>
                    
                    <div class="mt-auto space-y-2">
                        <button @click="modalOpen = true; currentGateway = <?= htmlspecialchars(json_encode($g)) ?>;" class="w-full bg-[#1E293B] hover:bg-gray-700 text-white py-2 rounded-lg transition text-sm font-medium border border-gray-600">
                            Connect / Manual Verify
                        </button>
                        
                        <?php if(!$g['is_active'] && !empty($g['upi_id'])): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="set_active_gateway">
                            <input type="hidden" name="gateway_id" value="<?= $g['id'] ?>">
                            <button class="w-full bg-blue-600 hover:bg-blue-700 text-white py-2 rounded-lg transition text-sm font-medium">Set as Default Active</button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- LEDGER TAB -->
        <div x-show="tab === 'ledger'" x-cloak>
            <div class="bg-[#111827] rounded-xl border border-[#1E293B] overflow-hidden">
                <div class="p-6 border-b border-[#1E293B] flex flex-wrap justify-between items-center gap-4">
                    <h2 class="text-xl font-bold text-white">Complete Ledger Entries</h2>
                    <div class="flex gap-2">
                        <form method="GET" class="flex gap-2">
                            <input type="hidden" name="tab" value="ledger">
                            <input type="date" name="date" value="<?= $_GET['date'] ?? '' ?>" class="bg-[#0B0F19] border border-[#1E293B] rounded-lg px-3 py-1.5 text-sm text-white outline-none">
                            <select name="status" class="bg-[#0B0F19] border border-[#1E293B] rounded-lg px-3 py-1.5 text-sm text-white outline-none">
                                <option value="">All Statuses</option>
                                <option value="SUCCESS" <?= ($_GET['status']??'')=='SUCCESS'?'selected':'' ?>>SUCCESS</option>
                                <option value="PENDING" <?= ($_GET['status']??'')=='PENDING'?'selected':'' ?>>PENDING</option>
                                <option value="FAILED" <?= ($_GET['status']??'')=='FAILED'?'selected':'' ?>>FAILED</option>
                            </select>
                            <button type="submit" class="bg-gray-700 hover:bg-gray-600 px-4 py-1.5 rounded-lg text-sm transition">Filter</button>
                        </form>
                        <a href="/?export_csv=1" class="bg-green-600 hover:bg-green-700 text-white px-4 py-1.5 rounded-lg text-sm transition font-medium">Export CSV</a>
                    </div>
                </div>

                <!-- Add Manual Entry -->
                <div class="p-4 bg-[#1E293B]/30 border-b border-[#1E293B]">
                    <form method="POST" class="flex flex-wrap gap-3 items-end">
                        <input type="hidden" name="action" value="add_ledger_entry">
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">Amount</label>
                            <input type="number" name="amount" required class="w-24 bg-[#0B0F19] border border-[#1E293B] rounded-md px-2 py-1 text-sm text-white">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">UTR (Optional)</label>
                            <input type="text" name="utr_number" class="w-40 bg-[#0B0F19] border border-[#1E293B] rounded-md px-2 py-1 text-sm text-white">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">Note</label>
                            <input type="text" name="note" class="w-48 bg-[#0B0F19] border border-[#1E293B] rounded-md px-2 py-1 text-sm text-white">
                        </div>
                        <div>
                            <label class="text-xs text-gray-500 mb-1 block">Status</label>
                            <select name="status" class="w-28 bg-[#0B0F19] border border-[#1E293B] rounded-md px-2 py-1 text-sm text-white">
                                <option value="SUCCESS">SUCCESS</option>
                                <option value="PENDING">PENDING</option>
                            </select>
                        </div>
                        <button type="submit" class="bg-blue-600 px-4 py-1 rounded-md text-sm text-white font-medium hover:bg-blue-700 transition h-[30px]">Add Manual Entry</button>
                    </form>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-left text-sm whitespace-nowrap">
                        <thead class="bg-[#0B0F19] text-gray-400 border-b border-[#1E293B]">
                            <tr>
                                <th class="px-6 py-4">Date / ID</th>
                                <th class="px-6 py-4">Merchant Node</th>
                                <th class="px-6 py-4">Amount</th>
                                <th class="px-6 py-4">UTR / VPA</th>
                                <th class="px-6 py-4">Status</th>
                                <th class="px-6 py-4">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#1E293B]">
                            <?php foreach($transactions as $t): ?>
                            <tr class="hover:bg-[#1E293B]/30">
                                <td class="px-6 py-4">
                                    <div class="text-xs text-gray-400"><?= date('M d, H:i', strtotime($t['created_at'])) ?></div>
                                    <div class="font-mono text-xs text-blue-300"><?= $t['order_id'] ?></div>
                                </td>
                                <td class="px-6 py-4 text-xs">
                                    <?= htmlspecialchars($t['gateway_name']) ?>
                                    <?php if($t['is_manual']) echo "<span class='bg-gray-700 px-1 ml-1 rounded'>Manual</span>"; ?>
                                </td>
                                <td class="px-6 py-4 font-bold text-white">₹<?= $t['amount'] ?></td>
                                <td class="px-6 py-4 text-xs font-mono">
                                    <div class="text-green-300"><?= $t['utr_number'] ?: '---' ?></div>
                                    <div class="text-gray-500"><?= $t['payer_vpa'] ?: '---' ?></div>
                                </td>
                                <td class="px-6 py-4 font-medium">
                                    <span class="<?= $t['status'] === 'SUCCESS' ? 'text-green-400' : ($t['status'] === 'FAILED' ? 'text-red-400' : 'text-yellow-400') ?>">
                                        <?= $t['status'] ?>
                                    </span>
                                </td>
                                <td class="px-6 py-4">
                                    <?php if($t['status'] === 'PENDING' || $t['status'] === 'UNDER_REVIEW'): ?>
                                    <div class="flex gap-2">
                                        <form method="POST" class="flex items-center gap-1">
                                            <input type="hidden" name="action" value="approve_txn">
                                            <input type="hidden" name="order_id" value="<?= $t['order_id'] ?>">
                                            <input type="text" name="utr_number" placeholder="Enter UTR" class="w-24 bg-[#0B0F19] border border-[#1E293B] rounded px-1 py-1 text-xs outline-none">
                                            <button class="bg-green-600 hover:bg-green-500 text-white px-2 py-1 rounded text-xs">Approve</button>
                                        </form>
                                        <form method="POST">
                                            <input type="hidden" name="action" value="fail_txn">
                                            <input type="hidden" name="order_id" value="<?= $t['order_id'] ?>">
                                            <button class="bg-red-600 hover:bg-red-500 text-white px-2 py-1 rounded text-xs">Fail</button>
                                        </form>
                                    </div>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <!-- TICKETS TAB -->
        <div x-show="tab === 'tickets'" x-cloak class="bg-[#111827] rounded-xl border border-[#1E293B] overflow-hidden p-6">
            <h2 class="text-xl font-bold text-white mb-6">UTR Verification & Support Tickets</h2>
            <div class="space-y-4">
                <?php foreach($tickets as $tk): ?>
                <div class="border border-[#1E293B] p-5 rounded-lg bg-[#0B0F19] flex flex-col md:flex-row gap-6">
                    <div class="flex-1">
                        <div class="flex justify-between items-center mb-3">
                            <span class="text-blue-400 font-mono font-medium">Order: <?= $tk['order_id'] ?></span>
                            <span class="<?= $tk['status'] === 'OPEN' ? 'bg-yellow-500/20 text-yellow-400' : 'bg-gray-800 text-gray-400' ?> text-xs px-2 py-1 rounded font-bold uppercase"><?= $tk['status'] ?></span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 text-sm mb-4">
                            <div><span class="text-gray-500">Submitted UTR:</span> <br><b class="text-white text-base font-mono tracking-widest"><?= htmlspecialchars($tk['utr_number']) ?></b></div>
                            <div><span class="text-gray-500">Payer Mobile:</span> <br><span class="text-gray-300"><?= htmlspecialchars($tk['payer_mobile'] ?? 'N/A') ?></span></div>
                        </div>
                        <div class="text-sm text-gray-400 italic bg-[#111827] p-3 rounded border border-[#1E293B]">
                            "<?= htmlspecialchars($tk['message'] ?: 'No message provided') ?>"
                        </div>
                        
                        <?php if($tk['status'] === 'OPEN'): ?>
                        <div class="mt-4 flex gap-3">
                            <form method="POST">
                                <input type="hidden" name="action" value="approve_txn">
                                <input type="hidden" name="order_id" value="<?= $tk['order_id'] ?>">
                                <input type="hidden" name="utr_number" value="<?= htmlspecialchars($tk['utr_number']) ?>">
                                <button class="bg-green-600 hover:bg-green-500 px-4 py-2 rounded-lg text-sm font-medium text-white shadow-lg shadow-green-900/20 transition">1-Click Verify & Approve</button>
                            </form>
                            <form method="POST">
                                <input type="hidden" name="action" value="fail_txn">
                                <input type="hidden" name="order_id" value="<?= $tk['order_id'] ?>">
                                <button class="bg-red-600 hover:bg-red-500 px-4 py-2 rounded-lg text-sm font-medium text-white transition">Reject Fake UTR</button>
                            </form>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if($tk['screenshot_url']): ?>
                    <div class="w-full md:w-64 flex-shrink-0">
                        <p class="text-xs text-gray-500 mb-2 uppercase">Payment Proof</p>
                        <a href="<?= htmlspecialchars($tk['screenshot_url']) ?>" target="_blank" class="block border border-[#1E293B] rounded-lg overflow-hidden hover:opacity-80 transition">
                            <img src="<?= htmlspecialchars($tk['screenshot_url']) ?>" alt="Screenshot" class="w-full h-auto object-cover max-h-48">
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php if(empty($tickets)) echo "<p class='text-sm text-gray-500 italic text-center py-8'>No pending support tickets or UTR submissions.</p>"; ?>
            </div>
        </div>

        <!-- API & WEBHOOKS TAB -->
        <div x-show="tab === 'api'" x-cloak class="grid grid-cols-1 md:grid-cols-2 gap-6">
            <div class="bg-[#111827] rounded-xl border border-[#1E293B] p-6">
                <h2 class="text-xl font-bold text-white mb-6">Developer API Settings</h2>
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="update_api_settings">
                    <div>
                        <label class="text-xs text-gray-500 uppercase block mb-1">API Key</label>
                        <input type="text" name="api_key" value="<?= htmlspecialchars($settings['api_key']) ?>" class="w-full bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white font-mono text-sm outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase block mb-1">API Secret</label>
                        <input type="text" name="api_secret" value="<?= htmlspecialchars($settings['api_secret']) ?>" class="w-full bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white font-mono text-sm outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase block mb-1">IP Whitelist (Comma separated)</label>
                        <textarea name="ip_whitelist" rows="3" class="w-full bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white font-mono text-sm outline-none focus:border-blue-500" placeholder="e.g. 192.168.1.1, 10.0.0.5"><?= htmlspecialchars($settings['ip_whitelist'] ?? '') ?></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-medium transition">Save API Settings</button>
                </form>
            </div>

            <div class="bg-[#111827] rounded-xl border border-[#1E293B] p-6">
                <h2 class="text-xl font-bold text-white mb-6">Multi-Webhook Engine</h2>
                <form method="POST" class="flex gap-2 mb-6">
                    <input type="hidden" name="action" value="add_webhook">
                    <input type="url" name="url" placeholder="https://yourdomain.com/webhook" required class="flex-1 bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white text-sm outline-none focus:border-blue-500">
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white px-4 rounded-lg font-medium transition">Add Webhook</button>
                </form>

                <div class="space-y-3">
                    <?php foreach($webhooks as $wh): ?>
                    <div class="flex justify-between items-center bg-[#0B0F19] border border-[#1E293B] p-3 rounded-lg">
                        <span class="text-sm font-mono text-gray-300 truncate mr-4"><?= htmlspecialchars($wh['url']) ?></span>
                        <form method="POST">
                            <input type="hidden" name="action" value="delete_webhook">
                            <input type="hidden" name="id" value="<?= $wh['id'] ?>">
                            <button class="text-red-400 hover:text-red-300 text-xs font-bold uppercase">Delete</button>
                        </form>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </main>

    <!-- Connect Merchant Modal -->
    <div x-show="modalOpen" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-black/70 backdrop-blur-sm p-4">
        <div @click.away="modalOpen = false" class="bg-[#111827] rounded-2xl border border-[#1E293B] shadow-2xl w-full max-w-lg overflow-hidden flex flex-col max-h-[90vh]">
            <div class="p-6 border-b border-[#1E293B] flex justify-between items-center bg-gradient-to-r from-blue-900/20 to-transparent">
                <h3 class="text-xl font-bold text-white">Manual Verify: <span x-text="currentGateway?.name" class="text-blue-400"></span></h3>
                <button @click="modalOpen = false" class="text-gray-400 hover:text-white">&times;</button>
            </div>
            <div class="p-6 overflow-y-auto">
                <form method="POST" class="space-y-4">
                    <input type="hidden" name="action" value="manual_verify_gateway">
                    <input type="hidden" name="gateway_id" :value="currentGateway?.id">
                    
                    <div>
                        <label class="text-xs text-gray-500 uppercase block mb-1">Merchant ID (MID)</label>
                        <input type="text" name="mid" :value="currentGateway?.mid" class="w-full bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase block mb-1">UPI ID (VPA)</label>
                        <input type="text" name="upi_id" :value="currentGateway?.upi_id" class="w-full bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase block mb-1">API Token / Merchant Key</label>
                        <input type="text" name="api_token" :value="currentGateway?.api_token" class="w-full bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white outline-none focus:border-blue-500">
                    </div>
                    <div>
                        <label class="text-xs text-gray-500 uppercase block mb-1">Session Cookie / Auth Token (Auto-Verify)</label>
                        <textarea name="session_cookie" rows="3" class="w-full bg-[#0B0F19] border border-[#1E293B] rounded-lg p-3 text-white outline-none focus:border-blue-500 font-mono text-xs" :value="currentGateway?.session_cookie"></textarea>
                    </div>
                    <button type="submit" class="w-full bg-blue-600 hover:bg-blue-700 text-white py-3 rounded-lg font-medium transition">Verify & Save Credentials</button>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
