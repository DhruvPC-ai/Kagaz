<?php
/*
 * PDO connection shared by the whole app.
 * If the database doesn't exist yet it is created, and on first run the tables
 * and sample data from database.sql are installed automatically.
 */
function db_connect(bool $selectDatabase = true): PDO
{
    $dsn = 'mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4'
         . ($selectDatabase ? ';dbname=' . DB_NAME : '');

    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET time_zone = '+05:30'", // IST for NOW() and timestamps
    ];
    if (DB_SSL_CA !== '') {
        // Encrypted connection, required by hosted databases such as TiDB Cloud
        $options[PDO::MYSQL_ATTR_SSL_CA] = DB_SSL_CA;
        $options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT] = true;
    }
    return new PDO($dsn, DB_USER, DB_PASS, $options);
}

/** Create tables + sample data from database.sql the first time the site runs. */
function install_if_needed(PDO $pdo): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) return; // e.g. image.php: no setup work needed
    if (!empty($_SESSION['db_ready'])) return;            // checked once per visitor session

    if (!$pdo->query("SHOW TABLES LIKE 'users'")->fetch()) {
        $sql = file_get_contents(__DIR__ . '/../database.sql');
        $sql = preg_replace('/--\s.*$/m', '', $sql);             // strip comments
        foreach (preg_split('/;\s*(\r?\n|$)/', $sql) as $statement) {
            $statement = trim($statement);
            if ($statement === '' || preg_match('/^(CREATE DATABASE|USE)\b/i', $statement)) continue;
            $pdo->exec($statement);
        }
    }
    apply_private_passwords($pdo);
    $_SESSION['db_ready'] = true;
}

/**
 * Give the built-in accounts the passwords from the private settings
 * (ADMIN_PASSWORD / CUSTOMER_PASSWORD), so the public defaults never work online.
 */
function apply_private_passwords(PDO $pdo): void
{
    $accounts = ['admin@example.com' => ADMIN_PASSWORD, 'customer@example.com' => CUSTOMER_PASSWORD];
    foreach ($accounts as $email => $password) {
        if ($password === '') continue;
        $stmt = $pdo->prepare('SELECT id, password FROM users WHERE email = ?');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user && !password_verify($password, $user['password'])) {
            $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), $user['id']]);
        }
    }
}

try {
    try {
        $pdo = db_connect();
    } catch (PDOException $e) {
        if ((int)$e->getCode() !== 1049) throw $e;               // 1049 = unknown database
        db_connect(false)->exec('CREATE DATABASE `' . str_replace('`', '', DB_NAME) . '` CHARACTER SET utf8mb4');
        $pdo = db_connect();
    }
    install_if_needed($pdo);
} catch (PDOException $e) {
    if (defined('JSON_API')) api_error('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    die('Database connection failed. Check the database settings (config/config.php or environment variables) '
        . 'and make sure MySQL is running.<br>' . htmlspecialchars($e->getMessage()));
}
