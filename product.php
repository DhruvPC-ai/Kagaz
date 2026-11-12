<?php
require __DIR__ . '/includes/init.php';

$stmt = $pdo->prepare('SELECT p.*, c.name AS category_name
                       FROM products p LEFT JOIN categories c ON c.id = p.category_id
                       WHERE p.id = ?');
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$product = $stmt->fetch();

if (!$product) {
    flash('error', 'That product no longer exists.');
    redirect('index.php');
}

$inCart = $_SESSION['cart'][$product['id']] ?? 0;

$pageTitle = $product['name'];
require __DIR__ . '/includes/header.php';
?>

<a class="back" href="<?= url('index.php') ?>">Back to shop</a>

<div class="detail">
    <div class="detail-img">
        <img src="<?= e(product_image($product['image'])) ?>" alt="<?= e($product['name']) ?>">
    </div>
    <div class="detail-info">
        <?php if ($product['category_name']): ?>
            <a class="muted" href="<?= url('index.php?category=' . $product['category_id']) ?>"><?= e($product['category_name']) ?></a>
        <?php endif; ?>
        <h1><?= e($product['name']) ?></h1>
        <p class="price-block"><span class="price price-lg"><?= money($product['price']) ?></span>
            <span class="muted small">Inclusive of all taxes</span></p>
        <p><?= nl2br(e($product['description'])) ?></p>

        <?php if ($product['stock'] > 0): ?>
            <p class="muted small"><?= (int)$product['stock'] ?> in stock.<?= $inCart ? ' ' . $inCart . ' already in your cart.' : '' ?></p>
            <form method="post" action="<?= url('cart.php') ?>" class="add-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?= $product['id'] ?>">
                <label for="qty">Quantity</label>
                <input id="qty" type="number" name="quantity" value="1" min="1" max="<?= (int)$product['stock'] ?>">
                <button class="btn" type="submit">Add to cart</button>
            </form>
        <?php else: ?>
            <p class="flash flash-info">Out of stock right now.</p>
        <?php endif; ?>
    </div>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
