/* ============================================================
   MAISON DES BAINS — storefront behaviour (API-driven)
   Talks to /api/* for cart, account, checkout, orders.
   ============================================================ */

const HEART = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"/></svg>`;
const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];

async function api(path, opts = {}) {
  const res = await fetch(path, {
    headers: { 'Content-Type': 'application/json' },
    credentials: 'same-origin',
    ...opts,
  });
  let data = {};
  try { data = await res.json(); } catch (e) { /* non-JSON */ }
  return { ok: res.ok, status: res.status, data };
}
const post = (path, body) => api(path, { method: 'POST', body: JSON.stringify(body || {}) });

/* Format an integer amount (already in the active currency's unit). */
function fmtAmount(amount) {
  const c = window.MDB_CURRENCY || { symbol: '€', minor: 100, decimals: 2, thousands: ',', position: 'before' };
  const parts = (amount / c.minor).toFixed(c.decimals).split('.');
  parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, c.thousands);
  const s = parts.join('.');
  return c.position === 'before' ? c.symbol + s : s + ' ' + c.symbol;
}
const gbp = fmtAmount;

/* Cookies (currency selection). */
function setCookie(k, v, days) {
  const d = new Date(); d.setTime(d.getTime() + days * 864e5);
  document.cookie = `${k}=${v};expires=${d.toUTCString()};path=/;samesite=Lax`;
}
function getCookie(k) {
  return document.cookie.split('; ').find(r => r.startsWith(k + '='))?.split('=')[1];
}

/* ============================================================
   FILTERS (home)
   ============================================================ */
const CATS = ['All', 'Soap', 'Wash', 'Body'];
const filtersEl = $('#filters');
if (filtersEl) {
  filtersEl.innerHTML = CATS.map((c, i) =>
    `<button class="tag${i === 0 ? ' is-active' : ''}" data-filter="${c}">${c}</button>`).join('');
  filtersEl.addEventListener('click', e => {
    const btn = e.target.closest('[data-filter]');
    if (btn) applyFilter(btn.dataset.filter);
  });
}
function applyFilter(cat) {
  if (!filtersEl) return;
  $$('.tag', filtersEl).forEach(t => t.classList.toggle('is-active', t.dataset.filter === cat));
  $$('.card').forEach(card => card.classList.toggle('is-hidden', !(cat === 'All' || card.dataset.cat === cat)));
}
$$('[data-cat]').forEach(el => el.addEventListener('click', () => {
  if (CATS.includes(el.dataset.cat) && filtersEl) applyFilter(el.dataset.cat);
}));

/* ============================================================
   CART STATE + DRAWER
   ============================================================ */
const cartCountEl = $('#cartCount');
const drawer = $('#drawer'), scrim = $('#scrim');
const drawerBody = $('#drawerBody'), drawerCount = $('#drawerCount'), drawerTotal = $('#drawerTotal');
const cartBtn = $('#cartBtn'), giftSwitch = $('#giftSwitch');
let giftWrap = false;
let lastCart = null;

function paintCount(count) {
  if (!cartCountEl) return;
  cartCountEl.textContent = count;
  cartCountEl.hidden = count === 0;
}

function renderDrawer(cart) {
  lastCart = cart;
  paintCount(cart.count);
  if (drawerCount) drawerCount.textContent = `(${cart.count})`;
  // Cart shows items only (+ gift wrap). Delivery is calculated at checkout.
  if (drawerTotal) drawerTotal.textContent = gbp(cart.subtotal_cents + cart.gift_wrap_cents);
  if (!drawerBody) return;
  if (cart.count === 0) {
    drawerBody.innerHTML = `<p class="drawer__empty">The basket is empty.</p>`;
    return;
  }
  drawerBody.innerHTML = giftNudge(cart) + cart.items.map(it => lineHTML(it, false)).join('');
  maybeShowGift(cart);
}

/* A cart line — normal (with quantity controls) or a complimentary gift. */
function lineHTML(it, asLink) {
  const plate = `<div class="line__plate">${it.image ? `<img src="${it.image}" alt="${it.name}" />` : `<span aria-hidden="true">${it.initial}</span>`}</div>`;
  const nameEl = asLink
    ? `<a class="line__name" href="/product?id=${it.product_identifier}">${it.name}</a>`
    : `<span class="line__name">${it.name}</span>`;
  if (it.is_gift) {
    return `
    <div class="line line--gift">
      ${plate}
      <div class="line__body">
        <span class="line__brand">${it.brand} · Complimentary</span>
        ${nameEl}
        <span class="line__size mono">${it.size}</span>
        <div class="line__foot">
          <span class="gifttag">Gift</span>
          <span class="line__price mono">Free</span>
        </div>
      </div>
    </div>`;
  }
  return `
    <div class="line">
      ${plate}
      <div class="line__body">
        <span class="line__brand">${it.brand}</span>
        ${nameEl}
        <span class="line__size mono">${it.size}</span>
        <div class="line__foot">
          <div class="qty">
            <button data-dec="${it.identifier}" aria-label="Decrease">−</button>
            <span>${it.quantity}</span>
            <button data-inc="${it.identifier}" aria-label="Increase">+</button>
          </div>
          <span class="line__price mono">${gbp(it.line_total)}</span>
        </div>
        <button class="line__remove" data-remove="${it.identifier}">Remove</button>
      </div>
    </div>`;
}

/* "Spend X more" nudge / claim prompt / confirmation. */
function giftNudge(cart) {
  if (cart.count === 0) return '';
  if (cart.gift_claimed) {
    return `<p class="gift-nudge is-unlocked">A complimentary Bal d'Afrique soap is included with your order.</p>`;
  }
  if (cart.gift_qualified) {
    return `<p class="gift-nudge is-unlocked">You've earned a complimentary soap. <button class="gift-nudge__claim" id="nudgeClaim">Claim your gift</button></p>`;
  }
  if (cart.gift_remaining > 0) {
    return `<p class="gift-nudge">Spend ${gbp(cart.gift_remaining)} more for a complimentary Bal d'Afrique soap.</p>`;
  }
  return '';
}

async function refreshCart() {
  const { data } = await api(`/api/cart/get?gift_wrap=${giftWrap ? 1 : 0}`);
  if (data.cart) {
    renderDrawer(data.cart);
    renderCartPage(data.cart);
    renderCheckoutSummary(data.cart);
  }
  return data.cart;
}

async function addToBag(id) {
  const { data } = await post('/api/cart/add', { product: id });
  if (data.cart) renderDrawer(data.cart);
  if (cartBtn) { cartBtn.classList.add('bump'); setTimeout(() => cartBtn.classList.remove('bump'), 320); }
  openDrawer();
}
async function setQty(id, qty) {
  const { data } = await post('/api/cart/update', { product: id, quantity: qty });
  if (data.cart) { renderDrawer(data.cart); renderCartPage(data.cart); }
}
async function removeItem(id) {
  const { data } = await post('/api/cart/remove', { product: id });
  if (data.cart) { renderDrawer(data.cart); renderCartPage(data.cart); }
}

function openDrawer() { if (!drawer) return; drawer.classList.add('is-open'); scrim.classList.add('is-open'); drawer.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; }
function closeDrawer() { if (!drawer) return; drawer.classList.remove('is-open'); scrim.classList.remove('is-open'); drawer.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; }

if (cartBtn) cartBtn.addEventListener('click', () => { refreshCart(); openDrawer(); });
if ($('#drawerClose')) $('#drawerClose').addEventListener('click', closeDrawer);
if (scrim) scrim.addEventListener('click', closeDrawer);
if (giftSwitch) giftSwitch.addEventListener('click', () => {
  giftWrap = !giftWrap;
  giftSwitch.setAttribute('aria-checked', String(giftWrap));
  refreshCart();
});

/* Delegated cart + wishlist actions */
document.addEventListener('click', e => {
  const add = e.target.closest('[data-add]');    if (add) { e.preventDefault(); return addToBag(add.dataset.add); }
  const inc = e.target.closest('[data-inc]');    if (inc) return setQty(inc.dataset.inc, currentQty(inc.dataset.inc) + 1);
  const dec = e.target.closest('[data-dec]');    if (dec) return setQty(dec.dataset.dec, currentQty(dec.dataset.dec) - 1);
  const rem = e.target.closest('[data-remove]'); if (rem) return removeItem(rem.dataset.remove);
  const wish = e.target.closest('[data-wish]');
  if (wish) { wish.classList.toggle('is-on'); }
});
function currentQty(id) {
  const it = lastCart && lastCart.items.find(i => i.identifier === id);
  return it ? it.quantity : 0;
}

/* ============================================================
   CART PAGE
   ============================================================ */
const cartLines = $('#cartLines');
function renderCartPage(cart) {
  if (!cartLines) return;
  const wrap = $('#cartPage');
  if (cart.count === 0) {
    cartLines.innerHTML = `<p class="drawer__empty">${wrap.dataset.empty}</p>`;
  } else {
    cartLines.innerHTML = giftNudge(cart) + cart.items.map(it => lineHTML(it, true)).join('');
  }
  const set = (id, v) => { const el = $(id); if (el) el.textContent = v; };
  // Items only (+ gift wrap). Delivery is calculated at checkout.
  set('#sumSubtotal', gbp(cart.subtotal_cents + cart.gift_wrap_cents));
}

/* ============================================================
   CHECKOUT PAGE
   ============================================================ */
const checkoutForm = $('#checkoutForm');
function renderCheckoutSummary(cart) {
  const lines = $('#coLines');
  if (!lines) return;
  lines.innerHTML = cart.items.map(it => `
    <div class="coitem">
      <span class="coitem__q mono">${it.quantity}×</span>
      <span class="coitem__n">${it.brand} — ${it.name} <span class="coitem__size">(${it.size})</span>${it.is_gift ? ' <span class="coitem__size">· Gift</span>' : ''}</span>
      <span class="mono">${it.is_gift ? 'Free' : gbp(it.line_total)}</span>
    </div>`).join('') || `<p class="drawer__empty">Your bag is empty.</p>`;
  const set = (id, v) => { const el = $(id); if (el) el.textContent = v; };
  set('#coSubtotal', gbp(cart.subtotal_cents));
  set('#coShipping', cart.count === 0 ? '—' : (cart.shipping_cents === 0 ? 'Complimentary' : gbp(cart.shipping_cents)));
  set('#coTotal', gbp(cart.total_cents));
  const giftRow = $('#coGiftRow');
  if (giftRow) giftRow.hidden = cart.gift_wrap_cents === 0;
}

if (checkoutForm) {
  const giftBox = $('#co-gift');
  if (giftBox) giftBox.addEventListener('change', () => { giftWrap = giftBox.checked; refreshCart(); });

  checkoutForm.addEventListener('submit', async e => {
    e.preventDefault();
    const err = $('#checkoutErr'); err.textContent = '';
    const btn = $('#placeOrder'); btn.disabled = true; btn.textContent = 'Placing order…';

    const body = Object.fromEntries(new FormData(checkoutForm).entries());
    body.gift_wrap = giftBox && giftBox.checked;

    const { data } = await post('/api/checkout/create', body);
    if (data.success && data.url) {
      window.location.href = data.url;
    } else {
      err.textContent = data.message || 'Something went wrong. Please try again.';
      btn.disabled = false; btn.textContent = 'Place order';
    }
  });
}

/* ============================================================
   AUTH (login / register tabs)
   ============================================================ */
const authCard = $('.auth__card');
if (authCard) {
  const redirect = authCard.dataset.redirect || '/account';
  $$('.auth__tab').forEach(tab => tab.addEventListener('click', () => {
    $$('.auth__tab').forEach(t => t.classList.remove('is-active'));
    tab.classList.add('is-active');
    $('#loginForm').hidden = tab.dataset.tab !== 'login';
    $('#registerForm').hidden = tab.dataset.tab !== 'register';
  }));

  $('#loginForm').addEventListener('submit', async e => {
    e.preventDefault();
    const err = $('#loginErr'); err.textContent = '';
    const body = Object.fromEntries(new FormData(e.target).entries());
    const { data } = await post('/api/account/login', body);
    if (data.success) window.location.href = redirect;
    else err.textContent = data.message || 'Could not sign in.';
  });

  $('#registerForm').addEventListener('submit', async e => {
    e.preventDefault();
    const err = $('#registerErr'); err.textContent = '';
    const body = Object.fromEntries(new FormData(e.target).entries());
    const { data } = await post('/api/account/register', body);
    if (data.success) window.location.href = redirect;
    else err.textContent = data.message || 'Could not create the account.';
  });
}

/* Sign out */
const logoutBtn = $('#logoutBtn');
if (logoutBtn) logoutBtn.addEventListener('click', async () => {
  await post('/api/account/logout', {});
  window.location.href = '/';
});

/* ============================================================
   PRODUCT PAGE — size selector
   ============================================================ */
const sizesEl = $('#sizes');
if (sizesEl) {
  $$('.size', sizesEl).forEach(btn => btn.addEventListener('click', () => {
    if (btn.dataset.sold === '1') return;
    $$('.size', sizesEl).forEach(b => b.classList.remove('is-active'));
    btn.classList.add('is-active');
    const price = btn.dataset.price, sku = btn.dataset.sku, variant = btn.dataset.variant;
    if ($('#pdpPrice')) $('#pdpPrice').textContent = price;
    if ($('#pdpSku')) $('#pdpSku').textContent = sku;
    const add = $('#pdpAdd');
    if (add) { add.dataset.add = variant; if ($('#pdpAddPrice')) $('#pdpAddPrice').textContent = price; }
  }));
}

/* ============================================================
   SEARCH OVERLAY
   ============================================================ */
const search = $('#search'), searchInput = $('#searchInput'), searchResults = $('#searchResults');
const PRODUCTS = window.MDB_SEARCH || [];
function openSearch() { if (!search) return; search.classList.add('is-open'); search.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; setTimeout(() => searchInput.focus(), 60); renderSearch(''); }
function closeSearch() { if (!search) return; search.classList.remove('is-open'); search.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; searchInput.value = ''; }
function renderSearch(q) {
  const term = q.trim().toLowerCase();
  const hits = !term ? PRODUCTS : PRODUCTS.filter(p =>
    (p.name + ' ' + p.brand + ' ' + p.line + ' ' + p.notes).toLowerCase().includes(term));
  if (!hits.length) { searchResults.innerHTML = `<p class="search__empty">Nothing in the house by that name.</p>`; return; }
  searchResults.innerHTML = hits.map(p =>
    `<a class="search__hit" href="/product?id=${p.id}"><span><b>${p.name}</b><i>${p.brand} · ${p.line}</i></span><span>${p.price}</span></a>`).join('');
}
if ($('#searchBtn')) $('#searchBtn').addEventListener('click', openSearch);
if ($('#searchClose')) $('#searchClose').addEventListener('click', closeSearch);
if (searchInput) searchInput.addEventListener('input', e => renderSearch(e.target.value));
document.addEventListener('keydown', e => { if (e.key === 'Escape') { closeDrawer(); closeSearch(); } });

/* ============================================================
   HEADER SHADOW + REVEALS
   ============================================================ */
const header = $('#header');
const onScroll = () => header && header.classList.toggle('is-stuck', window.scrollY > 8);
window.addEventListener('scroll', onScroll, { passive: true }); onScroll();

$$('[data-delay]').forEach(el => el.style.setProperty('--d', el.dataset.delay));
const io = new IntersectionObserver((entries) => {
  entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
}, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
$$('.reveal, .reveal-scale').forEach(el => io.observe(el));
window.addEventListener('load', () => $$('.hero .reveal').forEach(el => el.classList.add('is-in')));
setTimeout(() => $$('.reveal, .reveal-scale').forEach(el => el.classList.add('is-in')), 1600);

/* ============================================================
   NEWSLETTER
   ============================================================ */
const signupForm = $('#signupForm');
if (signupForm) signupForm.addEventListener('submit', e => {
  e.preventDefault(); signupForm.reset();
  $('#signupNote').textContent = 'Thank you. We will write soon.';
});

/* ============================================================
   CURRENCY SWITCH + GEO WELCOME
   ============================================================ */
document.addEventListener('click', e => {
  const btn = e.target.closest('[data-set-currency]');
  if (!btn) return;
  setCookie('CUR', btn.dataset.setCurrency, 365);
  setCookie('geo_seen', '1', 365);
  window.location.reload();
});

const geoModal = $('#geoModal');
function closeGeo() {
  if (!geoModal) return;
  geoModal.classList.remove('is-open');
  geoModal.setAttribute('aria-hidden', 'true');
  setCookie('geo_seen', '1', 365);
}
if (geoModal && !window.MDB_HAS_CURRENCY && !getCookie('geo_seen')) {
  setTimeout(async () => {
    let code = '', name = '';
    try {
      const r = await fetch('https://ipapi.co/json/');
      const j = await r.json();
      code = j.country_code || ''; name = j.country_name || '';
    } catch (err) {
      const tz = Intl.DateTimeFormat().resolvedOptions().timeZone || '';
      if (tz === 'Europe/Stockholm') { code = 'SE'; name = 'Sweden'; }
    }
    const isSE = code === 'SE';
    const text = $('#geoText'), primary = $('#geoPrimary'), secondary = $('#geoSecondary');
    if (isSE) {
      text.innerHTML = `You are visiting us from <b>${name || 'Sweden'}</b>. Prices are shown in <b>SEK</b>.`;
      primary.dataset.setCurrency = 'SEK'; primary.textContent = 'Sweden — kr SEK';
      secondary.dataset.setCurrency = 'EUR'; secondary.textContent = 'Continue to Europe — € EUR';
    } else {
      text.innerHTML = name
        ? `You are visiting us from <b>${name}</b>. Prices are shown in <b>EUR</b>.`
        : `Prices are shown in <b>EUR</b>. Would you like Swedish kronor?`;
      primary.dataset.setCurrency = 'EUR'; primary.textContent = 'Europe — € EUR';
      secondary.dataset.setCurrency = 'SEK'; secondary.textContent = 'Sweden — kr SEK';
    }
    geoModal.classList.add('is-open');
    geoModal.setAttribute('aria-hidden', 'false');
  }, 1200);
}
if ($('#geoOther')) $('#geoOther').addEventListener('click', closeGeo);
if ($('#geoScrim')) $('#geoScrim').addEventListener('click', closeGeo);

/* ---- Complimentary gift modal ---- */
const giftModal = $('#giftModal');
function openGift() { if (!giftModal) return; giftModal.classList.add('is-open'); giftModal.setAttribute('aria-hidden', 'false'); }
function closeGift() { if (!giftModal) return; giftModal.classList.remove('is-open'); giftModal.setAttribute('aria-hidden', 'true'); }
function claimGift() {
  setCookie('GIFT_CLAIMED', '1', 30);
  setCookie('GIFT_SEEN', '1', 1);
  closeGift();
  refreshCart().then(() => openDrawer());
}
function maybeShowGift(cart) {
  if (cart && cart.gift_qualified && !cart.gift_claimed && !getCookie('GIFT_SEEN')) {
    setCookie('GIFT_SEEN', '1', 1);   // show once (until claimed or a day passes)
    openGift();
  }
}
if ($('#giftClaim')) $('#giftClaim').addEventListener('click', claimGift);
if ($('#giftDismiss')) $('#giftDismiss').addEventListener('click', () => { setCookie('GIFT_SEEN', '1', 1); closeGift(); });
if ($('#giftScrim')) $('#giftScrim').addEventListener('click', () => { setCookie('GIFT_SEEN', '1', 1); closeGift(); });
/* Claim from the in-cart nudge link */
document.addEventListener('click', e => { if (e.target.closest('#nudgeClaim')) { e.preventDefault(); claimGift(); } });

/* ============================================================
   ICONS + INITIAL CART LOAD
   ============================================================ */
function drawIcons() { if (window.lucide) window.lucide.createIcons({ attrs: { 'stroke-width': 1.25, width: 18, height: 18 } }); }
drawIcons();
window.addEventListener('load', drawIcons);
refreshCart();
