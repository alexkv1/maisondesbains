<?php
$root = dirname(__DIR__, 2);
require_once $root . '/db.php';
require_once $root . '/functions.php';
require_once $root . '/utils/Auth/Verify.php';

$redirect = $_GET['redirect'] ?? '/account';
if (!preg_match('#^/[a-zA-Z0-9/_\-?=&]*$#', $redirect)) {
    $redirect = '/account';
}

// Already signed in — go straight through.
if ($AUTH->valid) {
    header('Location: ' . $redirect);
    exit;
}

$PAGE_TITLE = 'Sign in — Maison Des Bains';
require $root . '/utils/layout/header.php';
?>
<main class="pagepad auth">
  <div class="auth__card" data-redirect="<?= e($redirect) ?>">
    <div class="auth__tabs">
      <button class="auth__tab is-active" data-tab="login">Sign in</button>
      <button class="auth__tab" data-tab="register">Create account</button>
    </div>

    <form class="auth__form" id="loginForm">
      <div class="field"><label for="li-email">Email</label><input id="li-email" name="email" type="email" required autocomplete="email" /></div>
      <div class="field"><label for="li-pass">Password</label><input id="li-pass" name="password" type="password" required autocomplete="current-password" /></div>
      <button class="btn btn--primary btn--full" type="submit">Sign in</button>
      <p class="auth__err" id="loginErr" aria-live="polite"></p>
    </form>

    <form class="auth__form" id="registerForm" hidden>
      <div class="field-row">
        <div class="field"><label for="re-first">First name</label><input id="re-first" name="first_name" autocomplete="given-name" /></div>
        <div class="field"><label for="re-last">Last name</label><input id="re-last" name="last_name" autocomplete="family-name" /></div>
      </div>
      <div class="field"><label for="re-email">Email</label><input id="re-email" name="email" type="email" required autocomplete="email" /></div>
      <div class="field"><label for="re-pass">Password <span>(min. 8 characters)</span></label><input id="re-pass" name="password" type="password" required autocomplete="new-password" /></div>
      <button class="btn btn--primary btn--full" type="submit">Create account</button>
      <p class="auth__err" id="registerErr" aria-live="polite"></p>
    </form>
  </div>
</main>
<?php require $root . '/utils/layout/footer.php'; ?>
