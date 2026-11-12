<?php
require __DIR__ . '/../includes/init.php';
require_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $name = trim($_POST['name'] ?? '');
        if ($name === '') {
            flash('error', 'Enter a category name.');
        } else {
            try {
                $pdo->prepare('INSERT INTO categories (name) VALUES (?)')->execute([$name]);
                flash('success', 'Category "' . $name . '" added.');
            } catch (PDOException $ex) {
                flash('error', 'A category with that name already exists.');
            }
        }
    }

    if ($action === 'delete') {
        $pdo->prepare('DELETE FROM categories WHERE id = ?')->execute([(int)$_POST['id']]);
        flash('success', 'Category deleted. Its products are now uncategorised.');
    }
    redirect('admin/categories.php');
}

$categories = $pdo->query('SELECT c.*, COUNT(p.id) AS product_count FROM categories c
                           LEFT JOIN products p ON p.category_id = c.id
                           GROUP BY c.id ORDER BY c.name')->fetchAll();

$pageTitle = 'Categories';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>

<h1>Categories</h1>

<form method="post" class="inline-form">
    <?= csrf_field() ?>
    <input type="hidden" name="action" value="add">
    <input type="text" name="name" placeholder="New category name" required aria-label="New category name">
    <button class="btn" type="submit">Add category</button>
</form>

<div class="table-wrap">
    <table>
        <thead><tr><th>Name</th><th class="num">Products</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($categories as $c): ?>
            <tr>
                <td><?= e($c['name']) ?></td>
                <td class="num"><?= (int)$c['product_count'] ?></td>
                <td class="actions-cell">
                    <form method="post" onsubmit="return confirm('Delete this category?');">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= $c['id'] ?>">
                        <button class="link-btn danger" type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
