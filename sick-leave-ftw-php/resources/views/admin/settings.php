<?php
/**
 * Retention & SLA settings, plus the demo data reset.
 *
 * @var string $portal
 * @var int $retention_years
 * @var int $sla_amber_hrs
 * @var int $sla_red_hrs
 * @var int $entitlement_days
 */
?>
<div class="card">
  <h3><?= te('title_settings') ?></h3>

  <form method="post" action="<?= e(url()) ?>" data-guard>
    <?= csrf() ?>
    <input type="hidden" name="p" value="<?= e($portal) ?>">
    <input type="hidden" name="r" value="admin.settings">

    <label for="retention_years"><?= te('lbl_retention') ?></label>
    <input type="number" id="retention_years" name="retention_years" min="1" max="100"
           value="<?= e((string) $retention_years) ?>" required>

    <label for="sla_amber_hrs"><?= te('lbl_sla_amber') ?></label>
    <input type="number" id="sla_amber_hrs" name="sla_amber_hrs" min="1" max="720"
           value="<?= e((string) $sla_amber_hrs) ?>" required>

    <label for="sla_red_hrs"><?= te('lbl_sla_red') ?></label>
    <input type="number" id="sla_red_hrs" name="sla_red_hrs" min="1" max="1440"
           value="<?= e((string) $sla_red_hrs) ?>" required>

    <label for="entitlement_days"><?= te('lbl_entitlement') ?></label>
    <input type="number" id="entitlement_days" name="entitlement_days" min="1" max="365"
           value="<?= e((string) $entitlement_days) ?>" required>

    <button type="submit" class="btn block"><?= te('btn_save_settings') ?></button>
  </form>

  <hr class="divider">

  <div class="danger-zone">
    <h4><?= te('btn_reset_demo') ?></h4>
    <p class="mt-0 muted"><?= te('confirm_reset') ?></p>

    <form method="post" action="<?= e(url()) ?>" data-confirm="<?= topte('confirm_reset') ?>">
      <?= csrf() ?>
      <input type="hidden" name="p" value="<?= e($portal) ?>">
      <input type="hidden" name="r" value="admin.reset">

      <label for="confirm">Type <code>RESET</code> to confirm</label>
      <input type="text" id="confirm" name="confirm" required pattern="RESET" placeholder="RESET" autocomplete="off">

      <button type="submit" class="mt-12 btn red"><?= te('btn_reset_demo') ?></button>
    </form>
  </div>
</div>
