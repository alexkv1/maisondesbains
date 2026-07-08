<?php
$root = dirname(__DIR__, 2);
require_once $root . '/db.php';
require_once $root . '/functions.php';
require_once $root . '/utils/render.php';
require_once $root . '/utils/Auth/Verify.php';

// Guests are sent to sign in.
if (!$AUTH->valid) {
    header('Location: /login?redirect=/account');
    exit;
}

// Gifts available to (or claimed for) this member.
$gifts = $db->select(
    "SELECT g.id, g.label, g.status, p.name, v.size, p.image
       FROM `user_gifts` g
       JOIN `product_variants` v ON v.id = g.variant
       JOIN `products` p ON p.id = v.product
      WHERE g.`user` = ? AND g.`status` IN ('available','claimed')
      ORDER BY g.id DESC",
    [$AUTH->user], 'i'
) ?: [];

// Order history, rendered server-side.
$orders = $db->select(
    "SELECT `reference`, `total_cents`, `currency`, `status`, `date_created`
       FROM `orders` WHERE `user` = ? ORDER BY `id` DESC",
    [$AUTH->user], 'i'
) ?: [];

// Loyalty tier + progress.
$tier = $AUTH->tier;
$points = $AUTH->points;
$tiers = loyaltyTiers();
$nextTier = null;
foreach ($tiers as $t) {
    if ($t['min'] > $points) { $nextTier = $t; break; }
}
$toNext = $nextTier ? ($nextTier['min'] - $points) : 0;
// Progress within the current tier band.
$bandMin = $tier['min'];
$bandMax = $tier['max'] ?? max($points, $bandMin);
$progress = ($tier['max'] === null) ? 100 : min(100, round((($points - $bandMin) / max(1, ($bandMax - $bandMin))) * 100));

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
    <div class="account__actions">
      <?php if ($AUTH->is_admin): ?><a class="btn btn--ghost" href="/admin">Support admin</a><?php endif; ?>
      <button class="btn btn--secondary" id="logoutBtn">Sign out</button>
    </div>
  </div>

  <section class="loyalty">
    <div class="loyalty__head">
      <div>
        <span class="eyebrow">Maison Membership</span>
        <h2 class="loyalty__tier"><?= e($tier['name']) ?></h2>
      </div>
      <div class="loyalty__points">
        <span class="loyalty__num mono"><?= number_format($points) ?></span>
        <span class="loyalty__lbl">points</span>
      </div>
    </div>

    <div class="loyalty__bar" aria-hidden="true"><span style="width: <?= (int)$progress ?>%"></span></div>
    <p class="loyalty__next mono">
      <?php if ($nextTier): ?>
        <?= number_format($toNext) ?> points to <?= e($nextTier['name']) ?>
      <?php else: ?>
        You've reached our highest tier. Thank you.
      <?php endif; ?>
    </p>

    <div class="loyalty__benefits">
      <span class="eyebrow">Your benefits</span>
      <ul>
        <?php foreach ($tier['benefits'] as $b): ?>
          <li><span>—</span> <?= e($b) ?></li>
        <?php endforeach; ?>
      </ul>
    </div>

    <details class="loyalty__all">
      <summary>All tiers &amp; how points are earned</summary>
      <p class="loyalty__earn mono">Earn 10 points per €1 · 1 point per 1 kr spent.</p>
      <div class="loyalty__grid">
        <?php foreach ($tiers as $t): ?>
        <div class="loyalty__card<?= $t['key'] === $tier['key'] ? ' is-current' : '' ?>">
          <span class="loyalty__cardname"><?= e($t['name']) ?></span>
          <span class="loyalty__cardpts mono"><?= number_format($t['min']) ?><?= $t['max'] !== null ? '–' . number_format($t['max']) : '+' ?></span>
          <ul>
            <?php foreach ($t['benefits'] as $b): ?><li><?= e($b) ?></li><?php endforeach; ?>
          </ul>
        </div>
        <?php endforeach; ?>
      </div>
    </details>
  </section>

  <section class="gifts">
    <span class="eyebrow">Your gifts</span>
    <?php if (!$gifts): ?>
      <p class="account__empty">No gifts waiting just now. Reach the next tier — or a kindness from us — and they'll appear here.</p>
    <?php else: ?>
      <div class="gifts__grid">
        <?php foreach ($gifts as $g): $claimed = $g['status'] === 'claimed'; ?>
        <div class="giftcard<?= $claimed ? ' is-claimed' : '' ?>">
          <div class="giftcard__plate">
            <?php if (!empty($g['image'])): ?><img src="<?= e($g['image']) ?>" alt="<?= e($g['name']) ?>" />
            <?php else: ?><span aria-hidden="true"><?= e(mb_substr($g['name'], 0, 1)) ?></span><?php endif; ?>
          </div>
          <div class="giftcard__body">
            <span class="giftcard__label"><?= e($g['label']) ?></span>
            <span class="giftcard__name"><?= e($g['name']) ?> <span class="mono">(<?= e($g['size']) ?>)</span></span>
            <?php if ($claimed): ?>
              <span class="giftcard__status">Reserved for your next order</span>
              <button class="giftcard__remove" data-unclaim-gift="<?= (int)$g['id'] ?>">Remove</button>
            <?php else: ?>
              <button class="btn btn--secondary" data-claim-gift="<?= (int)$g['id'] ?>">Claim</button>
            <?php endif; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <p class="gifts__note mono">Claimed gifts are added free to your next order.</p>
    <?php endif; ?>
  </section>

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
            <td class="mono ta-r"><?= money((int)$o['total_cents'], $o['currency']) ?></td>
            <td class="ta-r"><a class="orders__view" href="/order?ref=<?= e($o['reference']) ?>">View</a></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    <?php endif; ?>
  </section>
</main>
<?php require $root . '/utils/layout/footer.php'; ?>
