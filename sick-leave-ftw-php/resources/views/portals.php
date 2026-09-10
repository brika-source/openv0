<?php
/**
 * Landing page: pick a portal.
 *
 * The two prototypes were two separate HTML files against one shared store.
 * Here they are two entry points into one installation and one database.
 *
 * @var list<string> $active_portals
 * @var bool         $timed_out
 * @var string       $page_title
 * @var array<int,array{message:string,type:string}> $flashes
 */
$requester = App\Domain::PORTAL_REQUESTER;
$approver  = App\Domain::PORTAL_APPROVER;
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

<div class="portal-picker">
  <div class="portal-picker-inner">
    <h1><?= te('choose_portal') ?></h1>
    <div class="sub"><?= te('choose_portal_sub') ?></div>

    <?php if ($timed_out): ?>
      <div class="banner warn"><?= te('err_session_expired') ?></div>
    <?php endif; ?>
    <?php foreach ($flashes as $flash): ?>
      <div class="banner <?= $flash['type'] === 'error' ? 'block' : 'info' ?>"><?= enl($flash['message']) ?></div>
    <?php endforeach; ?>

    <div class="portal-cards">
      <a class="portal-card" href="<?= e(url(['p' => $requester])) ?>">
        <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true"><rect width="34" height="34" rx="8" fill="#0B4C8C"/>
          <path d="M17 7v20M9 12c0 3 2 5 8 5s8-2 8-5M9 22c0-3 2-5 8-5s8 2 8 5" stroke="#fff" stroke-width="1.6" fill="none" stroke-linecap="round"/></svg>
        <h2><?= te('open_requester_portal') ?></h2>
        <div class="roles"><?= te('open_requester_roles') ?></div>
        <div class="go">→ <?= topte('sign_in') ?></div>
        <?php if (in_array($requester, $active_portals, true)): ?>
          <div class="signed-in">● <?= topte('lbl_signed_in_as') ?></div>
        <?php endif; ?>
      </a>

      <a class="portal-card" href="<?= e(url(['p' => $approver])) ?>">
        <svg width="34" height="34" viewBox="0 0 34 34" aria-hidden="true"><rect width="34" height="34" rx="8" fill="#1B8A5A"/>
          <path d="M10 17.5l4.5 4.5L24 12.5" stroke="#fff" stroke-width="2.2" fill="none" stroke-linecap="round" stroke-linejoin="round"/></svg>
        <h2><?= te('open_approver_portal') ?></h2>
        <div class="roles"><?= te('open_approver_roles') ?></div>
        <div class="go">→ <?= topte('sign_in') ?></div>
        <?php if (in_array($approver, $active_portals, true)): ?>
          <div class="signed-in">● <?= topte('lbl_signed_in_as') ?></div>
        <?php endif; ?>
      </a>
    </div>

    <div class="portal-foot"><?= e(App\Config::get('app.name')) ?> · v<?= e(App\Config::get('app.version')) ?></div>
  </div>
</div>

<script src="assets/js/app.js"></script>
</body>
</html>
