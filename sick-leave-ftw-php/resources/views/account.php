<?php
/**
 * The signed-in user's own account page.
 *
 * @var array<string,mixed> $user
 * @var string $portal
 * @var string $role
 */
use App\I18n;
?>
<div class="card">
  <h3><?= topte('tab_account') ?></h3>

  <dl class="kv">
    <dt><?= topte('lbl_full_name') ?></dt><dd><?= e($user['name']) ?></dd>
    <dt><?= topte('lbl_email') ?></dt><dd><?= e($user['email']) ?></dd>
    <dt><?= topte('th_id4') ?></dt><dd><?= e($user['id']) ?></dd>
    <dt><?= topte('lbl_department') ?></dt><dd><?= e($user['dept']) ?></dd>
    <dt><?= topte('lbl_role') ?></dt><dd><?= e(I18n::roleLabel($role)) ?></dd>
  </dl>
</div>

<div class="card">
  <h3><?= te('title_change_password') ?></h3>

  <form method="post" action="<?= e(url()) ?>" data-guard>
    <?= csrf() ?>
    <input type="hidden" name="p" value="<?= e($portal) ?>">
    <input type="hidden" name="r" value="account.password">

    <label class="req" for="current_password"><?= te('lbl_current_password') ?></label>
    <input type="password" id="current_password" name="current_password" required autocomplete="current-password">

    <label class="req" for="new_password"><?= te('lbl_new_password') ?></label>
    <input type="password" id="new_password" name="new_password" required minlength="8" autocomplete="new-password">

    <label class="req" for="confirm_password"><?= te('lbl_confirm_password') ?></label>
    <input type="password" id="confirm_password" name="confirm_password" required minlength="8" autocomplete="new-password">

    <button type="submit" class="btn block"><?= te('btn_change_password') ?></button>
  </form>
</div>
