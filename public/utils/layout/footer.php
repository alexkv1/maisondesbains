<?php
/**
 * Shared footer + basket drawer + search overlay. Include at the end of
 * every page, before </body>. Pages that embed the product catalogue for
 * search should set $SEARCH_PRODUCTS (array) before including.
 */
$SEARCH_PRODUCTS = $SEARCH_PRODUCTS ?? [];
?>
<footer class="footer">
  <div class="footer__grid">
    <div class="footer__lead">
      <p class="footer__line">Keep the ritual of water.</p>
      <form class="footer__signup" id="signupForm">
        <input type="email" placeholder="Email address" aria-label="Email address" required />
        <button type="submit">Join</button>
      </form>
      <p class="footer__note" id="signupNote" aria-live="polite"></p>
    </div>
    <div class="footer__col">
      <h4>The Maison</h4>
      <a href="/#ritual">Our Philosophy</a><a href="/#journal">The Journal</a><a href="/#collection">Brands</a><a href="/account">Account</a>
    </div>
    <div class="footer__col">
      <h4>Shop</h4>
      <a href="/#collection" data-cat="Soap">Soap</a><a href="/#collection" data-cat="Wash">Wash</a><a href="/#collection" data-cat="Body">Body</a><a href="/#collection">Le Labo</a><a href="/#collection">Byredo</a>
    </div>
    <div class="footer__col">
      <h4>Service</h4>
      <a href="/cart">Basket</a><a href="/checkout">Checkout</a><a href="/account">Orders</a><a href="/#">Care</a>
    </div>
  </div>
  <div class="footer__base">
    <span>© <?= date('Y') ?> Maison Des Bains</span>
    <span>MDB · Paris</span>
  </div>
</footer>

<!-- Basket drawer -->
<div class="scrim" id="scrim"></div>
<aside class="drawer" id="drawer" aria-label="Basket" aria-hidden="true">
  <div class="drawer__head">
    <span class="drawer__title">Basket <span id="drawerCount">(0)</span></span>
    <button class="drawer__close" id="drawerClose" aria-label="Close basket"><i data-lucide="x"></i></button>
  </div>
  <div class="drawer__body" id="drawerBody"></div>
  <div class="drawer__foot">
    <div class="drawer__wrap">
      <span>Gift wrap in unmarked paper</span>
      <button class="switch" id="giftSwitch" role="switch" aria-checked="false"><span class="switch__dot"></span></button>
    </div>
    <div class="drawer__row">
      <span>Subtotal</span>
      <span id="drawerTotal" class="mono">€0.00</span>
    </div>
    <p class="drawer__ship">Complimentary delivery over €75. Taxes calculated at checkout.</p>
    <a class="btn btn--primary btn--full" href="/checkout" id="checkoutBtn">Proceed to checkout</a>
  </div>
</aside>

<!-- Search overlay -->
<div class="search" id="search" aria-hidden="true">
  <div class="search__bar">
    <i data-lucide="search"></i>
    <input type="text" id="searchInput" placeholder="Search the Maison" aria-label="Search" />
    <button class="search__close" id="searchClose" aria-label="Close search"><i data-lucide="x"></i></button>
  </div>
  <div class="search__results" id="searchResults"></div>
</div>

<script>
  window.MDB_SEARCH = <?= json_encode(array_values($SEARCH_PRODUCTS)) ?>;
</script>
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.js"></script>
<script src="/assets/app.js"></script>
</body>
</html>
