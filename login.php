<?php
require __DIR__ . '/includes/init.php';

if (is_logged_in()) redirect('index.php');

$email = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email    = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ?');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true); // prevent session fixation
        $_SESSION['user'] = [
            'id'    => (int)$user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
        flash('success', 'Welcome back, ' . $user['name'] . '.');

        // Send the user back to the page that asked them to log in (only local paths)
        $target = $_SESSION['redirect_after_login'] ?? '';
        unset($_SESSION['redirect_after_login']);
        if ($target && str_starts_with($target, BASE_URL . '/') && !str_starts_with($target, '//')) {
            header('Location: ' . $target);
            exit;
        }
        redirect($user['role'] === 'admin' ? 'admin/index.php' : 'index.php');
    }
    $error = 'That email and password don\'t match an account.';
}

$pageTitle = 'Log in';
require __DIR__ . '/includes/header.php';
?>

<div class="auth">
    <h1>Log in</h1>
    <?php if ($error): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" class="form">
        <?= csrf_field() ?>
        <label>Email <input type="email" name="email" value="<?= e($email) ?>" required autofocus></label>
        <label>Password <input type="password" name="password" required></label>
        <button class="btn btn-block" type="submit">Log in</button>
    </form>
    <p class="muted">New here? <a href="<?= url('register.php') ?>">Create an account</a></p>
</div>

<?php require __DIR__ . '/includes/footer.php'; ?>
