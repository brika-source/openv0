<?php
/**
 * Unhandled-exception page. The detail is written to storage/logs/php-error.log;
 * it is only shown here when APP_DEBUG is explicitly enabled.
 *
 * @var bool      $debug
 * @var Throwable $exception
 */
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Error</title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>
<main class="page-narrow">
  <div class="card">
    <div class="err-page">
      <div class="code">500</div>
      <p class="err-lead"><?= te('err_generic') ?></p>
      <a class="btn secondary" href="<?= e(url()) ?>"><?= topte('back_to_portals') ?></a>
    </div>

    <?php if ($debug): ?>
      <hr class="divider">
      <h3><?= e($exception::class) ?></h3>
      <p><b><?= e($exception->getMessage()) ?></b></p>
      <p class="muted"><?= e($exception->getFile()) ?>:<?= e((string) $exception->getLine()) ?></p>
      <pre><?= e($exception->getTraceAsString()) ?></pre>
    <?php else: ?>
      <p class="text-center muted">Details were written to <code>storage/logs/php-error.log</code>.</p>
    <?php endif; ?>
  </div>
</main>
</body>
</html>
