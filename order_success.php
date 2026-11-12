<?php
require __DIR__ . '/includes/init.php';
require_login();

$stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? AND user_id = ?');
$stmt->execute([(int)($_GET['id'] ?? 0), current_user()['id']]);
$order = $stmt->fetch();

if (!$order) redirect('orders.php');

$pageTitle = 'Order confirmed';
require __DIR__ . '/includes/header.php';
?>

<div class="confirm">
    <?php if ($order['status'] === 'paid'): ?>
        <h1>Thanks, your order is confirmed.</h1>
        <p>Order #<?= $order['id'] ?> for <strong><?= money($order['total']) ?></strong> was paid with PayPal
           (charged as <?= usd($order['amount_usd']) ?>).</p>
        <p>It will be delivered to <?= e($order['shipping_city']) ?>, <?= e($order['shipping_state']) ?>.</p>
        <p class="muted small">PayPal transaction ID: <?= e($order['paypal_capture_id']) ?></p>
    <?php else: ?>
        <h1>Order #<?= $order['id'] ?></h1>
        <p>Status: <?= status_badge($order['status']) ?></p>
    <?php endif; ?>
    <div class="actions">
        <a class="btn" href="<?= url('orders.php') ?>">View my orders</a>
        <a class="btn btn-outline" href="<?= url('index.php') ?>">Keep shopping</a>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
