<?php
require_once __DIR__ . '/../functions.php';

/** Wishlist heart, drawn to Lucide's 1.25 stroke language. */
const MDB_HEART = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"/></svg>';

/**
 * Choose the default (displayed) variant for a product: the first available
 * size by position, falling back to the first variant.
 */
function defaultVariant(array $variants): ?array {
    $available = array_filter($variants, fn($v) => (int)$v['sold_out'] === 0 && (int)$v['status'] === 1);
    if ($available) {
        return array_values($available)[0];
    }
    return $variants[0] ?? null;
}

/** Render a product card. $variants are the product's sizes (ordered). */
function renderCard(array $p, array $variants = []): string {
    $id      = e($p['identifier']);
    $brand   = e($p['brand']);
    $name    = e($p['name']);
    $cat     = e($p['category']);
    $initial = e(mb_substr($p['name'], 0, 1));
    $badge   = $p['badge'] ?? null;
    $image   = $p['image'] ?? null;
    $heart   = MDB_HEART;

    $visual = $image
        ? '<img class="card__photo" src="' . e($image) . '" alt="' . $brand . ' ' . $name . '" loading="lazy" />'
        : '<span class="card__initial" aria-hidden="true">' . $initial . '</span>';

    $def       = defaultVariant($variants);
    $sellable  = array_filter($variants, fn($v) => (int)$v['sold_out'] === 0 && (int)$v['status'] === 1);
    $soldOut   = $def === null || count($sellable) === 0;
    $multiSize = count($sellable) > 1;

    $priceLabel = '';
    if ($def) {
        $priceLabel = ($multiSize ? 'from ' : '') . money(productPrice($def));
    }
    $sku = $def ? e($def['sku']) : '';

    $flag = '';
    if ($soldOut) {
        $flag = '<span class="card__flag">Coming Soon</span>';
    } elseif ($badge) {
        $flag = '<span class="card__flag">' . e($badge) . '</span>';
    }

    $add = '';
    if (!$soldOut && $def) {
        $addVariant = e($def['identifier']);
        $add = '<button class="card__add" data-add="' . $addVariant . '">Add to Basket</button>';
    }

    return <<<HTML
    <article class="card reveal" data-cat="{$cat}" data-id="{$id}">
      <div class="card__plate">
        {$flag}
        <button class="card__wish" data-wish="{$id}" aria-label="Add to wishlist">{$heart}</button>
        {$visual}
        <a class="card__link" href="/product?id={$id}" aria-label="{$brand} {$name}"></a>
        {$add}
      </div>
      <div class="card__meta">
        <span class="card__brand">{$brand}</span>
        <a class="card__name" href="/product?id={$id}">{$name}</a>
        <div class="card__row">
          <span class="card__price">{$priceLabel}</span>
          <span class="card__sku">{$sku}</span>
        </div>
      </div>
    </article>
    HTML;
}

/** Fetch all variants keyed by product id (ordered). */
function variantsByProduct(DB $db): array {
    $rows = $db->select(
        "SELECT * FROM `product_variants` WHERE `status` = 1 ORDER BY `product` ASC, `position` ASC, `id` ASC"
    ) ?: [];
    $map = [];
    foreach ($rows as $r) {
        $map[(int)$r['product']][] = $r;
    }
    return $map;
}
