-- Personal 10-in-1 UPI Payment Gateway Schema

DROP TABLE IF EXISTS `webhook_logs`, `webhooks`, `tickets`, `transactions_ledger`, `gateways`, `settings`;

-- 1. settings (Single row for global config)
CREATE TABLE `settings` (
  `id` INT PRIMARY KEY DEFAULT 1,
  `merchant_name` VARCHAR(255) DEFAULT 'My UPI Gateway',
  `theme_color` VARCHAR(20) DEFAULT '#2563EB',
  `logo_url` VARCHAR(255) DEFAULT NULL,
  `admin_pin` VARCHAR(255) DEFAULT '1234', -- Simple PIN lock for dashboard
  `admin_ip_whitelist` TEXT, -- Comma separated IPs for dashboard access, empty = open
  `active_gateway_id` INT DEFAULT 1,
  `api_key` VARCHAR(100) NOT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO `settings` (`id`, `api_key`) VALUES (1, 'UPIGW-SEC-1234567890');

-- 2. gateways (List of 10-in-1 configurations)
CREATE TABLE `gateways` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(50) NOT NULL, -- e.g., 'Paytm', 'PhonePe', 'Personal'
  `upi_id` VARCHAR(100) DEFAULT NULL,
  `mid` VARCHAR(100) DEFAULT NULL,
  `merchant_key` VARCHAR(255) DEFAULT NULL,
  `is_active` BOOLEAN DEFAULT FALSE,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO `gateways` (`name`, `upi_id`, `is_active`) VALUES 
('Personal UPI', 'mypay@upi', TRUE),
('Paytm Business', NULL, FALSE),
('PhonePe Business', NULL, FALSE),
('GPay Business', NULL, FALSE),
('BharatPe', NULL, FALSE);

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
  `webhook_url` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 4. tickets (Manual UTR submission & Disputes)
CREATE TABLE `tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(100) NOT NULL,
  `utr_number` VARCHAR(50) NOT NULL,
  `message` TEXT,
  `status` ENUM('OPEN', 'RESOLVED', 'REJECTED') DEFAULT 'OPEN',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `transactions_ledger`(`order_id`) ON DELETE CASCADE
);

-- 5. webhook_logs
CREATE TABLE `webhook_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(100) NOT NULL,
  `payload` JSON NOT NULL,
  `http_status` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`order_id`) REFERENCES `transactions_ledger`(`order_id`) ON DELETE CASCADE
);
