<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/cart.php';
require_once __DIR__ . '/../../utils/Auth/Verify.php';

requireMethod('POST');
$in = readInput();

$identifier = trim($in['variant'] ?? $in['product'] ?? '');
$qty = (int)($in['quantity'] ?? 0);   // absolute quantity; 0 removes
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

if ($qty <= 0) {
    $db->execute("DELETE FROM `cart_items` WHERE `cart` = ? AND `variant` = ?", [$cartId, $variantId], 'ii');
} else {
    $db->execute(
        "INSERT INTO `cart_items` (`cart`, `variant`, `quantity`) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE `quantity` = VALUES(`quantity`)",
        [$cartId, $variantId, min($qty, MDB_MAX_PER_ITEM)],
        'iii'
    );
}

respond(['success' => true, 'cart' => cartSummary($db, $cartId)]);
