<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/cart.php';
require_once __DIR__ . '/../../utils/Auth/Verify.php';

requireMethod('POST');
$in = readInput();

$identifier = trim($in['variant'] ?? $in['product'] ?? '');
$qty = max(1, (int)($in['quantity'] ?? 1));
if ($identifier === '') {
    respond(['success' => false, 'message' => 'Missing item.'], 400);
}

$rows = $db->select(
    "SELECT id, sold_out FROM `product_variants` WHERE `identifier` = ? AND `status` = 1 LIMIT 1",
    [$identifier],
    's'
);
if (!$rows) {
    respond(['success' => false, 'message' => 'That item does not exist.'], 404);
}
if ((int)$rows[0]['sold_out'] === 1) {
    respond(['success' => false, 'message' => 'That size is not available.'], 409);
}
$variantId = (int)$rows[0]['id'];

$userId = $AUTH->valid ? $AUTH->user : null;
$cartId = resolveCart($db, $userId);

$ok = $db->execute(
    "INSERT INTO `cart_items` (`cart`, `variant`, `quantity`) VALUES (?, ?, ?)
     ON DUPLICATE KEY UPDATE `quantity` = `quantity` + VALUES(`quantity`)",
    [$cartId, $variantId, $qty],
    'iii'
);
if (!$ok) {
    respond(['success' => false, 'message' => 'Could not add to your bag.'], 500);
}

respond(['success' => true, 'cart' => cartSummary($db, $cartId)]);
