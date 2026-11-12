<?php
require __DIR__ . '/includes/init.php';
require __DIR__ . '/includes/paypal.php';
require_login();

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([(int)($_GET['id'] ?? 0), current_user()['id']]);
$order = $stmt->fetch();

if (!$order) {
    flash('error', 'Order not found.');
    redirect('orders.php');
}
if ($order['status'] !== 'pending') {
    flash('info', 'Order #' . $order['id'] . ' is ' . $order['status'] . ', so there is nothing to pay.');
    redirect('orders.php');
}

$items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
$items->execute([$order['id']]);
$items = $items->fetchAll();

$pageTitle = 'Payment';
require __DIR__ . '/includes/header.php';
?>

<h1>Pay for order #<?= $order['id'] ?></h1>

<div class="two-col">
    <section>
        <h2>Pay with PayPal</h2>
        <?php if (!paypal_configured()): ?>
            <div class="flash flash-error">
                PayPal isn't set up yet. Add your sandbox Client ID and Secret to <code>config/config.php</code>, then reload this page.
            </div>
        <?php else: ?>
            <p class="muted">You'll log in with a PayPal <strong>sandbox</strong> buyer account. No real money is charged.</p>
            <div class="flash flash-info">
                PayPal can't take rupee payments within India, so your <?= money($order['total']) ?> is charged as
                <strong><?= usd($order['amount_usd']) ?></strong> (₹<?= number_format(INR_PER_USD, 2) ?> = $1).
            </div>
            <div id="pay-message" class="flash" hidden></div>
            <div id="paypal-button-container"></div>
        <?php endif; ?>
    </section>

    <aside class="summary">
        <h2>Order summary</h2>
        <?php foreach ($items as $item): ?>
            <div class="summary-line">
                <span><?= e($item['product_name']) ?> × <?= $item['quantity'] ?></span>
                <span><?= money($item['price'] * $item['quantity']) ?></span>
            </div>
        <?php endforeach; ?>
        <div class="summary-line summary-total">
            <span>Total</span><span class="price"><?= money($order['total']) ?></span>
        </div>
        <p class="muted small">Inclusive of all taxes.</p>
        <h3>Shipping to</h3>
        <p class="small">
            <?= e($order['shipping_name']) ?><br>
            <?= e($order['shipping_address']) ?><br>
            <?= e($order['shipping_city']) ?>, <?= e($order['shipping_state']) ?> <?= e($order['shipping_zip']) ?><br>
            <?= e($order['shipping_phone']) ?>
        </p>
    </aside>
</div>

<?php if (paypal_configured()): ?>
<script src="https://www.paypal.com/sdk/js?client-id=<?= e(PAYPAL_CLIENT_ID) ?>&currency=<?= e(PAYPAL_CURRENCY) ?>&intent=capture"></script>
<script>
    const ORDER_ID = <?= (int)$order['id'] ?>;
    const CSRF = <?= json_encode(csrf_token()) ?>;
    const message = document.getElementById('pay-message');

    function showMessage(text, type) {
        message.textContent = text;
        message.className = 'flash flash-' + type;
        message.hidden = false;
    }

    // POST to our server; if the reply isn't JSON, show what the server actually said
    function post(url, data) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(data)
        })
            .then(res => res.text())
            .then(text => {
                try {
                    return JSON.parse(text);
                } catch (e) {
                    const plain = text.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
                    throw new Error('The server sent an unexpected reply: ' + (plain.slice(0, 300) || '(empty response)'));
                }
            });
    }

    paypal.Buttons({
        // 1. Our server creates the PayPal order using the total stored in our database
        createOrder: () =>
            post(<?= json_encode(url('paypal/create_order.php')) ?>, { order_id: ORDER_ID })
                .then(data => {
                    if (!data.id) {
                        const msg = data.error || 'Could not start the payment.';
                        showMessage(msg, 'error');
                        throw new Error(msg);
                    }
                    return data.id;
                }),

        // 2. Buyer approved in the PayPal popup -> our server captures the payment
        onApprove: (data) =>
            post(<?= json_encode(url('paypal/capture_order.php')) ?>, { order_id: ORDER_ID, paypal_order_id: data.orderID })
                .then(result => {
                    if (result.success) {
                        window.location.href = <?= json_encode(url('order_success.php?id=')) ?> + ORDER_ID;
                    } else {
                        showMessage(result.error || 'The payment could not be completed. Try again.', 'error');
                    }
                }),

        onCancel: () => showMessage('Payment cancelled. Your order is saved, so you can pay whenever you\'re ready.', 'info'),
        onError: (err) => {
            console.error(err);
            showMessage(err.message || 'Something went wrong with PayPal. Try again.', 'error');
        }
    }).render('#paypal-button-container');
</script>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
