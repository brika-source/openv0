<?php
/**
 * Screen A4 — return-to-work assessment queue.
 *
 * @var string $portal
 * @var array<int,array<string,mixed>> $rows
 */
use App\Helpers;
use App\I18n;
?>
<div class="card">
  <h3><?= te('title_rtw_queue') ?></h3>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= te('th_case') ?></th>
          <th><?= te('th_employee') ?></th>
          <th><?= te('th_return_date') ?></th>
          <th><?= te('th_days_waiting') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows === []): ?>
          <tr><td colspan="5" class="empty"><?= te('empty_no_rtw') ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $case     = $row['case'];
              $employee = $row['employee'];
              $overdue  = Helpers::isPast((string) $case['to_date']);
            ?>
            <tr>
              <td class="nowrap"><?= e($case['case_id']) ?></td>
              <td class="nowrap"><?= e(($employee['name'] ?? '') . ' (' . $case['employee_id'] . ')') ?></td>
              <td class="nowrap">
                <?= e(fmt_date((string) $case['to_date'])) ?>
                <?= $overdue ? badge(I18n::topt('badge_overdue'), 'b-extended') : '' ?>
              </td>
              <td><?= e((string) Helpers::daysSince((string) $case['to_date'])) ?></td>
              <td class="nowrap">
                <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'case', 'id' => $case['case_id']])) ?>"><?= te('link_assess') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
