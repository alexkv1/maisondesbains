<?php
/**
 * Sample configuration. The real conf.php is written on the VM by the
 * GitHub Actions deploy (from repo secrets) and is NOT committed.
 * For local development, copy this to conf.php and fill in.
 *
 * Leave the Stripe keys empty to use the built-in mock checkout
 * (orders are marked paid immediately) — handy before wiring Stripe.
 */
return [
    'servername' => '127.0.0.1',
    'username'   => 'maison',
    'password'   => 'change-me',
    'dbname'     => 'maison_des_bains',

    'stripe-key'            => '',   // sk_test_...
    'stripe-webhook-secret' => '',   // whsec_...

    'sek-rate'              => 11.3, // EUR -> SEK display rate
];
