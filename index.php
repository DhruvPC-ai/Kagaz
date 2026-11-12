<?php
require __DIR__ . '/includes/init.php';

$search     = trim($_GET['q'] ?? '');
$categoryId = (int)($_GET['category'] ?? 0);

$sql = 'SELECT p.*, c.name AS category_name
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE 1';
$params = [];

if ($search !== '') {
    $sql .= ' AND (p.name LIKE ? OR p.description LIKE ?)';
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($categoryId) {
    $sql .= ' AND p.category_id = ?';
    $params[] = $categoryId;
}
$sql .= ' ORDER BY p.created_at DESC, p.id DESC';

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$products = $stmt->fetchAll();

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$pageTitle = 'Shop';
require __DIR__ . '/includes/header.php';
?>

<section class="hero">
    <h1>Paper, pens and the things that sit on a desk.</h1>
    <p>Handmade paper from Sanganer, brass from Moradabad, wood from Channapatna, and the everyday pens and notebooks in between.</p>
</section>

<form class="filters" method="get" action="<?= url('index.php') ?>">
    <div class="chips">
        <a href="<?= url('index.php' . ($search !== '' ? '?q=' . urlencode($search) : '')) ?>"
           class="chip <?= !$categoryId ? 'active' : '' ?>">All</a>
        <?php foreach ($categories as $c): ?>
            <a href="<?= url('index.php?category=' . $c['id'] . ($search !== '' ? '&q=' . urlencode($search) : '')) ?>"
               class="chip <?= $categoryId === (int)$c['id'] ? 'active' : '' ?>"><?= e($c['name']) ?></a>
        <?php endforeach; ?>
    </div>
    <div class="search">
        <?php if ($categoryId): ?><input type="hidden" name="category" value="<?= $categoryId ?>"><?php endif; ?>
        <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search products" aria-label="Search products">
        <button class="btn btn-outline" type="submit">Search</button>
    </div>
</form>

<?php if (!$products): ?>
    <div class="empty">
        <p>No products match that search.</p>
        <a class="btn" href="<?= url('index.php') ?>">See all products</a>
    </div>
<?php else: ?>
    <div class="grid">
        <?php foreach ($products as $p): ?>
            <article class="product">
                <a href="<?= url('product.php?id=' . $p['id']) ?>" class="product-img">
                    <img src="<?= e(product_image($p['image'])) ?>" alt="<?= e($p['name']) ?>" loading="lazy">
                </a>
                <div class="product-body">
                    <?php if ($p['category_name']): ?><span class="muted small"><?= e($p['category_name']) ?></span><?php endif; ?>
                    <h2><a href="<?= url('product.php?id=' . $p['id']) ?>"><?= e($p['name']) ?></a></h2>
                    <div class="product-foot">
                        <span class="price"><?= money($p['price']) ?></span>
                        <?php if ($p['stock'] > 0): ?>
                            <form method="post" action="<?= url('cart.php') ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="add">
                                <input type="hidden" name="product_id" value="<?= $p['id'] ?>">
                                <input type="hidden" name="quantity" value="1">
                                <button class="btn btn-small" type="submit">Add to cart</button>
                            </form>
                        <?php else: ?>
                            <span class="muted small">Out of stock</span>
                        <?php endif; ?>
                    </div>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
