<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';
require_once __DIR__ . '/../../utils/Auth/Verify.php';

if (!$AUTH->valid) {
    respond(['success' => true, 'authenticated' => false]);
}

respond([
    'success' => true,
    'authenticated' => true,
    'user' => [
        'email' => $AUTH->email,
        'first_name' => $AUTH->first_name,
        'last_name' => $AUTH->last_name,
    ],
]);
