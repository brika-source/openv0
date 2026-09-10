<?php
/**
 * Employee dashboard: entitlement KPIs, the state of any active case, and a
 * short recent-activity list.
 *
 * @var array<string,mixed>           $user
 * @var string                        $portal
 * @var array{used:int,entitlement:int,remaining:int} $balance
 * @var array<int,array<string,mixed>> $cases
 * @var int                           $ftw_count
 * @var array<string,mixed>|null      $active
 */
use App\Domain;
use App\I18n;
?>
<div class="kpi-row">
  <div class="kpi"><div class="num"><?= e((string) $balance['used']) ?></div><div class="lbl"><?= te('kpi_used') ?></div></div>
  <div class="kpi green"><div class="num"><?= e((string) $balance['remaining']) ?></div>
    <div class="lbl"><?= enl(I18n::sentence([['key' => 'kpi_remaining_of'], ' ' . $balance['entitlement']])) ?></div></div>
  <div class="kpi"><div class="num"><?= e((string) count($cases)) ?></div><div class="lbl"><?= te('kpi_total_cases') ?></div></div>
  <div class="kpi"><div class="num"><?= e((string) $ftw_count) ?></div><div class="lbl"><?= te('kpi_ftw_count') ?></div></div>
</div>

<?php if ($active !== null): ?>
  <?php $status = (string) $active['status']; ?>
  <?php if ($status === Domain::STATUS_RTW_PENDING && (int) ($active['rtw_required'] ?? 0) === 1 && ($active['rtw_outcome'] ?? null) === null): ?>
    <div class="banner block"><?= enl(I18n::sentence([
        ['key' => 'banner_rtw_block_b'],
        ' ' . $active['case_id'] . ' ',
        ['key' => 'banner_rtw_block_rest'],
    ])) ?></div>
  <?php elseif ($status === Domain::STATUS_PENDING): ?>
    <div class="banner warn"><?= enl(I18n::sentence([
        ['key' => 'banner_pending_b'],
        ' ',
        ['key' => 'banner_pending_rest'],
        ' ' . $active['case_id'],
        ['key' => 'banner_pending_end'],
    ])) ?></div>
  <?php else: ?>
    <div class="banner info">
      <?= topte('banner_active_case') ?> <b><?= e($active['case_id']) ?></b>
      — <?= badge(I18n::statusLabel($status), Domain::statusBadgeClass($status)) ?>
    </div>
  <?php endif; ?>
<?php else: ?>
  <div class="banner info"><?= te('banner_clear') ?></div>
<?php endif; ?>

<div class="card">
  <h3><?= te('recent_activity') ?></h3>
  <?php if ($cases === []): ?>
    <div class="empty"><?= te('no_records') ?></div>
  <?php else: ?>
    <?php foreach (array_slice($cases, 0, 4) as $case): ?>
      <div class="hist-item">
        <div class="ts">
          <?= e(fmt_date((string) $case['from_date'])) ?> – <?= e(fmt_date((string) $case['to_date'])) ?>
          · <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'case', 'id' => $case['case_id']])) ?>"><?= e($case['case_id']) ?></a>
        </div>
        <?= e($case['diagnosis']) ?>
        — <?= badge(I18n::statusLabel((string) $case['status']), Domain::statusBadgeClass((string) $case['status'])) ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
