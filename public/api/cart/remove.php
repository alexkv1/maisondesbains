<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/cart.php';
require_once __DIR__ . '/../../utils/Auth/Verify.php';

requireMethod('POST');
$in = readInput();

$identifier = trim($in['product'] ?? '');
if ($identifier === '') {
    respond(['success' => false, 'message' => 'Missing product.'], 400);
}

$rows = $db->select("SELECT id FROM `products` WHERE `identifier` = ? LIMIT 1", [$identifier], 's');
if (!$rows) {
    respond(['success' => false, 'message' => 'That product does not exist.'], 404);
}
$productId = (int)$rows[0]['id'];

$userId = $AUTH->valid ? $AUTH->user : null;
$cartId = resolveCart($db, $userId);

$db->execute("DELETE FROM `cart_items` WHERE `cart` = ? AND `product` = ?", [$cartId, $productId], 'ii');

respond(['success' => true, 'cart' => cartSummary($db, $cartId)]);
