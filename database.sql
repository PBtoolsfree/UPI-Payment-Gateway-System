-- Personal 10-in-1 UPI Payment Gateway Schema

DROP TABLE IF EXISTS `webhook_logs`, `webhooks`, `tickets`, `transactions_ledger`, `gateways`, `settings`;

-- 1. settings (Global UI & API Settings)
CREATE TABLE `settings` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `merchant_name` VARCHAR(255) DEFAULT 'My UPI Gateway',
  `theme_color` VARCHAR(20) DEFAULT '#2563EB',
  `logo_url` VARCHAR(255) DEFAULT NULL,
  `show_qr` BOOLEAN DEFAULT TRUE,
  `show_intent_buttons` BOOLEAN DEFAULT TRUE,
  `show_powered_by` BOOLEAN DEFAULT TRUE,
  `admin_pin` VARCHAR(255) DEFAULT '1234',
  `ip_whitelist` TEXT, -- API IP Whitelist
  `active_gateway_id` INT DEFAULT 1,
  `api_key` VARCHAR(100) NOT NULL,
  `api_secret` VARCHAR(100) NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO `settings` (`id`, `api_key`, `api_secret`) VALUES (1, 'UPIGW-KEY-1234567890', 'UPIGW-SEC-0987654321');

-- 2. gateways (10-in-1 Merchant Configs)
CREATE TABLE `gateways` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL,
  `upi_id` VARCHAR(100) DEFAULT NULL,
  `mid` VARCHAR(100) DEFAULT NULL,
  `api_token` TEXT DEFAULT NULL,
  `session_cookie` TEXT DEFAULT NULL,
  `is_active` BOOLEAN DEFAULT FALSE,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO `gateways` (`name`, `is_active`) VALUES 
('Personal UPI', TRUE),
('Paytm Business', FALSE),
('PhonePe Business', FALSE),
('BharatPe', FALSE),
('Google Pay Business', FALSE),
('HDFC Vyapar', FALSE),
('Pine Labs', FALSE),
('Airtel Merchant', FALSE),
('Freecharge Business', FALSE),
('MobiKwik Merchant', FALSE);

-- 3. transactions_ledger
CREATE TABLE `transactions_ledger` (
  `order_id` VARCHAR(100) PRIMARY KEY,
  `gateway_name` VARCHAR(50) DEFAULT 'Personal UPI',
  `amount` DECIMAL(10,2) NOT NULL,
  `utr_number` VARCHAR(50) DEFAULT NULL,
  `payer_vpa` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('PENDING', 'SUCCESS', 'FAILED', 'UNDER_REVIEW') DEFAULT 'PENDING',
  `note` TEXT,
  `redirect_url` VARCHAR(255) DEFAULT NULL,
  `is_manual` BOOLEAN DEFAULT FALSE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. tickets (Manual UTR submission & Screenshots)
CREATE TABLE `tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(100) NOT NULL,
  `utr_number` VARCHAR(50) NOT NULL,
  `payer_mobile` VARCHAR(20) DEFAULT NULL,
  `screenshot_url` VARCHAR(255) DEFAULT NULL,
  `status` ENUM('OPEN', 'RESOLVED', 'REJECTED') DEFAULT 'OPEN',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `transactions_ledger`(`order_id`) ON DELETE CASCADE
);

-- 5. webhooks (Unlimited Webhook URLs)
CREATE TABLE `webhooks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `url` VARCHAR(255) NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- 6. webhook_logs
CREATE TABLE `webhook_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(100) NOT NULL,
  `webhook_url` VARCHAR(255) NOT NULL,
  `payload` JSON NOT NULL,
  `http_status` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `transactions_ledger`(`order_id`) ON DELETE CASCADE
);
