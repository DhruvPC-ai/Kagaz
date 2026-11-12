<?php
require __DIR__ . '/includes/init.php';
require_login();

$stmt = $pdo->prepare('SELECT * FROM orders WHERE user_id = ? ORDER BY created_at DESC, id DESC');
$stmt->execute([current_user()['id']]);
$orders = $stmt->fetchAll();

$itemStmt = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');

$pageTitle = 'My orders';
require __DIR__ . '/includes/header.php';
?>

<h1>My orders</h1>

<?php if (!$orders): ?>
    <div class="empty">
        <p>You haven't placed any orders yet.</p>
        <a class="btn" href="<?= url('index.php') ?>">Start shopping</a>
    </div>
<?php endif; ?>

<?php foreach ($orders as $order):
    $itemStmt->execute([$order['id']]);
    $items = $itemStmt->fetchAll(); ?>
    <section class="order">
        <header class="order-head">
            <div>
                <h2>Order #<?= $order['id'] ?></h2>
                <span class="muted small">Placed <?= date('j M Y, g:i a', strtotime($order['created_at'])) ?></span>
            </div>
            <div class="order-meta">
                <?= status_badge($order['status']) ?>
                <strong><?= money($order['total']) ?></strong>
            </div>
        </header>
        <ul class="order-items">
            <?php foreach ($items as $item): ?>
                <li><span><?= e($item['product_name']) ?> × <?= $item['quantity'] ?></span><span><?= money($item['price'] * $item['quantity']) ?></span></li>
            <?php endforeach; ?>
        </ul>
        <?php if ($order['status'] === 'pending'): ?>
            <a class="btn btn-small" href="<?= url('pay.php?id=' . $order['id']) ?>">Pay now</a>
        <?php endif; ?>
    </section>
<?php endforeach; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
