<?php
/**
 * A record's timeline. Used by every case and FTW detail screen.
 *
 * @var array<int,array<string,mixed>> $entries
 */
?>
<?php if ($entries === []): ?>
  <div class="muted"><?= topte('none_word') ?></div>
<?php else: ?>
  <?php foreach ($entries as $entry): ?>
    <div class="hist-item">
      <div class="ts"><?= e(fmt_dt((string) $entry['ts'])) ?> — <?= e($entry['actor']) ?></div>
      <b><?= e($entry['action']) ?></b><?php
        $comment = (string) ($entry['comment'] ?? '');
        if ($comment !== '') {
            echo ': ' . e($comment);
        }
      ?>
    </div>
  <?php endforeach; ?>
<?php endif; ?>
