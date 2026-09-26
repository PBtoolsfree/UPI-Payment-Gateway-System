<?php
// public/index.php - Front controller for routing

require_once __DIR__ . '/../config.php';

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

// Simple routing
switch ($request_uri) {
    case '/':
    case '/index.php':
        require __DIR__ . '/../views/landing.php';
        break;
        
    case '/checkout':
        require __DIR__ . '/../views/checkout.php';
        break;
        
    case '/api/v1/create-order':
        require __DIR__ . '/../api/v1/create-order.php';
        break;
        
    case '/api/v1/check-status':
        require __DIR__ . '/../api/v1/check-status.php';
        break;
        
    case '/dashboard':
        // Placeholder for merchant dashboard
        echo "Merchant Dashboard - Coming Soon";
        break;
        
    case '/admin':
        // Placeholder for admin panel
        echo "Admin Panel - Coming Soon";
        break;

    default:
        http_response_code(404);
        echo "404 Not Found";
        break;
}
