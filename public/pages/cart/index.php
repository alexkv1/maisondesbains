<?php
$root = dirname(__DIR__, 2);
require_once $root . '/db.php';
require_once $root . '/functions.php';
require_once $root . '/utils/Auth/Verify.php';
$PAGE_TITLE = 'Your Basket — Maison Des Bains';
require $root . '/utils/layout/header.php';
?>
<main class="pagepad cartpage">
  <span class="secnum">The Basket</span>
  <h1 class="section-title">Your bag</h1>

  <div class="cartpage__grid" id="cartPage" data-empty="The basket is empty.">
    <div class="cartpage__lines" id="cartLines"></div>
    <aside class="cartpage__summary" id="cartSummary">
      <div class="cart-progress" id="cartPageProgress"></div>
      <div class="summary__row summary__row--total"><span>Subtotal</span><span class="mono" id="sumSubtotal"><?= money(0) ?></span></div>
      <p class="drawer__ship">Complimentary delivery over <?= freeShippingLabel() ?>. Delivery &amp; taxes calculated at checkout.</p>
      <a class="btn btn--primary btn--full" href="/checkout" id="cartCheckout">Proceed to checkout</a>
      <a class="btn btn--ghost btn--full" href="/#collection">Continue shopping</a>
    </aside>
  </div>
</main>
<?php require $root . '/utils/layout/footer.php'; ?>
