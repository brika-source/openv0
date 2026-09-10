<?php
/**
 * Screen B2 — fit-to-work assessment queue.
 *
 * @var string $portal
 * @var array<int,array<string,mixed>> $rows
 */
use App\Domain;
use App\I18n;
?>
<div class="card">
  <h3><?= te('title_ftw_queue') ?></h3>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= te('th_id') ?></th>
          <th><?= te('th_employee') ?></th>
          <th><?= te('th_requester') ?></th>
          <th><?= te('th_diagnosis') ?></th>
          <th><?= te('th_status') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows === []): ?>
          <tr><td colspan="6" class="empty"><?= te('empty_queue_clear') ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php $record = $row['record']; $employee = $row['employee']; ?>
            <tr>
              <td class="nowrap"><?= e($record['id']) ?></td>
              <td class="nowrap"><?= e(($employee['name'] ?? '') . ' (' . $record['employee_id'] . ')') ?></td>
              <td><?= e($record['requester_label']) ?></td>
              <td><?= e($record['diagnosis']) ?></td>
              <td><?= badge(I18n::ftwStatusLabel((string) $record['status']), Domain::ftwBadgeClass((string) $record['status'])) ?></td>
              <td class="nowrap">
                <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'ftw', 'id' => $record['id']])) ?>"><?= te('link_assess2') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
