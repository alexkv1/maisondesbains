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
    <span class="hero__ref reveal">Le Labo · Byredo · &amp; more</span>
    <div class="hero__inner">
      <p class="eyebrow reveal">Luxury bath &amp; body — honestly priced</p>
      <h1 class="hero__title reveal" data-delay="1">Luxury amenities,<br />made <em>affordable</em>.</h1>
      <p class="hero__lede reveal" data-delay="2">The cult soaps, shower gels and lotions of Le&nbsp;Labo, Byredo and more — the names you covet, at prices you don't expect. A complimentary gift on every order over <?= giftThresholdLabel() ?>.</p>
      <div class="hero__actions reveal" data-delay="3">
        <a href="#collection" class="btn btn--primary">Shop now</a>
        <a href="#collection" class="btn btn--ghost">View the collection →</a>
      </div>
    </div>
  </section>

  <section class="catstrip" aria-label="Categories">
    <a href="#collection" class="catstrip__item" data-cat="Soap"><span class="num">01</span><span class="cat">Soap</span></a>
    <a href="#collection" class="catstrip__item" data-cat="Wash"><span class="num">02</span><span class="cat">Wash</span></a>
    <a href="#collection" class="catstrip__item" data-cat="Body"><span class="num">03</span><span class="cat">Body</span></a>
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
    <span class="band__ref reveal">Why Maison Des Bains</span>
    <blockquote class="band__quote reveal" data-delay="1">
      The same coveted soaps, gels and lotions the great houses are known for — at prices that finally make sense.
    </blockquote>
    <a href="#collection" class="btn btn--onink reveal" data-delay="2">Shop the collection</a>
  </section>

  <section class="edit" id="edit">
    <div class="edit__plate reveal-scale">
      <span class="edit__mark">N° 12 / 100</span>
    </div>
    <div class="edit__copy">
      <span class="secnum reveal">N° 04 — Honest Luxury</span>
      <h2 class="section-title reveal">Luxury, without the markup.</h2>
      <p class="reveal">The same houses you'll find on the grandest shelves — Le&nbsp;Labo, Byredo and more — for a fraction of what you'd expect to pay.</p>
      <ul class="edit__list">
        <li class="reveal"><span>—</span> 100% genuine, every time</li>
        <li class="reveal"><span>—</span> Amenity sizes, everyday prices</li>
        <li class="reveal"><span>—</span> Gift wrapping whenever you'd like it</li>
      </ul>
      <a href="#collection" class="btn btn--secondary reveal">Shop now</a>
    </div>
  </section>

</main>

<?php require __DIR__ . '/utils/layout/footer.php'; ?>
