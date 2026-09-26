# 10-in-1 UPI Payment Gateway & Merchant SaaS Platform

Welcome to the **10-in-1 UPI Payment Gateway**. This project is a complete, production-ready SaaS platform that allows merchants to accept payments via Paytm, PhonePe, GPay, BharatPe, and more without any coding, utilizing dynamic QR codes, intent links, and multi-webhook systems.

## Features
- **10-in-1 Support:** Paytm, PhonePe, BharatPe, GPay, Pine Labs, HDFC Vyapar, and Personal UPI.
- **Dynamic QR & Deep Links:** Render NPCI UPI standard strings as QR codes and deep links.
- **Real-Time Polling:** Instant payment status updates via Alpine.js on the checkout page.
- **Auto-Expire System:** Cron job automatically transitions pending orders to failed after 10 minutes.
- **Merchant Dashboard:** Manage API keys, webhook URLs, UI customization (theme colors, logo), and ledger tracking.
- **Support Ticket System:** Integrated dispute and ticket management.
- **Admin Panel:** Zero-code settings manager for ReCAPTCHA, SMTP, and site details.

---

## 🚀 One-Click Installation on Oracle Cloud (OCI)

We have provided a fully automated **One-Click Installation Script** designed for **Ubuntu 22.04 / 24.04 LTS** (Oracle Cloud or any VPS). 

The script automatically:
1. Installs the **LEMP Stack** (Nginx, PHP 8.2-FPM, MySQL 8).
2. Configures **Oracle Cloud Firewall** (Port 80/443 Forwarding via iptables).
3. Clones this GitHub repository.
4. Sets up the **MySQL Database** and imports the schema (`database.sql`).
5. Configures **Nginx Server Blocks**.
6. Sets correct directory permissions.
7. Installs the **Auto-Expire Cron Job**.

### Step-by-Step Installation Guide

#### Step 1: Connect to your Oracle Cloud Server
Open your terminal (or PuTTY) and SSH into your Ubuntu instance:
```bash
ssh -i your-ssh-key.key ubuntu@YOUR_SERVER_PUBLIC_IP
```

#### Step 2: Download the One-Click Script
Download the `install.sh` script directly from this repository:
```bash
wget https://raw.githubusercontent.com/PBtoolsfree/UPI-Payment-Gateway-System/main/install.sh
```

#### Step 3: Make the Script Executable
Give the script execute permissions:
```bash
chmod +x install.sh
```

#### Step 4: Run the Installer
Execute the installation script with root privileges:
```bash
sudo ./install.sh
```

#### Step 5: Post-Installation
Once the script finishes executing, you will see a success message.
1. Open your web browser and go to `http://YOUR_SERVER_PUBLIC_IP`.
2. You will see the beautiful SaaS Landing Page.
3. **Important Note for Oracle Cloud:** Ensure that **Ingress Rules** for Port 80 (HTTP) and Port 443 (HTTPS) are added to your Virtual Cloud Network (VCN) Security List in the Oracle Cloud Console.

---

## 🔧 Manual Configuration (Optional)

If you wish to change the default database credentials or domain names:
1. Edit `/var/www/upi_gateway/config.php` to update your database settings.
2. Edit `/etc/nginx/sites-available/upi_gateway` to change the `server_name` to your custom domain.
3. Restart Nginx: `sudo systemctl reload nginx`.

---

## 🛡 Security & Best Practices
- **SSL Certificate:** It is highly recommended to secure your gateway with HTTPS. You can easily do this using Let's Encrypt:
  ```bash
  sudo apt install certbot python3-certbot-nginx -y
  sudo certbot --nginx -d yourdomain.com
  ```
- **Change Default Passwords:** Please change the default database passwords listed in the `install.sh` script before running it in a production environment.

## 📄 License
This project is for educational and authorized merchant usage only. Ensure you comply with NPCI and bank guidelines when using personal UPI for commercial purposes.
