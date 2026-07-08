<?php
/**
 * Stripe webhook — marks orders paid and empties the cart on
 * checkout.session.completed. Configure the endpoint in the Stripe
 * dashboard as: https://<host>/api/payments/webhook
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/cart.php';

$cfg = config();
$secret = $cfg['stripe-webhook-secret'] ?? '';

$payload = file_get_contents('php://input');

// Verify signature when a secret + the SDK are available.
$event = null;
if ($secret !== '' && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    $sig = $_SERVER['HTTP_STRIPE_SIGNATURE'] ?? '';
    try {
        $event = \Stripe\Webhook::constructEvent($payload, $sig, $secret);
    } catch (\Throwable $e) {
        http_response_code(400);
        exit;
    }
    $event = json_decode(json_encode($event), true);
} else {
    $event = json_decode($payload, true);
}

if (($event['type'] ?? '') !== 'checkout.session.completed') {
    http_response_code(200);
    echo 'ignored';
    exit;
}

$session = $event['data']['object'] ?? [];
$sessionId = $session['id'] ?? '';
$reference = $session['metadata']['order_reference'] ?? ($session['client_reference_id'] ?? '');

$db = new DB();

$rows = $db->select(
    "SELECT id FROM `orders` WHERE `stripe_session` = ? OR `reference` = ? LIMIT 1",
    [$sessionId, $reference],
    'ss'
);
if (!$rows) {
    http_response_code(200);
    echo 'no order';
    exit;
}
$orderId = (int)$rows[0]['id'];

$wasPaid = $db->select("SELECT `status` FROM `orders` WHERE `id` = ?", [$orderId], 'i');
$db->execute(
    "UPDATE `orders` SET `status` = 'paid', `date_paid` = ? WHERE `id` = ? AND `status` <> 'paid'",
    [time(), $orderId],
    'ii'
);
// On the transition to paid: decrement stock, award points, clear cart.
if ($wasPaid && $wasPaid[0]['status'] !== 'paid') {
    decrementStockForOrder($db, $orderId);

    $ord = $db->select("SELECT `user`, `currency`, `total_cents` FROM `orders` WHERE `id` = ?", [$orderId], 'i');
    $user = $ord[0]['user'] ?? null;
    if ($user !== null) {
        // Redeem claimed gifts included in this order.
        $db->execute(
            "UPDATE `user_gifts` SET `status` = 'redeemed', `date_redeemed` = ? WHERE `user` = ? AND `status` = 'claimed'",
            [time(), (int)$user], 'ii'
        );

        $u = $db->select("SELECT `points` FROM `users` WHERE `id` = ?", [(int)$user], 'i');
        $oldPoints = (int)($u[0]['points'] ?? 0);
        $oldTierKey = tierForPoints($oldPoints)['key'];
        $pts = pointsForOrder((int)$ord[0]['total_cents'], $ord[0]['currency'], $oldTierKey);
        $newPoints = $oldPoints + $pts;
        $db->execute("UPDATE `users` SET `points` = ? WHERE `id` = ?", [$newPoints, (int)$user], 'ii');

        // Promotion grants the new tier's welcome gift(s).
        $newTierKey = tierForPoints($newPoints)['key'];
        if (tierRank($newTierKey) > tierRank($oldTierKey) && tierRank($newTierKey) >= 1) {
            grantWelcomeGifts($db, (int)$user, $newTierKey);
        }

        $cartRows = $db->select("SELECT id FROM `carts` WHERE `user` = ? ORDER BY id DESC LIMIT 1", [(int)$user], 'i');
        if ($cartRows) {
            $db->execute("DELETE FROM `cart_items` WHERE `cart` = ?", [(int)$cartRows[0]['id']], 'i');
        }
    }
}

http_response_code(200);
echo 'ok';
