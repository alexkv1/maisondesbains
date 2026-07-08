<?php
/**
 * Session verification. Include this to obtain $AUTH — a stdClass with
 * ->valid and, when valid, the user's id and profile. Mirrors yuz's
 * cookie-token + sessions-table mechanism.
 */
require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../../functions.php';

function verifyAuth(DB $db): stdClass {
    $auth = new stdClass();
    $auth->valid = false;
    $auth->user = null;

    if (empty($_COOKIE['AUTH'])) {
        return $auth;
    }

    $token = $_COOKIE['AUTH'];
    if (!preg_match('/^[a-f0-9]{32,}$/', $token)) {
        return $auth;
    }

    $rows = $db->select(
        "SELECT * FROM `sessions` WHERE `token` = ? AND `status` = 1",
        [$token],
        's'
    );
    if (!$rows) {
        return $auth;
    }

    $session = $rows[0];
    $auth->user = (int)$session['user'];

    $userRows = $db->select(
        "SELECT * FROM `users` WHERE `id` = ? AND `status` = 1",
        [$auth->user],
        'i'
    );
    if (!$userRows) {
        $auth->user = null;
        return $auth;
    }

    $user = $userRows[0];
    $auth->email = $user['email'];
    $auth->first_name = $user['first_name'];
    $auth->last_name = $user['last_name'];
    $auth->phone = $user['phone'];
    $auth->points = (int)($user['points'] ?? 0);
    $auth->tier = tierForPoints($auth->points);
    $auth->is_admin = (int)($user['is_admin'] ?? 0) === 1;
    $auth->valid = true;

    return $auth;
}

// Convenience: expose $AUTH to any includer that also has (or creates) $db.
if (!isset($db) || !($db instanceof DB)) {
    $db = new DB();
}
$AUTH = verifyAuth($db);
