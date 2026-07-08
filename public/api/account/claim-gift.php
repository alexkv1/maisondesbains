<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/cart.php';
require_once __DIR__ . '/../../utils/Auth/Verify.php';

requireMethod('POST');
if (!$AUTH->valid) {
    respond(['success' => false, 'message' => 'Please sign in.'], 401);
}

$in = readInput();
$giftId = (int)($in['gift_id'] ?? 0);
if ($giftId <= 0) {
    respond(['success' => false, 'message' => 'Missing gift.'], 400);
}

// Only the owner may claim, and only an available gift.
$rows = $db->select(
    "SELECT id FROM `user_gifts` WHERE id = ? AND `user` = ? AND `status` = 'available' LIMIT 1",
    [$giftId, $AUTH->user], 'ii'
);
if (!$rows) {
    respond(['success' => false, 'message' => 'That gift is not available.'], 404);
}

$db->execute("UPDATE `user_gifts` SET `status` = 'claimed' WHERE id = ?", [$giftId], 'i');

// Ensure a cart exists so the gift shows on the next visit to the bag.
resolveCart($db, $AUTH->user);

respond(['success' => true]);
