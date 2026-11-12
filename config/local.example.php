<?php
/*
 * Local settings for XAMPP.
 * Copy this file to config/local.php and fill in your values.
 * config/local.php is listed in .gitignore, so your PayPal secret never ends up on GitHub.
 */
return [
    'PAYPAL_CLIENT_ID' => 'YOUR_SANDBOX_CLIENT_ID',
    'PAYPAL_SECRET'    => 'YOUR_SANDBOX_SECRET',

    // Private passwords for the two built-in accounts (leave empty to keep admin123 / customer123)
    'ADMIN_PASSWORD'    => '',
    'CUSTOMER_PASSWORD' => '',

    // Only needed if your MySQL setup differs from XAMPP's defaults:
    // 'DB_PASS' => '',
    // 'BASE_URL' => '/ecommerce',
];
