<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/cart.php';
require_once __DIR__ . '/../../utils/Auth/Verify.php';

$userId = $AUTH->valid ? $AUTH->user : null;
$giftWrap = !empty($_GET['gift_wrap']);

$cartId = resolveCart($db, $userId);
$tierKey = $AUTH->valid ? $AUTH->tier['key'] : null;
$summary = cartSummary($db, $cartId, $giftWrap, $tierKey, claimedWelcomeVariants($AUTH));

respond([
    'success' => true,
    'authenticated' => $AUTH->valid,
    'cart' => $summary,
]);
