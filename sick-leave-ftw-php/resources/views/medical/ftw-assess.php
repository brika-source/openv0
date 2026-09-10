<?php
/**
 * Screen B2 — the fit-to-work assessment record and its five outcomes.
 *
 * @var array<string,mixed>      $record
 * @var array<string,mixed>|null $employee
 * @var array<string,mixed>      $story
 * @var string                   $portal
 * @var bool                     $can_assess
 * @var string                   $outcome   pre-opened panel, from ?o=
 */
use App\Domain;
use App\Helpers;
use App\Http\View;
use App\I18n;
?>
<div class="breadcrumb">
  <a href="<?= e(url(['p' => $portal, 'r' => 'tab', 't' => 'ftwqueue'])) ?>">← <?= topte('title_ftw_queue') ?></a>
</div>

<div class="card">
  <div class="detail-head">
    <div>
      <h2><?= topte('title_ftw_assessment') ?> <?= e($record['id']) ?></h2>
      <div class="meta"><?= badge(I18n::ftwStatusLabel((string) $record['status']), Domain::ftwBadgeClass((string) $record['status'])) ?></div>
    </div>
  </div>

  <dl class="kv">
    <dt><?= topte('lbl_employee_colon') ?></dt>
    <dd><?= e(($employee['name'] ?? '') . ' (' . ($employee['dept'] ?? '') . ', ' . $record['employee_id'] . ')') ?></dd>
    <dt><?= topte('th_requester') ?></dt><dd><?= e($record['requester_label']) ?></dd>
    <dt><?= topte('lbl_diagnosis_colon') ?></dt><dd><?= e($record['diagnosis']) ?></dd>
    <dt><?= topte('lbl_medhistory_colon') ?></dt>
    <dd><?= e((string) ($record['med_history'] ?? '') !== '' ? $record['med_history'] : Helpers::EM_DASH) ?></dd>
    <dt><?= topte('lbl_jobdemands_colon') ?></dt>
    <dd>
      <?php
        $labels = array_map([I18n::class, 'jobDemandLabel'], $record['demands']);
        echo $labels === [] ? e(Helpers::EM_DASH) : e(implode(', ', $labels));
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

  <?php if (($record['diagnostic_details'] ?? null) !== null): ?>
    <div class="banner info">
      <b><?= topte('lbl_diagtest_details') ?></b> <?= e($record['diagnostic_details']) ?>
    </div>
  <?php endif; ?>

  <?php if (($record['restriction_details'] ?? null) !== null): ?>
    <div class="banner warn">
      <b><?= topte('lbl_restrictions') ?></b> <?= e($record['restriction_details']) ?><br>
      <span class="muted"><?= topte('lbl_review_date') ?> <?= e(fmt_date((string) $record['restriction_review_date'])) ?></span>
    </div>
  <?php endif; ?>

  <hr class="divider">
  <b><?= topte('patient_story_title') ?></b> — <?= topte('modal_patient_profile') ?>
  <div class="mt-8">
    <?= View::render('partials/patient-story', ['story' => $story, 'portal' => $portal]) ?>
  </div>

  <hr class="divider">
  <b><?= te('history_title') ?></b>
  <?= View::render('partials/history', ['entries' => $record['history']]) ?>

  <?php if ($can_assess): ?>
    <hr class="divider">
    <b><?= te('outcome_title') ?></b>

    <div class="my-8 row-actions no-print">
      <!-- The three outcomes that need no extra input post directly. -->
      <form method="post" action="<?= e(url()) ?>" class="inline" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="ftw.outcome">
        <input type="hidden" name="id" value="<?= e($record['id']) ?>">
        <input type="hidden" name="outcome" value="<?= e(Domain::FTW_FIT) ?>">
        <button type="submit" class="btn green"><?= te('btn_fit_to_work') ?></button>
      </form>

      <form method="post" action="<?= e(url()) ?>" class="inline" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="ftw.outcome">
        <input type="hidden" name="id" value="<?= e($record['id']) ?>">
        <input type="hidden" name="outcome" value="<?= e(Domain::FTW_NOTFIT) ?>">
        <button type="submit" class="btn red"><?= te('btn_not_fit_to_work') ?></button>
      </form>

      <button type="button" class="btn amber" data-show-panel="restrict"><?= te('btn_fit_restrictions') ?></button>

      <form method="post" action="<?= e(url()) ?>" class="inline" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="ftw.outcome">
        <input type="hidden" name="id" value="<?= e($record['id']) ?>">
        <input type="hidden" name="outcome" value="<?= e(Domain::FTW_F2F) ?>">
        <button type="submit" class="btn secondary"><?= te('btn_needs_f2f') ?></button>
      </form>

      <button type="button" class="btn secondary" data-show-panel="diagtest"><?= te('btn_needs_diagtest') ?></button>
    </div>

    <!-- Fit with restrictions -->
    <div class="decision-panel" data-panel="restrict" <?= $outcome === 'restrict' ? '' : 'hidden' ?>>
      <form method="post" action="<?= e(url()) ?>" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="ftw.outcome">
        <input type="hidden" name="id" value="<?= e($record['id']) ?>">
        <input type="hidden" name="outcome" value="<?= e(Domain::FTW_RESTRICT) ?>">

        <label class="req" for="restriction_details"><?= te('lbl_restriction_details') ?></label>
        <textarea id="restriction_details" name="restriction_details" rows="2" required
                  placeholder="<?= topte('ph_restriction') ?>"></textarea>

        <label class="req" for="restriction_review_date"><?= te('lbl_review_expiry') ?></label>
        <input type="date" id="restriction_review_date" name="restriction_review_date" required>

        <button type="submit" class="btn block amber"><?= te('btn_confirm_restrictions') ?></button>
      </form>
    </div>

    <!-- Needs further diagnostic tests -->
    <div class="decision-panel" data-panel="diagtest" <?= $outcome === 'diagtest' ? '' : 'hidden' ?>>
      <form method="post" action="<?= e(url()) ?>" data-guard>
        <?= csrf() ?>
        <input type="hidden" name="p" value="<?= e($portal) ?>">
        <input type="hidden" name="r" value="ftw.outcome">
        <input type="hidden" name="id" value="<?= e($record['id']) ?>">
        <input type="hidden" name="outcome" value="<?= e(Domain::FTW_DIAGTEST) ?>">

        <label class="req" for="diagnostic_details"><?= te('lbl_diagtest_details') ?></label>
        <textarea id="diagnostic_details" name="diagnostic_details" rows="2" required
                  placeholder="<?= topte('ph_diagtest') ?>"></textarea>

        <button type="submit" class="btn block secondary"><?= te('btn_confirm_diagtest') ?></button>
      </form>
    </div>
  <?php endif; ?>
</div>
