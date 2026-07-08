<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/cart.php';

requireMethod('POST');
$in = readInput();

$email = trim(strtolower($in['email'] ?? ''));
$password = (string)($in['password'] ?? '');
$firstName = trim($in['first_name'] ?? '');
$lastName = trim($in['last_name'] ?? '');

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    respond(['success' => false, 'message' => 'Please enter a valid email address.'], 400);
}
if (strlen($password) < 8) {
    respond(['success' => false, 'message' => 'Password must be at least 8 characters.'], 400);
}

$db = new DB();

$existing = $db->select("SELECT id FROM `users` WHERE `email` = ? LIMIT 1", [$email], 's');
if ($existing) {
    respond(['success' => false, 'message' => 'An account with that email already exists.'], 409);
}

$hash = password_hash($password, PASSWORD_DEFAULT);
$ok = $db->execute(
    "INSERT INTO `users` (`email`, `password_hash`, `first_name`, `last_name`, `status`, `date_created`)
     VALUES (?, ?, ?, ?, 1, ?)",
    [$email, $hash, $firstName, $lastName, time()],
    'ssssi'
);
if (!$ok) {
    respond(['success' => false, 'message' => 'Could not create the account.'], 500);
}

$userId = (int)$db->lastInsertId();

// Start a session (same mechanism as login).
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

// Adopt any guest cart.
resolveCart($db, $userId);

respond([
    'success' => true,
    'user' => ['email' => $email, 'first_name' => $firstName, 'last_name' => $lastName],
]);
