<?php
require __DIR__ . '/includes/init.php';
require_login();

$cart = cart_details($pdo);
if (!$cart['items']) {
    flash('info', 'Your cart is empty.');
    redirect('cart.php');
}

// Make sure nothing in the cart exceeds current stock
foreach ($cart['items'] as $item) {
    if ($item['quantity'] > $item['stock']) {
        flash('error', 'Only ' . $item['stock'] . ' of "' . $item['name'] . '" left. Update your cart to continue.');
        redirect('cart.php');
    }
}

$user = current_user();
$form = ['name' => $user['name'], 'phone' => '', 'address' => '', 'city' => '', 'state' => '', 'zip' => ''];
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    foreach ($form as $key => $_) {
        $form[$key] = trim($_POST[$key] ?? '');
    }

    // Indian mobile: optional +91 / 0 prefix, then 10 digits starting with 6-9
    $mobile = preg_replace('/[\s\-]/', '', $form['phone']);
    $mobile = preg_replace('/^(\+91|91|0)(?=\d{10}$)/', '', $mobile);

    if ($form['name'] === '')                            $errors[] = 'Enter the recipient\'s name.';
    if (!preg_match('/^[6-9]\d{9}$/', $mobile))         $errors[] = 'Enter a valid 10-digit mobile number.';
    if ($form['address'] === '')                         $errors[] = 'Enter the house number, building and street.';
    if ($form['city'] === '')                            $errors[] = 'Enter a city or town.';
    if (!in_array($form['state'], indian_states(), true)) $errors[] = 'Choose a state.';
    if (!preg_match('/^[1-9]\d{5}$/', $form['zip']))     $errors[] = 'Enter a valid 6-digit PIN code.';

    if (!$errors) {
        // Create the order as "pending". It becomes "paid" only after PayPal capture succeeds.
        $pdo->beginTransaction();
        try {
            $stmt = $pdo->prepare('INSERT INTO orders
                (user_id, total, amount_usd, shipping_name, shipping_phone, shipping_address,
                 shipping_city, shipping_state, shipping_zip)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
            $stmt->execute([$user['id'], $cart['total'], to_paypal_amount($cart['total']), $form['name'],
                            '+91 ' . $mobile, $form['address'], $form['city'], $form['state'], $form['zip']]);
            $orderId = (int)$pdo->lastInsertId();

            $itemStmt = $pdo->prepare('INSERT INTO order_items (order_id, product_id, product_name, price, quantity)
                                       VALUES (?, ?, ?, ?, ?)');
            foreach ($cart['items'] as $item) {
                $itemStmt->execute([$orderId, $item['id'], $item['name'], $item['price'], $item['quantity']]);
            }
            $pdo->commit();
        } catch (Exception $ex) {
            $pdo->rollBack();
            throw $ex;
        }

        redirect('pay.php?id=' . $orderId);
    }
}

$pageTitle = 'Checkout';
require __DIR__ . '/includes/header.php';
?>

<h1>Checkout</h1>

<div class="two-col">
    <section>
        <h2>Shipping details</h2>
        <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
        <form method="post" class="form">
            <?= csrf_field() ?>
            <label>Full name <input type="text" name="name" value="<?= e($form['name']) ?>" required></label>
            <label>Mobile number
                <input type="tel" name="phone" value="<?= e($form['phone']) ?>" placeholder="98765 43210" maxlength="16" required>
            </label>
            <label>Address <span class="hint">House no., building, street, area</span>
                <input type="text" name="address" value="<?= e($form['address']) ?>" required>
            </label>
            <div class="row">
                <label>City / town <input type="text" name="city" value="<?= e($form['city']) ?>" required></label>
                <label>PIN code
                    <input type="text" name="zip" value="<?= e($form['zip']) ?>" inputmode="numeric" pattern="[1-9][0-9]{5}" maxlength="6" required>
                </label>
            </div>
            <label>State
                <select name="state" required>
                    <option value="">Choose a state</option>
                    <?php foreach (indian_states() as $state): ?>
                        <option <?= $form['state'] === $state ? 'selected' : '' ?>><?= e($state) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <button class="btn btn-block" type="submit">Continue to payment</button>
        </form>
    </section>

    <aside class="summary">
        <h2>Order summary</h2>
        <?php foreach ($cart['items'] as $item): ?>
            <div class="summary-line">
                <span><?= e($item['name']) ?> × <?= $item['quantity'] ?></span>
                <span><?= money($item['subtotal']) ?></span>
            </div>
        <?php endforeach; ?>
        <div class="summary-line summary-total">
            <span>Total</span><span class="price"><?= money($cart['total']) ?></span>
        </div>
        <p class="muted small">Inclusive of all taxes. Free delivery across India.</p>
        <a class="muted small" href="<?= url('cart.php') ?>">Edit cart</a>
    </aside>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
