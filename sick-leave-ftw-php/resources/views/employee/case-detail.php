<?php
/**
 * The employee's view of one of their own cases, including the resubmission
 * form when the medical team has asked for more information.
 *
 * @var array<string,mixed> $case
 * @var array<string,mixed> $employee
 * @var string              $portal
 */
use App\Domain;
use App\Http\View;
use App\I18n;

$status = (string) $case['status'];
?>
<div class="breadcrumb">
  <a href="<?= e(url(['p' => $portal, 'r' => 'tab', 't' => 'mycases'])) ?>">← <?= topte('title_my_cases') ?></a>
</div>

<div class="card">
  <div class="detail-head">
    <div>
      <h2><?= e($case['case_id']) ?></h2>
      <div class="meta"><?= topte('lbl_status_colon') ?>
        <?= badge(I18n::statusLabel($status), Domain::statusBadgeClass($status)) ?></div>
    </div>
  </div>

  <dl class="kv">
    <dt><?= topte('lbl_diagnosis_colon') ?></dt><dd><?= e($case['diagnosis']) ?></dd>
    <dt><?= topte('lbl_specialty2') ?></dt><dd><?= e(I18n::specialtyLabel((string) $case['specialty'])) ?></dd>
    <dt><?= topte('lbl_from') ?></dt><dd><?= e(fmt_date((string) $case['from_date'])) ?></dd>
    <dt><?= topte('lbl_to') ?></dt><dd><?= e(fmt_date((string) $case['to_date'])) ?></dd>
  </dl>

  <?php if ($case['extensions'] !== []): ?>
    <div class="banner info">
      <?= topte('lbl_new_extended_to') ?>
      <?php foreach ($case['extensions'] as $extension): ?>
        <?= e(fmt_date((string) $extension['from_date'])) ?> → <b><?= e(fmt_date((string) $extension['to_date'])) ?></b>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <?php if (($case['restriction_details'] ?? null) !== null): ?>
    <div class="banner warn">
      <b><?= topte('lbl_restrictions') ?></b> <?= e($case['restriction_details']) ?><br>
      <span class="muted"><?= topte('lbl_review_date') ?> <?= e(fmt_date((string) $case['restriction_review_date'])) ?></span>
    </div>
  <?php endif; ?>

  <div class="mt-10">
    <b><?= topte('lbl_documents_v') ?></b>
    <?= View::render('partials/documents', [
        'documents'    => $case['documents'],
        'portal'       => $portal,
        'type'         => 'case',
        'can_download' => true,
    ]) ?>
  </div>

  <hr class="divider">
  <b><?= te('history_title') ?></b>
  <?= View::render('partials/history', ['entries' => $case['history']]) ?>

  <?php if ($status === Domain::STATUS_PENDING): ?>
    <hr class="divider">
    <div class="decision-panel">
      <h4><?= te('resubmit_label') ?></h4>
      <form method="post" action="<?= e(url()) ?>" enctype="multipart/form-data" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="case.resubmit">
        <input type="hidden" name="id" value="<?= e($case['case_id']) ?>">

        <label for="comment"><?= te('resubmit_label') ?></label>
        <textarea id="comment" name="comment" rows="2" placeholder="<?= topte('resubmit_ph') ?>"></textarea>

        <?= View::render('partials/upload-field', ['name' => 'documents', 'label' => 'lbl_additional_docs']) ?>

        <button type="submit" class="btn block"><?= te('btn_resubmit') ?></button>
      </form>
    </div>
  <?php endif; ?>
</div>
