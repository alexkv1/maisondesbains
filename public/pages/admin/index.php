<?php
/**
 * Support admin — grant gifts to customers.
 * Restricted to signed-in accounts with is_admin = 1. Not indexed.
 */
$root = dirname(__DIR__, 2);
require_once $root . '/db.php';
require_once $root . '/functions.php';
require_once $root . '/utils/cart.php';           // grantWelcomeGifts
require_once $root . '/utils/Auth/Verify.php';   // provides $db, $AUTH

// Guests → sign in; non-admins → refused.
if (!$AUTH->valid) {
    header('Location: /login?redirect=/admin');
    exit;
}

$msg = '';
$err = '';

$action = ($AUTH->is_admin && $_SERVER['REQUEST_METHOD'] === 'POST') ? ($_POST['action'] ?? '') : '';

if ($action === 'grant') {
    $email   = trim(strtolower($_POST['email'] ?? ''));
    $variant = trim($_POST['variant'] ?? '');
    $label   = trim($_POST['label'] ?? '') ?: 'A gift, with our compliments';
    $u = $db->select("SELECT id FROM `users` WHERE `email` = ? AND `status` = 1 LIMIT 1", [$email], 's');
    $v = $db->select("SELECT id FROM `product_variants` WHERE `identifier` = ? LIMIT 1", [$variant], 's');
    if (!$u) {
        $err = 'No account found for ' . $email . '.';
    } elseif (!$v) {
        $err = 'Unknown product.';
    } else {
        $ok = $db->execute(
            "INSERT INTO `user_gifts` (`user`, `variant`, `label`, `status`, `date_created`) VALUES (?, ?, ?, 'available', ?)",
            [(int)$u[0]['id'], (int)$v[0]['id'], $label, time()], 'iisi'
        );
        $msg = $ok ? ('Gift granted to ' . $email . '. It will appear in their account to claim.') : 'Could not grant the gift.';
    }
} elseif ($action === 'points') {
    $email = trim(strtolower($_POST['email'] ?? ''));
    $delta = (int)($_POST['points'] ?? 0);
    $u = $db->select("SELECT id, points FROM `users` WHERE `email` = ? AND `status` = 1 LIMIT 1", [$email], 's');
    if (!$u) {
        $err = 'No account found for ' . $email . '.';
    } elseif ($delta === 0) {
        $err = 'Enter a non-zero number of points.';
    } else {
        $uid = (int)$u[0]['id'];
        $old = (int)$u[0]['points'];
        $new = max(0, $old + $delta);
        $db->execute("UPDATE `users` SET `points` = ? WHERE `id` = ?", [$new, $uid], 'ii');
        // A manual award that promotes the member grants the welcome gift too.
        $oldTierKey = tierForPoints($old)['key'];
        $newTier    = tierForPoints($new);
        if (tierRank($newTier['key']) > tierRank($oldTierKey) && tierRank($newTier['key']) >= 1) {
            grantWelcomeGifts($db, $uid, $newTier['key']);
        }
        $verb = $delta > 0 ? 'Awarded ' . $delta : 'Deducted ' . abs($delta);
        $msg = $verb . ' points — ' . $email . ' now has ' . number_format($new) . ' (' . $newTier['name'] . ').';
    }
}

$variants = [];
$recent = [];
if ($AUTH->is_admin) {
    $variants = $db->select(
        "SELECT v.identifier, v.size, p.brand, p.name FROM `product_variants` v JOIN `products` p ON p.id = v.product
          WHERE v.status = 1 ORDER BY p.brand, p.name, v.position"
    ) ?: [];
    $recent = $db->select(
        "SELECT g.label, g.status, g.date_created, u.email, p.name, v.size
           FROM `user_gifts` g JOIN `users` u ON u.id = g.user
           JOIN `product_variants` v ON v.id = g.variant JOIN `products` p ON p.id = v.product
          ORDER BY g.id DESC LIMIT 20"
    ) ?: [];
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="robots" content="noindex, nofollow" />
<title>Support Admin — Maison Des Bains</title>
<link rel="stylesheet" href="/assets/ds/ds.css?v=<?= @filemtime($root . '/assets/ds/ds.css') ?>" />
<link rel="stylesheet" href="/assets/styles.css?v=<?= @filemtime($root . '/assets/styles.css') ?>" />
</head>
<body>
<main class="admin">
  <div class="admin__wrap">
    <div class="admin__head">
      <a href="/" class="wordmark">Maison Des Bains</a>
      <span class="eyebrow">Support Admin</span>
    </div>

    <?php if (!$AUTH->is_admin): ?>
      <div class="admin__card" style="text-align:center">
        <h1 class="section-title">Not authorised</h1>
        <p style="margin:1rem 0; color:var(--text-secondary)">This area is for Maison staff. You are signed in as <span class="mono"><?= e($AUTH->email) ?></span>.</p>
        <a class="btn btn--secondary" href="/account">Back to your account</a>
      </div>

    <?php else: ?>
      <div class="admin__bar">
        <h1 class="section-title">Grant a gift</h1>
        <a class="btn btn--ghost" href="/account">Account</a>
      </div>

      <?php if ($msg): ?><p class="admin__msg"><?= e($msg) ?></p><?php endif; ?>
      <?php if ($err): ?><p class="auth__err"><?= e($err) ?></p><?php endif; ?>

      <form class="admin__card" method="post" action="/admin">
        <input type="hidden" name="action" value="grant" />
        <div class="field">
          <label for="email">Customer email</label>
          <input id="email" name="email" type="email" required placeholder="name@example.com" />
        </div>
        <div class="field">
          <label for="variant">Gift product</label>
          <select id="variant" name="variant" class="admin__select" required>
            <?php foreach ($variants as $v): ?>
            <option value="<?= e($v['identifier']) ?>"><?= e($v['brand'] . ' — ' . $v['name'] . ' (' . $v['size'] . ')') ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="label">Message on the gift <span>(optional)</span></label>
          <input id="label" name="label" placeholder="A gift, with our compliments" />
        </div>
        <button class="btn btn--primary btn--full" type="submit">Grant gift</button>
      </form>

      <div class="admin__bar"><h1 class="section-title">Award points</h1></div>
      <form class="admin__card" method="post" action="/admin">
        <input type="hidden" name="action" value="points" />
        <div class="field">
          <label for="p-email">Customer email</label>
          <input id="p-email" name="email" type="email" required placeholder="name@example.com" />
        </div>
        <div class="field">
          <label for="p-points">Points <span>(use a negative number to deduct)</span></label>
          <input id="p-points" name="points" type="number" step="1" required placeholder="e.g. 250" />
        </div>
        <button class="btn btn--primary btn--full" type="submit">Award points</button>
      </form>

      <section class="admin__recent">
        <span class="eyebrow">Recently granted</span>
        <?php if (!$recent): ?>
          <p class="account__empty">No gifts granted yet.</p>
        <?php else: ?>
          <table class="orders">
            <thead><tr><th>Customer</th><th>Gift</th><th>Message</th><th>Status</th><th>Date</th></tr></thead>
            <tbody>
              <?php foreach ($recent as $r): ?>
              <tr>
                <td class="mono"><?= e($r['email']) ?></td>
                <td><?= e($r['name']) ?> <span class="mono">(<?= e($r['size']) ?>)</span></td>
                <td><?= e($r['label']) ?></td>
                <td><span class="pill pill--<?= $r['status'] === 'redeemed' ? 'paid' : 'pending' ?>"><?= e(ucfirst($r['status'])) ?></span></td>
                <td><?= e(date('j M Y', (int)$r['date_created'])) ?></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        <?php endif; ?>
      </section>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
