<?php
/*
 * Shared setup for the JSON endpoints called by the PayPal button (paypal/*.php).
 * Guarantees the browser always receives valid JSON:
 *  - stray output (PHP warnings/notices, whitespace in an edited config file) is discarded
 *  - fatal errors and uncaught exceptions are returned as a readable JSON error message
 */
define('JSON_API', true);
ob_start();
ini_set('display_errors', '0');

function api_error(string $message, int $code = 500): void
{
    while (ob_get_level()) ob_end_clean();
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['error' => $message]);
    exit;
}

set_exception_handler(function (Throwable $e) {
    api_error('Server error: ' . $e->getMessage());
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        api_error('Server error: ' . $err['message'] . ' (' . basename($err['file']) . ' line ' . $err['line'] . ')');
    }
});

require __DIR__ . '/init.php';
require __DIR__ . '/paypal.php';
