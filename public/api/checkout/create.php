<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/cart.php';
require_once __DIR__ . '/../../utils/Auth/Verify.php';

requireMethod('POST');
$in = readInput();

$userId = $AUTH->valid ? $AUTH->user : null;

// Customer details — prefill from the account, allow override from the form.
$email     = trim(strtolower($in['email'] ?? ($AUTH->valid ? $AUTH->email : '')));
$firstName = trim($in['first_name'] ?? ($AUTH->valid ? $AUTH->first_name : ''));
$lastName  = trim($in['last_name']  ?? ($AUTH->valid ? $AUTH->last_name  : ''));
$line1     = trim($in['address_line1'] ?? '');
$line2     = trim($in['address_line2'] ?? '');
$city      = trim($in['city'] ?? '');
$postcode  = trim($in['postcode'] ?? '');
$country   = trim($in['country'] ?? '');
$giftWrap  = !empty($in['gift_wrap']);

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
}
if ($firstName === '' || $line1 === '' || $city === '' || $postcode === '') {
    respond(['success' => false, 'message' => 'Please complete the delivery details.'], 400);
}

$cartId = resolveCart($db, $userId);
$summary = cartSummary($db, $cartId, $giftWrap);

if ($summary['count'] === 0) {
    respond(['success' => false, 'message' => 'Your bag is empty.'], 400);
}

// Guard against sold-out items slipping through.
foreach ($summary['items'] as $it) {
    if ((int)$it['sold_out'] === 1) {
        respond(['success' => false, 'message' => $it['name'] . ' is sold out.'], 409);
    }
}

// ---- Create the order (pending) with an immutable snapshot of the cart ----
$reference = generateOrderReference();
$currency = $summary['currency'];
$db->execute(
    "INSERT INTO `orders`
       (`reference`, `user`, `email`, `first_name`, `last_name`,
        `address_line1`, `address_line2`, `city`, `postcode`, `country`,
        `subtotal_cents`, `shipping_cents`, `gift_wrap_cents`, `total_cents`,
        `gift_wrap`, `currency`, `status`, `date_created`)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?)",
    [
        $reference, $userId, $email, $firstName, $lastName,
        $line1, $line2, $city, $postcode, $country,
        $summary['subtotal_cents'], $summary['shipping_cents'], $summary['gift_wrap_cents'], $summary['total_cents'],
        $giftWrap ? 1 : 0, $currency, time(),
    ],
    'sissssssssiiiiisi'
);
$orderId = (int)$db->lastInsertId();

foreach ($summary['items'] as $it) {
    $db->execute(
        "INSERT INTO `order_items` (`order`, `product`, `brand`, `name`, `sku`, `unit_price_cents`, `quantity`)
         VALUES (?, ?, ?, ?, ?, ?, ?)",
        [$orderId, $it['product_id'], $it['brand'], $it['name'], $it['sku'], $it['unit_price'], $it['quantity']],
        'iisssii'
    );
}

$cfg = config();
$stripeKey = $cfg['stripe-key'] ?? '';

// ---- Path A: real Stripe Checkout ----
if ($stripeKey !== '' && file_exists(__DIR__ . '/../../vendor/autoload.php')) {
    require_once __DIR__ . '/../../vendor/autoload.php';
    \Stripe\Stripe::setApiKey($stripeKey);

    $stripeCurrency = strtolower($currency);
    $lineItems = [];
    foreach ($summary['items'] as $it) {
        $lineItems[] = [
            'quantity' => $it['quantity'],
            'price_data' => [
                'currency' => $stripeCurrency,
                'unit_amount' => paymentMinor($it['unit_price'], $currency),
                'product_data' => ['name' => $it['brand'] . ' — ' . $it['name']],
            ],
        ];
    }
    if ($summary['shipping_cents'] > 0) {
        $lineItems[] = ['quantity' => 1, 'price_data' => [
            'currency' => $stripeCurrency, 'unit_amount' => paymentMinor($summary['shipping_cents'], $currency),
            'product_data' => ['name' => 'Delivery'],
        ]];
    }
    if ($summary['gift_wrap_cents'] > 0) {
        $lineItems[] = ['quantity' => 1, 'price_data' => [
            'currency' => $stripeCurrency, 'unit_amount' => paymentMinor($summary['gift_wrap_cents'], $currency),
            'product_data' => ['name' => 'Gift wrap'],
        ]];
    }

    try {
        $session = \Stripe\Checkout\Session::create([
            'mode' => 'payment',
            'payment_method_types' => ['card'],
            'customer_email' => $email,
            'line_items' => $lineItems,
            'client_reference_id' => $reference,
            'metadata' => ['order_reference' => $reference, 'order_id' => (string)$orderId],
            'success_url' => baseUrl() . '/order?ref=' . $reference . '&session_id={CHECKOUT_SESSION_ID}',
            'cancel_url'  => baseUrl() . '/checkout?cancelled=1',
        ]);
    } catch (\Throwable $e) {
        respond(['success' => false, 'message' => 'Payment could not be started.'], 502);
    }

    $db->execute("UPDATE `orders` SET `stripe_session` = ? WHERE `id` = ?", [$session->id, $orderId], 'si');
    respond(['success' => true, 'mode' => 'stripe', 'url' => $session->url]);
}

// ---- Path B: mock checkout (no Stripe key configured) ----
$db->execute(
    "UPDATE `orders` SET `status` = 'paid', `date_paid` = ? WHERE `id` = ?",
    [time(), $orderId],
    'ii'
);
// Empty the cart now that the order is placed.
$db->execute("DELETE FROM `cart_items` WHERE `cart` = ?", [$cartId], 'i');

respond(['success' => true, 'mode' => 'mock', 'url' => baseUrl() . '/order?ref=' . $reference]);
