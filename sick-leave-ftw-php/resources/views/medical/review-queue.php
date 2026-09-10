<?php
/**
 * Screen A3 — the medical review queue, longest-waiting case first, with the
 * SLA state of each.
 *
 * @var string $portal
 * @var array<int,array<string,mixed>> $rows
 */
use App\Helpers;
?>
<div class="card">
  <h3><?= te('title_review_queue') ?></h3>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= te('th_case') ?></th>
          <th><?= te('th_employee') ?></th>
          <th><?= te('th_diagnosis3') ?></th>
          <th><?= te('th_dates2') ?></th>
          <th><?= te('th_docs') ?></th>
          <th><?= te('th_sla') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows === []): ?>
          <tr><td colspan="7" class="empty"><?= te('empty_queue_clear') ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php $case = $row['case']; $employee = $row['employee']; $sla = $row['sla']; ?>
            <tr>
              <td class="nowrap"><?= e($case['case_id']) ?></td>
              <td class="nowrap"><?= e(($employee['name'] ?? '') . ' (' . $case['employee_id'] . ')') ?></td>
              <td><?= e($case['diagnosis']) ?></td>
              <td class="nowrap"><?= e(fmt_date((string) $case['from_date'])) ?>–<?= e(fmt_date((string) $case['to_date'])) ?></td>
              <td><?= e((string) count($case['documents'])) ?></td>
              <td class="sla-<?= e($sla !== null ? $sla['level'] : 'ok') ?>">
                <?= $sla !== null ? enl($sla['label']) : e(Helpers::EM_DASH) ?>
              </td>
              <td class="nowrap">
                <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'case', 'id' => $case['case_id']])) ?>"><?= te('link_review') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
