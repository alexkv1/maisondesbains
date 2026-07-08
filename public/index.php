<?php
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/utils/render.php';
require_once __DIR__ . '/utils/Auth/Verify.php';   // provides $db and $AUTH

$products = $db->select("SELECT * FROM `products` WHERE `status` = 1 ORDER BY `id` ASC") ?: [];
$VARIANTS = variantsByProduct($db);

// Catalogue for the search overlay.
$SEARCH_PRODUCTS = array_map(function ($p) use ($VARIANTS) {
    $def = defaultVariant($VARIANTS[(int)$p['id']] ?? []);
    return [
        'id' => $p['identifier'], 'name' => $p['name'], 'brand' => $p['brand'],
        'line' => $p['line'], 'notes' => $p['notes'],
        'price' => $def ? money(productPrice($def)) : '',
    ];
}, $products);

$PAGE_TITLE = 'Maison Des Bains — The Bath, Curated';
require __DIR__ . '/utils/layout/header.php';
?>

<main id="top">

  <section class="hero">
    <span class="hero__ref reveal">N° 01 — Spring Edition</span>
    <div class="hero__inner">
      <p class="eyebrow reveal">The Bath, Curated</p>
      <h1 class="hero__title reveal" data-delay="1">We don't sell soap.<br />We keep the <em>ritual</em> of water.</h1>
      <p class="hero__lede reveal" data-delay="2">A house that edits, rather than shouts — the washes, lotions and soaps of Le&nbsp;Labo and Byredo, chosen for how they hold a room.</p>
      <div class="hero__actions reveal" data-delay="3">
        <a href="#collection" class="btn btn--primary">Shop the Maison</a>
        <a href="#edit" class="btn btn--ghost">The Grey Bath →</a>
      </div>
    </div>
  </section>

  <section class="catstrip" aria-label="Categories">
    <a href="#collection" class="catstrip__item" data-cat="Soap"><span class="num">01</span><span class="cat">Soap</span></a>
    <a href="#collection" class="catstrip__item" data-cat="Wash"><span class="num">02</span><span class="cat">Wash</span></a>
    <a href="#collection" class="catstrip__item" data-cat="Body"><span class="num">03</span><span class="cat">Body</span></a>
    <a href="#collection" class="catstrip__item" data-cat="Set"><span class="num">04</span><span class="cat">Sets</span></a>
  </section>

  <section class="collection" id="collection">
    <div class="section-head">
      <div>
        <span class="secnum reveal">N° 02</span>
        <h2 class="section-title reveal">The Selection</h2>
      </div>
      <div class="filters" id="filters"></div>
    </div>
    <div class="product-grid" id="productGrid">
      <?php foreach ($products as $p) { echo renderCard($p, $VARIANTS[(int)$p['id']] ?? []); } ?>
    </div>
  </section>

  <section class="band" id="journal">
    <span class="band__ref reveal">N° 03 — The Journal</span>
    <blockquote class="band__quote reveal" data-delay="1">
      “The bath is the last room that asks nothing of you. We furnish it with the finest houses in the world, and then we leave you alone.”
    </blockquote>
    <a href="#collection" class="btn btn--onink reveal" data-delay="2">Read the Journal</a>
  </section>

  <section class="edit" id="edit">
    <div class="edit__plate reveal-scale">
      <span class="edit__mark">N° 12 / 100</span>
    </div>
    <div class="edit__copy">
      <span class="secnum reveal">N° 04 — The Edit</span>
      <h2 class="section-title reveal">The Grey Bath</h2>
      <p class="reveal">An edit in ash, smoke and stone — the scents we reach for when the light goes low. Cool, quiet, and entirely without colour, in the manner of the house.</p>
      <ul class="edit__list">
        <li class="reveal"><span>—</span> Triple-milled &amp; cold-processed</li>
        <li class="reveal"><span>—</span> Selected, never manufactured</li>
        <li class="reveal"><span>—</span> Wrapped by hand in unmarked paper</li>
      </ul>
      <a href="#collection" class="btn btn--secondary reveal">View the edit</a>
    </div>
  </section>

</main>

<?php require __DIR__ . '/utils/layout/footer.php'; ?>
