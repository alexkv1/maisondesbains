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

/** Format integer pence as £X.XX. */
function money(int $pence): string {
    return '£' . number_format($pence / 100, 2);
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
