<?php
/**
 * Cart resolution. A cart is keyed by a CART cookie token for guests, and
 * additionally linked to the user once authenticated. On login the guest
 * cart is merged into the user's cart (see api/account/login.php).
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

/** Return the current cart id, creating a cart + cookie if needed. */
function resolveCart(DB $db, ?int $userId = null): int {
    $token = $_COOKIE['CART'] ?? null;

    if ($token && !preg_match('/^[a-f0-9]{32,}$/', $token)) {
        $token = null;
    }

    // Prefer an existing cart for the logged-in user.
    if ($userId) {
        $rows = $db->select(
            "SELECT id, token FROM `carts` WHERE `user` = ? ORDER BY id DESC LIMIT 1",
            [$userId],
            'i'
        );
        if ($rows) {
            // Keep the cookie in sync with the user's cart.
            setCartCookie($rows[0]['token']);
            return (int)$rows[0]['id'];
        }
    }

    // Fall back to the cookie cart.
    if ($token) {
        $rows = $db->select("SELECT id FROM `carts` WHERE `token` = ? LIMIT 1", [$token], 's');
        if ($rows) {
            if ($userId) {
                $db->execute("UPDATE `carts` SET `user` = ? WHERE `id` = ?", [$userId, (int)$rows[0]['id']], 'ii');
            }
            return (int)$rows[0]['id'];
        }
    }

    // Create a fresh cart.
    $token = generateRandomString();
    $db->execute(
        "INSERT INTO `carts` (`user`, `token`, `date_created`) VALUES (?, ?, ?)",
        [$userId, $token, time()],
        'isi'
    );
    setCartCookie($token);
    return (int)$db->lastInsertId();
}

function setCartCookie(string $token): void {
    if (($_COOKIE['CART'] ?? null) === $token) {
        return;
    }
    setcookie('CART', $token, [
        'expires'  => time() + 60 * 60 * 24 * 60,
        'path'     => '/',
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    $_COOKIE['CART'] = $token;
}

/** Full cart contents + totals, computed in the visitor's active currency. */
function cartSummary(DB $db, int $cartId, bool $giftWrap = false): array {
    $currency = currentCurrency();
    $cfg = currencies()[$currency];

    $items = $db->select(
        "SELECT ci.id AS item_id, ci.quantity,
                v.id AS variant_id, v.identifier, v.size, v.price_cents, v.price_sek, v.sku, v.sold_out,
                p.id AS product_id, p.identifier AS product_identifier, p.brand, p.name, p.image
           FROM `cart_items` ci
           JOIN `product_variants` v ON v.id = ci.variant
           JOIN `products` p ON p.id = v.product
          WHERE ci.cart = ?
          ORDER BY ci.id ASC",
        [$cartId],
        'i'
    );
    if ($items === false) {
        $items = [];
    }

    $subtotal = 0;
    $count = 0;
    foreach ($items as &$it) {
        $it['quantity']    = (int)$it['quantity'];
        $it['unit_price']  = productPrice($it, $currency);   // $it carries price_cents / price_sek
        $it['line_total']  = $it['unit_price'] * $it['quantity'];
        $it['initial']     = mb_substr($it['name'], 0, 1);
        $subtotal += $it['line_total'];
        $count += $it['quantity'];
    }
    unset($it);

    // Complimentary gift once the spend threshold is met AND the shopper
    // has claimed it (via the gift popup).
    $giftThreshold = $cfg['gift_threshold'];
    $giftQualified = ($count > 0 && $subtotal >= $giftThreshold);
    $giftClaimed   = ($_COOKIE['GIFT_CLAIMED'] ?? '') === '1';
    if ($giftQualified && $giftClaimed) {
        $g = $db->select(
            "SELECT v.identifier, v.size, v.sku, p.brand, p.name, p.image, p.identifier AS product_identifier
               FROM `product_variants` v JOIN `products` p ON p.id = v.product
              WHERE v.identifier = ? LIMIT 1",
            [MDB_GIFT_VARIANT], 's'
        );
        if ($g) {
            $gi = $g[0];
            $items[] = [
                'identifier' => $gi['identifier'], 'product_identifier' => $gi['product_identifier'],
                'brand' => $gi['brand'], 'name' => $gi['name'], 'size' => $gi['size'], 'sku' => $gi['sku'],
                'image' => $gi['image'], 'quantity' => 1, 'unit_price' => 0, 'line_total' => 0,
                'sold_out' => 0, 'initial' => mb_substr($gi['name'], 0, 1), 'is_gift' => true,
            ];
        }
    }

    $giftWrapAmt   = ($giftWrap && $count > 0) ? $cfg['gift_wrap'] : 0;
    $freeThreshold = $cfg['free_threshold'];
    $shipping      = ($count > 0 && $subtotal < $freeThreshold) ? $cfg['shipping'] : 0;
    $total         = $subtotal + $shipping + $giftWrapAmt;

    // Keys keep the *_cents suffix for continuity; values are integers in the
    // active currency's unit (EUR cents or SEK kronor).
    return [
        'currency'         => $currency,
        'items'            => $items,
        'count'            => $count,
        'subtotal_cents'   => $subtotal,
        'shipping_cents'   => $shipping,
        'gift_wrap_cents'  => $giftWrapAmt,
        'total_cents'      => $total,
        'free_threshold'   => $freeThreshold,
        'gift_threshold'   => $giftThreshold,
        'gift_qualified'   => $giftQualified,
        'gift_claimed'     => $giftQualified && $giftClaimed,
        'gift_remaining'   => max(0, $giftThreshold - $subtotal),
    ];
}
