<?php
/*
 * Small helper functions used across the site.
 */

// ---------- Output & navigation ----------

/** Escape a value for safe HTML output (prevents XSS). */
function e($value): string
{
    return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
}

/** Build a site URL relative to BASE_URL. */
function url(string $path = ''): string
{
    return BASE_URL . '/' . ltrim($path, '/');
}

function redirect(string $path): void
{
    header('Location: ' . url($path));
    exit;
}

/**
 * Format rupees the Indian way: ₹1,00,000 (lakh grouping). Paise shown only when non-zero.
 */
function money($amount): string
{
    $amount = round((float)$amount, 2);
    [$rupees, $paise] = explode('.', number_format(abs($amount), 2, '.', ''));

    if (strlen($rupees) > 3) {
        $lastThree = substr($rupees, -3);
        $rest      = substr($rupees, 0, -3);
        $rupees    = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest) . ',' . $lastThree;
    }
    return ($amount < 0 ? '-' : '') . CURRENCY_SIGN . $rupees . ($paise !== '00' ? '.' . $paise : '');
}

/** Rupee amount converted to the PayPal currency (USD), as a 2-decimal string. */
function to_paypal_amount($inr): string
{
    return number_format(max(0.01, round((float)$inr / INR_PER_USD, 2)), 2, '.', '');
}

function usd($amount): string
{
    return '$' . number_format((float)$amount, 2) . ' ' . PAYPAL_CURRENCY;
}

function indian_states(): array
{
    return [
        'Andhra Pradesh', 'Arunachal Pradesh', 'Assam', 'Bihar', 'Chhattisgarh', 'Goa', 'Gujarat',
        'Haryana', 'Himachal Pradesh', 'Jharkhand', 'Karnataka', 'Kerala', 'Madhya Pradesh',
        'Maharashtra', 'Manipur', 'Meghalaya', 'Mizoram', 'Nagaland', 'Odisha', 'Punjab',
        'Rajasthan', 'Sikkim', 'Tamil Nadu', 'Telangana', 'Tripura', 'Uttar Pradesh',
        'Uttarakhand', 'West Bengal',
        // Union territories
        'Andaman and Nicobar Islands', 'Chandigarh', 'Dadra and Nagar Haveli and Daman and Diu',
        'Delhi', 'Jammu and Kashmir', 'Ladakh', 'Lakshadweep', 'Puducherry',
    ];
}

function product_image(?string $image): string
{
    if (!$image) return url('assets/img/placeholder.svg');
    if (str_starts_with($image, 'db:')) return url('image.php?id=' . (int)substr($image, 3)); // uploaded, stored in DB
    return url('assets/products/' . $image);                                                  // bundled sample image
}

/** Remove an uploaded image from the database (bundled sample images are left alone). */
function delete_product_image(PDO $pdo, ?string $image): void
{
    if ($image && str_starts_with($image, 'db:')) {
        $pdo->prepare('DELETE FROM product_images WHERE id = ?')->execute([(int)substr($image, 3)]);
    }
}

// ---------- Flash messages ----------

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $messages = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $messages;
}

// ---------- CSRF protection ----------

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

/** Token can come from a form field or (for fetch requests) the X-CSRF-Token header. */
function csrf_valid(): bool
{
    $token = $_POST['csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    return is_string($token) && hash_equals(csrf_token(), $token);
}

function verify_csrf(): void
{
    if (!csrf_valid()) {
        http_response_code(400);
        die('Your session expired or the form was tampered with. Go back, refresh the page and try again.');
    }
}

// ---------- Authentication ----------

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return isset($_SESSION['user']);
}

function is_admin(): bool
{
    return is_logged_in() && $_SESSION['user']['role'] === 'admin';
}

function require_login(): void
{
    if (!is_logged_in()) {
        $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'];
        flash('info', 'Log in to continue.');
        redirect('login.php');
    }
}

function require_admin(): void
{
    if (!is_admin()) {
        flash('error', 'That page is for administrators only.');
        redirect('login.php');
    }
}

// ---------- Cart (stored in the session as [product_id => quantity]) ----------

function cart_count(): int
{
    return array_sum($_SESSION['cart'] ?? []);
}

/**
 * Load cart products from the database so prices always come from the server,
 * never from the browser. Returns ['items' => [...], 'total' => float].
 */
function cart_details(PDO $pdo): array
{
    $cart = $_SESSION['cart'] ?? [];
    if (!$cart) {
        return ['items' => [], 'total' => 0.0];
    }

    $ids = array_map('intval', array_keys($cart));
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $stmt = $pdo->prepare("SELECT id, name, price, stock, image FROM products WHERE id IN ($placeholders)");
    $stmt->execute($ids);

    $items = [];
    $total = 0.0;
    $found = [];
    foreach ($stmt->fetchAll() as $p) {
        $qty = (int)$cart[$p['id']];
        $p['quantity'] = $qty;
        $p['subtotal'] = $qty * (float)$p['price'];
        $items[] = $p;
        $total += $p['subtotal'];
        $found[] = (int)$p['id'];
    }

    // Drop products that were deleted by the admin while sitting in the cart
    foreach ($ids as $id) {
        if (!in_array($id, $found, true)) {
            unset($_SESSION['cart'][$id]);
        }
    }

    return ['items' => $items, 'total' => $total];
}

// ---------- Misc ----------

function status_badge(string $status): string
{
    return '<span class="badge badge-' . e($status) . '">' . e(ucfirst($status)) . '</span>';
}

function json_response(array $data, int $code = 200): void
{
    while (ob_get_level()) ob_end_clean(); // drop any stray output so the body is pure JSON
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}
