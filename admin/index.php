<?php
require __DIR__ . '/../includes/init.php';
require_admin();

$stats = [
    'Revenue'   => money($pdo->query("SELECT COALESCE(SUM(total), 0) FROM orders WHERE status IN ('paid','shipped','delivered')")->fetchColumn()),
    'Paid orders' => $pdo->query("SELECT COUNT(*) FROM orders WHERE status IN ('paid','shipped','delivered')")->fetchColumn(),
    'Products'  => $pdo->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'Customers' => $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'customer'")->fetchColumn(),
];

$recent = $pdo->query('SELECT o.*, u.name AS customer FROM orders o JOIN users u ON u.id = o.user_id
                       ORDER BY o.created_at DESC, o.id DESC LIMIT 5')->fetchAll();
$lowStock = $pdo->query('SELECT id, name, stock FROM products WHERE stock <= 5 ORDER BY stock')->fetchAll();

$pageTitle = 'Admin dashboard';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>

<h1>Dashboard</h1>

<div class="stats">
    <?php foreach ($stats as $label => $value): ?>
        <div class="stat"><span class="muted small"><?= e($label) ?></span><strong><?= e($value) ?></strong></div>
    <?php endforeach; ?>
</div>

<div class="two-col">
    <section>
        <h2>Recent orders</h2>
        <?php if (!$recent): ?>
            <p class="muted">No orders yet.</p>
        <?php else: ?>
            <div class="table-wrap">
                <table>
                    <thead><tr><th>Order</th><th>Customer</th><th>Status</th><th class="num">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($recent as $o): ?>
                        <tr>
                            <td><a href="<?= url('admin/orders.php?id=' . $o['id']) ?>">#<?= $o['id'] ?></a></td>
                            <td><?= e($o['customer']) ?></td>
                            <td><?= status_badge($o['status']) ?></td>
                            <td class="num"><?= money($o['total']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
    <aside class="summary">
        <h2>Low stock</h2>
        <?php if (!$lowStock): ?>
            <p class="muted">Every product has more than 5 in stock.</p>
        <?php endif; ?>
        <?php foreach ($lowStock as $p): ?>
            <div class="summary-line">
                <a href="<?= url('admin/product_form.php?id=' . $p['id']) ?>"><?= e($p['name']) ?></a>
                <span><?= (int)$p['stock'] ?> left</span>
            </div>
        <?php endforeach; ?>
    </aside>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
