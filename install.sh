#!/bin/bash
# One-Click Installation Script for UPI Payment Gateway
# Designed for Oracle Cloud (OCI) Ubuntu 22.04/24.04 LTS

set -e

# Configuration
REPO_URL="https://github.com/PBtoolsfree/UPI-Payment-Gateway-System.git"
INSTALL_DIR="/var/www/upi_gateway"
DB_NAME="upi_gateway"
DB_USER="upi_user"
DB_PASS="UpiGateway@2026"

echo "=========================================================="
echo " Starting One-Click Installation for UPI Payment Gateway"
echo "=========================================================="

# 1. System Updates and Prerequisites
echo "[1/8] Updating system and installing prerequisites..."
sudo apt-get update && sudo apt-get upgrade -y
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y software-properties-common curl wget git unzip iptables-persistent

# 2. Install LEMP Stack
echo "[2/8] Installing Nginx, PHP, and MySQL..."
sudo apt-get install -y nginx mysql-server

# Use default PHP packages provided by the OS to avoid PPA issues on certain OCI images
sudo apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y php-fpm php-cli php-mysql php-curl php-gd php-mbstring php-xml php-zip php-bcmath

sudo systemctl enable nginx
sudo systemctl enable mysql

# Find PHP-FPM service name and enable/start it
PHP_SERVICE=$(systemctl list-unit-files | grep -iE '^php.*-fpm\.service' | awk '{print $1}' | head -n 1)
if [ -n "$PHP_SERVICE" ]; then
    sudo systemctl enable "$PHP_SERVICE"
    sudo systemctl start "$PHP_SERVICE"
fi
sudo systemctl start nginx mysql

# 3. Configure Firewall (Port Forwarding for OCI)
echo "[3/8] Configuring Oracle Cloud Firewall (Ports 80 & 443)..."
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 80 -j ACCEPT
sudo iptables -I INPUT 6 -m state --state NEW -p tcp --dport 443 -j ACCEPT
sudo netfilter-persistent save

# 4. Clone Repository
echo "[4/8] Downloading Source Code from GitHub..."
if [ -d "$INSTALL_DIR" ]; then
    echo "Directory $INSTALL_DIR already exists. Pulling latest changes..."
    cd $INSTALL_DIR
    sudo git pull origin main
else
    sudo git clone $REPO_URL $INSTALL_DIR
fi

# 5. Set Permissions
echo "[5/8] Setting proper file permissions..."
sudo chown -R www-data:www-data $INSTALL_DIR
sudo find $INSTALL_DIR -type d -exec chmod 755 {} \;
sudo find $INSTALL_DIR -type f -exec chmod 644 {} \;

# 6. Database Setup
echo "[6/8] Configuring MySQL Database..."
sudo mysql -e "CREATE DATABASE IF NOT EXISTS $DB_NAME;"
sudo mysql -e "CREATE USER IF NOT EXISTS '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
sudo mysql -e "GRANT ALL PRIVILEGES ON $DB_NAME.* TO '$DB_USER'@'localhost';"
sudo mysql -e "FLUSH PRIVILEGES;"

if [ -f "$INSTALL_DIR/database.sql" ]; then
    echo "Importing database schema..."
    sudo mysql $DB_NAME < $INSTALL_DIR/database.sql
else
    echo "Warning: database.sql not found!"
fi

# 7. Configure Nginx Server Block
echo "[7/8] Configuring Nginx..."

# Dynamically find the PHP-FPM socket path
PHP_SOCK=$(find /var/run/php/ -name "php*-fpm.sock" | head -n 1)
if [ -z "$PHP_SOCK" ]; then
    # Fallback if not found
    PHP_SOCK="unix:/var/run/php/php-fpm.sock"
else
    PHP_SOCK="unix:$PHP_SOCK"
fi

cat << EOF | sudo tee /etc/nginx/sites-available/upi_gateway
server {
    listen 80;
    server_name _;
    root /var/www/upi_gateway/public;
    index index.php index.html;

    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    location ~ \.php\$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass $PHP_SOCK;
    }

    location ~ /\.ht {
        deny all;
    }
}
EOF

sudo ln -sf /etc/nginx/sites-available/upi_gateway /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo systemctl reload nginx

# 8. Setup Auto-Fail Cron Job
echo "[8/8] Setting up Cron Jobs..."
CRON_JOB="* * * * * php $INSTALL_DIR/cron/expire_orders.php >> $INSTALL_DIR/cron_log.txt 2>&1"
(crontab -l 2>/dev/null | grep -v "expire_orders.php"; echo "$CRON_JOB") | crontab -

echo "=========================================================="
echo " Installation Completed Successfully! "
echo "=========================================================="
echo "You can now access your server via its Public IP address."
echo "Note: If it still does not load, ensure that Ingress Rules for Port 80 and 443 are allowed in your Oracle Cloud VCN Security List."
