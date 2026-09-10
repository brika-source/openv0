<?php
/**
 * A manager's view of an employee's fit-to-work restriction.
 *
 * Restriction, review date and outcome only — no diagnosis, no medical history,
 * no attachments.
 *
 * @var array<string,mixed> $record
 * @var array<string,mixed> $employee
 * @var string              $portal
 */
use App\Domain;
use App\Helpers;
use App\I18n;
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
    <?= badge(I18n::ftwStatusLabel((string) $record['status']), Domain::ftwBadgeClass((string) $record['status'])) ?>
  </div>

  <dl class="kv">
    <dt><?= topte('lbl_ftw_ref') ?></dt><dd><?= e($record['id']) ?></dd>
    <dt><?= topte('th_requester') ?></dt><dd><?= e($record['requester_label']) ?></dd>
    <dt><?= topte('lbl_jobdemands_colon') ?></dt>
    <dd>
      <?php
        $labels = array_map([I18n::class, 'jobDemandLabel'], $record['demands']);
        echo $labels === [] ? e(Helpers::EM_DASH) : e(implode(', ', $labels));
      ?>
    </dd>
  </dl>

  <?php if (($record['restriction_details'] ?? null) !== null): ?>
    <div class="banner warn">
      <b><?= topte('lbl_restrictions') ?></b> <?= e($record['restriction_details']) ?><br>
      <span class="muted">
        <?= topte('lbl_review_date') ?> <?= e(fmt_date((string) $record['restriction_review_date'])) ?>
        <?php if (Helpers::isPast((string) $record['restriction_review_date'])): ?>
          <?= badge(I18n::topt('badge_overdue'), 'b-extended') ?>
        <?php endif; ?>
      </span>
    </div>
  <?php else: ?>
    <div class="muted-block"><?= topte('none_word') ?></div>
  <?php endif; ?>
</div>
