<?php
/**
 * The employee's view of one of their own FTW assessments.
 *
 * @var array<string,mixed> $record
 * @var array<string,mixed> $employee
 * @var string              $portal
 */
use App\Domain;
use App\Http\View;
use App\I18n;
?>
<div class="breadcrumb">
  <a href="<?= e(url(['p' => $portal, 'r' => 'tab', 't' => 'myftw'])) ?>">← <?= topte('title_my_ftw') ?></a>
</div>

<div class="card">
  <div class="detail-head">
    <div>
      <h2><?= e($record['id']) ?></h2>
      <div class="meta"><?= topte('lbl_status_colon') ?>
        <?= badge(I18n::ftwStatusLabel((string) $record['status']), Domain::ftwBadgeClass((string) $record['status'])) ?></div>
    </div>
  </div>

  <dl class="kv">
    <dt><?= topte('lbl_employee_colon') ?></dt>
    <dd><?= e($employee['name'] . ' (' . $employee['dept'] . ', ' . $employee['id'] . ')') ?></dd>
    <dt><?= topte('lbl_diagnosis_colon') ?></dt><dd><?= e($record['diagnosis']) ?></dd>
    <dt><?= topte('lbl_medhistory_colon') ?></dt>
    <dd><?= e(($record['med_history'] ?? '') !== '' ? $record['med_history'] : App\Helpers::EM_DASH) ?></dd>
    <dt><?= topte('lbl_jobdemands_colon') ?></dt>
    <dd>
      <?php
        $labels = array_map([I18n::class, 'jobDemandLabel'], $record['demands']);
        echo $labels === [] ? e(App\Helpers::EM_DASH) : e(implode(', ', $labels));
        if ((string) ($record['job_free_text'] ?? '') !== '') {
            echo ' — ' . e($record['job_free_text']);
        }
      ?>
    </dd>
    <dt><?= topte('lbl_attachments_colon') ?></dt>
    <dd><?= View::render('partials/documents', [
        'documents'    => $record['attachments'],
        'portal'       => $portal,
        'type'         => 'ftw',
        'can_download' => true,
    ]) ?></dd>
  </dl>

  <?php if (($record['restriction_details'] ?? null) !== null): ?>
    <div class="banner warn">
      <b><?= topte('lbl_restrictions') ?></b> <?= e($record['restriction_details']) ?><br>
      <span class="muted"><?= topte('lbl_review_date') ?> <?= e(fmt_date((string) $record['restriction_review_date'])) ?></span>
    </div>
  <?php endif; ?>

  <?php if (($record['diagnostic_details'] ?? null) !== null): ?>
    <div class="banner info">
      <b><?= topte('lbl_diagtest_details') ?></b> <?= e($record['diagnostic_details']) ?>
    </div>
  <?php endif; ?>

  <hr class="divider">
  <b><?= te('history_title') ?></b>
  <?= View::render('partials/history', ['entries' => $record['history']]) ?>
</div>
