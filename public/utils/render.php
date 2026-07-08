<?php
require_once __DIR__ . '/../functions.php';

/** Wishlist heart, drawn to Lucide's 1.25 stroke language. */
const MDB_HEART = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.25" stroke-linecap="round" stroke-linejoin="round"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"/></svg>';

/** Render a product card (blank ivory plate + serif initial). */
function renderCard(array $p): string {
    $id      = e($p['identifier']);
    $brand   = e($p['brand']);
    $name    = e($p['name']);
    $cat     = e($p['category']);
    $price   = money(productPrice($p));
    $sku     = e($p['sku']);
    $initial = e(mb_substr($p['name'], 0, 1));
    $soldOut = (int)$p['sold_out'] === 1;
    $badge   = $p['badge'] ?? null;
    $heart   = MDB_HEART;

    $flag = '';
    if ($soldOut) {
        $flag = '<span class="card__flag">Sold Out</span>';
    } elseif ($badge) {
        $flag = '<span class="card__flag">' . e($badge) . '</span>';
    }
    $add = $soldOut ? '' : '<button class="card__add" data-add="' . $id . '">Add to Basket</button>';

    return <<<HTML
    <article class="card reveal" data-cat="{$cat}" data-id="{$id}">
      <div class="card__plate">
        {$flag}
        <button class="card__wish" data-wish="{$id}" aria-label="Add to wishlist">{$heart}</button>
        <span class="card__initial" aria-hidden="true">{$initial}</span>
        <a class="card__link" href="/product?id={$id}" aria-label="{$brand} {$name}"></a>
        {$add}
      </div>
      <div class="card__meta">
        <span class="card__brand">{$brand}</span>
        <a class="card__name" href="/product?id={$id}">{$name}</a>
        <div class="card__row">
          <span class="card__price">{$price}</span>
          <span class="card__sku">{$sku}</span>
        </div>
      </div>
    </article>
    HTML;
}
