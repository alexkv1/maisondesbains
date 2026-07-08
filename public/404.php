<?php
http_response_code(404);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/functions.php';
require_once __DIR__ . '/utils/Auth/Verify.php';
$PAGE_TITLE = 'Not found — Maison Des Bains';
require __DIR__ . '/utils/layout/header.php';
?>
<main class="pagepad" style="min-height:52vh">
  <span class="secnum">Error 404</span>
  <h1 class="section-title">This page is not in the house.</h1>
  <p style="margin-top:1.2rem"><a class="btn btn--secondary" href="/">Return home</a></p>
</main>
<?php require __DIR__ . '/utils/layout/footer.php'; ?>
