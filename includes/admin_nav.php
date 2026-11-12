<?php $current = basename($_SERVER['PHP_SELF']); ?>
<nav class="admin-nav">
    <a href="<?= url('admin/index.php') ?>" class="<?= $current === 'index.php' ? 'active' : '' ?>">Dashboard</a>
    <a href="<?= url('admin/products.php') ?>" class="<?= in_array($current, ['products.php', 'product_form.php']) ? 'active' : '' ?>">Products</a>
    <a href="<?= url('admin/categories.php') ?>" class="<?= $current === 'categories.php' ? 'active' : '' ?>">Categories</a>
    <a href="<?= url('admin/orders.php') ?>" class="<?= $current === 'orders.php' ? 'active' : '' ?>">Orders</a>
</nav>
