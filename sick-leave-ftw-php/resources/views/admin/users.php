<?php
/**
 * Users & roles.
 *
 * @var array<string,mixed> $user
 * @var string $portal
 * @var array<int,array<string,mixed>> $users
 * @var list<string> $roles
 * @var array<int,array<string,mixed>> $managers
 */
use App\Domain;
use App\Helpers;
use App\I18n;
?>
<div class="card">
  <h3><?= te('title_users') ?></h3>

  <div class="table-scroll">
    <table>
      <thead>
        <tr>
          <th><?= te('th_id4') ?></th>
          <th><?= te('th_name') ?></th>
          <th><?= te('th_dept2') ?></th>
          <th><?= te('th_role') ?></th>
          <th><?= te('th_manager') ?></th>
          <th><?= te('th_active') ?></th>
          <th><?= te('th_actions') ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $row): ?>
          <?php
            $isSelf   = (string) $row['id'] === (string) $user['id'];
            $isActive = (int) $row['is_active'] === 1;
          ?>
          <tr>
            <td class="nowrap"><?= e($row['id']) ?></td>
            <td class="nowrap">
              <?= e($row['name']) ?>
              <div class="doc-meta"><?= e($row['email']) ?></div>
            </td>
            <td><?= e($row['dept']) ?></td>
            <td>
              <?php if ($isSelf): ?>
                <?= e(I18n::roleLabel((string) $row['role'])) ?>
                <div class="doc-meta"><?= topte('lbl_signed_in_as') ?></div>
              <?php else: ?>
                <form method="post" action="<?= e(url()) ?>">
                  <?= csrf() ?>
                  <input type="hidden" name="p" value="<?= e($portal) ?>">
                  <input type="hidden" name="r" value="admin.role">
                  <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                  <div class="gap-6 form-row">
                    <select name="role">
                      <?php foreach ($roles as $role): ?>
                        <option value="<?= e($role) ?>" <?= $role === (string) $row['role'] ? 'selected' : '' ?>>
                          <?= e(I18n::roleLabel($role)) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <div class="flex-none">
                      <button type="submit" class="btn secondary sm"><?= topte('btn_save_settings') ?></button>
                    </div>
                  </div>
                </form>
              <?php endif; ?>
            </td>
            <td class="nowrap">
              <?= e(($row['manager_id'] ?? null) !== null ? App\Repo\Users::nameOf((string) $row['manager_id']) : Helpers::EM_DASH) ?>
            </td>
            <td>
              <?= $isActive
                  ? badge(I18n::topt('th_active'), 'b-approved')
                  : badge(I18n::topt('lbl_inactive'), 'b-rejected') ?>
            </td>
            <td class="nowrap">
              <?php if (!$isSelf): ?>
                <form method="post" action="<?= e(url()) ?>" class="inline"
                      <?= $isActive ? 'data-confirm="' . topte('confirm_deactivate') . '"' : '' ?>>
                  <?= csrf() ?>
                  <input type="hidden" name="p" value="<?= e($portal) ?>">
                  <input type="hidden" name="r" value="admin.active">
                  <input type="hidden" name="id" value="<?= e($row['id']) ?>">
                  <input type="hidden" name="active" value="<?= $isActive ? '0' : '1' ?>">
                  <button type="submit" class="btn <?= $isActive ? 'red' : 'green' ?> sm">
                    <?= topte($isActive ? 'btn_deactivate' : 'btn_reactivate') ?>
                  </button>
                </form>
              <?php else: ?>
                <?= e(Helpers::EM_DASH) ?>
              <?php endif; ?>
            </td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <h3><?= te('title_add_user') ?></h3>

  <form method="post" action="<?= e(url()) ?>" data-guard>
    <?= csrf() ?>
    <input type="hidden" name="p" value="<?= e($portal) ?>">
    <input type="hidden" name="r" value="admin.user.create">

    <div class="grid2">
      <div>
        <label class="req" for="name"><?= te('lbl_full_name') ?></label>
        <input type="text" id="name" name="name" required maxlength="160">
      </div>
      <div>
        <label class="req" for="email"><?= te('lbl_email') ?></label>
        <input type="email" id="email" name="email" required maxlength="190">
      </div>
    </div>

    <div class="grid2">
      <div>
        <label class="req" for="dept"><?= te('lbl_department') ?></label>
        <input type="text" id="dept" name="dept" required maxlength="160">
      </div>
      <div>
        <label for="role"><?= te('lbl_role') ?></label>
        <select id="role" name="role">
          <?php foreach ($roles as $role): ?>
            <option value="<?= e($role) ?>" <?= $role === Domain::ROLE_EMPLOYEE ? 'selected' : '' ?>>
              <?= e(I18n::roleLabel($role)) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid2">
      <div>
        <label for="manager_id"><?= te('lbl_reports_to') ?></label>
        <select id="manager_id" name="manager_id">
          <option value=""><?= topte('opt_no_manager') ?></option>
          <?php foreach ($managers as $manager): ?>
            <option value="<?= e($manager['id']) ?>"><?= e($manager['name'] . ' (' . $manager['id'] . ')') ?></option>
          <?php endforeach; ?>
        </select>
      </div>
      <div>
        <label class="req" for="password"><?= te('lbl_initial_password') ?></label>
        <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
      </div>
    </div>

    <button type="submit" class="btn block"><?= te('btn_add_user') ?></button>
  </form>
</div>
