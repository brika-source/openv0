<?php
/**
 * A manager's view of a restricted return to work.
 *
 * Deliberately limited to what a line manager needs in order to plan work:
 * the restriction itself, its review date, the status and the dates. The
 * diagnosis, the medical history, the case timeline and the attachments are
 * not passed to this template at all.
 *
 * @var array<string,mixed> $case
 * @var array<string,mixed> $employee
 * @var string              $portal
 */
use App\Domain;
use App\Helpers;
use App\I18n;

$status = (string) $case['status'];
?>
<div class="breadcrumb">
  <a href="<?= e(url(['p' => $portal, 'r' => 'tab', 't' => 'teamdash'])) ?>">← <?= topte('tab_teamdash') ?></a>
</div>

<div class="banner info"><?= te('banner_confidential') ?></div>

<div class="card">
  <div class="detail-head">
    <div>
      <h2><?= e($employee['name']) ?></h2>
      <div class="meta"><?= e($employee['dept'] . ' (' . $employee['id'] . ')') ?></div>
    </div>
    <?= badge(I18n::statusLabel($status), Domain::statusBadgeClass($status)) ?>
  </div>

  <dl class="kv">
    <dt><?= topte('lbl_case_ref') ?></dt><dd><?= e($case['case_id']) ?></dd>
    <dt><?= topte('lbl_from') ?></dt><dd><?= e(fmt_date((string) $case['from_date'])) ?></dd>
    <dt><?= topte('lbl_to') ?></dt><dd><?= e(fmt_date((string) $case['to_date'])) ?></dd>
  </dl>

  <?php if (($case['restriction_details'] ?? null) !== null): ?>
    <div class="banner warn">
      <b><?= topte('lbl_restrictions') ?></b> <?= e($case['restriction_details']) ?><br>
      <span class="muted">
        <?= topte('lbl_review_date') ?> <?= e(fmt_date((string) $case['restriction_review_date'])) ?>
        <?php if (Helpers::isPast((string) $case['restriction_review_date'])): ?>
          <?= badge(I18n::topt('badge_overdue'), 'b-extended') ?>
        <?php endif; ?>
      </span>
    </div>
  <?php else: ?>
    <div class="muted-block"><?= topte('none_word') ?></div>
  <?php endif; ?>
</div>
