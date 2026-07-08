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

/** The complimentary gift: which variant, unlocked above gift_threshold. */
const MDB_GIFT_VARIANT = 'bal-dafrique-soap-30g';

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
