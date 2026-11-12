<?php
require __DIR__ . '/../includes/init.php';
require_admin();

$id = (int)($_GET['id'] ?? 0);
$product = ['name' => '', 'category_id' => '', 'price' => '', 'stock' => '', 'description' => '', 'image' => null];

if ($id) {
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ?');
    $stmt->execute([$id]);
    $product = $stmt->fetch();
    if (!$product) {
        flash('error', 'Product not found.');
        redirect('admin/products.php');
    }
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $product['name']        = trim($_POST['name'] ?? '');
    $product['category_id'] = (int)($_POST['category_id'] ?? 0) ?: null;
    $product['price']       = $_POST['price'] ?? '';
    $product['stock']       = $_POST['stock'] ?? '';
    $product['description'] = trim($_POST['description'] ?? '');

    if ($product['name'] === '')                                            $errors[] = 'Enter a product name.';
    if (!is_numeric($product['price']) || $product['price'] <= 0)           $errors[] = 'Enter a price greater than 0.';
    if (filter_var($product['stock'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]) === false)
                                                                            $errors[] = 'Stock must be a whole number, 0 or more.';

    // Optional image upload (validated by actual image content, not file name)
    $newImageMime = null;
    if (!empty($_FILES['image']['name'])) {
        $allowed = ['image/jpeg' => 'jpg', 'image/png' => 'png', 'image/gif' => 'gif', 'image/webp' => 'webp'];
        $info = $_FILES['image']['error'] === UPLOAD_ERR_OK ? @getimagesize($_FILES['image']['tmp_name']) : false;

        if (!$info || !isset($allowed[$info['mime']])) {
            $errors[] = 'Upload a JPG, PNG, GIF or WEBP image.';
        } elseif ($_FILES['image']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Images must be 2 MB or smaller.';
        } else {
            $newImageMime = $info['mime'];
        }
    }

    if (!$errors) {
        if ($newImageMime) {
            // Store the image in the database (works on hosts without persistent disk)
            $stmt = $pdo->prepare('INSERT INTO product_images (mime, data) VALUES (?, ?)');
            $stmt->bindValue(1, $newImageMime);
            $stmt->bindValue(2, file_get_contents($_FILES['image']['tmp_name']), PDO::PARAM_LOB);
            $stmt->execute();
            $newImageId = $pdo->lastInsertId(); // read before running any other query

            delete_product_image($pdo, $product['image']);
            $product['image'] = 'db:' . $newImageId;
        }

        $values = [$product['category_id'], $product['name'], $product['description'],
                   round((float)$product['price'], 2), (int)$product['stock'], $product['image']];

        if ($id) {
            $values[] = $id;
            $pdo->prepare('UPDATE products SET category_id = ?, name = ?, description = ?, price = ?, stock = ?, image = ?
                           WHERE id = ?')->execute($values);
            flash('success', 'Product saved.');
        } else {
            $pdo->prepare('INSERT INTO products (category_id, name, description, price, stock, image)
                           VALUES (?, ?, ?, ?, ?, ?)')->execute($values);
            flash('success', 'Product added.');
        }
        redirect('admin/products.php');
    }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY name')->fetchAll();

$pageTitle = $id ? 'Edit product' : 'Add product';
require __DIR__ . '/../includes/header.php';
require __DIR__ . '/../includes/admin_nav.php';
?>

<h1><?= $id ? 'Edit product' : 'Add product' ?></h1>

<?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>

<form method="post" enctype="multipart/form-data" class="form form-wide">
    <?= csrf_field() ?>
    <label>Name <input type="text" name="name" value="<?= e($product['name']) ?>" required></label>
    <label>Category
        <select name="category_id">
            <option value="">None</option>
            <?php foreach ($categories as $c): ?>
                <option value="<?= $c['id'] ?>" <?= (int)$product['category_id'] === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </label>
    <div class="row">
        <label>Price in ₹ (incl. GST) <input type="number" name="price" step="0.01" min="0.01" value="<?= e($product['price']) ?>" required></label>
        <label>Stock <input type="number" name="stock" min="0" step="1" value="<?= e($product['stock']) ?>" required></label>
    </div>
    <label>Description <textarea name="description" rows="5"><?= e($product['description']) ?></textarea></label>
    <label>Image <input type="file" name="image" accept="image/jpeg,image/png,image/gif,image/webp"></label>
    <?php if ($product['image']): ?>
        <img class="preview" src="<?= e(product_image($product['image'])) ?>" alt="Current image">
        <p class="muted small">Upload a new image to replace this one.</p>
    <?php endif; ?>
    <div class="actions">
        <button class="btn" type="submit"><?= $id ? 'Save product' : 'Add product' ?></button>
        <a class="btn btn-outline" href="<?= url('admin/products.php') ?>">Cancel</a>
    </div>
</form>

<?php require __DIR__ . '/../includes/footer.php'; ?>
