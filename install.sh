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
echo "[2/8] Installing Nginx, PHP 8.2, and MySQL..."
sudo apt-get install -y nginx mysql-server
sudo add-apt-repository ppa:ondrej/php -y
sudo apt-get update
sudo DEBIAN_FRONTEND=noninteractive apt-get install -y php8.2-fpm php8.2-cli php8.2-mysql php8.2-curl php8.2-gd php8.2-mbstring php8.2-xml php8.2-zip php8.2-bcmath

sudo systemctl enable nginx
sudo systemctl enable php8.2-fpm
sudo systemctl enable mysql
sudo systemctl start nginx php8.2-fpm mysql

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
