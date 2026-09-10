<?php
/**
 * Portal login screen.
 *
 * @var string $portal
 * @var array<int,array<string,mixed>> $eligible
 * @var string $brand_title    label key
 * @var string $brand_tag      label key
 * @var string $note_key       label key
 * @var string $demo_password
 * @var bool   $demo_mode
 * @var bool   $timed_out
 * @var string $old_identifier
 * @var string $page_title
 * @var array<int,array{message:string,type:string}> $flashes
 */
?>
<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="robots" content="noindex, nofollow">
<title><?= e($page_title) ?></title>
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body>

<div id="loginScreen">
  <div class="login-card">
    <div class="brand">
      <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true"><rect width="34" height="34" rx="8" fill="#0B4C8C"/>
        <path d="M17 7v20M9 12c0 3 2 5 8 5s8-2 8-5M9 22c0-3 2-5 8-5s8 2 8 5" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/></svg>
      <div>
        <h1><?= te($brand_title) ?></h1>
        <div class="tag"><?= te($brand_tag) ?></div>
      </div>
    </div>

    <?php if ($timed_out): ?>
      <div class="mt-14 banner warn"><?= te('err_session_expired') ?></div>
    <?php endif; ?>

    <?php foreach ($flashes as $flash): ?>
      <div class="banner mt-14 <?= $flash['type'] === 'error' ? 'block' : 'info' ?>"><?= enl($flash['message']) ?></div>
    <?php endforeach; ?>

    <h2><?= te('sign_in') ?></h2>

    <form method="post" action="<?= e(url()) ?>" data-guard>
      <?= csrf() ?>
      <input type="hidden" name="p" value="<?= e($portal) ?>">
      <input type="hidden" name="r" value="login">

      <label for="identifier"><?= te('select_account') ?></label>
      <?php if ($demo_mode && $eligible !== []): ?>
        <select id="identifier" name="identifier" required>
          <?php foreach ($eligible as $account): ?>
            <option value="<?= e($account['id']) ?>" <?= (string) $account['id'] === $old_identifier ? 'selected' : '' ?>>
              <?= e($account['name'] . ' — ' . $account['dept'] . ' (' . App\I18n::topt('role_' . $account['role']) . ')') ?>
            </option>
          <?php endforeach; ?>
        </select>
      <?php else: ?>
        <input type="email" id="identifier" name="identifier" required autocomplete="username"
               value="<?= e($old_identifier) ?>" placeholder="name@example.com">
      <?php endif; ?>

      <label for="password"><?= te('lbl_password') ?></label>
      <input type="password" id="password" name="password" required autocomplete="current-password"
             placeholder="<?= topte('ph_password') ?>">

      <button type="submit" class="btn block"><?= te('sign_in_btn') ?></button>
    </form>

    <?php if ($demo_mode): ?>
      <div class="mt-14 conf-note">
        <?= topte('demo_password_hint') ?> <code><?= e($demo_password) ?></code>
      </div>
    <?php endif; ?>

    <div class="mt-10 text-center conf-note">
      <?= te('proto_prefix') ?> <?= te($note_key) ?>
    </div>

    <div class="mt-16 text-center">
      <a class="link" href="<?= e(url()) ?>"><?= topte('back_to_portals') ?></a>
    </div>
  </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
