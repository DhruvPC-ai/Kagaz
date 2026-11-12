<?php
/*
 * Minimal PayPal Orders v2 REST client (sandbox).
 * Flow: get OAuth access token -> create order -> buyer approves -> capture order.
 *
 * HTTP calls use cURL when the extension is loaded, and fall back to PHP's built-in
 * HTTPS streams otherwise (handy on XAMPP installs where php_curl.dll won't load).
 */

function paypal_configured(): bool
{
    return PAYPAL_CLIENT_ID !== 'YOUR_SANDBOX_CLIENT_ID' && PAYPAL_SECRET !== 'YOUR_SANDBOX_SECRET';
}

/**
 * Send an HTTP request. Returns [statusCode, responseBody].
 */
function http_send(string $method, string $url, array $headers, ?string $body): array
{
    // ---- Option 1: cURL ----
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $headers,
            CURLOPT_TIMEOUT        => 30,
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $response = curl_exec($ch);
        if ($response === false) {
            throw new RuntimeException('Could not reach PayPal: ' . curl_error($ch));
        }
        return [curl_getinfo($ch, CURLINFO_HTTP_CODE), $response];
    }

    // ---- Option 2: PHP streams (needs the openssl extension for https) ----
    if (str_starts_with($url, 'https://') && !extension_loaded('openssl')) {
        throw new RuntimeException('Neither the curl nor the openssl PHP extension is enabled, so PHP cannot make HTTPS requests. Enable one of them in php.ini and restart Apache.');
    }
    $headers[] = 'Content-Length: ' . strlen($body ?? '');
    $context = stream_context_create([
        'http' => [
            'method'        => $method,
            'header'        => implode("\r\n", $headers),
            'content'       => $body ?? '',
            'timeout'       => 30,
            'ignore_errors' => true, // still return the body for 4xx/5xx so PayPal's error message can be read
        ],
    ]);
    $response = @file_get_contents($url, false, $context);
    if ($response === false) {
        $reason = error_get_last()['message'] ?? 'unknown error';
        throw new RuntimeException('Could not reach PayPal: ' . $reason);
    }

    // Status line is the first response header, e.g. "HTTP/1.1 201 Created"
    $responseHeaders = function_exists('http_get_last_response_headers')
        ? http_get_last_response_headers()
        : ($http_response_header ?? []);
    preg_match('#^HTTP/\S+\s+(\d{3})#', $responseHeaders[0] ?? '', $m);
    return [(int)($m[1] ?? 0), $response];
}

/** Exchange client ID + secret for a short-lived OAuth access token. */
function paypal_access_token(): string
{
    [$status, $body] = http_send('POST', PAYPAL_API_BASE . '/v1/oauth2/token', [
        'Accept: application/json',
        'Content-Type: application/x-www-form-urlencoded',
        'Authorization: Basic ' . base64_encode(PAYPAL_CLIENT_ID . ':' . PAYPAL_SECRET),
    ], 'grant_type=client_credentials');

    $data = json_decode($body, true);
    if ($status !== 200 || empty($data['access_token'])) {
        throw new RuntimeException('PayPal rejected the credentials. Check PAYPAL_CLIENT_ID and PAYPAL_SECRET in config/local.php (or the Render environment variables), and that they are Sandbox keys.');
    }
    return $data['access_token'];
}

/**
 * Call a PayPal REST endpoint. Returns [httpStatus, decodedJsonBody].
 */
function paypal_request(string $method, string $path, ?array $payload = null): array
{
    $headers = [
        'Content-Type: application/json',
        'Authorization: Bearer ' . paypal_access_token(),
    ];
    // Capture calls send an empty body, create calls send JSON
    $body = $method === 'POST' ? ($payload === null ? '' : json_encode($payload)) : null;

    [$status, $response] = http_send($method, PAYPAL_API_BASE . $path, $headers, $body);
    return [$status, json_decode($response, true) ?? []];
}
