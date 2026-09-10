<?php
/**
 * Screen B1 — the FTW request form.
 *
 * Shared by the employee's "My FTW" tab (where the subject is fixed to
 * themselves) and the "Initiate FTW" tab used by managers, HR and the medical
 * team (where the subject is chosen from a list the caller is allowed to act
 * for).
 *
 * @var string                         $portal
 * @var array<string,mixed>            $user
 * @var list<string>                   $demands
 * @var array<int,array<string,mixed>> $candidates
 * @var array<string,mixed>|null       $fixed_employee  set when the subject cannot be changed
 * @var string                         $preselect
 */
use App\Http\View;
use App\I18n;
?>
<div class="card">
  <h3><?= te('title_request_ftw') ?></h3>

  <form method="post" action="<?= e(url()) ?>" enctype="multipart/form-data" data-guard>
    <?= csrf() ?>
    <input type="hidden" name="p" value="<?= e($portal) ?>">
    <input type="hidden" name="r" value="ftw.request">

    <label for="employee_id"><?= te('lbl_employee') ?></label>
    <?php if ($fixed_employee !== null): ?>
      <input type="text" value="<?= e($fixed_employee['name'] . ' (' . $fixed_employee['id'] . ')') ?>" disabled>
      <input type="hidden" name="employee_id" value="<?= e($fixed_employee['id']) ?>">
    <?php elseif ($candidates === []): ?>
      <div class="field-locked"><?= topte('empty_no_reports') ?></div>
    <?php else: ?>
      <select id="employee_id" name="employee_id" required>
        <?php foreach ($candidates as $candidate): ?>
          <option value="<?= e($candidate['id']) ?>" <?= (string) $candidate['id'] === $preselect ? 'selected' : '' ?>>
            <?= e($candidate['name'] . ' — ' . $candidate['dept'] . ' (' . $candidate['id'] . ')') ?>
          </option>
        <?php endforeach; ?>
      </select>
    <?php endif; ?>

    <label class="req" for="diagnosis"><?= te('lbl_current_diagnosis') ?></label>
    <textarea id="diagnosis" name="diagnosis" rows="2" required></textarea>

    <label for="med_history"><?= te('lbl_medhistory_summary') ?></label>
    <textarea id="med_history" name="med_history" rows="2"></textarea>

    <label><?= te('lbl_job_details') ?></label>
    <div class="checklist">
      <?php foreach ($demands as $index => $demand): ?>
        <label for="demand_<?= e((string) $index) ?>">
          <input type="checkbox" id="demand_<?= e((string) $index) ?>" name="demands[]" value="<?= e($demand) ?>">
          <?= e(I18n::jobDemandLabel($demand)) ?>
        </label>
      <?php endforeach; ?>
    </div>
    <textarea id="job_free_text" name="job_free_text" rows="2" placeholder="<?= topte('ph_job_additional') ?>"></textarea>

    <?= View::render('partials/upload-field', ['name' => 'attachments', 'label' => 'lbl_attachments']) ?>

    <button type="submit" class="btn block" <?= ($fixed_employee === null && $candidates === []) ? 'disabled' : '' ?>>
      <?= te('btn_submit_ftw') ?>
    </button>
  </form>
</div>
