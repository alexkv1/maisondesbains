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
        $u = $db->select("SELECT `points`, `pending_welcome` FROM `users` WHERE `id` = ?", [(int)$user], 'i');
        $oldPoints = (int)($u[0]['points'] ?? 0);
        $oldPending = $u[0]['pending_welcome'] ?? '';
        $oldTierKey = tierForPoints($oldPoints)['key'];
        $pts = pointsForOrder((int)$ord[0]['total_cents'], $ord[0]['currency'], $oldTierKey);
        $newPoints = $oldPoints + $pts;
        $db->execute("UPDATE `users` SET `points` = ? WHERE `id` = ?", [$newPoints, (int)$user], 'ii');

        // Welcome-gift lifecycle.
        $newTierKey = tierForPoints($newPoints)['key'];
        $consumed = false;
        if ($oldPending !== '') {
            $wv = welcomeGiftVariants($oldPending);
            $got = $db->select(
                "SELECT v.identifier FROM `order_items` oi JOIN `product_variants` v ON v.id = oi.variant WHERE oi.`order` = ?",
                [$orderId], 'i'
            );
            $ids = array_column($got ?: [], 'identifier');
            $consumed = $wv && count(array_intersect($wv, $ids)) === count($wv);
        }
        $newPending = (tierRank($newTierKey) > tierRank($oldTierKey) && tierRank($newTierKey) >= 1)
            ? $newTierKey : ($consumed ? '' : $oldPending);
        if ($newPending !== $oldPending) {
            $db->execute("UPDATE `users` SET `pending_welcome` = ? WHERE `id` = ?", [$newPending, (int)$user], 'si');
        }

        $cartRows = $db->select("SELECT id FROM `carts` WHERE `user` = ? ORDER BY id DESC LIMIT 1", [(int)$user], 'i');
        if ($cartRows) {
            $db->execute("DELETE FROM `cart_items` WHERE `cart` = ?", [(int)$cartRows[0]['id']], 'i');
        }
    }
}

http_response_code(200);
echo 'ok';
