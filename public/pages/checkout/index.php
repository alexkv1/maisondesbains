<?php
$root = dirname(__DIR__, 2);
require_once $root . '/db.php';
require_once $root . '/functions.php';
require_once $root . '/utils/cart.php';
require_once $root . '/utils/Auth/Verify.php';
$PAGE_TITLE = 'Checkout — Maison Des Bains';

$prefill = [
    'email'      => $AUTH->valid ? $AUTH->email : '',
    'first_name' => $AUTH->valid ? $AUTH->first_name : '',
    'last_name'  => $AUTH->valid ? $AUTH->last_name : '',
];

// Stock check on load — warn before the shopper fills in details.
$cartId = resolveCart($db, $AUTH->valid ? $AUTH->user : null);
$summary = cartSummary($db, $cartId, false, $AUTH->valid ? $AUTH->tier['key'] : null, claimedGifts($db, $AUTH->valid ? $AUTH->user : null));
$stockIssues = checkStock($db, $summary['items']);

require $root . '/utils/layout/header.php';
?>
<main class="pagepad checkout">
  <span class="secnum">Checkout</span>
  <h1 class="section-title">Delivery &amp; payment</h1>

  <?php if (!$AUTH->valid): ?>
  <p class="checkout__signin">Have an account? <a href="/login?redirect=/checkout">Sign in</a> to prefill your details — or continue as a guest below.</p>
  <?php endif; ?>

  <?php if ($stockIssues): ?>
  <div class="stock-alert" role="alert">
    <p class="stock-alert__lead">Some items need attention before you can pay:</p>
    <ul>
      <?php foreach ($stockIssues as $iss): ?>
        <li><?= e($iss['name']) ?> <span class="mono">(<?= e($iss['size']) ?>)</span> —
          <?= $iss['reason'] === 'qty'
                ? 'only ' . (int)$iss['available'] . ' available'
                : 'no longer available' ?></li>
      <?php endforeach; ?>
    </ul>
    <a class="btn btn--secondary" href="/cart">Return to the bag to adjust</a>
  </div>
  <?php endif; ?>

  <div class="checkout__grid">
    <form class="checkout__form" id="checkoutForm" autocomplete="on">
      <div class="field">
        <label for="co-email">Email</label>
        <input id="co-email" name="email" type="email" required value="<?= e($prefill['email']) ?>" />
      </div>
      <div class="field-row">
        <div class="field"><label for="co-first">First name</label><input id="co-first" name="first_name" required value="<?= e($prefill['first_name']) ?>" /></div>
        <div class="field"><label for="co-last">Last name</label><input id="co-last" name="last_name" value="<?= e($prefill['last_name']) ?>" /></div>
      </div>
      <div class="field"><label for="co-l1">Address</label><input id="co-l1" name="address_line1" required /></div>
      <div class="field"><label for="co-l2">Address line 2 <span>(optional)</span></label><input id="co-l2" name="address_line2" /></div>
      <div class="field-row">
        <div class="field"><label for="co-city">City</label><input id="co-city" name="city" required /></div>
        <div class="field"><label for="co-post">Postcode</label><input id="co-post" name="postcode" required /></div>
      </div>
      <div class="field"><label for="co-country">Country</label><input id="co-country" name="country" value="France" /></div>

      <label class="giftline"><input type="checkbox" id="co-gift" name="gift_wrap" /> <span>Gift wrap in unmarked paper (+<?= money(currencies()[currentCurrency()]['gift_wrap']) ?>)</span></label>

      <button type="submit" class="btn btn--primary btn--lg btn--full" id="placeOrder"<?= $stockIssues ? ' disabled' : '' ?>>Place order</button>
      <p class="checkout__err" id="checkoutErr" aria-live="polite"></p>
      <p class="checkout__note mono" id="checkoutMode"></p>
    </form>

    <aside class="checkout__summary" id="coSummary">
      <span class="eyebrow">Your order</span>
      <div class="checkout__lines" id="coLines"></div>
      <div class="summary__row"><span>Subtotal</span><span class="mono" id="coSubtotal"><?= money(0) ?></span></div>
      <div class="summary__row"><span>Delivery</span><span class="mono" id="coShipping">—</span></div>
      <div class="summary__row" id="coGiftRow" hidden><span>Gift wrap</span><span class="mono" id="coGift"><?= money(currencies()[currentCurrency()]['gift_wrap']) ?></span></div>
      <div class="summary__row summary__row--total"><span>Total</span><span class="mono" id="coTotal"><?= money(0) ?></span></div>
    </aside>
  </div>
</main>
<?php require $root . '/utils/layout/footer.php'; ?>
