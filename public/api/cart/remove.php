<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/cart.php';
require_once __DIR__ . '/../../utils/Auth/Verify.php';

requireMethod('POST');
$in = readInput();

$identifier = trim($in['variant'] ?? $in['product'] ?? '');
if ($identifier === '') {
    respond(['success' => false, 'message' => 'Missing item.'], 400);
}

$rows = $db->select("SELECT id FROM `product_variants` WHERE `identifier` = ? LIMIT 1", [$identifier], 's');
if (!$rows) {
    respond(['success' => false, 'message' => 'That item does not exist.'], 404);
}
$variantId = (int)$rows[0]['id'];

$userId = $AUTH->valid ? $AUTH->user : null;
$cartId = resolveCart($db, $userId);

$db->execute("DELETE FROM `cart_items` WHERE `cart` = ? AND `variant` = ?", [$cartId, $variantId], 'ii');

respond(['success' => true, 'cart' => cartSummary($db, $cartId)]);
