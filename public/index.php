<?php
// public/index.php - Front controller
require_once __DIR__ . '/../config.php';

$request_uri = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);

switch ($request_uri) {
    case '/':
    case '/index.php':
        require __DIR__ . '/../views/dashboard.php';
        break;
        
    case '/pay':
        require __DIR__ . '/../views/pay.php';
        break;
        
    case '/api/create-order':
        require __DIR__ . '/../api/create-order.php';
        break;
        
    case '/api/status':
        require __DIR__ . '/../api/status.php';
        break;

    default:
        http_response_code(404);
        echo "404 Not Found";
        break;
}
