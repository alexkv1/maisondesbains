<?php
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';

$db = new DB();

if (!empty($_COOKIE['AUTH'])) {
    $db->execute("UPDATE `sessions` SET `status` = 0 WHERE `token` = ?", [$_COOKIE['AUTH']], 's');
    setcookie('AUTH', '', ['expires' => time() - 3600, 'path' => '/']);
}

respond(['success' => true]);
