-- Database Schema for 10-in-1 UPI Payment Gateway & Merchant SaaS Platform
-- 1. website_settings
CREATE TABLE `website_settings` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `site_title` VARCHAR(255) DEFAULT 'UPI Gateway',
  `site_logo` VARCHAR(255) DEFAULT NULL,
  `support_phone` VARCHAR(20) DEFAULT NULL,
  `support_email` VARCHAR(100) DEFAULT NULL,
  `office_address` TEXT,
  `recaptcha_site_key` VARCHAR(255) DEFAULT NULL,
  `recaptcha_secret_key` VARCHAR(255) DEFAULT NULL,
  `smtp_host` VARCHAR(255) DEFAULT NULL,
  `smtp_port` INT DEFAULT 587,
  `smtp_user` VARCHAR(255) DEFAULT NULL,
  `smtp_pass` VARCHAR(255) DEFAULT NULL,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Default Settings Insert
INSERT INTO `website_settings` (`site_title`, `support_email`) VALUES ('UPI Gateway', 'support@example.com');

-- 2. plans
CREATE TABLE `plans` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `name` VARCHAR(100) NOT NULL,
  `price` DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  `duration_days` INT NOT NULL DEFAULT 30,
  `transaction_fee_percent` DECIMAL(5,2) DEFAULT 0.00,
  `transaction_limit` INT DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- 3. users (Super Admin & Merchants)
CREATE TABLE `users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `role` ENUM('admin', 'merchant') DEFAULT 'merchant',
  `name` VARCHAR(100) NOT NULL,
  `email` VARCHAR(100) NOT NULL UNIQUE,
  `password` VARCHAR(255) NOT NULL,
  `phone` VARCHAR(20) DEFAULT NULL,
  `referral_code` VARCHAR(50) UNIQUE DEFAULT NULL,
  `referred_by` INT DEFAULT NULL,
  `wallet_balance` DECIMAL(10,2) DEFAULT 0.00,
  `plan_id` INT DEFAULT NULL,
  `plan_expiry` DATETIME DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`referred_by`) REFERENCES `users`(`id`) ON DELETE SET NULL,
  FOREIGN KEY (`plan_id`) REFERENCES `plans`(`id`) ON DELETE SET NULL
);

-- 4. merchants (Settings for Merchants)
CREATE TABLE `merchants` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL UNIQUE,
  `api_key` VARCHAR(100) UNIQUE NOT NULL,
  `secret_key` VARCHAR(100) UNIQUE NOT NULL,
  `allowed_ips` TEXT, -- Comma separated or *
  `theme_color` VARCHAR(20) DEFAULT '#000000',
  `logo_url` VARCHAR(255) DEFAULT NULL,
  `show_qr` BOOLEAN DEFAULT TRUE,
  `show_intent_buttons` BOOLEAN DEFAULT TRUE,
  `show_powered_by` BOOLEAN DEFAULT TRUE,
  `upi_id` VARCHAR(100) DEFAULT NULL,
  `mid` VARCHAR(100) DEFAULT NULL, -- Paytm/PhonePe MID
  `active_gateway` ENUM('paytm', 'phonepe', 'bharatpe', 'gpay', 'pinelabs', 'hdfc', 'personal') DEFAULT 'personal',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- 5. transactions_ledger
CREATE TABLE `transactions_ledger` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `order_id` VARCHAR(100) NOT NULL UNIQUE,
  `merchant_id` INT NOT NULL,
  `gateway_type` VARCHAR(50) DEFAULT 'personal',
  `amount` DECIMAL(10,2) NOT NULL,
  `utr_number` VARCHAR(50) DEFAULT NULL,
  `payer_vpa` VARCHAR(100) DEFAULT NULL,
  `status` ENUM('PENDING', 'SUCCESS', 'FAILED', 'UNDER_REVIEW') DEFAULT 'PENDING',
  `note` TEXT,
  `redirect_url` VARCHAR(255) DEFAULT NULL,
  `ip_address` VARCHAR(45) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE
);

-- 6. webhooks
CREATE TABLE `webhooks` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `merchant_id` INT NOT NULL,
  `webhook_url` VARCHAR(255) NOT NULL,
  `is_active` BOOLEAN DEFAULT TRUE,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`merchant_id`) REFERENCES `merchants`(`id`) ON DELETE CASCADE
);

-- 7. webhook_logs
CREATE TABLE `webhook_logs` (
  `id` BIGINT AUTO_INCREMENT PRIMARY KEY,
  `webhook_id` INT NOT NULL,
  `order_id` VARCHAR(100) NOT NULL,
  `payload` JSON NOT NULL,
  `http_status` INT DEFAULT NULL,
  `response_body` TEXT,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`webhook_id`) REFERENCES `webhooks`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`order_id`) REFERENCES `transactions_ledger`(`order_id`) ON DELETE CASCADE
);

-- 8. tickets
CREATE TABLE `tickets` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `user_id` INT NOT NULL,
  `order_id` VARCHAR(100) DEFAULT NULL,
  `utr_number` VARCHAR(50) DEFAULT NULL,
  `subject` VARCHAR(255) NOT NULL,
  `status` ENUM('OPEN', 'IN_PROGRESS', 'RESOLVED', 'REJECTED') DEFAULT 'OPEN',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- 9. ticket_replies
CREATE TABLE `ticket_replies` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `ticket_id` INT NOT NULL,
  `user_id` INT NOT NULL,
  `message` TEXT NOT NULL,
  `attachment_url` VARCHAR(255) DEFAULT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`user_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);

-- 10. referrals
CREATE TABLE `referrals` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `referrer_id` INT NOT NULL,
  `referred_id` INT NOT NULL,
  `commission_amount` DECIMAL(10,2) DEFAULT 0.00,
  `status` ENUM('PENDING', 'PAID') DEFAULT 'PENDING',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`referrer_id`) REFERENCES `users`(`id`) ON DELETE CASCADE,
  FOREIGN KEY (`referred_id`) REFERENCES `users`(`id`) ON DELETE CASCADE
);
