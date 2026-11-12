<?php
require __DIR__ . '/includes/init.php';

if (is_logged_in()) redirect('index.php');

$name = $email = '';
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name     = trim($_POST['name'] ?? '');
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm  = $_POST['confirm'] ?? '';

    if ($name === '')                                $errors[] = 'Enter your name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Enter a valid email address.';
    if (strlen($password) < 6)                       $errors[] = 'Use a password of at least 6 characters.';
    if ($password !== $confirm)                      $errors[] = 'The two passwords don\'t match.';

    if (!$errors) {
        $stmt = $pdo->prepare('SELECT id FROM users WHERE email = ?');
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            $errors[] = 'An account with that email already exists. Log in instead.';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
        $stmt->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);

        session_regenerate_id(true);
        $_SESSION['user'] = ['id' => (int)$pdo->lastInsertId(), 'name' => $name, 'email' => $email, 'role' => 'customer'];
        flash('success', 'Account created. Welcome, ' . $name . '.');

        $target = $_SESSION['redirect_after_login'] ?? '';
        unset($_SESSION['redirect_after_login']);
        if ($target && str_starts_with($target, BASE_URL . '/') && !str_starts_with($target, '//')) {
            header('Location: ' . $target);
            exit;
        }
        redirect('index.php');
    }
}

$pageTitle = 'Create account';
require __DIR__ . '/includes/header.php';
?>

<div class="auth">
    <h1>Create an account</h1>
    <?php foreach ($errors as $err): ?><div class="flash flash-error"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <label>Full name <input type="text" name="name" value="<?= e($name) ?>" required autofocus></label>
        <label>Email <input type="email" name="email" value="<?= e($email) ?>" required></label>
        <label>Password <input type="password" name="password" minlength="6" required></label>
        <label>Confirm password <input type="password" name="confirm" minlength="6" required></label>
        <button class="btn btn-block" type="submit">Create account</button>
    </form>
    <p class="muted">Already have an account? <a href="<?= url('login.php') ?>">Log in</a></p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
