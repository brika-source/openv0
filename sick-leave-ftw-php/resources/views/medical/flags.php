<?php
/**
 * Pattern / fraud flags (8.4). Informational only — they never block a
 * submission or an approval.
 *
 * @var string $portal
 * @var array<int,array{user:array<string,mixed>,flag:array{flag:bool,reason:string}}> $flagged
 */
use App\I18n;
?>
<div class="banner info"><?= te('banner_flags_info') ?></div>

<div class="card">
  <h3><?= te('title_flags') ?></h3>

  <?php if ($flagged === []): ?>
    <div class="empty"><?= te('empty_no_flags') ?></div>
  <?php else: ?>
    <?php foreach ($flagged as $item): ?>
      <div class="hist-item">
        <span class="flagchip"><?= topte('flagged_label') ?></span>
        &nbsp;<b><?= e($item['user']['name']) ?></b> (<?= e($item['user']['dept']) ?>) — <?= e($item['flag']['reason']) ?>
        &nbsp;<a class="link" href="<?= e(url(['p' => $portal, 'r' => 'patient', 'id' => $item['user']['id']])) ?>"><?= topte('btn_lookup') ?></a>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
