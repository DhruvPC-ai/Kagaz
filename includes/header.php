<?php
/** @var string $pageTitle  set by each page before including this file */
$pageTitle = isset($pageTitle) ? $pageTitle . ' | ' . SITE_NAME : SITE_NAME;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,400;12..96,600;12..96,800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= url('assets/css/style.css') ?>">
</head>
<body>
<header class="site-header">
    <div class="wrap nav">
        <a class="logo" href="<?= url('index.php') ?>"><?= e(SITE_NAME) ?></a>
        <nav>
            <a href="<?= url('index.php') ?>">Shop</a>
            <a href="<?= url('cart.php') ?>">Cart<?php if (cart_count()): ?> <span class="count"><?= cart_count() ?></span><?php endif; ?></a>
            <?php if (is_logged_in()): ?>
                <a href="<?= url('orders.php') ?>">My orders</a>
                <?php if (is_admin()): ?><a href="<?= url('admin/index.php') ?>">Admin</a><?php endif; ?>
                <a href="<?= url('logout.php') ?>">Log out</a>
            <?php else: ?>
                <a href="<?= url('login.php') ?>">Log in</a>
                <a class="btn btn-small" href="<?= url('register.php') ?>">Sign up</a>
            <?php endif; ?>
        </nav>
    </div>
</header>
<main class="wrap">
<?php foreach (get_flashes() as $f): ?>
    <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['message']) ?></div>
<?php endforeach; ?>
