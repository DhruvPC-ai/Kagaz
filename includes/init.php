<?php
/*
 * Bootstrap file included at the top of every page.
 */
$isHttps = ($_SERVER['HTTPS'] ?? '') === 'on' || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
session_set_cookie_params(['httponly' => true, 'samesite' => 'Lax', 'secure' => $isHttps]);
session_start();

require_once __DIR__ . '/../config/config.php';
date_default_timezone_set(TIMEZONE);

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
