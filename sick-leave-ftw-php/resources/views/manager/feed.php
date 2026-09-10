<?php
/**
 * Decisions feed — the notifications this manager was copied on.
 *
 * @var array<int,array<string,mixed>> $notifications
 */
?>
<div class="card">
  <h3><?= te('title_decisions_feed') ?></h3>

  <?php if ($notifications === []): ?>
    <div class="empty"><?= te('empty_no_items') ?></div>
  <?php else: ?>
    <?php foreach ($notifications as $notification): ?>
      <div class="hist-item">
        <div class="ts"><?= e(fmt_dt((string) $notification['ts'])) ?> · <?= e($notification['event']) ?></div>
        <?= e($notification['message']) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
