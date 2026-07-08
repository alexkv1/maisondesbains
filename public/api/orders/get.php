<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/Auth/Verify.php';

$reference = trim($_GET['ref'] ?? '');
if ($reference === '') {
    respond(['success' => false, 'message' => 'Missing order reference.'], 400);
}

$rows = $db->select("SELECT * FROM `orders` WHERE `reference` = ? LIMIT 1", [$reference], 's');
if (!$rows) {
    respond(['success' => false, 'message' => 'Order not found.'], 404);
}
$order = $rows[0];

// Access rule: the owner (when the order has a user) must be signed in as
// that user. Guest orders (user IS NULL) are viewable by reference — the
// success redirect from checkout is the only place that reference appears.
if ($order['user'] !== null) {
    if (!$AUTH->valid || (int)$AUTH->user !== (int)$order['user']) {
        respond(['success' => false, 'message' => 'Order not found.'], 404);
    }
}

$items = $db->select(
    "SELECT `brand`, `name`, `sku`, `unit_price_cents`, `quantity`
       FROM `order_items` WHERE `order` = ? ORDER BY id ASC",
    [(int)$order['id']],
    'i'
);

// Trim internal fields.
unset($order['id'], $order['stripe_session'], $order['user']);

respond(['success' => true, 'order' => $order, 'items' => $items ?: []]);
