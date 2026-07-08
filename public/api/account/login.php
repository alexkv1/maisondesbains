<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/cart.php';

requireMethod('POST');
$in = readInput();

$email = trim(strtolower($in['email'] ?? ''));
$password = (string)($in['password'] ?? '');

if (!$email || !$password) {
    respond(['success' => false, 'message' => 'Enter your email and password.'], 400);
}

$db = new DB();

$rows = $db->select("SELECT * FROM `users` WHERE `email` = ? AND `status` = 1 LIMIT 1", [$email], 's');
if (!$rows || !password_verify($password, $rows[0]['password_hash'])) {
    respond(['success' => false, 'message' => 'Incorrect email or password.'], 401);
}

$user = $rows[0];
$userId = (int)$user['id'];

// Capture the current guest cart before we switch context.
$guestToken = $_COOKIE['CART'] ?? null;

$token = generateRandomString();
$db->execute(
    "INSERT INTO `sessions` (`user`, `token`, `date_created`, `status`) VALUES (?, ?, ?, 1)",
    [$userId, $token, time()],
    'isi'
);
setcookie('AUTH', $token, [
    'expires'  => time() + 60 * 60 * 24 * 30,
    'path'     => '/',
    'httponly' => true,
    'samesite' => 'Lax',
]);

// Merge guest cart items into the user's cart.
mergeGuestCart($db, $userId, $guestToken);

respond([
    'success' => true,
    'user' => [
        'email' => $user['email'],
        'first_name' => $user['first_name'],
        'last_name' => $user['last_name'],
    ],
]);

/** Move items from a guest cart into the user's cart, summing quantities. */
function mergeGuestCart(DB $db, int $userId, ?string $guestToken): void {
    // The user's canonical cart (creates/links one).
    $userCart = resolveCart($db, $userId);

    if (!$guestToken || !preg_match('/^[a-f0-9]{32,}$/', $guestToken)) {
        return;
    }
    $guestRows = $db->select("SELECT id FROM `carts` WHERE `token` = ? LIMIT 1", [$guestToken], 's');
    if (!$guestRows) {
        return;
    }
    $guestCart = (int)$guestRows[0]['id'];
    if ($guestCart === $userCart) {
        return;
    }

    $items = $db->select("SELECT variant, quantity FROM `cart_items` WHERE `cart` = ?", [$guestCart], 'i');
    foreach ($items ?: [] as $it) {
        $db->execute(
            "INSERT INTO `cart_items` (`cart`, `variant`, `quantity`) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE `quantity` = `quantity` + VALUES(`quantity`)",
            [$userCart, (int)$it['variant'], (int)$it['quantity']],
            'iii'
        );
    }
    // Retire the guest cart.
    $db->execute("DELETE FROM `cart_items` WHERE `cart` = ?", [$guestCart], 'i');
    $db->execute("DELETE FROM `carts` WHERE `id` = ?", [$guestCart], 'i');
}
