<?php
/**
 * Cart resolution. A cart is keyed by a CART cookie token for guests, and
 * additionally linked to the user once authenticated. On login the guest
 * cart is merged into the user's cart (see api/account/login.php).
 */
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../functions.php';

/** Welcome-gift variants to include for this shopper (pending + claimed). */
function claimedWelcomeVariants($AUTH): array {
    if (empty($AUTH->valid) || empty($AUTH->pending_welcome)) return [];
    if (($_COOKIE['WELCOME_CLAIMED'] ?? '') !== '1') return [];
    return welcomeGiftVariants($AUTH->pending_welcome);
}

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

/**
 * Verify each cart item against current stock. Returns a list of issues:
 * [{identifier, name, size, available, reason}] where reason is
 * 'out' (0 available / sold out), 'qty' (fewer than requested) or
 * 'unavailable' (variant gone). Empty array = everything is in stock.
 */
function checkStock(DB $db, array $items): array {
    $issues = [];
    foreach ($items as $it) {
        if (!empty($it['is_gift'])) continue;   // the free gift isn't a purchase
        $rows = $db->select(
            "SELECT `stock`, `sold_out` FROM `product_variants` WHERE `identifier` = ? LIMIT 1",
            [$it['identifier']], 's'
        );
        if (!$rows) {
            $issues[] = ['identifier' => $it['identifier'], 'name' => $it['name'], 'size' => $it['size'], 'available' => 0, 'reason' => 'unavailable'];
            continue;
        }
        $stock = (int) $rows[0]['stock'];
        $soldOut = (int) $rows[0]['sold_out'] === 1;
        if ($soldOut || $stock <= 0) {
            $issues[] = ['identifier' => $it['identifier'], 'name' => $it['name'], 'size' => $it['size'], 'available' => 0, 'reason' => 'out'];
        } elseif ((int) $it['quantity'] > $stock) {
            $issues[] = ['identifier' => $it['identifier'], 'name' => $it['name'], 'size' => $it['size'], 'available' => $stock, 'reason' => 'qty'];
        }
    }
    return $issues;
}

/** Reduce variant stock for a paid order's line items (never below 0). */
function decrementStockForOrder(DB $db, int $orderId): void {
    $items = $db->select(
        "SELECT `variant`, `quantity` FROM `order_items` WHERE `order` = ? AND `variant` IS NOT NULL",
        [$orderId], 'i'
    );
    foreach ($items ?: [] as $it) {
        $db->execute(
            "UPDATE `product_variants` SET `stock` = GREATEST(`stock` - ?, 0) WHERE `id` = ?",
            [(int) $it['quantity'], (int) $it['variant']], 'ii'
        );
    }
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
function cartSummary(DB $db, int $cartId, bool $giftWrap = false, ?string $tierKey = null, array $welcomeVariants = []): array {
    $currency = currentCurrency();
    $cfg = currencies()[$currency];
    $benefits = tierBenefits($tierKey);

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

    // Complimentary gift: unlocked by spend threshold (claimed via popup),
    // or automatically for Platinum/Diamond members on every order.
    $giftThreshold = $cfg['gift_threshold'];
    $autoGift      = $benefits['auto_gift'];
    $giftQualified = ($count > 0 && ($subtotal >= $giftThreshold || $autoGift));
    $giftClaimed   = $autoGift || (($_COOKIE['GIFT_CLAIMED'] ?? '') === '1');
    if ($giftQualified && $giftClaimed) {
        $g = $db->select(
            "SELECT v.id AS variant_id, v.identifier, v.size, v.sku, p.brand, p.name, p.image, p.identifier AS product_identifier
               FROM `product_variants` v JOIN `products` p ON p.id = v.product
              WHERE v.identifier = ? LIMIT 1",
            [MDB_GIFT_VARIANT], 's'
        );
        if ($g) {
            $gi = $g[0];
            // Platinum/Diamond auto-gift is a surprise — mask the product.
            // The spend-threshold claim reveals the soap.
            $masked = $autoGift;
            $items[] = [
                'identifier'         => $gi['identifier'],
                'product_identifier' => $masked ? null : $gi['product_identifier'],
                'variant_id'         => (int)$gi['variant_id'],
                'brand'              => $masked ? 'With our compliments' : $gi['brand'],
                'name'               => $masked ? 'A complimentary gift' : $gi['name'],
                'size'               => $masked ? '' : $gi['size'],
                'sku'                => $gi['sku'],       // real SKU kept for fulfilment
                'image'              => $masked ? null : $gi['image'],
                'quantity' => 1, 'unit_price' => 0, 'line_total' => 0, 'sold_out' => 0,
                'initial'  => $masked ? '✻' : mb_substr($gi['name'], 0, 1),
                'is_gift'  => true, 'is_masked' => $masked, 'gift_label' => 'Gift',
            ];
        }
    }

    // Welcome gifts (revealed) granted on reaching a tier, once claimed.
    foreach ($welcomeVariants as $wv) {
        if ($count === 0) break;
        $w = $db->select(
            "SELECT v.id AS variant_id, v.identifier, v.size, v.sku, p.brand, p.name, p.image, p.identifier AS product_identifier
               FROM `product_variants` v JOIN `products` p ON p.id = v.product
              WHERE v.identifier = ? LIMIT 1",
            [$wv], 's'
        );
        if ($w) {
            $wi = $w[0];
            $items[] = [
                'identifier' => $wi['identifier'], 'product_identifier' => $wi['product_identifier'],
                'variant_id' => (int)$wi['variant_id'], 'brand' => $wi['brand'], 'name' => $wi['name'],
                'size' => $wi['size'], 'sku' => $wi['sku'], 'image' => $wi['image'],
                'quantity' => 1, 'unit_price' => 0, 'line_total' => 0, 'sold_out' => 0,
                'initial' => mb_substr($wi['name'], 0, 1),
                'is_gift' => true, 'is_masked' => false, 'gift_label' => 'Welcome gift',
            ];
        }
    }

    $giftWrapAmt   = ($giftWrap && $count > 0) ? ($benefits['free_wrap'] ? 0 : $cfg['gift_wrap']) : 0;

    // Delivery: free for everyone over the universal threshold (€75 / 850 kr),
    // and free for Platinum/Diamond over the member threshold (€50 / 500 kr).
    $freeThreshold  = $cfg['free_threshold'];
    $memberFree     = $benefits['free_shipping'] && $subtotal >= $cfg['gift_threshold'];
    $freeDelivery   = ($subtotal >= $freeThreshold) || $memberFree;
    $shipping       = ($count > 0 && !$freeDelivery) ? $cfg['shipping'] : 0;
    $total          = $subtotal + $shipping + $giftWrapAmt;

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
        'free_wrap'        => $benefits['free_wrap'],
        'free_shipping'    => $benefits['free_shipping'],
    ];
}
