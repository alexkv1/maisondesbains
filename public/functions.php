<?php
/**
 * Shared helpers for Maison Des Bains.
 */

/** HTML-escape a string for safe output. */
function e(string $s): string {
    return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

/** A URL-safe random token (session / cart cookies). */
function generateRandomString(int $length = 48): string {
    return substr(bin2hex(random_bytes($length)), 0, $length);
}

/** RFC-4122 v4 UUID. */
function generateUUID(): string {
    $data = random_bytes(16);
    $data[6] = chr((ord($data[6]) & 0x0f) | 0x40);
    $data[8] = chr((ord($data[8]) & 0x3f) | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/** Human-facing order reference, e.g. MDB-7Q4X9A. */
function generateOrderReference(): string {
    $alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';
    $ref = '';
    for ($i = 0; $i < 6; $i++) {
        $ref .= $alphabet[random_int(0, strlen($alphabet) - 1)];
    }
    return 'MDB-' . $ref;
}

/** Emit JSON and stop. */
function respond(array $payload, int $status = 200): void {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($payload);
    exit;
}

/**
 * Currencies. Each product carries a SET price per currency (no conversion),
 * so prices are always clean. Amounts everywhere are integers in the
 * currency's smallest displayed unit: EUR in cents (÷100, 2 decimals),
 * SEK in whole kronor (÷1, 0 decimals). Shipping, the free-delivery
 * threshold and gift wrap are likewise set per currency.
 */
function currencies(): array {
    return [
        'EUR' => [
            'code' => 'EUR', 'symbol' => '€', 'decimals' => 2, 'minor' => 100,
            'thousands' => ',', 'position' => 'before', 'country' => 'Europe',
            'price_col' => 'price_cents', 'shipping' => 500, 'free_threshold' => 7500,
            'gift_wrap' => 400, 'gift_threshold' => 5000,
        ],
        'SEK' => [
            'code' => 'SEK', 'symbol' => 'kr', 'decimals' => 0, 'minor' => 1,
            'thousands' => ' ', 'position' => 'after', 'country' => 'Sweden',
            'price_col' => 'price_sek', 'shipping' => 59, 'free_threshold' => 850,
            'gift_wrap' => 45, 'gift_threshold' => 500,
        ],
    ];
}

/** The spend-campaign gift (claimed above gift_threshold by any shopper). */
const MDB_GIFT_VARIANT = 'bal-dafrique-soap-30g';

/** The complimentary gift included on every Platinum/Diamond order. */
function perOrderGift(?string $tierKey): ?array {
    return [
        'platinum' => ['variant' => 'bal-dafrique-soap-30g',      'label' => 'Platinum gift'],
        'diamond'  => ['variant' => 'bal-dafrique-shower-gel-50ml', 'label' => 'Diamond gift'],
    ][$tierKey] ?? null;
}

/** Maximum quantity of any single item allowed in the cart. */
const MDB_MAX_PER_ITEM = 10;

/* ============================================================
   LOYALTY — points & tiers
   Earn 10 points per € / 1 point per kr on paid orders.
   ============================================================ */
function loyaltyTiers(): array {
    return [
        ['key' => 'silver',   'name' => 'Silver',   'min' => 0,    'max' => 500,
         'benefits' => ['Complimentary gift wrapping']],
        ['key' => 'gold',     'name' => 'Gold',     'min' => 501,  'max' => 2000,
         'benefits' => ['A welcome gift on reaching Gold', 'Complimentary gift wrapping', 'Early access to new arrivals & private editions']],
        ['key' => 'platinum', 'name' => 'Platinum', 'min' => 2001, 'max' => 5000,
         'benefits' => ['A welcome gift on reaching Platinum', 'Complimentary gift wrapping', 'Complimentary delivery over 50 € / 500 kr', 'A complimentary gift with every order', 'Priority dispatch']],
        ['key' => 'diamond',  'name' => 'Diamond',  'min' => 5001, 'max' => null,
         'benefits' => ['A welcome gift on reaching Diamond', 'Complimentary gift wrapping', 'Complimentary delivery over 50 € / 500 kr', 'A complimentary gift with every order', 'Double points on every order', 'Annual full-size gift & private concierge']],
    ];
}

/** The tier row for a points balance. */
function tierForPoints(int $points): array {
    $tiers = loyaltyTiers();
    foreach ($tiers as $t) {
        if ($points >= $t['min'] && ($t['max'] === null || $points <= $t['max'])) {
            return $t;
        }
    }
    return $tiers[0];
}

/** Mechanical benefits derived from a tier key. */
function tierBenefits(?string $key): array {
    $free_wrap        = in_array($key, ['silver', 'gold', 'platinum', 'diamond'], true);
    // Free delivery is a Platinum/Diamond perk, over the gift_threshold (€50 / 500 kr).
    $free_shipping    = in_array($key, ['platinum', 'diamond'], true);
    $auto_gift        = in_array($key, ['platinum', 'diamond'], true);
    $points_multiplier = $key === 'diamond' ? 2 : 1;
    return compact('free_wrap', 'free_shipping', 'auto_gift', 'points_multiplier');
}

/** Points earned for an order total (in the currency's minor unit). */
function pointsForOrder(int $amount, string $currency, ?string $tierKey = null): int {
    // EUR: 10 pts per € (= cents / 10). SEK: 1 pt per kr (= kronor).
    $base = $currency === 'EUR' ? intdiv($amount, 10) : $amount;
    return $base * tierBenefits($tierKey)['points_multiplier'];
}

/** Numeric rank of a tier (0 = silver … 3 = diamond). */
function tierRank(string $key): int {
    return ['silver' => 0, 'gold' => 1, 'platinum' => 2, 'diamond' => 3][$key] ?? 0;
}

/** The welcome-gift variant identifiers granted on reaching a tier. */
function welcomeGiftVariants(string $tierKey): array {
    return [
        'gold'     => ['bal-dafrique-shower-gel-50ml'],
        'platinum' => ['bal-dafrique-shower-gel-50ml', 'bal-dafrique-body-lotion-50ml'],
        'diamond'  => ['santal-33-shower-gel-90ml', 'santal-33-body-lotion-90ml'],
    ][$tierKey] ?? [];
}

/** The visitor's selected currency (CUR cookie), defaulting to EUR. */
function currentCurrency(): string {
    $c = strtoupper($_COOKIE['CUR'] ?? 'EUR');
    return isset(currencies()[$c]) ? $c : 'EUR';
}

/** A product's set price (integer, in the currency's unit). */
function productPrice(array $p, ?string $code = null): int {
    $code = $code && isset(currencies()[$code]) ? $code : currentCurrency();
    return (int) $p[currencies()[$code]['price_col']];
}

/**
 * Format an integer amount that is already in $code's unit.
 * $code defaults to the visitor's currency.
 */
function money(int $amount, ?string $code = null): string {
    $code = $code && isset(currencies()[$code]) ? $code : currentCurrency();
    $cur  = currencies()[$code];
    $num  = number_format($amount / $cur['minor'], $cur['decimals'], '.', $cur['thousands']);
    return $cur['position'] === 'before' ? $cur['symbol'] . $num : $num . ' ' . $cur['symbol'];
}

/** Minor-unit integer amount for a payment processor (EUR cents / SEK öre). */
function paymentMinor(int $amount, string $code): int {
    return $code === 'EUR' ? $amount : $amount * 100;
}

/** Free-delivery threshold, formatted without trailing decimals (e.g. €75, 850 kr). */
function freeShippingLabel(?string $code = null): string {
    $code = $code && isset(currencies()[$code]) ? $code : currentCurrency();
    $cur  = currencies()[$code];
    $num  = number_format($cur['free_threshold'] / $cur['minor'], 0, '.', $cur['thousands']);
    return $cur['position'] === 'before' ? $cur['symbol'] . $num : $num . ' ' . $cur['symbol'];
}

/** True if the request method matches, else 405. */
function requireMethod(string $method): void {
    if ($_SERVER['REQUEST_METHOD'] !== $method) {
        respond(['success' => false, 'message' => 'Method not allowed.'], 405);
    }
}

/** Read JSON or form body into an array. */
function readInput(): array {
    $ct = $_SERVER['CONTENT_TYPE'] ?? '';
    if (stripos($ct, 'application/json') !== false) {
        $raw = file_get_contents('php://input');
        $data = json_decode($raw, true);
        return is_array($data) ? $data : [];
    }
    return $_POST;
}

/** Load conf.php as an array. */
function config(): array {
    static $cfg = null;
    if ($cfg === null) {
        $cfg = require __DIR__ . '/conf.php';
    }
    return $cfg;
}

/** The site's absolute base URL (scheme + host). */
function baseUrl(): string {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}
