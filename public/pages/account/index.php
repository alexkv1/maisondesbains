<?php
$root = dirname(__DIR__, 2);
require_once $root . '/db.php';
require_once $root . '/functions.php';
require_once $root . '/utils/Auth/Verify.php';

// Guests are sent to sign in.
if (!$AUTH->valid) {
    header('Location: /login?redirect=/account');
    exit;
}

// Order history, rendered server-side.
$orders = $db->select(
    "SELECT `reference`, `total_cents`, `status`, `date_created`
       FROM `orders` WHERE `user` = ? ORDER BY `id` DESC",
    [$AUTH->user], 'i'
) ?: [];

$PAGE_TITLE = 'Your Account — Maison Des Bains';
require $root . '/utils/layout/header.php';
?>
<main class="pagepad account">
  <div class="account__head">
    <div>
      <span class="secnum">The Maison</span>
      <h1 class="section-title">Good day, <?= e($AUTH->first_name ?: 'friend') ?>.</h1>
      <p class="account__email mono"><?= e($AUTH->email) ?></p>
    </div>
    <button class="btn btn--secondary" id="logoutBtn">Sign out</button>
  </div>

  <section class="account__orders">
    <span class="eyebrow">Order history</span>
    <?php if (!$orders): ?>
      <p class="account__empty">You have not yet placed an order. <a href="/#collection">Begin with the selection.</a></p>
    <?php else: ?>
      <table class="orders">
        <thead><tr><th>Reference</th><th>Date</th><th>Status</th><th class="ta-r">Total</th><th></th></tr></thead>
        <tbody>
          <?php foreach ($orders as $o): ?>
          <tr>
            <td class="mono"><?= e($o['reference']) ?></td>
            <td><?= e(date('j M Y', (int)$o['date_created'])) ?></td>
            <td><span class="pill pill--<?= e($o['status']) ?>"><?= e(ucfirst($o['status'])) ?></span></td>
            <td class="mono ta-r"><?= money((int)$o['total_cents']) ?></td>
            <td class="ta-r"><a class="orders__view" href="/order?ref=<?= e($o['reference']) ?>">View</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>
<?php require $root . '/utils/layout/footer.php'; ?>
