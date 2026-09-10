<?php
/**
 * The unified patient story: balance, any pattern flag, and the complete sick
 * leave and fit-to-work history for one employee.
 *
 * A direct port of the prototype's patientStoryHtml(), shown on the medical
 * review screen, the RTW screen, the FTW assessment screen and the patient
 * profile page. Only roles that may see clinical detail ever render it.
 *
 * @var array<string,mixed> $story   employee, balance, flag, cases, ftws
 * @var string              $portal
 */
$employee = $story['employee'];
$balance  = $story['balance'];
$flag     = $story['flag'];
?>
<?php if ($employee === null): ?>
  <div class="muted"><?= topte('err_not_found') ?></div>
<?php else: ?>
<div class="story-head">
  <b><?= e($employee['name']) ?></b> — <?= e($employee['dept']) ?> (<?= e($employee['id']) ?>)<br>
  <span class="muted"><?= enl(App\I18n::sentence([
      ['key' => 'balance_line'],
      ' ' . $balance['used'] . ' ',
      ['key' => 'of_entitlement'],
      ' ' . $balance['entitlement'] . ' ',
      ['key' => 'days_word'],
  ])) ?></span>
  <?php if ($flag['flag']): ?>
    <div class="mt-6">
      <span class="flagchip"><?= topte('flagged_label') ?></span> <?= e($flag['reason']) ?>
    </div>
  <?php endif; ?>
</div>

<b><?= te('sick_leave_history') ?> (<?= e((string) count($story['cases'])) ?>)</b>
<div class="story-block">
  <?php if ($story['cases'] === []): ?>
    <div class="muted"><?= te('no_sick_history') ?></div>
  <?php else: ?>
    <?php foreach ($story['cases'] as $case): ?>
      <div class="hist-item">
        <div class="ts">
          <?= e(fmt_date((string) $case['from_date'])) ?>–<?= e(fmt_date((string) $case['to_date'])) ?>
          · <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'case', 'id' => $case['case_id']])) ?>"><?= e($case['case_id']) ?></a>
        </div>
        <?= e($case['diagnosis']) ?> (<?= e(App\I18n::specialtyLabel((string) $case['specialty'])) ?>)
        — <?= badge(App\I18n::statusLabel((string) $case['status']), App\Domain::statusBadgeClass((string) $case['status'])) ?>
        <?php if (($case['rtw_outcome'] ?? null) !== null): ?>
          · RTW: <?= e($case['rtw_outcome']) ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

<b><?= te('ftw_history') ?> (<?= e((string) count($story['ftws'])) ?>)</b>
<div class="my-6">
  <?php if ($story['ftws'] === []): ?>
    <div class="muted"><?= te('no_ftw_history') ?></div>
  <?php else: ?>
    <?php foreach ($story['ftws'] as $record): ?>
      <div class="hist-item">
        <div class="ts">
          <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'ftw', 'id' => $record['id']])) ?>"><?= e($record['id']) ?></a>
        </div>
        <?= e($record['diagnosis']) ?>
        — <?= badge(App\I18n::ftwStatusLabel((string) $record['status']), App\Domain::ftwBadgeClass((string) $record['status'])) ?>
        <?php if (($record['restriction_details'] ?? null) !== null): ?>
          · <?= e($record['restriction_details']) ?>
        <?php endif; ?>
      </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>
<?php endif; ?>
