<?php
/**
 * Shared page head + header chrome. A page includes this after setting
 * optional $PAGE_TITLE and $PAGE_DESC. Requires $AUTH to be available
 * (include utils/Auth/Verify.php first) to reflect sign-in state.
 */
$PAGE_TITLE = $PAGE_TITLE ?? 'Maison Des Bains — Luxury Amenities, Made Affordable';
$PAGE_DESC  = $PAGE_DESC ?? 'Cult soaps, shower gels and body lotions from Le Labo, Byredo and more — the names you covet, at prices you don\'t expect.';
$signedIn   = isset($AUTH) && $AUTH->valid;

// Never let the browser serve a stale page (HTML is always freshly rendered).
header('Cache-Control: no-cache, no-store, must-revalidate');
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<title><?= htmlspecialchars($PAGE_TITLE) ?></title>
<meta name="description" content="<?= htmlspecialchars($PAGE_DESC) ?>" />
<link rel="stylesheet" href="/assets/ds/ds.css?v=<?= @filemtime(__DIR__ . '/../../assets/ds/ds.css') ?>" />
<link rel="stylesheet" href="/assets/styles.css?v=<?= @filemtime(__DIR__ . '/../../assets/styles.css') ?>" />
</head>
<body>

<div class="announce announce--center">
  <span>Fast shipping · Complimentary gift over <?= giftThresholdLabel() ?></span>
</div>

<header class="header" id="header">
  <nav class="nav">
    <ul class="nav__links">
      <li><a href="/#collection" data-cat="Body">Body</a></li>
      <li><a href="/#collection" data-cat="Bath">Bath</a></li>
      <li><a href="/#collection" data-cat="Hands">Hands</a></li>
      <li><a href="/#collection" data-cat="Home">Home</a></li>
    </ul>
    <a href="/" class="wordmark" aria-label="Maison Des Bains — home">Maison Des Bains</a>
    <div class="nav__actions">
      <button class="iconbtn" id="searchBtn" aria-label="Search"><i data-lucide="search"></i></button>
      <a class="iconbtn" href="/account" aria-label="Account"><i data-lucide="user"></i></a>
      <button class="iconbtn iconbtn--cart" id="cartBtn" aria-label="Basket">
        <i data-lucide="shopping-bag"></i>
        <span class="cart-count" id="cartCount" hidden>0</span>
      </button>
    </div>
  </nav>
</header>
