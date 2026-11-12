<?php
require __DIR__ . '/includes/init.php';

// ---- Handle cart actions ----
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $_SESSION['cart'] = $_SESSION['cart'] ?? [];
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        $id  = (int)($_POST['product_id'] ?? 0);
        $qty = max(1, (int)($_POST['quantity'] ?? 1));

        $stmt = $pdo->prepare('SELECT name, stock FROM products WHERE id = ?');
        $stmt->execute([$id]);
        $product = $stmt->fetch();

        if (!$product || $product['stock'] < 1) {
            flash('error', 'That product is out of stock.');
        } else {
            $newQty = ($_SESSION['cart'][$id] ?? 0) + $qty;
            if ($newQty > $product['stock']) {
                $newQty = (int)$product['stock'];
                flash('info', 'Only ' . $product['stock'] . ' of "' . $product['name'] . '" available, so your cart has the maximum.');
            } else {
                flash('success', '"' . $product['name'] . '" added to your cart.');
            }
            $_SESSION['cart'][$id] = $newQty;
        }
    }

    if ($action === 'update') {
        // A "Remove" button submits remove=<id>; otherwise update all quantities
        if (isset($_POST['remove'])) {
            unset($_SESSION['cart'][(int)$_POST['remove']]);
            flash('success', 'Item removed.');
        } else {
            $stmt = $pdo->prepare('SELECT stock FROM products WHERE id = ?');
            foreach ((array)($_POST['qty'] ?? []) as $id => $qty) {
                $id = (int)$id;
                $qty = (int)$qty;
                if ($qty <= 0) {
                    unset($_SESSION['cart'][$id]);
                    continue;
                }
                $stmt->execute([$id]);
                $stock = (int)$stmt->fetchColumn();
                $_SESSION['cart'][$id] = min($qty, $stock);
                if ($stock < 1) unset($_SESSION['cart'][$id]);
            }
            flash('success', 'Cart updated.');
        }
    }

    redirect('cart.php');
}

// ---- Show cart ----
$cart = cart_details($pdo);

$pageTitle = 'Cart';
require __DIR__ . '/includes/header.php';
?>

<h1>Your cart</h1>

<?php if (!$cart['items']): ?>
    <div class="empty">
        <p>Your cart is empty.</p>
        <a class="btn" href="<?= url('index.php') ?>">Browse products</a>
    </div>
<?php else: ?>
    <form method="post" action="<?= url('cart.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
        <div class="table-wrap">
            <table>
                <thead>
                    <tr><th>Product</th><th>Price</th><th>Quantity</th><th class="num">Subtotal</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($cart['items'] as $item): ?>
                    <tr>
                        <td class="cell-product">
                            <img src="<?= e(product_image($item['image'])) ?>" alt="">
                            <a href="<?= url('product.php?id=' . $item['id']) ?>"><?= e($item['name']) ?></a>
                        </td>
                        <td><?= money($item['price']) ?></td>
                        <td>
                            <input class="qty" type="number" name="qty[<?= $item['id'] ?>]" value="<?= $item['quantity'] ?>"
                                   min="0" max="<?= (int)$item['stock'] ?>" aria-label="Quantity for <?= e($item['name']) ?>">
                        </td>
                        <td class="num"><?= money($item['subtotal']) ?></td>
                        <td><button class="link-btn" type="submit" form="remove-form" name="remove" value="<?= $item['id'] ?>">Remove</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="cart-foot">
            <button class="btn btn-outline" type="submit">Update cart</button>
            <div class="cart-total">
                <span>Total <span class="muted small">(incl. all taxes)</span></span>
                <strong class="price price-lg"><?= money($cart['total']) ?></strong>
                <a class="btn" href="<?= url('checkout.php') ?>">Go to checkout</a>
            </div>
        </div>
    </form>

    <!-- Separate form so pressing Enter in a quantity box updates instead of removing -->
    <form id="remove-form" method="post" action="<?= url('cart.php') ?>">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update">
    </form>
<?php endif; ?>

<?php require __DIR__ . '/includes/footer.php'; ?>
