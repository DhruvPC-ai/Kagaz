<?php
/*
 * Central configuration.
 *
 * Every setting is read in this order:
 *   1. an environment variable (used on Render / any host)
 *   2. config/local.php       (your own values on XAMPP; never commit this file)
 *   3. the default below      (XAMPP defaults)
 */
$local = is_file(__DIR__ . '/local.php') ? require __DIR__ . '/local.php' : [];

$setting = function (string $key, $default) use ($local) {
    $value = getenv($key);
    if ($value !== false) return $value;
    return $local[$key] ?? $default;
};

// ---- Database ----
define('DB_HOST',   $setting('DB_HOST', 'localhost'));
define('DB_PORT',   (int)$setting('DB_PORT', 3306));
define('DB_NAME',   $setting('DB_NAME', 'ecommerce_db'));
define('DB_USER',   $setting('DB_USER', 'root'));
define('DB_PASS',   $setting('DB_PASS', ''));          // XAMPP's root user has no password by default
define('DB_SSL_CA', $setting('DB_SSL_CA', ''));        // CA bundle path; set it to turn on TLS (needed for TiDB Cloud)

// ---- Account passwords ----
// Set these privately (config/local.php or Render's Environment tab). When set, the
// admin@example.com and customer@example.com accounts use these passwords instead of
// the public defaults (admin123 / customer123) from database.sql.
define('ADMIN_PASSWORD',    $setting('ADMIN_PASSWORD', ''));
define('CUSTOMER_PASSWORD', $setting('CUSTOMER_PASSWORD', ''));

// ---- Site ----
define('SITE_NAME', 'Kagaz');
define('BASE_URL', rtrim($setting('BASE_URL', '/ecommerce'), '/')); // folder inside htdocs, or "/" at a domain root
define('TIMEZONE', 'Asia/Kolkata');
define('CURRENCY_SIGN', '₹');                          // all prices are stored and shown in Indian rupees

// ---- PayPal (SANDBOX ONLY - no real money moves) ----
// Get these from https://developer.paypal.com > Apps & Credentials > Sandbox
define('PAYPAL_CLIENT_ID', $setting('PAYPAL_CLIENT_ID', 'YOUR_SANDBOX_CLIENT_ID'));
define('PAYPAL_SECRET',    $setting('PAYPAL_SECRET', 'YOUR_SANDBOX_SECRET'));
define('PAYPAL_API_BASE',  $setting('PAYPAL_API_BASE', 'https://api-m.sandbox.paypal.com'));

// PayPal no longer processes INR payments inside India (since April 2021),
// so the rupee total is converted to US dollars when it is sent to PayPal.
// This is a fixed demo rate: update it to the current rate if you like.
define('PAYPAL_CURRENCY', 'USD');
define('INR_PER_USD', (float)$setting('INR_PER_USD', 88.00));
