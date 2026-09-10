<?php
/**
 * The employee's own sick leave cases.
 *
 * @var string $portal
 * @var array<int,array<string,mixed>> $cases
 */
use App\Domain;
use App\I18n;
?>
<div class="card">
  <h3><?= te('title_my_cases') ?></h3>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= te('th_case_id') ?></th>
          <th><?= te('th_dates') ?></th>
          <th><?= te('th_diagnosis') ?></th>
          <th><?= te('th_status') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($cases === []): ?>
          <tr><td colspan="5" class="empty"><?= te('empty_no_cases') ?></td></tr>
        <?php else: ?>
          <?php foreach ($cases as $case): ?>
            <tr>
              <td class="nowrap"><?= e($case['case_id']) ?></td>
              <td class="nowrap"><?= e(fmt_date((string) $case['from_date'])) ?> – <?= e(fmt_date((string) $case['to_date'])) ?></td>
              <td><?= e($case['diagnosis']) ?></td>
              <td><?= badge(I18n::statusLabel((string) $case['status']), Domain::statusBadgeClass((string) $case['status'])) ?></td>
              <td class="nowrap">
                <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'case', 'id' => $case['case_id']])) ?>"><?= te('link_view') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
