<?php
require __DIR__ . '/../includes/init.php';
require_admin();

$statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];

// ---- Update an order's status ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $orderId = (int)($_POST['id'] ?? 0);
    $status  = $_POST['status'] ?? '';
    if (in_array($status, $statuses, true)) {
        $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?')->execute([$status, $orderId]);
        flash('success', 'Order #' . $orderId . ' marked as ' . $status . '.');
    }
    redirect('admin/orders.php?id=' . $orderId);
}

// ---- Single order view ----
if (isset($_GET['id'])) {
    $stmt = $pdo->prepare('SELECT o.*, u.name AS customer, u.email FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?');
    $stmt->execute([(int)$_GET['id']]);
    $order = $stmt->fetch();
    if (!$order) {
        flash('error', 'Order not found.');
        redirect('admin/orders.php');
    }
    $items = $pdo->prepare('SELECT * FROM order_items WHERE order_id = ?');
    $items->execute([$order['id']]);
    $items = $items->fetchAll();
}
// ---- Order list ----
else {
    $filter = in_array($_GET['status'] ?? '', $statuses, true) ? $_GET['status'] : '';
    $sql = 'SELECT o.*, u.name AS customer FROM orders o JOIN users u ON u.id = o.user_id';
    $stmt = $pdo->prepare($sql . ($filter ? ' WHERE o.status = ?' : '') . ' ORDER BY o.created_at DESC, o.id DESC');
    $stmt->execute($filter ? [$filter] : []);
    $orders = $stmt->fetchAll();
}

$pageTitle = 'Orders';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>

<?php if (isset($order)): ?>
    <a class="back" href="<?= url('admin/orders.php') ?>">All orders</a>
    <div class="page-head">
        <h1>Order #<?= $order['id'] ?></h1>
        <?= status_badge($order['status']) ?>
    </div>

    <div class="two-col">
        <section>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Product</th><th class="num">Price</th><th class="num">Qty</th><th class="num">Subtotal</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td><?= e($item['product_name']) ?></td>
                            <td class="num"><?= money($item['price']) ?></td>
                            <td class="num"><?= $item['quantity'] ?></td>
                            <td class="num"><?= money($item['price'] * $item['quantity']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <tr><td colspan="3"><strong>Total</strong></td><td class="num"><strong><?= money($order['total']) ?></strong></td></tr>
                    </tbody>
                </table>
            </div>

            <form method="post" class="inline-form">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= $order['id'] ?>">
                <label for="status">Status</label>
                <select id="status" name="status">
                    <?php foreach ($statuses as $s): ?>
                        <option value="<?= $s ?>" <?= $s === $order['status'] ? 'selected' : '' ?>><?= ucfirst($s) ?></option>
                    <?php endforeach; ?>
                </select>
                <button class="btn" type="submit">Update status</button>
            </form>
        </section>

        <aside class="summary">
            <h2>Customer</h2>
            <p class="small"><?= e($order['customer']) ?><br><?= e($order['email']) ?></p>
            <h3>Ship to</h3>
            <p class="small">
                <?= e($order['shipping_name']) ?><br>
                <?= e($order['shipping_address']) ?><br>
                <?= e($order['shipping_city']) ?>, <?= e($order['shipping_state']) ?> <?= e($order['shipping_zip']) ?><br>
                <?= e($order['shipping_phone']) ?>
            </p>
            <h3>Payment</h3>
            <p class="small">
                Placed <?= date('j M Y, g:i a', strtotime($order['created_at'])) ?><br>
                <?php if ($order['paid_at']): ?>
                    Paid <?= date('j M Y, g:i a', strtotime($order['paid_at'])) ?><br>
                    PayPal charged: <?= usd($order['amount_usd']) ?><br>
                    PayPal transaction: <?= e($order['paypal_capture_id']) ?>
                <?php else: ?>
                    Not paid
                <?php endif; ?>
            </p>
        </aside>
    </div>

<?php else: ?>
    <h1>Orders</h1>
    <div class="chips">
        <a class="chip <?= !$filter ? 'active' : '' ?>" href="<?= url('admin/orders.php') ?>">All</a>
        <?php foreach ($statuses as $s): ?>
            <a class="chip <?= $filter === $s ? 'active' : '' ?>" href="<?= url('admin/orders.php?status=' . $s) ?>"><?= ucfirst($s) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Order</th><th>Date</th><th>Customer</th><th>Status</th><th class="num">Total</th></tr></thead>
            <tbody>
            <?php foreach ($orders as $o): ?>
                <tr>
                    <td><a href="<?= url('admin/orders.php?id=' . $o['id']) ?>">#<?= $o['id'] ?></a></td>
                    <td><?= date('j M Y', strtotime($o['created_at'])) ?></td>
                    <td><?= e($o['customer']) ?></td>
                    <td><?= status_badge($o['status']) ?></td>
                    <td class="num"><?= money($o['total']) ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$orders): ?>
                <tr><td colspan="5" class="muted">No orders<?= $filter ? ' with this status' : '' ?>.</td></tr>
            <?php endif; ?>
            </tbody>
        </table>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
