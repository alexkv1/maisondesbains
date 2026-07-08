<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/Auth/Verify.php';

if (!$AUTH->valid) {
    respond(['success' => false, 'message' => 'Not signed in.'], 401);
}

$orders = $db->select(
    "SELECT `reference`, `total_cents`, `currency`, `status`, `date_created`
       FROM `orders` WHERE `user` = ? ORDER BY `id` DESC",
    [$AUTH->user],
    'i'
);

respond(['success' => true, 'orders' => $orders ?: []]);
