<?php
/**
 * Restriction expiry tracking (8.6) — restrictions from both FTW assessments
 * and restricted returns to work whose review date is due or overdue.
 *
 * @var string $portal
 * @var array<int,array<string,mixed>> $rows
 */
use App\Helpers;
use App\I18n;
?>
<div class="card">
  <h3><?= te('title_expiry') ?></h3>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= te('th_ftwid') ?></th>
          <th><?= te('th_employee') ?></th>
          <th><?= te('th_restriction') ?></th>
          <th><?= te('th_review_date') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows === []): ?>
          <tr><td colspan="5" class="empty"><?= te('empty_no_expiry') ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php
              $overdue  = Helpers::isPast($row['review_date']);
              $employee = $row['employee'];
              $target   = $row['kind'] === 'ftw'
                  ? url(['p' => $portal, 'r' => 'ftw', 'id' => $row['ref_id']])
                  : url(['p' => $portal, 'r' => 'case', 'id' => $row['ref_id']]);
            ?>
            <tr>
              <td class="nowrap"><?= e($row['ref_id']) ?></td>
              <td class="nowrap"><?= e(($employee['name'] ?? '') . ' (' . $row['employee_id'] . ')') ?></td>
              <td><?= e($row['details']) ?></td>
              <td class="nowrap">
                <?= e(fmt_date($row['review_date'])) ?>
                <?= $overdue
                    ? badge(I18n::topt('badge_overdue'), 'b-extended')
                    : badge(I18n::topt('badge_due_soon'), 'b-pending') ?>
              </td>
              <td class="nowrap"><a class="link" href="<?= e($target) ?>"><?= te('link_rereview') ?></a></td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
