<?php
/**
 * The employee's own FTW assessments, plus the request form (Screen B1,
 * employee-initiated).
 *
 * @var array<string,mixed>            $user
 * @var string                         $portal
 * @var array<int,array<string,mixed>> $records
 * @var list<string>                   $demands
 */
use App\Domain;
use App\Http\View;
use App\I18n;
?>
<div class="card">
  <h3><?= te('title_my_ftw') ?></h3>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= te('th_id') ?></th>
          <th><?= te('th_diagnosis') ?></th>
          <th><?= te('th_status') ?></th>
          <th></th>
        </tr>
      </thead>
      <tbody>
        <?php if ($records === []): ?>
          <tr><td colspan="4" class="empty"><?= te('empty_no_ftw') ?></td></tr>
        <?php else: ?>
          <?php foreach ($records as $record): ?>
            <tr>
              <td class="nowrap"><?= e($record['id']) ?></td>
              <td><?= e($record['diagnosis']) ?></td>
              <td><?= badge(I18n::ftwStatusLabel((string) $record['status']), Domain::ftwBadgeClass((string) $record['status'])) ?></td>
              <td class="nowrap">
                <a class="link" href="<?= e(url(['p' => $portal, 'r' => 'ftw', 'id' => $record['id']])) ?>"><?= te('link_view') ?></a>
              </td>
            </tr>
          <?php endforeach; ?>
        <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?= View::render('ftw/form', [
    'portal'      => $portal,
    'user'        => $user,
    'demands'     => $demands,
    'candidates'  => [],
    'fixed_employee' => $user,
    'preselect'   => '',
]) ?>
