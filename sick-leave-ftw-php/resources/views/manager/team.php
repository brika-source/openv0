<?php
/**
 * Manager team dashboard (8.3).
 *
 * Status, dates, RTW-overdue and restricted-duty only. No diagnosis, no medical
 * history, no documents — the confidentiality rule in the banner is enforced by
 * what this template is given, not by what it chooses to print.
 *
 * @var array<string,mixed> $user
 * @var string              $portal
 * @var array<int,array<string,mixed>> $rows
 */
use App\Domain;
use App\Helpers;
use App\I18n;
?>
<div class="banner info"><?= te('banner_confidential') ?></div>

<div class="card">
  <h3><?= enl(I18n::sentence([['key' => 'title_team_dashboard'], ' ' . $user['name']])) ?></h3>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= te('th_employee') ?></th>
          <th><?= te('th_dept') ?></th>
          <th><?= te('th_current_status') ?></th>
          <th><?= te('th_expected_return') ?></th>
          <th><?= te('th_rtw_overdue') ?></th>
          <th><?= te('th_restricted_duty') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($rows === []): ?>
          <tr><td colspan="7" class="empty"><?= te('empty_no_reports') ?></td></tr>
        <?php else: ?>
          <?php foreach ($rows as $row): ?>
            <?php $member = $row['member']; $active = $row['active']; ?>
            <tr>
              <td class="nowrap"><?= e($member['name'] . ' (' . $member['id'] . ')') ?></td>
              <td><?= e($member['dept']) ?></td>
              <td>
                <?php if ($active !== null): ?>
                  <?= badge(I18n::statusLabel((string) $active['status']), Domain::statusBadgeClass((string) $active['status'])) ?>
                <?php else: ?>
                  <?= badge(I18n::topt('status_active_atwork'), 'b-closed') ?>
                <?php endif; ?>
              </td>
              <td class="nowrap"><?= $active !== null ? e(fmt_date((string) $active['to_date'])) : e(Helpers::EM_DASH) ?></td>
              <td><?= $row['rtw_overdue'] ? badge(I18n::topt('badge_rtw_overdue'), 'b-extended') : e(Helpers::EM_DASH) ?></td>
              <td><?= $row['restricted'] ? badge(I18n::topt('badge_restricted_duty'), 'b-restrict') : e(Helpers::EM_DASH) ?></td>
              <td class="nowrap">
                <?php if ($row['ftw_restriction'] !== null): ?>
                  <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'ftw', 'id' => $row['ftw_restriction']['id']])) ?>"><?= te('link_view_restriction') ?></a>
                <?php elseif ($row['restricted_case'] !== null): ?>
                  <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'case', 'id' => $row['restricted_case']['case_id']])) ?>"><?= te('link_view_restriction') ?></a>
                <?php else: ?>
                  <?= e(Helpers::EM_DASH) ?>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>

  <div class="table-note"><?= topte('req_visibility_note') ?></div>
</div>
