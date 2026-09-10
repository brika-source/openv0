<?php
/** @var string $message */
?>
<div class="card">
  <div class="err-page">
    <div class="code">⚠</div>
    <p class="err-lead"><?= e($message) ?></p>
    <a class="btn secondary" href="<?= e(url()) ?>"><?= topte('back_to_portals') ?></a>
  </div>
</div>
