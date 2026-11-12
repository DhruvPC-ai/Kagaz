<?php
/*
 * Serves product images uploaded through the admin panel (stored in the database).
 */
require __DIR__ . '/config/config.php';
require __DIR__ . '/includes/db.php';

$stmt = $pdo->prepare('SELECT mime, data FROM product_images WHERE id = ?');
$stmt->execute([(int)($_GET['id'] ?? 0)]);
$image = $stmt->fetch();

if (!$image) {
    http_response_code(404);
    exit;
}

header('Content-Type: ' . $image['mime']);
header('Content-Length: ' . strlen($image['data']));
header('Cache-Control: public, max-age=604800, immutable'); // a new upload always gets a new id
header('X-Content-Type-Options: nosniff');
echo $image['data'];
