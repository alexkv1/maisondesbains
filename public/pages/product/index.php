<?php
$root = dirname(__DIR__, 2);
require_once $root . '/db.php';
require_once $root . '/functions.php';
require_once $root . '/utils/render.php';
require_once $root . '/utils/Auth/Verify.php';

$id = trim($_GET['id'] ?? '');
$rows = $id ? $db->select("SELECT * FROM `products` WHERE `identifier` = ? AND `status` = 1 LIMIT 1", [$id], 's') : [];

if (!$rows) {
    http_response_code(404);
    $PAGE_TITLE = 'Not found — Maison Des Bains';
    require $root . '/utils/layout/header.php';
    echo '<main class="pagepad"><p class="secnum">Error 404</p><h1 class="section-title">This object is not in the house.</h1><p style="margin-top:1rem"><a class="btn btn--secondary" href="/#collection">Return to the selection</a></p></main>';
    require $root . '/utils/layout/footer.php';
    exit;
}
$p = $rows[0];
$soldOut = (int)$p['sold_out'] === 1;

// A few other objects, for "Also in the house".
$related = $db->select(
    "SELECT * FROM `products` WHERE `status` = 1 AND `identifier` <> ? ORDER BY RAND() LIMIT 4",
    [$p['identifier']], 's'
) ?: [];

$SEARCH_PRODUCTS = array_map(fn($x) => [
    'id' => $x['identifier'], 'name' => $x['name'], 'brand' => $x['brand'],
    'line' => $x['line'], 'notes' => $x['notes'], 'price' => money((int)$x['price_cents']),
], $db->select("SELECT * FROM `products` WHERE `status` = 1") ?: []);

$PAGE_TITLE = $p['brand'] . ' ' . $p['name'] . ' — Maison Des Bains';
$PAGE_DESC  = $p['blurb'];
require $root . '/utils/layout/header.php';
?>
<main class="product">
  <nav class="crumbs"><a href="/">Home</a> <span>/</span> <a href="/#collection"><?= e($p['category']) ?></a> <span>/</span> <?= e($p['name']) ?></nav>

  <div class="product__grid">
    <div class="product__plate">
      <?php if ($soldOut): ?><span class="card__flag">Sold Out</span>
      <?php elseif (!empty($p['badge'])): ?><span class="card__flag"><?= e($p['badge']) ?></span><?php endif; ?>
      <span class="product__initial" aria-hidden="true"><?= e(mb_substr($p['name'], 0, 1)) ?></span>
      <span class="product__ref mono"><?= e($p['sku']) ?></span>
    </div>

    <div class="product__info">
      <span class="card__brand"><?= e($p['brand']) ?></span>
      <h1 class="product__name"><?= e($p['name']) ?></h1>
      <p class="product__line mono"><?= e($p['line']) ?></p>
      <p class="product__price mono"><?= money((int)$p['price_cents']) ?></p>

      <p class="product__blurb"><?= e($p['blurb']) ?></p>

      <?php if (!empty($p['notes'])): ?>
      <div class="product__notes">
        <span class="eyebrow">Notes</span>
        <p class="mono"><?= e($p['notes']) ?></p>
      </div>
      <?php endif; ?>

      <div class="product__actions">
        <?php if ($soldOut): ?>
          <button class="btn btn--secondary" disabled>Sold Out</button>
          <p class="product__soldnote">Sign in to be told when it returns to the house.</p>
        <?php else: ?>
          <button class="btn btn--primary btn--lg" id="pdpAdd" data-add="<?= e($p['identifier']) ?>">Add to Basket — <?= money((int)$p['price_cents']) ?></button>
          <button class="card__wish product__wish" data-wish="<?= e($p['identifier']) ?>" aria-label="Add to wishlist"><?= MDB_HEART ?></button>
        <?php endif; ?>
      </div>

      <ul class="product__meta-list">
        <li><span>—</span> Complimentary delivery over €75</li>
        <li><span>—</span> Wrapped by hand in unmarked paper</li>
        <li><span>—</span> Selected by the Maison, never manufactured</li>
      </ul>
    </div>
  </div>

  <?php if ($related): ?>
  <section class="also">
    <div class="section-head"><div><span class="secnum">Also in the house</span><h2 class="section-title">You may also keep</h2></div></div>
    <div class="product-grid">
      <?php foreach ($related as $r) { echo renderCard($r); } ?>
    </div>
  </section>
  <?php endif; ?>
</main>
<?php require $root . '/utils/layout/footer.php'; ?>
