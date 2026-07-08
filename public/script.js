/* ============================================================
   MAISON DES BAINS — storefront behaviour
   Monochrome, quiet, unhurried. No emoji. £ prices in mono.
   ============================================================ */

/* ---- Catalogue (from the house sample data) ---- */
const PRODUCTS = [
  { id:'p1', brand:'Le Labo',  name:'Santal 33',     line:'Bar Soap',      price:38, sku:'MDB·04—217', cat:'Soap', notes:'Sandalwood · Cardamom · Leather', blurb:'A cult sandalwood, pressed into a triple-milled bar. Warm, smoky, quietly addictive.' },
  { id:'p2', brand:'Byredo',   name:'Rose Noir',     line:'Hand Wash',     price:45, sku:'MDB·04—331', cat:'Body', notes:'Black Rose · Freesia · Musk', blurb:'A darkened rose for the basin — sharp at first, then soft as dusk.' },
  { id:'p3', brand:'Diptyque', name:'Bain Moussant', line:'Bubble Bath',   price:52, sku:'MDB·05—118', cat:'Bath', notes:'Fig Leaf · Cedar · Green Sap', blurb:'A foaming fig bath drawn from a Mediterranean garden after rain.', soldOut:true },
  { id:'p4', brand:'Dior',     name:'Blanc de Peau', line:'Cleansing Bar', price:40, sku:'MDB·04—402', cat:'Soap', notes:'White Iris · Rice · Cotton', blurb:'A powder-soft white bar. Skin left matte, clean, unscented at the finish.', badge:'New' },
  { id:'p5', brand:'Byredo',   name:'Mojave Ghost',  line:'Body Lotion',   price:58, sku:'MDB·06—077', cat:'Body', notes:'Sandalwood · Violet · Amber', blurb:'A desert flower that blooms against all odds — powdery, resinous, resolute.' },
  { id:'p6', brand:'Le Labo',  name:'Thé Noir 29',   line:'Bath Salts',    price:62, sku:'MDB·05—244', cat:'Bath', notes:'Black Tea · Fig · Bay Leaves', blurb:'Coarse grey salts steeped in black tea. For the long, slow soak.' },
  { id:'p7', brand:'Diptyque', name:'Baies Candle',  line:'Home',          price:68, sku:'MDB·07—012', cat:'Home', notes:'Blackcurrant · Bulgarian Rose', blurb:'The house classic. Berries and rose, for the room the bath opens onto.', badge:'Limited' },
  { id:'p8', brand:'Dior',     name:'Gris Poudré',   line:'Body Oil',      price:72, sku:'MDB·06—190', cat:'Body', notes:'Grey Iris · Musk · Vanilla', blurb:'A weightless grey oil that disappears into damp skin.' },
];

const CATS = ['All', 'Soap', 'Bath', 'Body', 'Home'];
const gbp = n => '£' + n.toFixed(2);
const byId = id => PRODUCTS.find(p => p.id === id);
const initialOf = p => p.name.trim()[0];

const wishlist = new Set();

/* ---- Wishlist heart (Lucide-language, 1.25 stroke) ---- */
const HEART = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"/></svg>`;

/* ============================================================
   RENDER PRODUCTS + FILTERS
   ============================================================ */
const grid = document.getElementById('productGrid');
const filtersEl = document.getElementById('filters');

function cardHTML(p) {
  return `
  <article class="card reveal" data-cat="${p.cat}" data-id="${p.id}">
    <div class="card__plate">
      ${p.badge ? `<span class="card__flag">${p.badge}</span>` : ''}
      ${p.soldOut ? `<span class="card__flag">Sold Out</span>` : ''}
      <button class="card__wish" data-wish="${p.id}" aria-label="Add to wishlist">${HEART}</button>
      <span class="card__initial" aria-hidden="true">${initialOf(p)}</span>
      ${p.soldOut ? '' : `<button class="card__add" data-add="${p.id}">Add to Basket</button>`}
    </div>
    <div class="card__meta">
      <span class="card__brand">${p.brand}</span>
      <span class="card__name">${p.name}</span>
      <div class="card__row">
        <span class="card__price">${gbp(p.price)}</span>
        <span class="card__sku">${p.sku}</span>
      </div>
    </div>
  </article>`;
}

grid.innerHTML = PRODUCTS.map(cardHTML).join('');

filtersEl.innerHTML = CATS.map((c, i) =>
  `<button class="tag${i === 0 ? ' is-active' : ''}" data-filter="${c}">${c}</button>`
).join('');

function applyFilter(cat) {
  filtersEl.querySelectorAll('.tag').forEach(t =>
    t.classList.toggle('is-active', t.dataset.filter === cat));
  document.querySelectorAll('.card').forEach(card =>
    card.classList.toggle('is-hidden', !(cat === 'All' || card.dataset.cat === cat)));
}

filtersEl.addEventListener('click', e => {
  const btn = e.target.closest('[data-filter]');
  if (btn) applyFilter(btn.dataset.filter);
});

/* Category links (nav + strip + footer) scroll to collection and filter */
document.querySelectorAll('[data-cat]').forEach(el => {
  el.addEventListener('click', () => {
    const cat = el.dataset.cat;
    if (CATS.includes(cat)) applyFilter(cat);
  });
});

/* ============================================================
   BASKET
   ============================================================ */
const basket = new Map();       // id -> qty
let giftWrap = false;

const cartCountEl = document.getElementById('cartCount');
const cartBtn = document.getElementById('cartBtn');
const drawer = document.getElementById('drawer');
const scrim = document.getElementById('scrim');
const drawerBody = document.getElementById('drawerBody');
const drawerCount = document.getElementById('drawerCount');
const drawerTotal = document.getElementById('drawerTotal');

function addToBasket(id) {
  basket.set(id, (basket.get(id) || 0) + 1);
  renderBasket();
  cartBtn.classList.add('bump');
  setTimeout(() => cartBtn.classList.remove('bump'), 320);
  openDrawer();
}
function setQty(id, q) {
  if (q <= 0) basket.delete(id); else basket.set(id, q);
  renderBasket();
}

function renderBasket() {
  const count = [...basket.values()].reduce((a, b) => a + b, 0);
  let total = [...basket.entries()].reduce((a, [id, q]) => a + byId(id).price * q, 0);
  if (giftWrap && basket.size) total += 4;

  cartCountEl.textContent = count;
  cartCountEl.hidden = count === 0;
  drawerCount.textContent = `(${count})`;
  drawerTotal.textContent = gbp(total);

  if (basket.size === 0) {
    drawerBody.innerHTML = `<p class="drawer__empty">The basket is empty.</p>`;
    return;
  }
  drawerBody.innerHTML = [...basket.entries()].map(([id, q]) => {
    const p = byId(id);
    return `
    <div class="line">
      <div class="line__plate"><span aria-hidden="true">${initialOf(p)}</span></div>
      <div class="line__body">
        <span class="line__brand">${p.brand}</span>
        <span class="line__name">${p.name}</span>
        <div class="line__foot">
          <div class="qty">
            <button data-dec="${id}" aria-label="Decrease">&minus;</button>
            <span>${q}</span>
            <button data-inc="${id}" aria-label="Increase">+</button>
          </div>
          <span class="line__price">${gbp(p.price * q)}</span>
        </div>
        <button class="line__remove" data-remove="${id}">Remove</button>
      </div>
    </div>`;
  }).join('');
}

/* Delegated actions */
document.addEventListener('click', e => {
  const add = e.target.closest('[data-add]');    if (add) return addToBasket(add.dataset.add);
  const inc = e.target.closest('[data-inc]');    if (inc) return setQty(inc.dataset.inc, (basket.get(inc.dataset.inc) || 0) + 1);
  const dec = e.target.closest('[data-dec]');    if (dec) return setQty(dec.dataset.dec, (basket.get(dec.dataset.dec) || 0) - 1);
  const rem = e.target.closest('[data-remove]'); if (rem) return setQty(rem.dataset.remove, 0);
  const wish = e.target.closest('[data-wish]');
  if (wish) {
    const id = wish.dataset.wish;
    wishlist.has(id) ? wishlist.delete(id) : wishlist.add(id);
    wish.classList.toggle('is-on', wishlist.has(id));
    wish.setAttribute('aria-label', wishlist.has(id) ? 'Remove from wishlist' : 'Add to wishlist');
  }
});

/* Gift wrap switch */
const giftSwitch = document.getElementById('giftSwitch');
giftSwitch.addEventListener('click', () => {
  giftWrap = !giftWrap;
  giftSwitch.setAttribute('aria-checked', String(giftWrap));
  renderBasket();
});

/* Drawer open / close */
function openDrawer() {
  drawer.classList.add('is-open'); scrim.classList.add('is-open');
  drawer.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden';
}
function closeDrawer() {
  drawer.classList.remove('is-open'); scrim.classList.remove('is-open');
  drawer.setAttribute('aria-hidden', 'true'); document.body.style.overflow = '';
}
cartBtn.addEventListener('click', openDrawer);
document.getElementById('drawerClose').addEventListener('click', closeDrawer);
scrim.addEventListener('click', closeDrawer);
document.getElementById('checkoutBtn').addEventListener('click', () => {
  if (basket.size === 0) return;
  const b = document.getElementById('checkoutBtn');
  b.textContent = 'One moment…';
  setTimeout(() => { b.textContent = 'Proceed to checkout'; }, 1500);
});

renderBasket();

/* ============================================================
   SEARCH OVERLAY
   ============================================================ */
const search = document.getElementById('search');
const searchInput = document.getElementById('searchInput');
const searchResults = document.getElementById('searchResults');

function openSearch() { search.classList.add('is-open'); search.setAttribute('aria-hidden', 'false'); document.body.style.overflow = 'hidden'; setTimeout(() => searchInput.focus(), 60); renderSearch(''); }
function closeSearch() { search.classList.remove('is-open'); search.setAttribute('aria-hidden', 'true'); document.body.style.overflow = ''; searchInput.value = ''; }

function renderSearch(q) {
  const term = q.trim().toLowerCase();
  const hits = !term ? PRODUCTS : PRODUCTS.filter(p =>
    (p.name + ' ' + p.brand + ' ' + p.line + ' ' + p.notes).toLowerCase().includes(term));
  if (!hits.length) { searchResults.innerHTML = `<p class="search__empty">Nothing in the house by that name.</p>`; return; }
  searchResults.innerHTML = hits.map(p =>
    `<div class="search__hit" data-open="${p.id}"><span><b>${p.name}</b><i>${p.brand} · ${p.line}</i></span><span>${gbp(p.price)}</span></div>`
  ).join('');
}
document.getElementById('searchBtn').addEventListener('click', openSearch);
document.getElementById('searchClose').addEventListener('click', closeSearch);
searchInput.addEventListener('input', e => renderSearch(e.target.value));
searchResults.addEventListener('click', e => {
  const hit = e.target.closest('[data-open]');
  if (!hit) return;
  closeSearch();
  applyFilter('All');
  const card = document.querySelector(`.card[data-id="${hit.dataset.open}"]`);
  if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
});

document.addEventListener('keydown', e => {
  if (e.key === 'Escape') { closeDrawer(); closeSearch(); }
});

/* ============================================================
   HEADER SHADOW ON SCROLL
   ============================================================ */
const header = document.getElementById('header');
const onScroll = () => header.classList.toggle('is-stuck', window.scrollY > 8);
window.addEventListener('scroll', onScroll, { passive: true });
onScroll();

/* ============================================================
   REVEAL ON SCROLL (slow drape)
   ============================================================ */
document.querySelectorAll('[data-delay]').forEach(el => el.style.setProperty('--d', el.dataset.delay));
const io = new IntersectionObserver((entries) => {
  entries.forEach(en => { if (en.isIntersecting) { en.target.classList.add('is-in'); io.unobserve(en.target); } });
}, { threshold: 0.12, rootMargin: '0px 0px -6% 0px' });
document.querySelectorAll('.reveal, .reveal-scale').forEach(el => io.observe(el));
window.addEventListener('load', () => {
  document.querySelectorAll('.hero .reveal').forEach(el => el.classList.add('is-in'));
});
/* Safety: never leave content hidden if the observer never fires */
setTimeout(() => document.querySelectorAll('.reveal, .reveal-scale').forEach(el => el.classList.add('is-in')), 1600);

/* ============================================================
   NEWSLETTER
   ============================================================ */
const signupForm = document.getElementById('signupForm');
const signupNote = document.getElementById('signupNote');
signupForm.addEventListener('submit', e => {
  e.preventDefault();
  signupForm.reset();
  signupNote.textContent = 'Thank you. We will write soon.';
});

/* Footer year */
document.getElementById('year').textContent = new Date().getFullYear();

/* ============================================================
   LUCIDE ICONS (strokeWidth 1.25)
   ============================================================ */
function drawIcons() {
  if (window.lucide) window.lucide.createIcons({ attrs: { 'stroke-width': 1.25, width: 18, height: 18 } });
}
drawIcons();
window.addEventListener('load', drawIcons);
