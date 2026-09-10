<?php
/**
 * Screen A2 — sick leave submission form.
 *
 * @var array<string,mixed> $user
 * @var string              $portal
 * @var list<string>        $specialties
 */
use App\I18n;
?>
<div class="card">
  <h3><?= te('form_title') ?></h3>

  <form method="post" action="<?= e(url()) ?>" enctype="multipart/form-data" data-guard>
    <?= csrf() ?>
    <input type="hidden" name="p" value="<?= e($portal) ?>">
    <input type="hidden" name="r" value="case.submit">

    <div class="grid3">
      <div><label><?= te('lbl_employee_name') ?></label><input type="text" value="<?= e($user['name']) ?>" disabled></div>
      <div><label><?= te('lbl_employee_id') ?></label><input type="text" value="<?= e($user['id']) ?>" disabled></div>
      <div><label><?= te('lbl_department') ?></label><input type="text" value="<?= e($user['dept']) ?>" disabled></div>
    </div>

    <label class="req" for="diagnosis"><?= te('lbl_diagnosis') ?></label>
    <textarea id="diagnosis" name="diagnosis" rows="2" required placeholder="<?= topte('ph_diagnosis') ?>"></textarea>

    <div class="grid2">
      <div>
        <label class="req" for="from_date"><?= te('lbl_from_date') ?></label>
        <input type="date" id="from_date" name="from_date" required>
      </div>
      <div>
        <label class="req" for="to_date"><?= te('lbl_to_date') ?></label>
        <input type="date" id="to_date" name="to_date" required>
      </div>
    </div>

    <label for="specialty"><?= te('lbl_specialty') ?></label>
    <select id="specialty" name="specialty">
      <?php foreach ($specialties as $specialty): ?>
        <option value="<?= e($specialty) ?>"><?= e(I18n::specialtyLabel($specialty)) ?></option>
      <?php endforeach; ?>
    </select>

    <?= App\Http\View::render('partials/upload-field', ['name' => 'documents', 'label' => 'lbl_docs']) ?>

    <button type="submit" class="btn block"><?= te('btn_submit_request') ?></button>
  </form>
</div>
