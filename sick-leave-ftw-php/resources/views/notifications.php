<?php
/**
 * Notification inbox.
 *
 * @var string $portal
 * @var array<int,array<string,mixed>> $notifications
 */
$unreadCount = 0;
foreach ($notifications as $notification) {
    if ((int) ($notification['is_read'] ?? 0) === 0) {
        $unreadCount++;
    }
}
?>
<div class="card">
  <h3>
    <span><?= te('notif_title') ?></span>
    <?php if ($unreadCount > 0): ?>
      <form method="post" action="<?= e(url()) ?>" class="inline">
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="notifications.read">
        <input type="hidden" name="_return" value="<?= e(url(['p' => $portal, 'r' => 'notifications'])) ?>">
        <button type="submit" class="btn ghost sm">
          <?= topte('btn_mark_all_read') ?> (<?= e((string) $unreadCount) ?>)
        </button>
      </form>
    <?php endif; ?>
  </h3>

  <?php if ($notifications === []): ?>
    <div class="empty"><?= te('notif_empty') ?></div>
  <?php else: ?>
    <?php foreach ($notifications as $notification): ?>
      <?php
        $isUnread = (int) ($notification['is_read'] ?? 0) === 0;
        $refId    = (string) ($notification['ref_id'] ?? '');
        $isFtw    = str_starts_with($refId, 'FTW-');
        $target   = $refId === ''
            ? null
            : url(['p' => $portal, 'r' => $isFtw ? 'ftw' : 'case', 'id' => $refId]);
      ?>
      <div class="notif-item<?= $isUnread ? ' unread' : '' ?>">
        <div class="ts">
          <?= e(fmt_dt((string) $notification['ts'])) ?> · <?= e($notification['event']) ?>
          <?php if ($target !== null): ?>
            · <a class="link" href="<?= e($target) ?>"><?= e($refId) ?></a>
          <?php endif; ?>
          <?php if ($isUnread): ?>
            · <b><?= topte('lbl_unread') ?></b>
          <?php endif; ?>
        </div>
        <?= e($notification['message']) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
