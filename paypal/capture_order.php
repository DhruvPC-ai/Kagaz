<?php
/*
 * Step 2 of the payment: the buyer approved the payment in PayPal, now capture it.
 * Verifies the PayPal order belongs to our order and that the captured amount matches,
 * then marks the order paid, reduces stock and empties the cart.
 */
require __DIR__ . '/../includes/api.php';

if (!is_logged_in()) json_response(['error' => 'Log in to pay.'], 401);
if (!csrf_valid())   json_response(['error' => 'Session expired. Refresh the page.'], 400);

$input         = json_decode(file_get_contents('php://input'), true) ?? [];
$orderId       = (int)($input['order_id'] ?? 0);
$paypalOrderId = (string)($input['paypal_order_id'] ?? '');

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ? AND status = \'pending\'');
$stmt->execute([$orderId, current_user()['id']]);
$order = $stmt->fetch();

if (!$order || $paypalOrderId === '' || $order['paypal_order_id'] !== $paypalOrderId) {
    json_response(['error' => 'Payment doesn\'t match this order.'], 400);
}

try {
    [$status, $res] = paypal_request('POST', '/v2/checkout/orders/' . rawurlencode($paypalOrderId) . '/capture');
} catch (RuntimeException $ex) {
    json_response(['error' => $ex->getMessage()], 502);
}

$capture = $res['purchase_units'][0]['payments']['captures'][0] ?? null;

if (!in_array($status, [200, 201], true) || ($res['status'] ?? '') !== 'COMPLETED'
    || !$capture || $capture['status'] !== 'COMPLETED') {
    $reason = $res['details'][0]['description'] ?? ($res['message'] ?? 'payment not completed');
    json_response(['error' => 'PayPal declined the payment: ' . $reason], 402);
}

// Double-check PayPal charged exactly what the order is worth
$expected = number_format((float)$order['amount_usd'], 2, '.', '');
if ($capture['amount']['value'] !== $expected || $capture['amount']['currency_code'] !== PAYPAL_CURRENCY) {
    json_response(['error' => 'Payment amount mismatch. Contact support with order #' . $order['id'] . '.'], 400);
}

$pdo->beginTransaction();
try {
    $update = $pdo->prepare('UPDATE orders SET status = \'paid\', paypal_capture_id = ?, paid_at = NOW()
                             WHERE id = ? AND status = \'pending\'');
    $update->execute([$capture['id'], $order['id']]);

    if ($update->rowCount() === 1) {
        $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
        $items->execute([$order['id']]);
        $reduce = $pdo->prepare('UPDATE products SET stock = GREATEST(stock - ?, 0) WHERE id = ?');
        foreach ($items->fetchAll() as $item) {
            if ($item['product_id']) {
                $reduce->execute([$item['quantity'], $item['product_id']]);
            }
        }
    }
    $pdo->commit();
} catch (Exception $ex) {
    $pdo->rollBack();
    json_response(['error' => 'Payment received but the order could not be updated. Contact support with order #' . $order['id'] . '.'], 500);
}

unset($_SESSION['cart']);
json_response(['success' => true]);
