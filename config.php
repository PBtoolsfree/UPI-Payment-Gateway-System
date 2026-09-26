<?php
// config.php - Database connection and configuration

define('DB_HOST', 'localhost');
define('DB_USER', 'upi_user');
define('DB_PASS', 'UpiGateway@2026');
define('DB_NAME', 'upi_gateway');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    // Set the PDO error mode to exception
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Set default fetch mode
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Helper function to send JSON response
function jsonResponse($status, $message, $data = [], $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json');
    echo json_encode([
        'status' => $status,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

// Function to fetch website settings
function getWebsiteSettings($pdo) {
    $stmt = $pdo->query("SELECT * FROM website_settings LIMIT 1");
    return $stmt->fetch();
}
