<?php
require __DIR__ . '/../includes/init.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    verify_csrf();
    $stmt = $pdo->prepare('SELECT image FROM products WHERE id = ?');
    $stmt->execute([(int)$_POST['id']]);
    $image = $stmt->fetchColumn();

    $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([(int)$_POST['id']]);
    delete_product_image($pdo, $image);
    flash('success', 'Product deleted. Past orders still show it by name.');
    redirect('admin/products.php');
}

$products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p
                         LEFT JOIN categories c ON c.id = p.category_id ORDER BY p.id DESC')->fetchAll();

$pageTitle = 'Products';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>

<div class="page-head">
    <h1>Products</h1>
    <a class="btn" href="<?= url('admin/product_form.php') ?>">Add product</a>
</div>

<div class="table-wrap">
    <table>
        <thead><tr><th>Product</th><th>Category</th><th class="num">Price</th><th class="num">Stock</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $p): ?>
            <tr>
                <td class="cell-product">
                    <img src="<?= e(product_image($p['image'])) ?>" alt="">
                    <?= e($p['name']) ?>
                </td>
                <td><?= e($p['category_name'] ?? 'None') ?></td>
                <td class="num"><?= money($p['price']) ?></td>
                <td class="num"><?= (int)$p['stock'] ?></td>
                <td class="actions-cell">
                    <a href="<?= url('admin/product_form.php?id=' . $p['id']) ?>">Edit</a>
                    <form method="post" onsubmit="return confirm('Delete this product?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $p['id'] ?>">
                        <button class="link-btn danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        <?php if (!$products): ?>
            <tr><td colspan="5" class="muted">No products yet. Add your first one.</td></tr>
        <?php endif; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
