<?php
// config.php
define('DB_HOST', 'localhost');
define('DB_USER', 'upi_user');
define('DB_PASS', 'UpiGateway@2026');
define('DB_NAME', 'upi_gateway');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Database Connection failed");
}

function jsonResponse($status, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message, 'data' => $data]);
    exit;
}

// Fetch global settings
$stmt = $pdo->query("SELECT * FROM settings WHERE id = 1");
$settings = $stmt->fetch();

// Check Admin Auth (Simple PIN session)
session_start();
function requireAdmin() {
    global $settings;
    if (isset($_GET['logout'])) {
        session_destroy();
        header("Location: /");
        exit;
    }
    
    if (isset($_POST['admin_pin'])) {
        if ($_POST['admin_pin'] === $settings['admin_pin']) {
            $_SESSION['is_admin'] = true;
        } else {
            $error = "Invalid PIN";
        }
    }
    
    if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
        $error_msg = isset($error) ? "<p style='color:red;'>$error</p>" : "";
        echo "<!DOCTYPE html><html lang='en'><head><title>Admin Login</title><script src='https://cdn.tailwindcss.com'></script></head>
        <body class='bg-[#0B0F19] flex items-center justify-center h-screen'>
            <div class='bg-[#111827] p-8 rounded-xl border border-[#1E293B] shadow-2xl w-96 text-center'>
                <h2 class='text-2xl font-bold text-transparent bg-clip-text bg-gradient-to-r from-blue-400 via-indigo-400 to-purple-500 mb-6'>Enter PIN</h2>
                $error_msg
                <form method='POST'>
                    <input type='password' name='admin_pin' class='w-full bg-[#0B0F19] text-white border border-[#1E293B] rounded-lg p-3 mb-4 focus:ring-2 focus:ring-blue-500 outline-none text-center tracking-widest text-lg' autofocus required>
                    <button type='submit' class='w-full bg-blue-600 hover:bg-blue-700 text-white font-semibold py-3 rounded-lg transition'>Unlock</button>
                </form>
            </div>
        </body></html>";
        exit;
    }
}
