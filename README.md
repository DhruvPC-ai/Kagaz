# E-Commerce Website with PayPal Payment Gateway

A working online store for the Indian market built with **PHP**, **MySQL** and the **PayPal REST API (Orders v2)**. It runs locally on XAMPP and deploys to **Render** (Docker) with a free **TiDB Cloud** MySQL-compatible database. Payments use the **PayPal sandbox**, so it's a full proof of concept and no real money is involved.

The demo store, "Kagaz", sells Indian stationery: handmade Sanganeri paper notebooks, Channapatna wooden pen stands, Moradabad brass rulers, pens and more.

**Indian localisation:** prices in rupees with Indian digit grouping (₹1,00,000), "inclusive of all taxes" (GST) labels, checkout with a state/UT dropdown, 6-digit PIN code and 10-digit mobile number validation, and all times in IST (Asia/Kolkata).

**Why PayPal charges in USD:** PayPal stopped domestic payments in India on 1 April 2021 and INR is no longer accepted for Indian accounts, so Indian businesses that use PayPal receive international payments in USD. The store therefore shows and stores everything in ₹, and at checkout converts the rupee total to USD (rate set by `INR_PER_USD`) for PayPal. The customer sees the exact USD amount before paying, and the server checks PayPal charged exactly that.

## Features

**Customers** can browse products, filter by category, search, view product details, add items to a session cart, update quantities, register and log in, check out with an Indian shipping address, pay through PayPal, and see their order history with status.

**Admins** get a dashboard (revenue, paid orders, products, customers, low-stock list), can add, edit and delete products with image upload, manage categories, view every order with its shipping and PayPal transaction details, and update order status (pending, paid, shipped, delivered, cancelled).

**Under the hood:** PDO prepared statements everywhere, `password_hash`/`password_verify`, CSRF tokens on every form and AJAX call, HTML output escaping, session regeneration on login, server-side price calculation, PayPal amount verification on capture, stock reduction inside a database transaction, image uploads validated by content and stored in the database, secrets kept out of Git (environment variables or a git-ignored `config/local.php`), TLS to the hosted database, HttpOnly/SameSite session cookies, and automatic database setup on first run.

## Tech stack

PHP 8 (no framework), MySQL/MariaDB (TiDB Cloud in production), PayPal JavaScript SDK on the front end, PayPal Orders v2 REST API on the back end via cURL, plain HTML and CSS, Docker + Apache on Render.

## Folder structure

```
ecommerce/
├── admin/                  Admin panel
│   ├── index.php           Dashboard
│   ├── products.php        Product list + delete
│   ├── product_form.php    Add / edit product (with image upload)
│   ├── categories.php      Add / delete categories
│   └── orders.php          Order list, order detail, status update
├── assets/
│   ├── css/style.css
│   ├── img/placeholder.svg
│   └── products/           Sample product images
├── config/                 (blocked from the web)
│   ├── config.php          Settings: env variables > local.php > defaults
│   └── local.example.php   Copy to local.php for your XAMPP secrets
├── includes/               Shared code             (blocked from the web)
│   ├── init.php            Session + config + DB + helpers bootstrap
│   ├── db.php              PDO connection, TLS, first-run auto-install
│   ├── functions.php       Escaping, CSRF, auth, cart, flash messages
│   ├── paypal.php          PayPal OAuth + REST request helper
│   ├── header.php / footer.php / admin_nav.php
├── paypal/
│   ├── create_order.php    AJAX: create PayPal order from our DB total
│   └── capture_order.php   AJAX: capture payment, verify, mark order paid
├── index.php               Shop / product listing
├── image.php               Serves admin-uploaded images from the database
├── product.php             Product detail
├── cart.php                Cart (add / update / remove)
├── checkout.php            Shipping form, creates pending order
├── pay.php                 PayPal buttons
├── order_success.php       Confirmation
├── orders.php              Customer order history
├── login.php / register.php / logout.php
├── database.sql            Schema + sample data (run automatically on first visit)
├── Dockerfile              PHP 8.2 + Apache image for Render
├── render.yaml             Render Blueprint (one-click service setup)
├── .htaccess               Blocks .sql/.md/Dockerfile etc. and directory listings
└── .gitignore              Keeps config/local.php out of Git
```

## Setup (XAMPP)

### 1. Install and start XAMPP
Download XAMPP (PHP 8.x) from apachefriends.org and install it. Open the **XAMPP Control Panel** and click **Start** for **Apache** and **MySQL**.

### 2. Copy the project
Unzip the project and place the `ecommerce` folder at `C:\xampp\htdocs\ecommerce` (Windows) or `/Applications/XAMPP/htdocs/ecommerce` (macOS). If you use a different folder name, set `BASE_URL` in `config/local.php` (step 7) to match.

### 3. Open the site
Open `http://localhost/ecommerce`. On the first visit the site creates the `ecommerce_db` database, its tables, 3 categories, 8 products and 2 accounts automatically (from `database.sql`). If you'd rather do it by hand, import `database.sql` in phpMyAdmin first.

### 4. Log in

The site comes with two accounts: an admin (`admin@example.com`) and a customer, Priya Sharma (`customer@example.com`). Their passwords are private settings, never stored in this repository:

1. In the `config` folder, copy `local.example.php` to a new file named `local.php` (it's git-ignored).
2. Set `ADMIN_PASSWORD` and `CUSTOMER_PASSWORD` to passwords of your choice.
3. Reload the site and log in with them. (If you were already browsing the site, log out first.)

Until you set them, the accounts use the defaults from `database.sql`, which is fine on your own computer but must never be used online. Anyone can also create their own customer account with **Sign up**.

Everything except paying works at this point.

### 5. Create a free PayPal developer account
Go to https://developer.paypal.com and click **Log in to Dashboard**. Log in with an existing PayPal account, or click **Sign Up** and create one with your email and mobile number. You do **not** need to add a credit card, debit card or bank account: those are only needed to send or receive real money, and this project never touches real money. If the sign-up flow asks you to link a card, skip that step.

### 6. Create US-based sandbox test accounts
PayPal gives every developer a "sandbox": fake PayPal accounts with fake money. Because sandbox accounts created with India as the country run into the INR restriction, create your own US ones:

1. In the dashboard, make sure the toggle at the top says **Sandbox**.
2. Go to **Testing Tools > Sandbox Accounts > Create account**.
3. Create a **Business** account with country **United States**. This is the "store" that receives money.
4. Create a **Personal** account with country **United States**. This is the "customer" that pays. It comes with a fake balance and a fake card.
5. For the Personal account, click the three dots > **View/Edit account** and note its email and password.

### 7. Get the API credentials
1. Go to **Apps & Credentials** (still in **Sandbox**) and click **Create App**.
2. Give it any name, choose **Merchant**, and under **Sandbox Account** pick the US **Business** account from step 6.
3. In the `config` folder, copy `local.example.php` to a new file named `local.php` and paste them in:
   ```php
   'PAYPAL_CLIENT_ID' => 'AbC...your client id...',
   'PAYPAL_SECRET'    => 'EfG...your secret...',
   ```
   `local.php` is git-ignored, so your secret won't be pushed to GitHub.

### 8. Make a test purchase
1. Log in as `customer@example.com` with your `CUSTOMER_PASSWORD`, add products to the cart, go to checkout.
2. Fill in an Indian address (e.g. Pune, Maharashtra, PIN 411004, mobile 98765 43210) and click **Continue to payment**.
3. The payment page shows the rupee total and the USD amount PayPal will charge. Click the **PayPal** button.
4. In the popup, log in with the **sandbox Personal** email and password from step 6 and click **Pay** (or **Complete Purchase**).
5. You're redirected to the confirmation page. The order shows as **Paid** with a PayPal transaction ID, stock is reduced and the cart is empty.
6. Log in as admin to see the order, then mark it **Shipped** or **Delivered**.

To see the money arrive, log in at https://www.sandbox.paypal.com with the sandbox **Business** account.

## Deploy online (Render + TiDB Cloud, free)

Render runs the site from the `Dockerfile`. Render's free plan doesn't include MySQL, so the database lives on **TiDB Cloud Starter**, which is free and speaks the MySQL protocol, so the PHP code doesn't change. Uploaded product images are stored in the database because Render's free plan has no persistent disk.

### A. Put the project on GitHub
1. Create a free account at https://github.com and click **New repository**. Name it (e.g. `kagaz-ecommerce`), keep it **Public** so recruiters can see the code, and create it.
2. Upload the project: either click **uploading an existing file** and drag in everything *inside* the `ecommerce` folder (not the folder itself), or use Git:
   ```
   cd C:\xampp\htdocs\ecommerce
   git init
   git add .
   git commit -m "Kagaz e-commerce site"
   git branch -M main
   git remote add origin https://github.com/YOUR-USERNAME/kagaz-ecommerce.git
   git push -u origin main
   ```
3. Check that `config/local.php` is **not** in the repository. (`.gitignore` excludes it when using Git; when uploading by drag-and-drop, leave it out yourself.)

### B. Create the database on TiDB Cloud
1. Sign up at https://tidbcloud.com (free, no card needed for the Starter tier).
2. Create a **Starter** cluster. Pick a region near your Render region, e.g. **Singapore (ap-southeast-1)** if offered.
3. Open the cluster and click **Connect** (top right). Choose **Public** connection type and click **Generate Password**.
4. Note the **Host** (like `gateway01.ap-southeast-1.prod.aws.tidbcloud.com`), **Port** `4000`, **User** (like `2aBcDeFgH.root`) and the **Password**. Save the password somewhere safe; it's shown only once.
5. If the dialog mentions an IP access list or firewall, allow all IPs (`0.0.0.0/0`): Render's free plan has no fixed outgoing IP. The connection is still protected by the password and TLS.

You don't need to create tables: the site creates the `kagaz` database and everything in it on its first visit.

### C. Deploy on Render
1. Sign up at https://render.com with your GitHub account.
2. Click **New > Blueprint**, pick your repository, and Render reads `render.yaml`.
3. It asks for the secret values. Fill them in:

   | Variable | Value |
   |---|---|
   | `DB_HOST` | TiDB host from step B4 |
   | `DB_USER` | TiDB user from step B4 (with its prefix) |
   | `DB_PASS` | TiDB password |
   | `ADMIN_PASSWORD` | A strong password for `admin@example.com` |
   | `CUSTOMER_PASSWORD` | A strong password for `customer@example.com` |
   | `PAYPAL_CLIENT_ID` | Sandbox Client ID (setup step 7) |
   | `PAYPAL_SECRET` | Sandbox Secret |

   `DB_PORT` (4000), `DB_NAME` (kagaz), `DB_SSL_CA` (turns on TLS) and `INR_PER_USD` are filled in by the Blueprint.
4. Click **Apply**. The first build takes a few minutes. When it says **Live**, open the `https://kagaz-shop.onrender.com`-style URL shown at the top.
5. The first page load creates the database tables. Log in with your private passwords and make a sandbox purchase to confirm PayPal works online.

Prefer clicking through manually? **New > Web Service**, choose the repo, set **Language/Runtime** to **Docker**, **Instance type** to **Free**, and add the environment variables above plus `DB_PORT=4000`, `DB_NAME=kagaz`, `DB_SSL_CA=/etc/ssl/certs/ca-certificates.crt`.

### Things to know about the free hosting
- **Cold starts:** a free Render service sleeps after about 15 minutes without visitors, and the next visit takes up to a minute to wake it. Open the link yourself a minute before an interview.
- **Logins reset on redeploy/sleep:** PHP sessions live in the container, so a sleeping or redeployed site logs everyone out and empties carts. Orders, products and images are safe in TiDB.
- **Updating the site:** push to GitHub and Render redeploys automatically.
- **Account passwords:** the live site uses `ADMIN_PASSWORD` and `CUSTOMER_PASSWORD` from Render's **Environment** tab, which only you can see. To change one, edit it there and save; Render restarts the site and the new password takes effect on the next login. If either is left empty, that account falls back to the public default password, so always set both before sharing the link.

## Do I need a real PayPal account or card?

You need a free PayPal login only to open the developer dashboard and get sandbox credentials. No card or bank details are required for that, and nothing is ever charged. Every payment in this project happens between two **sandbox** accounts using fake balances, and your real PayPal login can't be used to pay in the sandbox at all.

## How the payment flow works

```
Browser (pay.php)                Our server (PHP)                     PayPal API
-----------------                ----------------                     ----------
Click PayPal button  ─────────▶  paypal/create_order.php
                                  - loads order's USD amount from MySQL
                                    (₹ total converted at checkout)
                                  - POST /v1/oauth2/token  ─────────▶ access token
                                  - POST /v2/checkout/orders ───────▶ PayPal order ID
                     ◀─────────  returns PayPal order ID
PayPal popup: buyer logs in
and approves the payment
onApprove            ─────────▶  paypal/capture_order.php
                                  - checks the PayPal order ID matches our order
                                  - POST /v2/checkout/orders/{id}/capture ─▶ COMPLETED
                                  - verifies captured USD amount matches
                                  - transaction: order = paid, reduce stock
                                  - empties cart
                     ◀─────────  { success: true }
Redirect to order_success.php
```

The order is created as **pending** at checkout and only becomes **paid** after PayPal confirms the capture on the server. The browser never sends a price, so tampering with the page can't change what is charged.

## Troubleshooting

**"SSL certificate problem: unable to get local issuer certificate"** when paying (common on Windows XAMPP). Open `C:\xampp\php\php.ini`, find the `[curl]` section and set:
```
curl.cainfo = "C:\xampp\apache\bin\curl-ca-bundle.crt"
openssl.cafile = "C:\xampp\apache\bin\curl-ca-bundle.crt"
```
Remove the leading `;` if present, save, and restart Apache from the Control Panel.

**cURL won't load on XAMPP** ("Unable to load dynamic library 'curl'" in Apache's error.log): the site doesn't need it. When cURL isn't available, it talks to PayPal through PHP's built-in HTTPS support instead, which only needs `extension=openssl` enabled in `php.ini` (it is by default in XAMPP).

**"PayPal rejected the credentials"**: the Client ID or Secret is wrong, or you copied **Live** credentials instead of **Sandbox** ones.

**PayPal popup says the payment can't be processed or the card was declined**: your sandbox accounts were probably created with India as the country. Create US Business and Personal sandbox accounts (step 6), link the app to the US Business account, and pay with the US Personal account.

**Changing the exchange rate**: set `INR_PER_USD` in `config/local.php` (XAMPP) or in the Render service's **Environment** tab. It applies to orders placed after the change.

**Apache won't start**: port 80 is in use (often Skype or IIS). In XAMPP click **Config > httpd.conf** for Apache, change `Listen 80` to `Listen 8080`, and open `http://localhost:8080/ecommerce`.

**"Database connection failed"**: on XAMPP, MySQL isn't started, or you set a MySQL root password (add `'DB_PASS' => '...'` to `config/local.php`). On Render, check `DB_HOST`, `DB_USER`, `DB_PASS` in the service's **Environment** tab, and that `DB_PORT` is 4000 and `DB_SSL_CA` is set (TiDB refuses unencrypted connections).

**Render build or deploy fails**: open the service's **Logs** tab. "Port scan timeout" means Apache didn't start: make sure you didn't edit the `CMD` line in the `Dockerfile`.

**Pages load without styling or links 404**: on XAMPP the folder name doesn't match `BASE_URL` (default `/ecommerce`; override it in `config/local.php`).

**Uploaded images vanished after moving from XAMPP to Render**: they were stored in your local database. Re-upload them in the admin panel online; they're saved in TiDB from then on.

**PayPal button doesn't appear**: you need an internet connection; the PayPal SDK loads from paypal.com.
