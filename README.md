# Personal 10-in-1 UPI Payment Gateway

A lightweight, single-owner Personal UPI Gateway built with a beautiful Dark Navy & Slate UI. This allows you to collect payments across 10+ UPI apps directly to your bank account without transaction fees, using a simple PIN-locked dashboard.

## Features
- **Single-Owner Mode:** Perfect for freelancers, indie hackers, and small businesses.
- **Dark Theme Checkout:** Beautiful `#0B0F19` background with blue-to-purple gradients.
- **10-in-1 Gateway Manager:** Toggle between Personal UPI, Paytm, PhonePe, and GPay inside the dashboard.
- **Live Ledger & Ticketing:** View real-time transactions, approve manual UTR submissions, and track collections.
- **Auto-Expire Engine:** Background cron automatically expires unverified 10-minute-old orders.

## Dashboard Access
- Visit `/` (root directory).
- Default Admin PIN: **`1234`**

## 🚀 One-Click Installation

To install on Ubuntu 22.04 / 24.04 LTS (Oracle Cloud or any VPS):

1. Connect via SSH.
2. Download the installer:
   ```bash
   wget https://raw.githubusercontent.com/PBtoolsfree/UPI-Payment-Gateway-System/main/install.sh
   ```
3. Make it executable and run:
   ```bash
   chmod +x install.sh
   sudo ./install.sh
   ```
4. Important: After the script completes, manually import the updated `database.sql` into your database since the schema was refactored for personal use.

## Developer APIs

### 1. Create Order (`POST /api/create-order`)
- **Headers:** `X-Api-Key: UPIGW-SEC-1234567890` (or as set in dashboard)
- **Body:** `amount=10.00&order_ref=XYZ&customer_note=Test`
- **Response:** Returns `payment_url`.

### 2. Check Status (`GET /api/status?order_id=XYZ`)
- Returns real-time status (`PENDING`, `SUCCESS`, `FAILED`, `UNDER_REVIEW`).
