<?php
$root = dirname(__DIR__, 2);
require_once $root . '/db.php';
require_once $root . '/functions.php';
require_once $root . '/utils/Auth/Verify.php';

$reference = trim($_GET['ref'] ?? '');
$rows = $reference
    ? $db->select("SELECT * FROM `orders` WHERE `reference` = ? LIMIT 1", [$reference], 's')
    : [];

$order = $rows[0] ?? null;

// Access rule: owner must be signed in; guest orders are viewable by reference.
if ($order && $order['user'] !== null) {
    if (!$AUTH->valid || (int)$AUTH->user !== (int)$order['user']) {
        $order = null;
    }
}

$PAGE_TITLE = 'Order Confirmation — Maison Des Bains';
require $root . '/utils/layout/header.php';

if (!$order): ?>
  <main class="pagepad">
    <span class="secnum">Order</span>
    <h1 class="section-title">We could not find that order.</h1>
    <p style="margin-top:1rem"><a class="btn btn--secondary" href="/account">Go to your account</a></p>
  </main>
<?php
else:
    $items = $db->select(
        "SELECT `brand`, `name`, `sku`, `unit_price_cents`, `quantity` FROM `order_items` WHERE `order` = ? ORDER BY id ASC",
        [(int)$order['id']], 'i'
    ) ?: [];
    $paid = $order['status'] === 'paid';
?>
  <main class="pagepad confirm">
    <div class="confirm__hero">
      <span class="secnum"><?= $paid ? 'Order confirmed' : 'Order received' ?></span>
      <h1 class="section-title">Thank you<?= $order['first_name'] ? ', ' . e($order['first_name']) : '' ?>.</h1>
      <p class="confirm__lede">
        <?php if ($paid): ?>
          Your order is confirmed and being wrapped by hand. A confirmation has been sent to <span class="mono"><?= e($order['email']) ?></span>.
        <?php else: ?>
          Your order has been received and is awaiting payment confirmation.
        <?php endif; ?>
      </p>
      <p class="confirm__ref">Reference <span class="mono"><?= e($order['reference']) ?></span></p>
    </div>

    <div class="confirm__grid">
      <div class="confirm__items">
        <span class="eyebrow">Your objects</span>
        <?php foreach ($items as $it): ?>
        <div class="line line--static">
          <div class="line__plate"><span aria-hidden="true"><?= e(mb_substr($it['name'], 0, 1)) ?></span></div>
          <div class="line__body">
            <span class="line__brand"><?= e($it['brand']) ?></span>
            <span class="line__name"><?= e($it['name']) ?></span>
            <span class="mono confirm__qty"><?= (int)$it['quantity'] ?> × <?= money((int)$it['unit_price_cents'], $order['currency']) ?></span>
          </div>
          <span class="line__price mono"><?= money((int)$it['unit_price_cents'] * (int)$it['quantity'], $order['currency']) ?></span>
        </div>
        <?php endforeach; ?>
      </div>

      <aside class="confirm__summary">
        <span class="eyebrow">Summary</span>
        <div class="summary__row"><span>Subtotal</span><span class="mono"><?= money((int)$order['subtotal_cents'], $order['currency']) ?></span></div>
        <div class="summary__row"><span>Delivery</span><span class="mono"><?= (int)$order['shipping_cents'] === 0 ? 'Complimentary' : money((int)$order['shipping_cents'], $order['currency']) ?></span></div>
        <?php if ((int)$order['gift_wrap_cents'] > 0): ?>
        <div class="summary__row"><span>Gift wrap</span><span class="mono"><?= money((int)$order['gift_wrap_cents'], $order['currency']) ?></span></div>
        <?php endif; ?>
        <div class="summary__row summary__row--total"><span>Total</span><span class="mono"><?= money((int)$order['total_cents'], $order['currency']) ?></span></div>

        <div class="confirm__ship">
          <span class="eyebrow">Delivering to</span>
          <p><?= e(trim($order['first_name'] . ' ' . $order['last_name'])) ?><br />
             <?= e($order['address_line1']) ?><?= $order['address_line2'] ? '<br />' . e($order['address_line2']) : '' ?><br />
             <?= e($order['city']) ?>, <?= e($order['postcode']) ?><br />
             <?= e($order['country']) ?></p>
        </div>

        <a class="btn btn--secondary btn--full" href="/account">View your orders</a>
        <a class="btn btn--ghost btn--full" href="/#collection">Continue shopping</a>
      </aside>
    </div>
  </main>
<?php endif; ?>
<?php require $root . '/utils/layout/footer.php'; ?>
