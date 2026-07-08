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
$action = ($in['action'] ?? 'claim') === 'unclaim' ? 'unclaim' : 'claim';
if ($giftId <= 0) {
    respond(['success' => false, 'message' => 'Missing gift.'], 400);
}

if ($action === 'unclaim') {
    // Return a reserved gift to available.
    $rows = $db->select(
        "SELECT id FROM `user_gifts` WHERE id = ? AND `user` = ? AND `status` = 'claimed' LIMIT 1",
        [$giftId, $AUTH->user], 'ii'
    );
    if (!$rows) {
        respond(['success' => false, 'message' => 'That gift is not reserved.'], 404);
    }
    $db->execute("UPDATE `user_gifts` SET `status` = 'available' WHERE id = ?", [$giftId], 'i');
    respond(['success' => true]);
}

// Claim: only the owner, only an available gift.
$rows = $db->select(
    "SELECT id FROM `user_gifts` WHERE id = ? AND `user` = ? AND `status` = 'available' LIMIT 1",
    [$giftId, $AUTH->user], 'ii'
);
if (!$rows) {
    respond(['success' => false, 'message' => 'That gift is not available.'], 404);
}
$db->execute("UPDATE `user_gifts` SET `status` = 'claimed' WHERE id = ?", [$giftId], 'i');
resolveCart($db, $AUTH->user);   // ensure a cart exists

respond(['success' => true]);
