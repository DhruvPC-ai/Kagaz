<?php
/*
 * Step 1 of the payment: create a PayPal order for one of our pending orders.
 * Called by the PayPal button (fetch) on pay.php. Returns JSON: { id: "<paypal order id>" }.
 */
require __DIR__ . '/../includes/api.php';

if (!is_logged_in()) json_response(['error' => 'Log in to pay.'], 401);
if (!csrf_valid())   json_response(['error' => 'Session expired. Refresh the page.'], 400);

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId = (int)($input['order_id'] ?? 0);

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = \'pending\'');
$stmt->execute([$orderId, current_user()['id']]);
$order = $stmt->fetch();
if (!$order) json_response(['error' => 'This order can\'t be paid.'], 404);

try {
    // The amount comes from OUR database, never from the browser.
    // amount_usd is the rupee total converted at checkout (PayPal doesn't accept INR in India).
    [$status, $res] = paypal_request('POST', '/v2/checkout/orders', [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => (string)$order['id'],
            'description'  => SITE_NAME . ' order #' . $order['id'] . ' (' . money($order['total']) . ')',
            'amount' => [
                'currency_code' => PAYPAL_CURRENCY,
                'value'         => number_format((float)$order['amount_usd'], 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'brand_name'          => SITE_NAME,
            'shipping_preference' => 'NO_SHIPPING', // we already collected the address
            'user_action'         => 'PAY_NOW',
        ],
    ]);
} catch (RuntimeException $ex) {
    json_response(['error' => $ex->getMessage()], 502);
}

if ($status !== 201 || empty($res['id'])) {
    json_response(['error' => 'PayPal could not create the payment: ' . ($res['message'] ?? 'unknown error')], 502);
}

// Remember which PayPal order belongs to this order so capture can verify it
$pdo->prepare('UPDATE orders SET paypal_order_id = ? WHERE id = ?')->execute([$res['id'], $order['id']]);

json_response(['id' => $res['id']]);
