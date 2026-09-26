#!/bin/bash
# OCI Setup Script for 10-in-1 UPI Payment Gateway
# OS: Ubuntu 22.04/24.04 LTS
# Stack: Nginx, PHP 8.2-FPM, MySQL 8

set -e

echo "Starting OCI Setup for UPI Payment Gateway..."

# 1. Update and Install Prerequisites
sudo apt-get update && sudo apt-get upgrade -y
sudo apt-get install -y software-properties-common curl wget git unzip iptables-persistent

# 2. Install Nginx
sudo apt-get install -y nginx
sudo systemctl enable nginx
sudo systemctl start nginx

# 3. Install PHP 8.2 & Extensions
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update
sudo apt-get install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-curl php8.2-gd \
                        php8.2-mbstring php8.2-xml php8.2-zip php8.2-bcmath

sudo systemctl enable php8.2-fpm
sudo systemctl start php8.2-fpm

# 4. Install MySQL 8
sudo apt-get install -y mysql-server
sudo systemctl enable mysql
sudo systemctl start mysql

# Basic MySQL Security (Ideally run mysql_secure_installation manually)
# We will create a default database and user
sudo mysql -e "CREATE DATABASE IF NOT EXISTS upi_gateway;"
sudo mysql -e "CREATE USER IF NOT EXISTS 'upi_user'@'localhost' IDENTIFIED BY 'UpiGateway@2026';"
sudo mysql -e "GRANT ALL PRIVILEGES ON upi_gateway.* TO 'upi_user'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

# 5. Configure OCI Firewall (iptables)
echo "Configuring iptables for OCI (allowing 80 and 443)..."
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save

# 6. Configure Nginx Server Block (Basic HTTP setup)
cat << 'EOF' | sudo tee /etc/nginx/sites-available/upi_gateway
server {
    listen 80;
    server_name _;
    root /var/www/upi_gateway/public;
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

# Enable Site
sudo mkdir -p /var/www/upi_gateway/public
sudo chown -R www-data:www-data /var/www/upi_gateway
sudo ln -sf /etc/nginx/sites-available/upi_gateway /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo systemctl reload nginx

# 7. Setup Cron for Auto-Fail (Expire Orders)
CRON_JOB="* * * * * php /var/www/upi_gateway/cron/expire_orders.php >> /var/log/upi_gateway_cron.log 2>&1"
(crontab -l 2>/dev/null; echo "$CRON_JOB") | crontab -

echo "Setup Complete! Your server is ready."
echo "Please clone your project to /var/www/upi_gateway and import database.sql"
echo "Don't forget to configure SSL with Certbot!"
